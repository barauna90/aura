@extends('layouts.admin')
@section('title', 'Indicações e bônus')
@section('admin')
@php($brl = fn ($c) => 'R$ '.number_format($c / 100, 2, ',', '.'))
@php($ctone = ['PENDING' => 'neutral', 'VALIDATED' => 'success', 'CANCELED' => 'danger', 'REVERSED' => 'danger'])
<h1 class="text-2xl font-semibold">Indicações e bônus</h1>
<p class="text-sm text-muted">Fluxo: indicação → cadastro → assinatura → pagamento confirmado → validação ({{ $settings->validation_days }} dias) → a cada {{ $settings->milestone_referrals }} validadas, bônus de {{ $brl($settings->milestone_reward_cents) }} → saque → pagamento.</p>

<form method="POST" action="{{ route('admin.referrals.update') }}" class="card mt-5 space-y-3">
    @csrf @method('PUT')
    <h2 class="font-semibold">Regras do programa</h2>
    <div class="grid gap-3 sm:grid-cols-3">
        <div><label class="label">Indicações por meta</label><input class="input" name="milestone_referrals" type="number" min="1" value="{{ $settings->milestone_referrals }}"><p class="mt-1 text-xs text-muted">Quantas indicações com pagamento confirmado e validadas liberam um bônus.</p></div>
        <div><label class="label">Valor do bônus por meta (centavos)</label><input class="input" name="milestone_reward_cents" type="number" min="0" value="{{ $settings->milestone_reward_cents }}"><p class="mt-1 text-xs text-muted">Ex.: 4000 = R$ 40,00.</p></div>
        <div><label class="label">Desconto para o indicado na assinatura (centavos)</label><input class="input" name="referred_discount_cents" type="number" min="0" value="{{ $settings->referred_discount_cents }}"><p class="mt-1 text-xs text-muted">Aplicado na primeira cobrança de quem entrou por código.</p></div>
        <div><label class="label">Validação da indicação (dias)</label><input class="input" name="validation_days" type="number" min="0" value="{{ $settings->validation_days }}"><p class="mt-1 text-xs text-muted">Se o indicado cancelar ou houver estorno nesse prazo, a indicação não conta.</p></div>
        <div><label class="label">Valor mínimo para saque (centavos)</label><input class="input" name="min_withdrawal_cents" type="number" min="0" value="{{ $settings->min_withdrawal_cents }}"></div>
        <div><label class="label">Formas de pagamento (vírgula)</label><input class="input" name="payout_methods" value="{{ implode(',', $settings->payout_methods) }}"></div>
    </div>
    <button class="btn-primary">Salvar</button>
</form>

<div class="card mt-4 overflow-x-auto">
    <h2 class="font-semibold">Saques pendentes</h2>
    <table class="table mt-3"><thead><tr><th>Afiliado</th><th>Valor</th><th>Método</th><th>Solicitado em</th><th></th></tr></thead><tbody>
        @forelse($withdrawals as $w)<tr><td>{{ $w->user->name }} <span class="text-xs text-muted">{{ $w->user->email }}</span></td><td>{{ $brl($w->amount_cents) }}</td><td>{{ $w->method }}</td><td>{{ $w->created_at->format('d/m/Y') }}</td><td><form method="POST" action="{{ route('admin.referrals.pay', $w) }}" data-confirm="Confirmar que o saque foi pago?">@csrf<button class="btn-success px-3 py-1 text-xs">Marcar como pago</button></form></td></tr>@empty<tr><td colspan="5" class="text-muted">Nenhum saque pendente.</td></tr>@endforelse
    </tbody></table>
</div>

<div class="card mt-4 overflow-x-auto">
    <h2 class="font-semibold">Indicações efetivadas (pagamento confirmado)</h2>
    <table class="table mt-3"><thead><tr><th>Afiliado</th><th>Indicado</th><th>Status</th><th>Valida em</th><th>Bônus</th><th>Sinais</th><th>Bloquear</th></tr></thead><tbody>
        @forelse($conversions as $c)
            <tr><td>{{ $c->affiliate->email }}</td><td>{{ $c->referredUser->email }}</td><td><span class="badge-{{ $ctone[$c->status] }}">{{ $c->status }}</span>@if($c->blocked_reason)<p class="text-xs text-muted">{{ $c->blocked_reason }}</p>@endif</td><td>{{ $c->available_at?->format('d/m/Y') }}</td><td>{{ $c->commission_id ? '#'.$c->commission_id : '—' }}</td><td class="text-xs text-warning">{{ implode(', ', $c->fraud_flags ?? []) }}</td>
                <td>@if(in_array($c->status, ['PENDING', 'VALIDATED']))<form method="POST" action="{{ route('admin.referrals.block_conversion', $c) }}" class="flex gap-1">@csrf<input class="input px-2 py-1 text-xs" name="reason" placeholder="motivo" required><button class="btn-danger px-2 py-1 text-xs">Bloquear</button></form>@endif</td></tr>
        @empty<tr><td colspan="7" class="text-muted">Nenhuma indicação efetivada.</td></tr>@endforelse
    </tbody></table>
</div>

<div class="card mt-4 overflow-x-auto">
    <h2 class="font-semibold">Bônus gerados</h2>
    <table class="table mt-3"><thead><tr><th>Afiliado</th><th>Indicações</th><th>Valor</th><th>Status</th><th>Gerado em</th><th>Bloquear</th></tr></thead><tbody>
        @forelse($commissions as $c)
            <tr><td>{{ $c->affiliate->email }}</td><td>{{ $c->conversions_count }}</td><td>{{ $brl($c->amount_cents) }}</td><td><span class="badge-neutral">{{ $c->status }}</span>@if($c->blocked_reason)<p class="text-xs text-muted">{{ $c->blocked_reason }}</p>@endif</td><td>{{ $c->created_at->format('d/m/Y') }}</td>
                <td>@if(in_array($c->status, ['AVAILABLE', 'REQUESTED']))<form method="POST" action="{{ route('admin.referrals.block', $c) }}" class="flex gap-1">@csrf<input class="input px-2 py-1 text-xs" name="reason" placeholder="motivo" required><button class="btn-danger px-2 py-1 text-xs">Bloquear</button></form>@endif</td></tr>
        @empty<tr><td colspan="6" class="text-muted">Nenhum bônus gerado.</td></tr>@endforelse
    </tbody></table>
</div>
<div class="notice-neutral mt-4">Antifraude automático: autoindicação, mesmo CPF, mesmo meio de pagamento e chargeback bloqueiam a indicação; volume anormal e cancelamentos repetidos geram alerta. Estorno de um indicado que já compunha um bônus não pago cancela o bônus e devolve as demais indicações à contagem.</div>
@endsection
