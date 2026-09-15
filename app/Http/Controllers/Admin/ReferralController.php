<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\ReferralConversion;
use App\Models\ReferralSetting;
use App\Models\Withdrawal;
use App\Services\AuditService;
use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals, private readonly AuditService $audit) {}

    public function index(): View
    {
        return view('admin.referrals', [
            'settings' => ReferralSetting::current(),
            'commissions' => Commission::with('affiliate')->latest()->limit(100)->get(),
            'conversions' => ReferralConversion::with(['affiliate', 'referredUser'])->latest()->limit(100)->get(),
            'withdrawals' => Withdrawal::with('user')->whereIn('status', ['REQUESTED', 'APPROVED'])->latest()->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'milestone_referrals' => ['required', 'integer', 'min:1', 'max:100'],
            'milestone_reward_cents' => ['required', 'integer', 'min:0'],
            'referred_discount_cents' => ['required', 'integer', 'min:0'],
            'validation_days' => ['required', 'integer', 'min:0', 'max:365'],
            'min_withdrawal_cents' => ['required', 'integer', 'min:0'],
            'payout_methods' => ['required', 'string'],
        ]);
        $data['payout_methods'] = array_values(array_filter(array_map('trim', explode(',', $data['payout_methods']))));
        ReferralSetting::current()->update($data);
        $this->audit->log('referral.settings.updated', $request->user()->id, null, null, $data);

        return back()->with('status', 'Regras de comissionamento salvas.');
    }

    public function block(Request $request, Commission $commission): RedirectResponse
    {
        $this->referrals->blockCommission($commission, $request->validate(['reason' => ['required', 'string', 'max:300']])['reason'], $request->user()->id);

        return back()->with('status', 'Bônus bloqueado.');
    }

    public function blockConversion(Request $request, ReferralConversion $conversion): RedirectResponse
    {
        $this->referrals->blockConversion($conversion, $request->validate(['reason' => ['required', 'string', 'max:300']])['reason'], $request->user()->id);

        return back()->with('status', 'Indicação bloqueada.');
    }

    public function pay(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $this->referrals->payWithdrawal($withdrawal, $request->user()->id);

        return back()->with('status', 'Saque marcado como pago.');
    }
}
