@extends('layouts.app')
@section('title', 'Simulado concluído')
@section('content')
@php($E = \App\Support\Enem::class)
@php($r = $result)
@php($status = ['CORRECT' => ['Acertou', 'success'], 'WRONG' => ['Errou', 'danger'], 'BLANK' => ['Em branco', 'neutral'], 'ANNULLED' => ['Anulada', 'warning']])
<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">Simulado concluído</h1>
        <p class="text-sm text-muted">{{ $session->exam->title }} · {{ $session->mode === 'PROVA_REAL' ? 'Modo Prova Real' : 'Modo Estudo' }}{{ $session->status === 'EXPIRED' ? ' · encerrada automaticamente pelo tempo' : '' }}</p>
    </div>
    <div class="flex gap-2"><a href="{{ route('exams.index') }}" class="btn-secondary">Refazer / outra prova</a><a href="{{ route('performance') }}" class="btn-primary">Meu desempenho</a></div>
</div>

<div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Acertos oficiais pelo gabarito</p><p class="mt-1 text-2xl font-semibold">{{ $r->correct }} / {{ $r->total_questions }}</p><p class="text-xs text-muted">{{ $r->percent }}% de acertos</p></div>
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Erros · Em branco</p><p class="mt-1 text-2xl font-semibold">{{ $r->wrong }} · {{ $r->blank }}</p></div>
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Tempo utilizado</p><p class="mt-1 text-2xl font-semibold">{{ \App\Services\Exam\TimerRules::formatHms($r->time_used_seconds) }}</p><p class="text-xs text-muted">{{ round($r->avg_seconds_per_question) }}s por questão em média</p></div>
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Respostas alteradas</p><p class="mt-1 text-2xl font-semibold">{{ $r->changed_answers }}</p><p class="text-xs text-muted">Questões com mudança no cartão</p></div>
</div>

<div class="notice-neutral mt-4">{{ $scoreNotice }}</div>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        <h2 class="font-semibold">Veja onde você pode melhorar</h2>
        <div class="mt-4 space-y-3">
            @foreach($r->by_area as $a)
                <div>
                    <div class="mb-1 flex justify-between text-xs text-muted"><span>{{ $E::AREA_SHORT[$a['area']] ?? $a['area'] }} — {{ $a['correct'] }}/{{ $a['total'] }} ({{ $a['blank'] }} em branco)</span><span>{{ $a['percent'] }}%</span></div>
                    <div class="h-2 rounded-full bg-surface-2"><div class="h-2 rounded-full bg-gradient-to-r from-[#7c5cff] to-[#22d3ee]" style="width: {{ $a['percent'] }}%"></div></div>
                </div>
            @endforeach
        </div>
        @if($r->by_discipline)
            <h3 class="mt-5 text-sm font-medium">Por disciplina (classificação pedagógica validada)</h3>
            <ul class="mt-2 grid gap-1 text-sm sm:grid-cols-2">
                @foreach($r->by_discipline as $d)<li class="flex justify-between rounded-lg border border-border px-3 py-1.5"><span>{{ $d['discipline'] }}</span><span class="text-muted">{{ $d['correct'] }}/{{ $d['total'] }} · {{ $d['percent'] }}%</span></li>@endforeach
            </ul>
        @endif
    </div>
    <div class="card">
        <h2 class="font-semibold">Redação</h2>
        @if(!$session->exam->has_essay || $session->exam->essayPrompt?->review_status !== 'VERIFIED')
            <p class="mt-2 text-sm text-muted">Esta prova não possui redação verificada.</p>
        @elseif($session->essay)
            <p class="mt-2 text-sm">Status: <span class="badge-{{ $session->essay->status === 'EVALUATED' ? 'success' : 'primary' }}">{{ $session->essay->status }}</span></p>
            @if($session->essay->finalResult)<p class="mt-2 text-2xl font-semibold">{{ $session->essay->finalResult->total }} <span class="text-xs font-normal text-muted">{{ \App\Support\Disclaimers::ESSAY_SCORE_LABEL }}</span></p>@endif
            <a href="{{ $session->essay->status === 'DRAFT' ? route('essays.edit', $session->essay) : route('essays.report', $session->essay) }}" class="btn-primary mt-3">{{ $session->essay->status === 'DRAFT' ? 'Enviar redação para avaliação' : 'Ver relatório da redação' }}</a>
        @else
            <p class="mt-2 text-sm text-muted">Agora que a prova foi encerrada, sua redação pode ser escrita e enviada para avaliação simulada.</p>
            <form method="POST" action="{{ route('essays.from_session', $session) }}" class="mt-3">@csrf<button class="btn-primary">Abrir minha redação</button></form>
        @endif
        <a href="{{ route('help') }}?session={{ $session->id }}" class="mt-4 block text-xs text-primary underline">Perguntar ao Professor IA por que errei</a>
    </div>
</div>

<div class="card mt-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold">Relatório de questões</h2>
        <div class="flex gap-1">
            @foreach(['todas' => 'Todas', 'erradas' => 'Erradas', 'branco' => 'Em branco'] as $k => $l)
                <a href="?filtro={{ $k }}" class="btn-{{ $filter === $k ? 'primary' : 'secondary' }} px-3 py-1.5">{{ $l }}</a>
            @endforeach
        </div>
    </div>
    <div class="mt-4 overflow-x-auto">
        <table class="table">
            <thead><tr><th>Questão</th><th>Área</th><th>Cartão</th><th>Caderno</th><th>Gabarito oficial</th><th>Status</th><th>Assunto</th><th></th></tr></thead>
            <tbody>
                @foreach($questions as $q)
                    @continue($filter === 'erradas' && $q['status'] !== 'WRONG')
                    @continue($filter === 'branco' && $q['status'] !== 'BLANK')
                    <tr>
                        <td class="font-medium">{{ $q['number'] }} @if($q['page'])<span class="text-xs text-muted">p.{{ $q['page'] }}</span>@endif</td>
                        <td>{{ $E::AREA_SHORT[$q['area']] ?? $q['area'] }}</td>
                        <td>{{ $q['marked'] ?? '—' }}</td>
                        <td class="text-muted">{{ $q['draft'] ?? '—' }}</td>
                        <td>{{ $q['annulled'] ? 'Anulada' : ($q['official'] ?? '—') }}</td>
                        <td><span class="badge-{{ $status[$q['status']][1] }}">{{ $status[$q['status']][0] }}</span></td>
                        <td class="text-muted">{{ $q['topic'] ?? '—' }}@if($q['skill']) <span class="text-xs">· {{ $q['skill'] }}</span>@endif</td>
                        <td>
                            <details><summary class="cursor-pointer text-xs text-primary">Resolução</summary>
                                <p class="mt-1 whitespace-pre-wrap text-sm {{ $q['resolution'] ? '' : 'text-muted' }}">{{ $q['resolution'] ?? $q['resolution_notice'] }}</p>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
