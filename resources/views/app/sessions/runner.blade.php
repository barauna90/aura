@extends('layouts.base')
@section('title', 'Prova em andamento')
@section('body')
@php($D = \App\Support\Disclaimers::class)
@php($real = $session->mode === 'PROVA_REAL')

@if($session->status === 'CREATED')
    <div class="glow-bg flex min-h-screen items-center justify-center px-4">
        <div class="card-glass w-full max-w-2xl space-y-4">
            <a href="{{ route('exams.show', $session->exam) }}" class="text-sm text-muted hover:underline">← Voltar</a>
            <h1 class="text-xl font-semibold">{{ $session->exam->title }}</h1>
            <p class="text-sm text-muted">{{ $real ? 'Modo Prova Real' : 'Modo Estudo' }} · Tempo total: {{ \App\Services\Exam\TimerRules::formatHms($session->exam->duration_minutes * 60) }}</p>
            <div class="notice-primary">{{ $D::ANSWER_SHEET_ONLY }}</div>
            @if($real)
                <div class="notice-warning"><strong>Ao iniciar, o cronômetro não pode ser pausado, reiniciado nem estendido.</strong> O tempo continua contando mesmo se você fechar a página. A prova encerra automaticamente quando o tempo terminar.</div>
            @else
                <div class="notice-warning">{{ $D::STUDY_MODE_NOTICE }}</div>
            @endif
            <form method="POST" action="{{ route('sessions.start', $session) }}">@csrf<button class="btn-primary px-6 py-3 text-base">Iniciar agora</button></form>
        </div>
    </div>
@else
    <div id="exam-runner" class="flex h-screen flex-col"
         data-session-id="{{ $session->id }}" data-status="{{ $session->status }}" data-remaining="{{ $state['remaining_seconds'] ?? '' }}"
         data-state-url="{{ route('sessions.state', $session) }}" data-answers-url="{{ route('sessions.answers', $session) }}"
         data-pause-url="{{ route('sessions.pause', $session) }}" data-resume-url="{{ route('sessions.resume', $session) }}"
         data-finish-url="{{ route('sessions.finish', $session) }}" data-result-url="{{ route('sessions.result', $session) }}"
         data-pdf-url="{{ route('booklets.pdf', $session->booklet) }}" data-page-count="{{ $session->booklet->page_count }}" data-has-essay="{{ $session->exam->has_essay ? 1 : 0 }}">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-bg-2 px-4 py-2">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium">{{ $session->exam->title }}</p>
                <p class="text-xs text-muted">{{ $real ? 'Modo Prova Real' : 'Modo Estudo' }} · <span id="save-state">Cartão salvo</span></p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right" aria-live="polite"><p class="text-[10px] uppercase tracking-wide text-muted">Tempo restante</p><p id="timer" class="font-mono text-2xl font-semibold tabular-nums">--:--:--</p></div>
                @if(!$real && $session->status === 'IN_PROGRESS')<button id="btn-pause" class="btn-secondary px-3 py-1.5">Pausar</button>@endif
                @if(!$real && $session->status === 'PAUSED')<button id="btn-resume" class="btn-primary px-3 py-1.5">Continuar</button>@endif
                <button id="btn-finish" class="btn-danger px-3 py-1.5" @disabled($session->status === 'PAUSED')>Encerrar prova</button>
            </div>
        </header>

        @if($session->status === 'PAUSED')
            <div class="flex flex-1 items-center justify-center p-6"><div class="notice-warning max-w-md"><strong>Prova pausada.</strong> O caderno e o cartão-resposta ficam ocultos enquanto a sessão está pausada.</div></div>
            <div id="answer-sheet" hidden></div>
        @else
            <div class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-[1fr_360px]">
                <section aria-label="Caderno oficial" class="flex min-h-[50vh] flex-col border-b border-border md:min-h-0 md:border-b-0 md:border-r">
                    <div class="flex items-center justify-between gap-2 border-b border-border bg-bg-2 px-3 py-1.5 text-sm">
                        <button id="pdf-prev" class="btn-secondary px-3 py-1" aria-label="Página anterior">‹ Anterior</button>
                        <span>Página <input id="pdf-page" type="number" min="1" max="{{ $session->booklet->page_count }}" value="1" class="w-14 rounded border border-border bg-bg-2 px-1 text-center" aria-label="Número da página"> de {{ $session->booklet->page_count }}</span>
                        <button id="pdf-next" class="btn-secondary px-3 py-1" aria-label="Próxima página">Próxima ›</button>
                    </div>
                    <iframe id="pdf-frame" title="Caderno oficial da prova" src="{{ route('booklets.pdf', $session->booklet) }}#page=1&toolbar=0&navpanes=0&view=FitH" class="h-full w-full flex-1 bg-surface-2"></iframe>
                </section>
                <section aria-label="Cartão-resposta digital" class="flex min-h-0 flex-col bg-bg-2">
                    <div class="flex items-center justify-between border-b border-border px-3 py-2 text-sm">
                        <span class="font-medium">Cartão-resposta</span>
                        <span class="text-muted"><span id="answered-count">0</span> respondidas · <span id="blank-count">0</span> em branco · {{ $state['answer_sheet']['total'] }} total</span>
                    </div>
                    <ol id="answer-sheet" class="flex-1 overflow-y-auto p-2" aria-label="Questões do cartão-resposta">
                        @foreach($state['answer_sheet']['answers'] as $a)
                            <li data-question="{{ $a->question_id }}" class="flex items-center gap-2 border-b border-border/60 px-1 py-1.5 last:border-0">
                                <span class="w-9 shrink-0 text-right text-sm tabular-nums text-muted">{{ $a->question_number }}</span>
                                <div role="group" aria-label="Questão {{ $a->question_number }}" class="flex gap-1.5">
                                    @foreach(\App\Support\Enem::OPTIONS as $opt)
                                        <button type="button" class="bubble" data-option="{{ $opt }}" aria-pressed="{{ $a->option === $opt ? 'true' : 'false' }}" aria-label="Questão {{ $a->question_number }}, alternativa {{ $opt }}">{{ $opt }}</button>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <p class="border-t border-border px-3 py-2 text-[11px] text-muted">{{ $D::ANSWER_SHEET_ONLY }}</p>
                </section>
            </div>
        @endif
    </div>
@endif
@endsection
