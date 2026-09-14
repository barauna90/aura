@extends('layouts.app')
@section('title', 'Plano de estudos')
@section('content')
@php($E = \App\Support\Enem::class)
@php($kinds = ['QUESTOES' => 'Questões', 'REVISAO' => 'Revisão', 'PROVA' => 'Prova completa', 'REDACAO' => 'Redação', 'LEITURA' => 'Leitura'])
<h1 class="text-2xl font-semibold">Plano de estudos</h1>
<p class="text-sm text-muted">Gerado a partir das suas provas, erros, redações e tempo disponível. Adapta-se conforme você evolui.</p>

<form method="POST" action="{{ route('study.generate') }}" class="card mt-5">
    @csrf
    <h2 class="font-semibold">{{ $plan ? 'Regenerar plano' : 'Gerar meu plano' }}</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-4">
        <div><label class="label" for="kind">Tipo</label><select class="input" id="kind" name="kind"><option value="REGULAR">Regular</option><option value="INTENSIVO">Intensivo ENEM (reta final)</option></select></div>
        <div><label class="label" for="weekly_hours">Horas por semana</label><input class="input" id="weekly_hours" name="weekly_hours" type="number" min="1" max="80" value="{{ auth()->user()->weekly_hours ?? 10 }}"></div>
        <div><label class="label" for="days_left">Dias até o ENEM (opcional)</label><input class="input" id="days_left" name="days_left" type="number" min="1" max="400"></div>
        <div class="flex items-end"><button class="btn-primary w-full">Gerar plano</button></div>
    </div>
</form>

@if(!$plan)
    <div class="mt-6 rounded-2xl border border-dashed border-border p-8 text-center"><p class="font-medium">Você ainda não tem um plano.</p><p class="mt-1 text-sm text-muted">Faça pelo menos uma prova para um plano mais preciso, ou gere um plano inicial agora.</p></div>
@else
    <div class="notice-neutral mt-5">
        {{ $plan->kind === 'INTENSIVO' ? 'Modo Intensivo' : 'Plano regular' }} · {{ $plan->weekly_hours }}h/semana
        @if($plan->target_date) · ENEM em {{ $plan->target_date->format('d/m/Y') }} ({{ $plan->days_left }} dias) @endif
        · Prioridade: {{ collect($plan->rationale['prioritized_areas'] ?? [])->sortBy(fn ($a) => $a['percent'] ?? -1)->map(fn ($a) => $E::AREA_SHORT[$a['area']] ?? $a['area'])->implode(' › ') }}
    </div>
    <div class="mt-4 space-y-4">
        @foreach($byDay as $day => $tasks)
            <div class="card">
                <h3 class="font-medium capitalize">{{ \Carbon\Carbon::parse($day)->translatedFormat('l, d \d\e F') }}</h3>
                <ul class="mt-3 space-y-2">
                    @foreach($tasks as $t)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border px-3 py-2 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="badge-{{ $t->kind === 'PROVA' ? 'primary' : ($t->kind === 'REDACAO' ? 'warning' : 'neutral') }}">{{ $kinds[$t->kind] ?? $t->kind }}</span>
                                <span class="{{ $t->status === 'DONE' ? 'line-through text-muted' : '' }}">{{ $t->title }}</span><span class="text-xs text-muted">{{ $t->minutes }} min</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('study.task', $t) }}" class="flex items-center gap-1">@csrf @method('PATCH')<input type="date" name="scheduled_on" aria-label="Reagendar" class="input px-1 py-0.5 text-xs" onchange="this.form.submit()"></form>
                                <form method="POST" action="{{ route('study.task', $t) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $t->status === 'DONE' ? 'PENDING' : 'DONE' }}"><button class="btn-{{ $t->status === 'DONE' ? 'secondary' : 'success' }} px-3 py-1 text-xs">{{ $t->status === 'DONE' ? 'Reabrir' : 'Concluir' }}</button></form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@endif

<div class="card mt-6">
    <h2 class="font-semibold">Metas</h2>
    <p class="text-xs text-muted">Gamificação discreta: metas simples para manter o ritmo.</p>
    <ul class="mt-3 space-y-1 text-sm">
        @foreach($goals as $g)<li class="flex justify-between rounded-lg border border-border px-3 py-1.5"><span>{{ str_replace('_', ' ', ucfirst(strtolower($g->kind))) }}: <strong>{{ $g->target }}</strong></span><form method="POST" action="{{ route('study.goal.destroy', $g) }}">@csrf @method('DELETE')<button class="text-xs text-muted hover:text-danger">remover</button></form></li>@endforeach
    </ul>
    <form method="POST" action="{{ route('study.goal') }}" class="mt-3 flex flex-wrap items-end gap-2">
        @csrf
        <div><label class="label" for="goal-kind">Meta</label><select class="input" id="goal-kind" name="kind"><option value="QUESTOES_DIA">Questões por dia</option><option value="HORAS_SEMANA">Horas por semana</option><option value="REDACOES_MES">Redações por mês</option><option value="PROVAS_MES">Provas completas por mês</option><option value="SEQUENCIA">Sequência de dias</option></select></div>
        <div><label class="label" for="goal-target">Valor</label><input class="input w-24" id="goal-target" name="target" type="number" min="1" value="10"></div>
        <button class="btn-secondary">Adicionar</button>
    </form>
</div>
@endsection
