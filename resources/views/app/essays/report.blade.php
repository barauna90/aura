@extends('layouts.app')
@section('title', 'Relatório da redação')
@section('content')
@php($pending = in_array($essay->status, ['SUBMITTED', 'EVALUATING'], true))
@if($pending)<meta http-equiv="refresh" content="8">@endif
<div class="flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="text-2xl font-semibold">Relatório da redação</h1><p class="text-sm text-muted">{{ $essay->prompt->theme }} · ENEM {{ $essay->prompt->exam->edition->year }}</p></div>
    <a href="{{ route('essays.index') }}" class="btn-secondary">Minhas redações</a>
</div>
<div class="notice-neutral mt-4">{{ $report['notice'] }}</div>

@if($pending)
    <div class="notice-primary mt-4"><strong>Avaliação em andamento.</strong> Dois avaliadores independentes analisam sua redação pelas cinco competências. Se houver divergência, um terceiro avaliador é acionado. Esta página atualiza automaticamente.</div>
@elseif($essay->status === 'FAILED')
    <div class="notice-danger mt-4">A avaliação falhou. Nossa equipe foi notificada; tente reenviar mais tarde.</div>
@elseif($essay->status === 'EVALUATED')
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">{{ $report['score_label'] }}</p><p class="mt-1 text-3xl font-semibold gradient-text">{{ $report['total'] }}</p><p class="text-xs text-muted">{{ $report['used_third'] ? 'Com terceiro avaliador' : 'Dois avaliadores' }}</p></div>
        @foreach($report['competencies'] as $c)<div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Competência {{ $c['competency'] }}</p><p class="mt-1 text-2xl font-semibold">{{ $c['score'] ?? '—' }}</p></div>@endforeach
    </div>

    <div class="mt-4 space-y-4">
        @foreach($report['competencies'] as $c)
            <div class="card">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="font-semibold">Competência {{ $c['competency'] }} <span class="ml-2 text-lg">{{ $c['score'] }}</span></h2>
                    <div class="flex gap-1">@foreach($c['analyses'] as $a)<span class="badge-neutral">Avaliador {{ $a['evaluator'] }}: {{ $a['score'] }}</span>@endforeach</div>
                </div>
                <p class="mt-1 text-sm text-muted">{{ $c['label'] }}</p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    @foreach($c['analyses'] as $a)
                        <div class="rounded-xl border border-border bg-surface-2/60 p-3 text-sm">
                            <p class="text-xs font-medium uppercase text-muted">Avaliador {{ $a['evaluator'] }}</p>
                            <p class="mt-1">{{ $a['justification'] ?: '—' }}</p>
                            @if($a['excerpts'])<ul class="mt-2 space-y-1">@foreach($a['excerpts'] as $t)<li class="border-l-2 border-warning pl-2 italic text-muted">“{{ $t }}”</li>@endforeach</ul>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="card"><h2 class="font-semibold">Principais pontos positivos</h2><ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach($report['positives'] as $p)<li>{{ $p }}</li>@endforeach</ul></div>
        <div class="card"><h2 class="font-semibold">Principais erros e o que melhorar</h2><ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach($report['improvements'] as $p)<li>{{ $p }}</li>@endforeach</ul></div>
        <div class="card"><h2 class="font-semibold">Checklist de evolução</h2><ul class="mt-2 space-y-1 text-sm">@foreach($report['checklist'] as $c)<li class="flex items-center gap-2"><span class="{{ $c['ok'] ? 'text-success' : 'text-muted' }}" aria-hidden="true">{{ $c['ok'] ? '✓' : '○' }}</span><span class="sr-only">{{ $c['ok'] ? 'atingido' : 'pendente' }}</span>{{ $c['item'] }}</li>@endforeach</ul></div>
        <div class="card">
            <h2 class="font-semibold">Plano de estudo recomendado</h2>
            @if($report['recommendation'])
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach($report['recommendation']['suggestions'] as $s)<li>{{ $s }}</li>@endforeach</ul>
                <p class="mt-2 text-sm text-muted">{{ $report['recommendation']['next_step'] }}</p>
            @endif
            <p class="mt-3 text-xs text-muted">Veja como melhorar este parágrafo: material pedagógico gerado após a avaliação — não é texto oficial.</p>
        </div>
    </div>
@endif

<div class="card mt-4"><h2 class="font-semibold">Seu texto</h2><p class="essay-sheet mt-3 whitespace-pre-wrap px-2 text-base">{{ $essay->final_text ?? $essay->draft_text }}</p></div>
@endsection
