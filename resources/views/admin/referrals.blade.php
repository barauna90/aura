@extends('layouts.admin')
@section('title', 'Indicações e comissões')
@section('admin')
@php($brl = fn ($c) => 'R$ '.number_format($c / 100, 2, ',', '.'))
<h1 class="text-2xl font-semibold">Indicações e comissões</h1>
<p class="text-sm text-muted">Fluxo: indicação → cadastro → assinatura → pagamento confirmado → validação → liberada → saque → pagamento.</p>

<form method="POST" action="{{ route('admin.referrals.update') }}" class="card mt-5 space-y-3">
    @csrf @method('PUT')
    <h2 class="font-semibold">Regras de comissionamento</h2>
    <div class="grid gap-3 sm:grid-cols-3">
        <div><label class="label">Modelo</label><select class="input" name="model"><option value="PERCENT" @selected($settings->model === 'PERCENT')>Percentual</option><option value="FIXED" @selected($settings->model === 'FIXED')>Valor fixo (centavos)</option></select></div>
        <div><label class="label">Comissão do indicador (centavos ou %)</label><input class="input" name="value" type="number" value="{{ $settings->value }}"><p class="mt-1 text-xs text-muted">Ex.: 1000 = R$ 10,00 no modelo fixo.</p></div>
        <div><label class="label">Desconto para o indicado na assinatura (centavos)</label><input class="input" name="referred_discount_cents" type="number" value="{{ $settings->referred_discount_cents }}"><p class="mt-1 text-xs text-muted">Aplicado na primeira cobrança de quem entrou por código.</p></div>
        <div><label class="label">Bloqueio da comissão (dias)</label><input class="input" name="validation_days" type="number" value="{{ $settings->validation_days }}"><p class="mt-1 text-xs text-muted">Se o indicado cancelar ou houver estorno nesse prazo, a comissão não é efetivada.</p></div>
        <div><label class="label">Valor mínimo para saque (centavos)</label><input class="input" name="min_withdrawal_cents" type="number" value="{{ $settings->min_withdrawal_cents }}"></div>
        <div><label class="label">Formas de pagamento (vírgula)</label><input class="input" name="payout_methods" value="{{ implode(',', $settings->payout_methods) }}"></div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="first_payment_only" value="1" @checked($settings->first_payment_only)> Comissão somente na primeira mensalidade</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="recurring" value="1" @checked($settings->recurring)> Comissão recorrente</label>
    <button class="btn-primary">Salvar</button>
</form>

<div class="card mt-4 overflow-x-auto">
    <h2 class="font-semibold">Saques pendentes</h2>
    <table class="table mt-3"><thead><tr><th>Afiliado</th><th>Valor</th><th>Método</th><th>Solicitado em</th><th></th></tr></thead><tbody>
        @forelse($withdrawals as $w)<tr><td>{{ $w->user->name }} <span class="text-xs text-muted">{{ $w->user->email }}</span></td><td>{{ $brl($w->amount_cents) }}</td><td>{{ $w->method }}</td><td>{{ $w->created_at->format('d/m/Y') }}</td><td><form method="POST" action="{{ route('admin.referrals.pay', $w) }}" data-confirm="Confirmar que o saque foi pago?">@csrf<button class="btn-success px-3 py-1 text-xs">Marcar como pago</button></form></td></tr>@empty<tr><td colspan="5" class="text-muted">Nenhum saque pendente.</td></tr>@endforelse
    </tbody></table>
</div>

<div class="card mt-4 overflow-x-auto">
    <h2 class="font-semibold">Comissões recentes</h2>
    <table class="table mt-3"><thead><tr><th>Afiliado</th><th>Indicado</th><th>Valor</th><th>Status</th><th>Sinais</th><th>Bloquear</th></tr></thead><tbody>
        @forelse($commissions as $c)
            <tr><td>{{ $c->affiliate->email }}</td><td>{{ $c->referredUser->email }}</td><td>{{ $brl($c->amount_cents) }}</td><td><span class="badge-neutral">{{ $c->status }}</span></td><td class="text-xs text-warning">{{ implode(', ', $c->fraud_flags ?? []) }}</td>
                <td>@if(in_array($c->status, ['PENDING', 'APPROVED', 'AVAILABLE', 'REQUESTED']))<form method="POST" action="{{ route('admin.referrals.block', $c) }}" class="flex gap-1">@csrf<input class="input px-2 py-1 text-xs" name="reason" placeholder="motivo" required><button class="btn-danger px-2 py-1 text-xs">Bloquear</button></form>@endif</td></tr>
        @empty<tr><td colspan="6" class="text-muted">Nenhuma comissão.</td></tr>@endforelse
    </tbody></table>
</div>
<div class="notice-neutral mt-4">Antifraude automático: autoindicação, mesmo CPF, mesmo meio de pagamento e chargeback bloqueiam a comissão; volume anormal e cancelamentos repetidos geram alerta.</div>
@endsection
