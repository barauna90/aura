<?php

namespace App\Services\Promotion;

use Carbon\CarbonInterface;

/** PROMOTION AGENT — regras puras de cupom. */
final class CouponRules
{
    /**
     * @param  array{type:string, value:int, months:?int, starts_at:CarbonInterface, ends_at:?CarbonInterface, max_uses:?int, max_uses_per_user:int, min_amount_cents:?int, is_active:bool, allowed_plan_codes:array}  $c
     * @param  array{now:CarbonInterface, plan_code:string, amount_cents:int, total_uses:int, user_uses:int}  $ctx
     * @return array{valid:bool, reason?:string, discount_cents:int, trial_days:int, discounted_months:int}
     */
    public static function apply(array $c, array $ctx): array
    {
        $fail = fn (string $reason) => ['valid' => false, 'reason' => $reason, 'discount_cents' => 0, 'trial_days' => 0, 'discounted_months' => 0];
        if (! $c['is_active']) {
            return $fail('Cupom inativo');
        }
        if ($ctx['now']->lt($c['starts_at'])) {
            return $fail('Cupom ainda não está vigente');
        }
        if ($c['ends_at'] && $ctx['now']->gt($c['ends_at'])) {
            return $fail('Cupom expirado');
        }
        if ($c['max_uses'] !== null && $ctx['total_uses'] >= $c['max_uses']) {
            return $fail('Cupom esgotado');
        }
        if ($ctx['user_uses'] >= $c['max_uses_per_user']) {
            return $fail('Você já utilizou este cupom');
        }
        if ($c['allowed_plan_codes'] && ! in_array($ctx['plan_code'], $c['allowed_plan_codes'], true)) {
            return $fail('Cupom não válido para este plano');
        }
        if ($c['min_amount_cents'] !== null && $ctx['amount_cents'] < $c['min_amount_cents']) {
            return $fail('Valor mínimo não atingido');
        }
        $pct = fn () => (int) floor($ctx['amount_cents'] * min(100, $c['value']) / 100);

        return match ($c['type']) {
            'PERCENT', 'FIRST_MONTH' => ['valid' => true, 'discount_cents' => $pct(), 'trial_days' => 0, 'discounted_months' => 1],
            'FIXED' => ['valid' => true, 'discount_cents' => min($c['value'], $ctx['amount_cents']), 'trial_days' => 0, 'discounted_months' => 1],
            'N_MONTHS' => ['valid' => true, 'discount_cents' => $pct(), 'trial_days' => 0, 'discounted_months' => $c['months'] ?? 1],
            'FREE_TRIAL' => ['valid' => true, 'discount_cents' => 0, 'trial_days' => $c['value'], 'discounted_months' => 0],
            default => $fail('Tipo de cupom desconhecido'),
        };
    }
}
