@extends('layouts.admin')
@section('title', 'Cupons e promoções')
@section('admin')
<h1 class="text-2xl font-semibold">Cupons e promoções</h1>
<p class="text-sm text-muted">Percentual, valor fixo, primeiro mês, X meses com desconto, teste gratuito, cupom individual ou por afiliado.</p>
<div class="mt-5 grid gap-6 lg:grid-cols-[360px_1fr]">
    <form method="POST" action="{{ route('admin.coupons.store') }}" class="card space-y-3">
        @csrf
        <h2 class="font-semibold">Novo cupom</h2>
        <div><label class="label">Código</label><input class="input uppercase" name="code" required></div>
        <div><label class="label">Tipo</label><select class="input" name="type"><option value="PERCENT">Percentual</option><option value="FIXED">Valor fixo (centavos)</option><option value="FIRST_MONTH">Primeiro mês (%)</option><option value="N_MONTHS">X meses com desconto (%)</option><option value="FREE_TRIAL">Teste gratuito (dias)</option></select></div>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="label">Valor</label><input class="input" name="value" type="number" min="1" required></div>
            <div><label class="label">Meses (N_MONTHS)</label><input class="input" name="months" type="number"></div>
            <div><label class="label">Início</label><input class="input" name="starts_at" type="date"></div>
            <div><label class="label">Fim</label><input class="input" name="ends_at" type="date"></div>
            <div><label class="label">Limite total</label><input class="input" name="max_uses" type="number"></div>
            <div><label class="label">Por usuário</label><input class="input" name="max_uses_per_user" type="number" value="1"></div>
        </div>
        <div><label class="label">Valor mínimo (centavos)</label><input class="input" name="min_amount_cents" type="number"></div>
        <fieldset><legend class="label">Planos permitidos (vazio = todos)</legend><div class="flex flex-wrap gap-2 text-sm">@foreach($plans as $p)<label class="flex items-center gap-1"><input type="checkbox" name="plans[]" value="{{ $p->id }}"> {{ $p->code }}</label>@endforeach</div></fieldset>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Ativo</label>
        <button class="btn-primary">Salvar cupom</button>
    </form>
    <div class="card overflow-x-auto">
        <h2 class="font-semibold">Cupons</h2>
        <table class="table mt-3">
            <thead><tr><th>Código</th><th>Tipo</th><th>Valor</th><th>Vigência</th><th>Usos</th><th>Planos</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($coupons as $c)
                    <tr><td class="font-mono">{{ $c->code }}</td><td>{{ $c->type }}</td><td>{{ $c->value }}{{ $c->months ? " × {$c->months}m" : '' }}</td><td>{{ $c->starts_at->format('d/m/Y') }} – {{ $c->ends_at?->format('d/m/Y') ?? '∞' }}</td><td>{{ $c->usages_count }}{{ $c->max_uses ? "/{$c->max_uses}" : '' }}</td><td>{{ $c->plans->pluck('code')->implode(', ') ?: 'todos' }}</td><td><span class="badge-{{ $c->is_active ? 'success' : 'neutral' }}">{{ $c->is_active ? 'ativo' : 'inativo' }}</span></td></tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Nenhum cupom.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
