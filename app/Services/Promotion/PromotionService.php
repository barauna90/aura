<?php

namespace App\Services\Promotion;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;

class PromotionService
{
    /** Avalia um cupom sem consumi-lo. */
    public function evaluate(string $code, User $user, string $planCode, int $amountCents): array
    {
        $coupon = Coupon::with('plans')->where('code', strtoupper(trim($code)))->first();
        if (! $coupon) {
            return ['valid' => false, 'reason' => 'Cupom não encontrado', 'discount_cents' => 0, 'trial_days' => 0, 'discounted_months' => 0];
        }
        $out = CouponRules::apply([
            'type' => $coupon->type, 'value' => $coupon->value, 'months' => $coupon->months,
            'starts_at' => $coupon->starts_at, 'ends_at' => $coupon->ends_at, 'max_uses' => $coupon->max_uses,
            'max_uses_per_user' => $coupon->max_uses_per_user, 'min_amount_cents' => $coupon->min_amount_cents,
            'is_active' => $coupon->is_active, 'allowed_plan_codes' => $coupon->plans->pluck('code')->all(),
        ], [
            'now' => now(), 'plan_code' => $planCode, 'amount_cents' => $amountCents,
            'total_uses' => $coupon->usages()->count(), 'user_uses' => $coupon->usages()->where('user_id', $user->id)->count(),
        ]);
        $out['coupon_id'] = $coupon->id;

        return $out;
    }

    public function consume(int $couponId, User $user): void
    {
        CouponUsage::create(['coupon_id' => $couponId, 'user_id' => $user->id]);
    }

    /** Devolve um uso (cobrança cancelada antes do pagamento). */
    public function release(int $couponId, User $user): void
    {
        CouponUsage::where('coupon_id', $couponId)->where('user_id', $user->id)->latest('id')->first()?->delete();
    }
}
