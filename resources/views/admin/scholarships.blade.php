@extends('layouts.admin')
@section('title', 'Bolsas e patrocínios')
@section('admin')
<h1 class="text-2xl font-semibold">Bolsas e patrocínios</h1>
<p class="text-sm text-muted">Acesso gratuito por 30, 90, 180 ou 365 dias, ou integral. Empresas podem financiar vagas; o sistema controla as vagas automaticamente.</p>
<div class="mt-5 grid gap-4 md:grid-cols-2">
    <form method="POST" action="{{ route('admin.scholarships.store') }}" class="card space-y-3">
        @csrf
        <h2 class="font-semibold">Conceder bolsa</h2>
        <div><label class="label">E-mail do aluno</label><input class="input" name="email" type="email" required></div>
        <div><label class="label">Duração</label><select class="input" name="duration"><option value="30">30 dias</option><option value="90">90 dias</option><option value="180">6 meses</option><option value="365">12 meses</option><option value="unlimited">Acesso integral gratuito</option></select></div>
        <div><label class="label">Patrocinador (opcional)</label><select class="input" name="sponsor_id"><option value="">— sem patrocínio —</option>@foreach($sponsors->where('active', true) as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->used_seats }}/{{ $s->seats }})</option>@endforeach</select></div>
        <div><label class="label">Motivo</label><input class="input" name="reason"></div>
        <button class="btn-primary">Conceder</button>
    </form>
    <div class="card">
        <h2 class="font-semibold">Empresas patrocinadoras</h2>
        <ul class="mt-2 text-sm">@forelse($sponsors as $s)<li class="flex justify-between border-b border-border py-1"><span>{{ $s->name }}</span><span class="text-muted">{{ $s->used_seats }}/{{ $s->seats }} bolsas</span></li>@empty<li class="text-muted">Nenhum patrocinador.</li>@endforelse</ul>
        <form method="POST" action="{{ route('admin.scholarships.sponsor') }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf<div><label class="label">Nome</label><input class="input" name="name" required></div><div><label class="label">Vagas</label><input class="input w-24" name="seats" type="number" min="1" required></div><button class="btn-secondary">Adicionar</button></form>
    </div>
</div>
<div class="card mt-4 overflow-x-auto">
    <h2 class="font-semibold">Bolsas concedidas</h2>
    <table class="table mt-3"><thead><tr><th>Aluno</th><th>Duração</th><th>Validade</th><th>Patrocínio</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($scholarships as $s)
            <tr><td>{{ $s->user->name }} <span class="text-xs text-muted">{{ $s->user->email }}</span></td><td>{{ $s->days ? "{$s->days} dias" : 'Integral' }}</td><td>{{ $s->starts_at->format('d/m/Y') }} – {{ $s->ends_at?->format('d/m/Y') ?? '∞' }}</td><td>{{ $s->sponsor?->name ?? '—' }}</td><td><span class="badge-{{ $s->active ? 'success' : 'neutral' }}">{{ $s->active ? 'ativa' : 'revogada' }}</span></td>
                <td>@if($s->active)<form method="POST" action="{{ route('admin.scholarships.revoke', $s) }}" data-confirm="Revogar esta bolsa?">@csrf<button class="text-xs text-danger underline">Revogar</button></form>@endif</td></tr>
        @empty<tr><td colspan="6" class="text-muted">Nenhuma bolsa.</td></tr>@endforelse
    </tbody></table>
</div>
@endsection
