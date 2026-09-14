<?php

namespace App\Services\Referral;

use App\Models\Commission;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\ReferralClick;
use App\Models\ReferralSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * REFERRAL ORCHESTRATOR:
 * INDICAÇÃO → CADASTRO → ASSINATURA → PAGAMENTO CONFIRMADO → VALIDAÇÃO → LIBERADA → SAQUE → PAGA
 */
class ReferralService
{
    public function __construct(private readonly AuditService $audit) {}

    public function trackClick(string $code, ?string $ip, ?string $userAgent): ?User
    {
        $referrer = User::where('referral_code', strtoupper($code))->first();
        if ($referrer) {
            ReferralClick::create(['referrer_id' => $referrer->id, 'ip_hash' => $ip ? hash('sha256', $ip) : null, 'user_agent' => mb_substr((string) $userAgent, 0, 255)]);
        }

        return $referrer;
    }

    public static function generateCode(string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: ''), 0, 6)) ?: 'ALUNO';
        for ($i = 0; $i < 10; $i++) {
            $code = $base.random_int(100, 999);
            if (! User::where('referral_code', $code)->exists()) {
                return $code;
            }
        }

        return $base.strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Desconto para o indicado (centavos), aplicado somente na primeira assinatura
     * de um usuário que entrou por código de indicação e ainda não pagou nada.
     */
    public function discountFor(User $user, Plan $plan): int
    {
        if (! $user->referred_by_id || $plan->price_cents <= 0) {
            return 0;
        }
        if (Payment::where('user_id', $user->id)->where('status', 'CONFIRMED')->exists()) {
            return 0;
        }

        return min($plan->price_cents, (int) ReferralSetting::current()->referred_discount_cents);
    }

    /** Desistência (cancelamento) enquanto a comissão ainda está bloqueada: não é efetivada. */
    public function onSubscriptionCanceled(Subscription $subscription): void
    {
        Commission::where('subscription_id', $subscription->id)->whereIn('status', ['PENDING', 'APPROVED'])->each(function (Commission $c) {
            $c->update(['status' => 'CANCELED', 'blocked_reason' => 'Indicado cancelou a assinatura durante o período de validação']);
            $this->audit->log('commission.canceled', null, 'Commission', $c->id, ['reason' => 'DESISTENCIA']);
        });
    }

    /** Chamado após pagamento CONFIRMADO (webhook). */
    public function onPaymentConfirmed(Payment $payment): void
    {
        $payment->loadMissing(['user.referredBy', 'subscription']);
        $referrer = $payment->user->referredBy;
        if (! $referrer || ! $payment->subscription || ! $payment->confirmed_at) {
            return;
        }
        $s = ReferralSetting::current();
        $ordinal = Payment::where('subscription_id', $payment->subscription_id)->where('status', 'CONFIRMED')->where('confirmed_at', '<=', $payment->confirmed_at)->count();
        if (! CommissionRules::isCommissionable($ordinal, ['recurring' => $s->recurring, 'first_payment_only' => $s->first_payment_only])) {
            return;
        }
        $amount = CommissionRules::computeCents($payment->amount_cents - $payment->discount_cents, ['model' => $s->model, 'value' => $s->value]);
        if ($amount <= 0) {
            return;
        }
        $fraud = CommissionRules::assessFraud($this->fraudSignals($referrer, $payment->user, $payment->instrument_hash));

        $commission = Commission::firstOrCreate(['payment_id' => $payment->id, 'affiliate_id' => $referrer->id], [
            'referred_user_id' => $payment->user_id,
            'subscription_id' => $payment->subscription_id,
            'amount_cents' => $amount,
            'status' => $fraud['block'] ? 'CANCELED' : 'PENDING',
            'available_at' => CommissionRules::availableAt($payment->confirmed_at, $s->validation_days),
            'fraud_flags' => $fraud['flags'],
            'blocked_reason' => $fraud['block'] ? 'Bloqueada automaticamente: '.implode(', ', $fraud['flags']) : null,
        ]);
        if ($fraud['flags']) {
            $this->audit->alert('WARN', 'referrals.fraud', "Sinais de fraude na comissão {$commission->id}", ['flags' => $fraud['flags']]);
        }
        $this->audit->log('commission.created', null, 'Commission', $commission->id, ['amount' => $amount, 'flags' => $fraud['flags']]);
    }

    public function onPaymentReversed(Payment $payment, string $reason): void
    {
        foreach ($payment->commissions as $c) {
            $target = $c->status === 'PAID' ? 'REVERSED' : 'CANCELED';
            if (CommissionRules::canTransition($c->status, $target)) {
                $c->update(['status' => $target, 'blocked_reason' => $reason]);
                $this->audit->log('commission.'.strtolower($target), null, 'Commission', $c->id, ['reason' => $reason]);
            }
        }
    }

    /** Job diário: libera comissões maduras (assinatura indicada precisa continuar ativa). */
    public function releaseMatured(): int
    {
        $released = 0;
        Commission::with('subscription')->whereIn('status', ['PENDING', 'APPROVED'])->where('available_at', '<=', now())->each(function (Commission $c) use (&$released) {
            $sub = $c->subscription;
            if (in_array($sub->status, ['CANCELED', 'REFUNDED', 'SUSPENDED'], true) || $sub->cancel_at_period_end || $sub->canceled_at) {
                $c->update(['status' => 'CANCELED', 'blocked_reason' => 'Assinatura indicada não permaneceu ativa']);

                return;
            }
            $c->update(['status' => 'AVAILABLE']);
            $released++;
        });

        return $released;
    }

    public function dashboard(User $user): array
    {
        $s = ReferralSetting::current();
        $commissions = Commission::where('affiliate_id', $user->id)->latest()->get();
        $sum = fn (array $st) => $commissions->whereIn('status', $st)->sum('amount_cents');
        $clicks = ReferralClick::where('referrer_id', $user->id)->count();
        $subs = Subscription::whereIn('status', ['ACTIVE', 'TRIALING'])->whereHas('user', fn ($q) => $q->where('referred_by_id', $user->id))->count();

        return [
            'code' => $user->referral_code,
            'link' => route('referral.landing', $user->referral_code),
            'clicks' => $clicks,
            'signups' => User::where('referred_by_id', $user->id)->count(),
            'subscriptions' => $subs,
            'conversion' => $clicks ? round($subs / $clicks * 100, 1) : 0,
            'pending_cents' => $sum(['PENDING', 'APPROVED']),
            'available_cents' => $sum(['AVAILABLE']),
            'requested_cents' => $sum(['REQUESTED']),
            'paid_cents' => $sum(['PAID']),
            'min_withdrawal_cents' => $s->min_withdrawal_cents,
            'commission_cents' => $s->model === 'FIXED' ? $s->value : null,
            'commission_percent' => $s->model === 'PERCENT' ? $s->value : null,
            'referred_discount_cents' => $s->referred_discount_cents,
            'validation_days' => $s->validation_days,
            'history' => $commissions,
        ];
    }

    public function requestWithdrawal(User $user, string $pixKey): Withdrawal
    {
        $s = ReferralSetting::current();
        $available = Commission::where('affiliate_id', $user->id)->where('status', 'AVAILABLE')->get();
        $total = $available->sum('amount_cents');
        $check = CommissionRules::canWithdraw($total, $s->min_withdrawal_cents);
        if (! $check['ok']) {
            throw ValidationException::withMessages(['withdrawal' => $check['reason']]);
        }

        return DB::transaction(function () use ($user, $available, $total, $pixKey) {
            $w = Withdrawal::create(['user_id' => $user->id, 'amount_cents' => $total, 'method' => 'PIX', 'pix_key_hash' => hash('sha256', $pixKey)]);
            Commission::whereIn('id', $available->pluck('id'))->update(['status' => 'REQUESTED', 'withdrawal_id' => $w->id]);
            $this->audit->log('withdrawal.requested', $user->id, 'Withdrawal', $w->id, ['total' => $total]);

            return $w;
        });
    }

    public function payWithdrawal(Withdrawal $w, int $actorId): void
    {
        DB::transaction(function () use ($w, $actorId) {
            $w->update(['status' => 'PAID', 'paid_at' => now()]);
            Commission::where('withdrawal_id', $w->id)->where('status', 'REQUESTED')->update(['status' => 'PAID']);
            $this->audit->log('withdrawal.paid', $actorId, 'Withdrawal', $w->id);
        });
    }

    public function blockCommission(Commission $c, string $reason, int $actorId): void
    {
        if (! CommissionRules::canTransition($c->status, 'CANCELED')) {
            throw ValidationException::withMessages(['commission' => "Não é possível cancelar comissão em {$c->status}."]);
        }
        $c->update(['status' => 'CANCELED', 'blocked_reason' => $reason]);
        $this->audit->log('commission.blocked', $actorId, 'Commission', $c->id, ['reason' => $reason]);
    }

    private function fraudSignals(User $referrer, User $referred, ?string $instrumentHash): array
    {
        return [
            'same_user' => $referrer->id === $referred->id,
            'same_cpf' => $referred->cpf_hash !== null && $referrer->cpf_hash === $referred->cpf_hash,
            'same_instrument' => $instrumentHash ? Payment::where('instrument_hash', $instrumentHash)->where('user_id', '!=', $referred->id)->exists() : false,
            'same_ip_recent' => false,
            'signups_24h' => User::where('referred_by_id', $referrer->id)->where('created_at', '>=', now()->subDay())->count(),
            'referred_cancellations' => Subscription::where('status', 'CANCELED')->whereHas('user', fn ($q) => $q->where('referred_by_id', $referrer->id))->count(),
            'chargebacks' => Payment::where('user_id', $referred->id)->where('status', 'CHARGEBACK')->count(),
        ];
    }
}
