<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Promotion\PromotionService;
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
        $amount = max(0, $plan->price_cents - $discount);

        if ($cpf) {
            $user->forceFill(['cpf_hash' => hash('sha256', preg_replace('/\D/', '', $cpf))])->save();
        }
        if ($phone) {
            $user->forceFill(['phone' => $phone])->save();
        }

        $customerId = $this->gateway->ensureCustomer($user, $cpf, $phone);

        return DB::transaction(function () use ($user, $plan, $billingType, $amount, $discount, $trialDays, $couponId, $customerId) {
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
            $this->audit->log('subscription.checkout', $user->id, 'Subscription', $subscription->id, ['plan' => $plan->code, 'billing_type' => $billingType, 'discount' => $discount]);

            return ['subscription' => $subscription, 'payment' => $payment, 'pix_image' => $remote['payment']['pix_image'], 'discount_cents' => $discount, 'trial_days' => $trialDays];
        });
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
