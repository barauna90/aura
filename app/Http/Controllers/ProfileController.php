<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** USER + COMPLIANCE (LGPD): perfil, acessibilidade, consentimentos, exportação, exclusão. */
class ProfileController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function onboarding(): View
    {
        return view('app.onboarding');
    }

    public function storeOnboarding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'goal' => ['required', 'in:Medicina,Direito,Engenharia,Licenciaturas,Outro'],
            'target_exam_date' => ['nullable', 'date'],
            'weekly_hours' => ['required', 'integer', 'min:1', 'max:80'],
            'main_difficulty' => ['nullable', 'string', 'max:200'],
        ]);
        $request->user()->update($data + ['onboarding_done' => true]);

        return redirect()->route('exams.index')->with('status', 'Perfil salvo. Escolha uma prova oficial para o seu diagnóstico.');
    }

    public function edit(Request $request): View
    {
        return view('app.profile', ['user' => $request->user(), 'consents' => Consent::where('user_id', $request->user()->id)->latest()->get()->unique('type')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'font_scale' => ['required', 'integer', 'in:90,100,115,130,150'],
            'theme' => ['required', 'in:dark,light'],
            'marketing_email' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);
        $user = $request->user();
        $user->update(['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'font_scale' => $data['font_scale'], 'theme' => $data['theme']]);
        if (! empty($data['password'])) {
            $user->update(['password' => $data['password']]);
        }
        $marketing = $request->boolean('marketing_email');
        $last = Consent::where('user_id', $user->id)->where('type', 'marketing_email')->latest()->first();
        if (! $last || $last->granted !== $marketing) {
            Consent::create(['user_id' => $user->id, 'type' => 'marketing_email', 'version' => '2026-01', 'granted' => $marketing]);
        }

        return back()->with('status', 'Perfil atualizado.');
    }

    /** Portabilidade (LGPD): exporta os dados do titular em JSON. */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->audit->log('lgpd.export', $user->id);

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'user' => $user->only(['name', 'email', 'phone', 'goal', 'target_exam_date', 'weekly_hours', 'main_difficulty', 'created_at']),
            'sessions' => $user->examSessions()->with(['result', 'answerSheet.answers'])->get(),
            'essays' => $user->essays()->with('finalResult')->get(),
            'error_notebook' => $user->errorNotebook()->get(),
            'subscriptions' => $user->subscriptions()->get(),
            'payments' => $user->payments()->get(['id', 'amount_cents', 'discount_cents', 'status', 'billing_type', 'created_at']),
            'consents' => $user->consents()->get(),
        ], 200, ['Content-Disposition' => 'attachment; filename="meus-dados-aura.json"'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** Exclusão (anonimização) — registros financeiros exigidos por lei são mantidos. */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required']]);
        $user = $request->user();
        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Senha incorreta.']);
        }
        if ($user->subscriptions()->whereIn('status', ['ACTIVE', 'TRIALING'])->exists()) {
            return back()->withErrors(['password' => 'Cancele sua assinatura antes de excluir a conta.']);
        }
        DB::transaction(function () use ($user) {
            $user->essays()->update(['draft_text' => null, 'final_text' => null]);
            $user->forceFill([
                'name' => 'Usuário removido', 'email' => "deleted-{$user->id}@anon.invalid", 'password' => Hash::make(bin2hex(random_bytes(16))),
                'cpf_hash' => null, 'phone' => null, 'goal' => null, 'main_difficulty' => null, 'is_active' => false,
            ])->save();
            $user->delete();
            $this->audit->log('lgpd.delete', $user->id);
        });
        Auth::logout();
        $request->session()->invalidate();

        return redirect()->route('landing')->with('status', 'Sua conta foi excluída.');
    }
}
