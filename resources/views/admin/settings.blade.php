@extends('layouts.admin')
@section('title', 'Configurações')
@section('admin')
<h1 class="text-2xl font-semibold">Configurações</h1>
<p class="text-sm text-muted">Chaves de API ficam criptografadas no banco. Campos secretos em branco mantêm o valor atual.</p>

<form method="POST" action="{{ route('admin.settings.update') }}" class="mt-5 space-y-4">
    @csrf @method('PUT')
    <div class="card space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Asaas — cobrança e liberação automática</h2>
            <span class="badge-{{ $asaasReady ? 'success' : 'warning' }}">{{ $asaasReady ? 'Chave configurada' : 'Não configurado' }}</span>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            <div><label class="label" for="asaas_environment">Ambiente</label><select class="input" id="asaas_environment" name="asaas_environment"><option value="sandbox" @selected($values['asaas.environment'] === 'sandbox')>Sandbox (testes)</option><option value="production" @selected($values['asaas.environment'] === 'production')>Produção</option></select><p class="mt-1 text-xs text-muted">API: {{ $asaasBase }}. Chaves de sandbox e produção são independentes.</p></div>
            <div><label class="label" for="asaas_api_key">Chave de API (access_token)</label><input class="input" id="asaas_api_key" name="asaas_api_key" type="password" autocomplete="off" placeholder="{{ $values['asaas.api_key'] ?? 'Cole a chave gerada em Integrações → Chaves de API' }}"></div>
            <div><label class="label" for="asaas_webhook_token">Token de autenticação do webhook</label><input class="input" id="asaas_webhook_token" name="asaas_webhook_token" type="password" autocomplete="off" placeholder="{{ $values['asaas.webhook_token'] ?? 'Defina um token forte (não use a chave de API)' }}"><p class="mt-1 text-xs text-muted">O Asaas envia este valor no header <code>asaas-access-token</code>; só eventos com o token correto liberam acesso.</p></div>
            <div><label class="label">URL do webhook (cadastre no Asaas)</label><code class="block break-all rounded-xl bg-surface-2 px-3 py-2 text-xs">{{ $webhookUrl }}</code><p class="mt-1 text-xs text-muted">Eventos: PAYMENT_CONFIRMED, PAYMENT_RECEIVED, PAYMENT_OVERDUE, PAYMENT_REFUNDED, PAYMENT_CHARGEBACK_REQUESTED, PAYMENT_DELETED. Fila sequencial, API v3.</p></div>
        </div>
    </div>

    <div class="card space-y-4">
        <h2 class="font-semibold">Inteligência artificial (correção de redação e Professor IA)</h2>
        <div class="grid gap-3 md:grid-cols-3">
            <div><label class="label" for="ai_provider">Provedor</label><select class="input" id="ai_provider" name="ai_provider"><option value="mock" @selected($values['ai.provider'] === 'mock')>Simulado (desenvolvimento)</option><option value="anthropic" @selected($values['ai.provider'] === 'anthropic')>Anthropic (Claude)</option></select></div>
            <div><label class="label" for="ai_anthropic_api_key">Chave de API Anthropic</label><input class="input" id="ai_anthropic_api_key" name="ai_anthropic_api_key" type="password" autocomplete="off" placeholder="{{ $values['ai.anthropic_api_key'] ?? 'sk-ant-…' }}"></div>
            <div><label class="label" for="ai_model">Modelo</label><input class="input" id="ai_model" name="ai_model" value="{{ $values['ai.model'] }}"></div>
            <div><label class="label" for="essay_divergence_total">Divergência máx. no total (A×B) para acionar o avaliador C</label><input class="input" id="essay_divergence_total" name="essay_divergence_total" type="number" value="{{ $values['essay.divergence_total'] }}"></div>
            <div><label class="label" for="essay_divergence_competency">Divergência máx. por competência</label><input class="input" id="essay_divergence_competency" name="essay_divergence_competency" type="number" value="{{ $values['essay.divergence_competency'] }}"></div>
            <div><label class="label" for="site_support_email">E-mail de suporte</label><input class="input" id="site_support_email" name="site_support_email" type="email" value="{{ $values['site.support_email'] }}"></div>
        </div>
        <p class="text-xs text-muted">Ajuste os limites de divergência conforme a regra oficial usada como referência na edição.</p>
    </div>
    <button class="btn-primary">Salvar configurações</button>
</form>
<form method="POST" action="{{ route('admin.settings.asaas_test') }}" class="mt-3">@csrf<button class="btn-secondary" @disabled(!$asaasReady)>Testar conexão com o Asaas</button></form>
@endsection
