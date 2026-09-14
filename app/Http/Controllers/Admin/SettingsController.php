<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\Billing\AsaasGateway;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Configurações: chave de API do Asaas (criptografada), token do webhook, IA. */
class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings, private readonly AuditService $audit) {}

    public function index(AsaasGateway $asaas): View
    {
        $values = [];
        foreach (SettingsService::KEYS as $key => $meta) {
            $values[$key] = $meta['secret'] ? $this->settings->masked($key) : $this->settings->get($key);
        }

        return view('admin.settings', [
            'values' => $values,
            'keys' => SettingsService::KEYS,
            'webhookUrl' => route('webhooks.asaas'),
            'asaasBase' => $asaas->baseUrl(),
            'asaasReady' => $asaas->isConfigured(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asaas_environment' => ['required', 'in:sandbox,production'],
            'asaas_api_key' => ['nullable', 'string', 'max:300'],
            'asaas_webhook_token' => ['nullable', 'string', 'max:200'],
            'ai_provider' => ['required', 'in:mock,anthropic'],
            'ai_anthropic_api_key' => ['nullable', 'string', 'max:300'],
            'ai_model' => ['required', 'string', 'max:60'],
            'essay_divergence_total' => ['required', 'integer', 'min:0', 'max:1000'],
            'essay_divergence_competency' => ['required', 'integer', 'min:0', 'max:200'],
            'site_support_email' => ['nullable', 'email'],
        ]);
        $map = [
            'asaas_environment' => 'asaas.environment', 'asaas_api_key' => 'asaas.api_key', 'asaas_webhook_token' => 'asaas.webhook_token',
            'ai_provider' => 'ai.provider', 'ai_anthropic_api_key' => 'ai.anthropic_api_key', 'ai_model' => 'ai.model',
            'essay_divergence_total' => 'essay.divergence_total', 'essay_divergence_competency' => 'essay.divergence_competency',
            'site_support_email' => 'site.support_email',
        ];
        foreach ($map as $field => $key) {
            $secret = SettingsService::KEYS[$key]['secret'];
            // Segredos em branco mantêm o valor atual (o campo mostra só uma máscara).
            if ($secret && ($data[$field] ?? '') === '') {
                continue;
            }
            $this->settings->set($key, isset($data[$field]) ? (string) $data[$field] : null);
        }
        if (! empty($data['asaas_webhook_token']) || ! empty($data['asaas_api_key'])) {
            $this->audit->log('settings.asaas.updated', $request->user()->id);
        }

        return back()->with('status', 'Configurações salvas.');
    }

    public function testAsaas(AsaasGateway $asaas): RedirectResponse
    {
        try {
            $r = $asaas->ping();

            return back()->with($r['ok'] ? 'status' : 'error', $r['ok'] ? "Conexão com o Asaas OK ({$r['account']})." : "Asaas respondeu HTTP {$r['status']}. Verifique a chave e o ambiente.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Falha ao conectar: '.$e->getMessage());
        }
    }
}
