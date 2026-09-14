<?php

namespace App\Http\Controllers;

use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals) {}

    /** /r/CODIGO → registra clique, grava cookie e envia ao cadastro. */
    public function landing(Request $request, string $code): RedirectResponse
    {
        $code = strtoupper($code);
        $referrer = $this->referrals->trackClick($code, $request->ip(), $request->userAgent());
        $redirect = redirect()->route('register', ['ref' => $referrer ? $code : null]);

        return $referrer ? $redirect->withCookie(cookie('aura_ref', $code, 60 * 24 * 30)) : $redirect;
    }

    public function index(Request $request): View
    {
        return view('app.referral', $this->referrals->dashboard($request->user()));
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $data = $request->validate(['pix_key' => ['required', 'string', 'max:120']]);
        $this->referrals->requestWithdrawal($request->user(), $data['pix_key']);

        return back()->with('status', 'Saque solicitado. Você será notificado quando for pago.');
    }
}
