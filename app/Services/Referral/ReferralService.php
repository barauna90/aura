<?php

namespace App\Services\Referral;

use App\Models\Commission;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\ReferralClick;
use App\Models\ReferralConversion;
use App\Models\ReferralSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * REFERRAL ORCHESTRATOR (meta de indicações):
 * INDICAÇÃO → CADASTRO → ASSINATURA → PAGAMENTO CONFIRMADO → indicação PENDING
 *   → (prazo de validação sem desistência) VALIDATED
 *   → a cada N validadas: bônus (Commission) AVAILABLE → SAQUE → PAGO
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

    /** Desistência (cancelamento) enquanto a indicação ainda está no prazo de validação: não é efetivada. */
    public function onSubscriptionCanceled(Subscription $subscription): void
    {
        ReferralConversion::where('subscription_id', $subscription->id)->where('status', 'PENDING')->each(function (ReferralConversion $c) {
            $c->update(['status' => 'CANCELED', 'blocked_reason' => 'Indicado cancelou a assinatura durante o período de validação']);
            $this->audit->log('referral.conversion.canceled', null, 'ReferralConversion', $c->id, ['reason' => 'DESISTENCIA']);
        });
    }

    /** Chamado após pagamento CONFIRMADO (webhook): registra a indicação efetivada (pendente de validação). */
    public function onPaymentConfirmed(Payment $payment): void
    {
        $payment->loadMissing(['user.referredBy', 'subscription']);
        $referrer = $payment->user->referredBy;
        if (! $referrer || ! $payment->subscription || ! $payment->confirmed_at) {
            return;
        }
        $ordinal = Payment::where('user_id', $payment->user_id)->where('status', 'CONFIRMED')->where('confirmed_at', '<=', $payment->confirmed_at)->count();
        $already = ReferralConversion::where('referred_user_id', $payment->user_id)->exists();
        if (! CommissionRules::isConversion($ordinal, $already)) {
            return;
        }
        $s = ReferralSetting::current();
        $fraud = CommissionRules::assessFraud($this->fraudSignals($referrer, $payment->user, $payment->instrument_hash));

        $conversion = ReferralConversion::create([
            'affiliate_id' => $referrer->id,
            'referred_user_id' => $payment->user_id,
            'subscription_id' => $payment->subscription_id,
            'payment_id' => $payment->id,
            'status' => $fraud['block'] ? 'CANCELED' : 'PENDING',
            'available_at' => CommissionRules::availableAt($payment->confirmed_at, $s->validation_days),
            'fraud_flags' => $fraud['flags'],
            'blocked_reason' => $fraud['block'] ? 'Bloqueada automaticamente: '.implode(', ', $fraud['flags']) : null,
        ]);
        if ($fraud['flags']) {
            $this->audit->alert('WARN', 'referrals.fraud', "Sinais de fraude na indicação {$conversion->id}", ['flags' => $fraud['flags']]);
        }
        $this->audit->log('referral.conversion.created', null, 'ReferralConversion', $conversion->id, ['flags' => $fraud['flags']]);
    }

    /** Estorno/chargeback do pagamento do indicado. */
    public function onPaymentReversed(Payment $payment, string $reason): void
    {
        $c = ReferralConversion::where('payment_id', $payment->id)->first();
        if ($c) {
            $this->reverseConversion($c, $reason);
        }
    }

    /**
     * Job diário: valida indicações maduras (assinatura indicada precisa continuar ativa)
     * e gera um bônus a cada N indicações validadas. Retorna quantas indicações foram validadas.
     */
    public function releaseMatured(): int
    {
        $validated = 0;
        $affiliates = [];
        ReferralConversion::with('subscription')->where('status', 'PENDING')->where('available_at', '<=', now())->each(function (ReferralConversion $c) use (&$validated, &$affiliates) {
            $sub = $c->subscription;
            if (in_array($sub->status, ['CANCELED', 'REFUNDED', 'SUSPENDED'], true) || $sub->cancel_at_period_end || $sub->canceled_at) {
                $c->update(['status' => 'CANCELED', 'blocked_reason' => 'Assinatura indicada não permaneceu ativa']);

                return;
            }
            $c->update(['status' => 'VALIDATED']);
            $affiliates[$c->affiliate_id] = true;
            $validated++;
        });
        foreach (array_keys($affiliates) as $affiliateId) {
            $this->grantMilestones($affiliateId);
        }

        return $validated;
    }

    /** Agrupa indicações validadas (ainda sem bônus) de N em N e cria o bônus disponível para saque. */
    public function grantMilestones(int $affiliateId): int
    {
        $s = ReferralSetting::current();
        $granted = 0;
        DB::transaction(function () use ($affiliateId, $s, &$granted) {
            $pool = ReferralConversion::where('affiliate_id', $affiliateId)->where('status', 'VALIDATED')->whereNull('commission_id')->orderBy('id')->lockForUpdate()->get();
            $ready = CommissionRules::milestonesReady($pool->count(), $s->milestone_referrals);
            for ($i = 0; $i < $ready; $i++) {
                $group = $pool->slice($i * $s->milestone_referrals, $s->milestone_referrals);
                $commission = Commission::create([
                    'affiliate_id' => $affiliateId,
                    'amount_cents' => $s->milestone_reward_cents,
                    'conversions_count' => $group->count(),
                    'status' => 'AVAILABLE',
                ]);
                ReferralConversion::whereIn('id', $group->pluck('id'))->update(['commission_id' => $commission->id]);
                $this->audit->log('commission.granted', null, 'Commission', $commission->id, ['amount' => $s->milestone_reward_cents, 'conversions' => $group->pluck('id')->all()]);
                $granted++;
            }
        });

        return $granted;
    }

    public function dashboard(User $user): array
    {
        $s = ReferralSetting::current();
        $commissions = Commission::with('conversions.referredUser')->where('affiliate_id', $user->id)->latest()->get();
        $conversions = ReferralConversion::with('referredUser')->where('affiliate_id', $user->id)->latest()->get();
        $sum = fn (array $st) => $commissions->whereIn('status', $st)->sum('amount_cents');
        $clicks = ReferralClick::where('referrer_id', $user->id)->count();
        $subs = Subscription::whereIn('status', ['ACTIVE', 'TRIALING'])->whereHas('user', fn ($q) => $q->where('referred_by_id', $user->id))->count();
        $validatedUnused = $conversions->where('status', 'VALIDATED')->whereNull('commission_id')->count();

        return [
            'code' => $user->referral_code,
            'link' => route('referral.landing', $user->referral_code),
            'clicks' => $clicks,
            'signups' => User::where('referred_by_id', $user->id)->count(),
            'subscriptions' => $subs,
            'conversion' => $clicks ? round($subs / $clicks * 100, 1) : 0,
            'pending_conversions' => $conversions->where('status', 'PENDING')->count(),
            'validated_conversions' => $conversions->where('status', 'VALIDATED')->count(),
            'progress' => CommissionRules::progress($validatedUnused, $s->milestone_referrals),
            'available_cents' => $sum(['AVAILABLE']),
            'requested_cents' => $sum(['REQUESTED']),
            'paid_cents' => $sum(['PAID']),
            'min_withdrawal_cents' => $s->min_withdrawal_cents,
            'milestone_referrals' => $s->milestone_referrals,
            'milestone_reward_cents' => $s->milestone_reward_cents,
            'referred_discount_cents' => $s->referred_discount_cents,
            'validation_days' => $s->validation_days,
            'history' => $commissions,
            'conversions' => $conversions,
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

    /** Admin: bloqueia um bônus ainda não pago (definitivo: as indicações que o compõem também são canceladas). */
    public function blockCommission(Commission $c, string $reason, int $actorId): void
    {
        if (! CommissionRules::canTransition($c->status, 'CANCELED')) {
            throw ValidationException::withMessages(['commission' => "Não é possível cancelar bônus em {$c->status}."]);
        }
        DB::transaction(function () use ($c, $reason, $actorId) {
            $this->detachFromWithdrawal($c);
            $c->update(['status' => 'CANCELED', 'blocked_reason' => $reason]);
            $c->conversions()->update(['status' => 'CANCELED', 'blocked_reason' => $reason]);
            $this->audit->log('commission.blocked', $actorId, 'Commission', $c->id, ['reason' => $reason]);
        });
    }

    /** Admin: bloqueia uma indicação (ex.: fraude confirmada). */
    public function blockConversion(ReferralConversion $c, string $reason, int $actorId): void
    {
        if (! CommissionRules::canTransitionConversion($c->status, 'CANCELED')) {
            throw ValidationException::withMessages(['conversion' => "Não é possível bloquear indicação em {$c->status}."]);
        }
        $this->reverseConversion($c, $reason, 'CANCELED', $actorId);
    }

    /**
     * Indicação deixa de valer (estorno, chargeback, bloqueio). Se já compunha um bônus:
     * bônus ainda não pago é cancelado e as demais indicações voltam ao "pote" (contam de novo);
     * bônus já pago fica REVERSED (registro de dívida) sem devolver as demais.
     */
    private function reverseConversion(ReferralConversion $c, string $reason, string $target = 'REVERSED', ?int $actorId = null): void
    {
        DB::transaction(function () use ($c, $reason, $target, $actorId) {
            if (! CommissionRules::canTransitionConversion($c->status, $target)) {
                return;
            }
            $commission = $c->commission;
            $c->update(['status' => $target, 'blocked_reason' => $reason, 'commission_id' => null]);
            $this->audit->log('referral.conversion.'.strtolower($target), $actorId, 'ReferralConversion', $c->id, ['reason' => $reason]);
            if (! $commission) {
                return;
            }
            $ctarget = $commission->status === 'PAID' ? 'REVERSED' : 'CANCELED';
            if (CommissionRules::canTransition($commission->status, $ctarget)) {
                if ($ctarget === 'CANCELED') {
                    $this->detachFromWithdrawal($commission);
                }
                $commission->update(['status' => $ctarget, 'blocked_reason' => "Indicação #{$c->id} invalidada: {$reason}"]);
                $this->audit->log('commission.'.strtolower($ctarget), $actorId, 'Commission', $commission->id, ['reason' => $reason]);
                if ($ctarget === 'CANCELED') {
                    // As outras indicações continuam válidas: voltam a contar para a próxima meta.
                    $commission->conversions()->update(['commission_id' => null]);
                    $this->grantMilestones($commission->affiliate_id);
                }
            }
        });
    }

    /** Bônus cancelado enquanto aguardava saque: abate do pedido de saque (rejeita se zerar). */
    private function detachFromWithdrawal(Commission $c): void
    {
        if ($c->status !== 'REQUESTED' || ! $c->withdrawal_id) {
            return;
        }
        $w = Withdrawal::lockForUpdate()->find($c->withdrawal_id);
        if ($w && $w->status !== 'PAID') {
            $remaining = max(0, $w->amount_cents - $c->amount_cents);
            $w->update(['amount_cents' => $remaining, 'status' => $remaining === 0 ? 'REJECTED' : $w->status]);
        }
        $c->withdrawal_id = null;
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
