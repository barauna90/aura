<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Plan;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        return view('admin.coupons', [
            'coupons' => Coupon::with('plans')->withCount('usages')->latest()->get(),
            'plans' => Plan::where('price_cents', '>', 0)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'type' => ['required', 'in:PERCENT,FIXED,FIRST_MONTH,N_MONTHS,FREE_TRIAL'],
            'value' => ['required', 'integer', 'min:1'],
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['required', 'integer', 'min:1'],
            'min_amount_cents' => ['nullable', 'integer', 'min:0'],
            'plans' => ['nullable', 'array'], 'plans.*' => ['exists:plans,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $plans = $data['plans'] ?? [];
        unset($data['plans']);
        $data['code'] = strtoupper(trim($data['code']));
        $data['starts_at'] = $data['starts_at'] ?? now();
        $data['is_active'] = $request->boolean('is_active', true);
        $coupon = Coupon::updateOrCreate(['code' => $data['code']], $data);
        $coupon->plans()->sync($plans);
        $audit->log('coupon.upserted', $request->user()->id, 'Coupon', $coupon->id);

        return back()->with('status', "Cupom {$coupon->code} salvo.");
    }
}
