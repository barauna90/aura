<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
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
            'commissions' => Commission::with(['affiliate', 'referredUser'])->latest()->limit(100)->get(),
            'withdrawals' => Withdrawal::with('user')->whereIn('status', ['REQUESTED', 'APPROVED'])->latest()->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'model' => ['required', 'in:PERCENT,FIXED'],
            'value' => ['required', 'integer', 'min:0'],
            'recurring' => ['nullable', 'boolean'],
            'first_payment_only' => ['nullable', 'boolean'],
            'validation_days' => ['required', 'integer', 'min:0', 'max:365'],
            'min_withdrawal_cents' => ['required', 'integer', 'min:0'],
            'payout_methods' => ['required', 'string'],
        ]);
        $data['recurring'] = $request->boolean('recurring');
        $data['first_payment_only'] = $request->boolean('first_payment_only');
        $data['payout_methods'] = array_values(array_filter(array_map('trim', explode(',', $data['payout_methods']))));
        ReferralSetting::current()->update($data);
        $this->audit->log('referral.settings.updated', $request->user()->id, null, null, $data);

        return back()->with('status', 'Regras de comissionamento salvas.');
    }

    public function block(Request $request, Commission $commission): RedirectResponse
    {
        $this->referrals->blockCommission($commission, $request->validate(['reason' => ['required', 'string', 'max:300']])['reason'], $request->user()->id);

        return back()->with('status', 'Comissão bloqueada.');
    }

    public function pay(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $this->referrals->payWithdrawal($withdrawal, $request->user()->id);

        return back()->with('status', 'Saque marcado como pago.');
    }
}
