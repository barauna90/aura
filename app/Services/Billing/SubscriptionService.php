<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Promotion\PromotionService;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SUBSCRIPTION ORCHESTRATOR. Preços, limites e trial vêm do banco (Plan).
 * O status ACTIVE só é atribuído pelo webhook do gateway — nunca pelo navegador.
 */
class SubscriptionService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PromotionService $promotions,
        private readonly AuditService $audit,
        private readonly ReferralService $referrals,
    ) {}

    /**
     * @return array{subscription:Subscription, payment:Payment, pix_image:?string, discount_cents:int, trial_days:int}
     */
    public function checkout(User $user, Plan $plan, string $billingType, ?string $couponCode, ?string $cpf, ?string $phone): array
    {
        if (! $plan->is_active || $plan->price_cents === 0) {
            throw ValidationException::withMessages(['plan' => 'Plano indisponível para assinatura.']);
        }
        if (Subscription::where('user_id', $user->id)->whereIn('status', ['ACTIVE', 'TRIALING'])->where('current_period_end', '>', now())->exists()) {
            throw ValidationException::withMessages(['plan' => 'Você já possui uma assinatura ativa.']);
        }

        $discount = 0;
        $trialDays = $plan->trial_days;
        $couponId = null;
        if ($couponCode) {
            $c = $this->promotions->evaluate($couponCode, $user, $plan->code, $plan->price_cents);
            if (! $c['valid']) {
                throw ValidationException::withMessages(['coupon' => $c['reason']]);
            }
            $discount = $c['discount_cents'];
            $trialDays = max($trialDays, $c['trial_days']);
            $couponId = $c['coupon_id'];
        }
        // Desconto de indicação (configurável): só na primeira assinatura de quem entrou por um código.
        $referralDiscount = $this->referrals->discountFor($user, $plan);
        $discount = min($plan->price_cents, $discount + $referralDiscount);
        $amount = max(0, $plan->price_cents - $discount);

        if ($cpf) {
            $user->forceFill(['cpf_hash' => hash('sha256', preg_replace('/\D/', '', $cpf))])->save();
        }
        if ($phone) {
            $user->forceFill(['phone' => $phone])->save();
        }

        $customerId = $this->gateway->ensureCustomer($user, $cpf, $phone);

        return DB::transaction(function () use ($user, $plan, $billingType, $amount, $discount, $referralDiscount, $trialDays, $couponId, $customerId) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => $trialDays > 0 ? 'TRIALING' : 'PENDING',
                'gateway' => $this->gateway->name(),
                'billing_type' => $billingType,
                'current_period_start' => now(),
                'current_period_end' => now()->addDays($trialDays),
                'coupon_id' => $couponId,
            ]);
            $remote = $this->gateway->createSubscription($user, $customerId, $plan, $billingType, $amount, "{$plan->name} — assinatura mensal", 'sub:'.$subscription->id);
            $subscription->update(['gateway_subscription_id' => $remote['subscription_id']]);
            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'gateway' => $this->gateway->name(),
                'gateway_payment_id' => $remote['payment']['id'],
                'billing_type' => $billingType,
                'amount_cents' => $plan->price_cents,
                'discount_cents' => $discount,
                'status' => 'PENDING',
                'due_date' => $remote['payment']['due_date'],
                'invoice_url' => $remote['payment']['invoice_url'],
                'bank_slip_url' => $remote['payment']['bank_slip_url'],
                'pix_payload' => $remote['payment']['pix_payload'],
            ]);
            if ($couponId) {
                $this->promotions->consume($couponId, $user);
            }
            $this->audit->log('subscription.checkout', $user->id, 'Subscription', $subscription->id, ['plan' => $plan->code, 'billing_type' => $billingType, 'discount' => $discount, 'referral_discount' => $referralDiscount]);

            return ['subscription' => $subscription, 'payment' => $payment, 'pix_image' => $remote['payment']['pix_image'], 'discount_cents' => $discount, 'trial_days' => $trialDays];
        });
    }

    /**
     * Troca de plano ANTES do pagamento: a cobrança pendente é cancelada no gateway e uma
     * nova assinatura é gerada para o plano escolhido (mesma forma de pagamento, mesmo cupom).
     *
     * @return array{subscription:Subscription, payment:Payment, pix_image:?string, discount_cents:int, trial_days:int}
     */
    public function changePendingPlan(User $user, Plan $newPlan): array
    {
        $pending = Subscription::with('coupon')->where('user_id', $user->id)->where('status', 'PENDING')->latest()->first();
        if (! $pending) {
            throw ValidationException::withMessages(['plan' => 'Não há assinatura aguardando pagamento para trocar de plano.']);
        }
        if ($pending->plan_id === $newPlan->id) {
            throw ValidationException::withMessages(['plan' => 'Este já é o plano escolhido.']);
        }

        $couponCode = null;
        DB::transaction(function () use ($user, $pending, &$couponCode) {
            if ($pending->gateway_subscription_id) {
                $this->gateway->cancelSubscription($pending->gateway_subscription_id);
            }
            $pending->update(['status' => 'CANCELED', 'canceled_at' => now(), 'cancel_at_period_end' => true]);
            Payment::where('subscription_id', $pending->id)->where('status', 'PENDING')->update(['status' => 'CANCELED']);
            if ($pending->coupon_id) {
                // O uso do cupom volta a valer para a nova cobrança.
                $couponCode = $pending->coupon?->code;
                $this->promotions->release($pending->coupon_id, $user);
            }
            $this->audit->log('subscription.plan_change', $user->id, 'Subscription', $pending->id, ['from_plan_id' => $pending->plan_id, 'to_plan_id' => null]);
        });

        $out = $this->checkout($user, $newPlan, $pending->billing_type ?? 'PIX', $couponCode, null, null);
        $this->audit->log('subscription.plan_changed', $user->id, 'Subscription', $out['subscription']->id, ['from' => $pending->id, 'plan' => $newPlan->code]);

        return $out;
    }

    /** Cancela ao fim do período pago — sem renovação enganosa, sem perda imediata. */
    public function cancel(User $user): Subscription
    {
        $sub = Subscription::where('user_id', $user->id)->whereIn('status', ['ACTIVE', 'TRIALING', 'PENDING', 'PAST_DUE'])->latest()->first();
        if (! $sub) {
            throw ValidationException::withMessages(['subscription' => 'Nenhuma assinatura ativa.']);
        }
        if ($sub->gateway_subscription_id) {
            $this->gateway->cancelSubscription($sub->gateway_subscription_id);
        }
        $sub->update(['cancel_at_period_end' => true, 'canceled_at' => now(), 'status' => $sub->status === 'PENDING' ? 'CANCELED' : $sub->status]);
        $this->audit->log('subscription.cancel_requested', $user->id, 'Subscription', $sub->id);
        // Desistência dentro do período de bloqueio: a comissão do indicador não é efetivada.
        $this->referrals->onSubscriptionCanceled($sub);

        return $sub;
    }

    /** Job horário: expira períodos encerrados. */
    public function expireEnded(): int
    {
        $n = 0;
        Subscription::whereIn('status', ['ACTIVE', 'TRIALING'])->where('current_period_end', '<', now())->each(function (Subscription $s) use (&$n) {
            $status = $s->cancel_at_period_end ? 'CANCELED' : ($s->status === 'TRIALING' ? 'EXPIRED' : 'PAST_DUE');
            $s->update(['status' => $status]);
            $this->audit->log('subscription.'.strtolower($status), null, 'Subscription', $s->id);
            $n++;
        });

        return $n;
    }
}
