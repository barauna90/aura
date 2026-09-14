@extends('layouts.app')
@section('title', 'Meu desempenho')
@section('content')
@php($E = \App\Support\Enem::class)
@php($o = $overview)
@php($colors = ['LINGUAGENS' => '#a78bfa', 'HUMANAS' => '#fbbf24', 'NATUREZA' => '#34d399', 'MATEMATICA' => '#60a5fa'])
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold">Meu desempenho</h1>
    <div class="flex gap-1">@foreach(['7D' => '7 dias', '30D' => '30 dias', '90D' => '90 dias', 'ALL' => 'Tudo'] as $k => $l)<a href="?periodo={{ $k }}" class="btn-{{ $period === $k ? 'primary' : 'secondary' }} px-3 py-1.5">{{ $l }}</a>@endforeach</div>
</div>

<div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Acertos</p><p class="mt-1 text-2xl font-semibold">{{ $o['accuracy'] !== null ? $o['accuracy'].'%' : '—' }}</p><p class="text-xs text-muted">Pelo gabarito oficial</p></div>
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Em branco</p><p class="mt-1 text-2xl font-semibold">{{ $o['blank_rate'] !== null ? $o['blank_rate'].'%' : '—' }}</p><p class="text-xs text-muted">{{ $o['changed_answers'] }} respostas alteradas</p></div>
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Horas estudadas</p><p class="mt-1 text-2xl font-semibold">{{ $o['hours_studied'] }}h</p></div>
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Provas</p><p class="mt-1 text-2xl font-semibold">{{ $o['full_exams'] }} <span class="text-sm font-normal text-muted">completas</span> · {{ $o['partial_exams'] }} <span class="text-sm font-normal text-muted">parciais</span></p></div>
</div>

<div class="card mt-4">
    <h2 class="font-semibold">Mapa de desempenho por área</h2>
    @if(empty($o['series']))<p class="mt-2 text-sm text-muted">Sem provas concluídas no período.</p>@else
        <div class="mt-3"><x-line-chart label="Evolução do percentual de acertos por área" :series="collect($E::OBJECTIVE_AREAS)->map(fn ($a) => ['name' => $E::AREA_SHORT[$a], 'color' => $colors[$a], 'points' => array_map(fn ($s) => $s['by_area'][$a] ?? 0, $o['series'])])->all()" /></div>
        <p class="mt-2 text-xs text-muted">Média móvel (3 provas): {{ implode(' · ', array_map(fn ($m) => $m.'%', $o['moving_average'])) }}</p>
    @endif
</div>

<div class="mt-4 grid gap-4 md:grid-cols-2">
    <div class="card">
        <h2 class="font-semibold">Redação — {{ $o['essay']['label'] }}</h2>
        @if($o['essay']['series'])<x-line-chart label="Evolução da nota simulada de redação" :max="1000" :series="[['name' => 'Nota simulada', 'color' => '#ec4899', 'points' => array_column($o['essay']['series'], 'total')]]" />@else<p class="mt-2 text-sm text-muted">Nenhuma redação avaliada no período.</p>@endif
        <p class="mt-2 text-xs text-muted">{{ $o['essay']['notice'] }}</p>
    </div>
    <div class="card">
        <h2 class="font-semibold">Assuntos com mais erros</h2>
        <p class="text-xs text-muted">Somente questões com classificação pedagógica validada.</p>
        @if(!$weakTopics)<p class="mt-2 text-sm text-muted">Ainda não há dados suficientes.</p>@endif
        <ul class="mt-3 space-y-1 text-sm">@foreach($weakTopics as $t)<li class="flex justify-between rounded-lg border border-border px-3 py-1.5"><span><span class="badge-neutral mr-1">{{ $E::AREA_SHORT[$t['area']] }}</span>{{ $t['name'] }}</span><span class="text-muted">{{ $t['errors'] }} erro(s)</span></li>@endforeach</ul>
    </div>
</div>
<div class="notice-neutral mt-4">{{ $o['score_notice'] }}</div>

<div class="card mt-4">
    <h2 class="font-semibold">Histórico</h2>
    <p class="text-xs text-muted">Selecione duas provas para comparar. Refazer uma prova mantém o resultado anterior.</p>
    <form method="GET" action="{{ route('performance.compare') }}" class="mt-3 overflow-x-auto">
        <table class="table">
            <thead><tr><th></th><th>Prova</th><th>Modo</th><th>Data</th><th>Tempo</th><th>Acertos</th><th>Redação</th><th>Dispositivo</th><th></th></tr></thead>
            <tbody>
                @foreach($history as $h)
                    <tr>
                        <td><input type="radio" name="a" value="{{ $h->id }}" aria-label="Anterior"> <input type="radio" name="b" value="{{ $h->id }}" aria-label="Atual"></td>
                        <td>{{ $h->exam->title }}</td>
                        <td>{{ $h->mode === 'PROVA_REAL' ? 'Prova Real' : 'Estudo' }}</td>
                        <td>{{ $h->finished_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $h->time_used_seconds !== null ? \App\Services\Exam\TimerRules::formatHms($h->time_used_seconds) : '—' }}</td>
                        <td>{{ $h->result?->correct }}/{{ $h->result?->total_questions }} ({{ $h->result?->percent }}%)</td>
                        <td>{{ $h->essay?->finalResult?->total ?? '—' }}</td>
                        <td class="text-muted">{{ $h->device ?? '—' }}</td>
                        <td><a href="{{ route('sessions.result', $h) }}" class="text-primary underline">Ver</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-3 flex items-center gap-3"><button class="btn-secondary">Comparar (anterior → atual)</button><span class="text-xs text-muted">Marque uma prova na coluna "anterior" e outra em "atual".</span></div>
    </form>
    <div class="mt-3">{{ $history->links() }}</div>
</div>
@endsection
