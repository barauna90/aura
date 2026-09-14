<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const TERMS_VERSION = '2026-01';

    public function __construct(private readonly AuditService $audit) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            $this->audit->log('auth.login_failed', null, null, null, ['email' => $credentials['email']], $request->ip());

            return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $this->audit->log('auth.login', Auth::id(), null, null, null, $request->ip());

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(Request $request): View
    {
        return view('auth.register', ['referralCode' => $request->query('ref', $request->cookie('aura_ref'))]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::min(8)],
            'referral_code' => ['nullable', 'string', 'max:32'],
            'accept_terms' => ['accepted'],
        ]);
        $referrer = ! empty($data['referral_code']) ? User::where('referral_code', strtoupper($data['referral_code']))->first() : null;

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'referral_code' => ReferralService::generateCode($data['name']),
            'referred_by_id' => $referrer?->id,
        ]);
        foreach (['terms', 'privacy'] as $type) {
            Consent::create(['user_id' => $user->id, 'type' => $type, 'version' => self::TERMS_VERSION, 'granted' => true]);
        }
        $this->audit->log('auth.register', $user->id, 'User', $user->id, ['referrer' => $referrer?->id], $request->ip());
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('onboarding')->withCookie(cookie()->forget('aura_ref'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }

    public function showForgot(): View
    {
        return view('auth.forgot');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Se o e-mail estiver cadastrado, enviaremos um link para redefinir a senha.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', Rules\Password::min(8)]]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
        });

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', 'Senha redefinida. Entre com a nova senha.')
            : back()->withErrors(['email' => 'Link inválido ou expirado.']);
    }
}
