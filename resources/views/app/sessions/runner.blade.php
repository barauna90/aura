@extends('layouts.base')
@section('title', 'Prova em andamento')
@section('body')
@php($D = \App\Support\Disclaimers::class)
@php($E = \App\Support\Enem::class)
@php($real = $session->mode === 'PROVA_REAL')

@if($session->status === 'CREATED')
    <div class="glow-bg flex min-h-screen items-center justify-center px-4">
        <div class="card-glass w-full max-w-2xl space-y-4">
            <a href="{{ route('exams.show', $session->exam) }}" class="text-sm text-muted hover:underline">← Voltar</a>
            <h1 class="text-xl font-semibold">{{ $session->exam->title }}</h1>
            <p class="text-sm text-muted">{{ $real ? 'Modo Prova Real' : 'Modo Estudo' }} · Tempo total: {{ \App\Services\Exam\TimerRules::formatHms($session->exam->duration_minutes * 60) }} · {{ $state['answer_sheet']['total'] }} questões</p>
            <div class="notice-primary"><strong>Como funciona.</strong> À esquerda você lê o caderno oficial. À direita há duas abas: no <strong>Caderno</strong> você marca a alternativa de cada questão enquanto resolve (como circular no papel); no <strong>Cartão-resposta</strong> você transfere suas respostas. {{ $D::ANSWER_SHEET_ONLY }}</div>
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
                <p class="text-xs text-muted">{{ $real ? 'Modo Prova Real' : 'Modo Estudo' }}@if($session->language) · {{ $E::LANGUAGES[$session->language] }}@endif · <span id="save-state">Salvo</span></p>
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
            <div class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-[1fr_400px]">
                <section aria-label="Caderno oficial" class="flex min-h-[50vh] flex-col border-b border-border md:min-h-0 md:border-b-0 md:border-r">
                    <div class="flex items-center justify-between gap-2 border-b border-border bg-bg-2 px-3 py-1.5 text-sm">
                        <button id="pdf-prev" class="btn-secondary px-3 py-1" aria-label="Página anterior">‹ Anterior</button>
                        <span>Página <input id="pdf-page" type="number" min="1" max="{{ $session->booklet->page_count }}" value="1" class="w-14 rounded border border-border bg-bg-2 px-1 text-center" aria-label="Número da página"> de {{ $session->booklet->page_count }}</span>
                        <button id="pdf-next" class="btn-secondary px-3 py-1" aria-label="Próxima página">Próxima ›</button>
                    </div>
                    <iframe id="pdf-frame" title="Caderno oficial da prova" src="{{ route('booklets.pdf', $session->booklet) }}#page=1&toolbar=0&navpanes=0&view=FitH" class="h-full w-full flex-1 bg-surface-2"></iframe>
                </section>

                <section aria-label="Marcações e cartão-resposta" class="flex min-h-0 flex-col bg-bg-2">
                    <div role="tablist" class="grid grid-cols-2 border-b border-border text-sm">
                        <button type="button" role="tab" data-panel-tab="booklet" aria-selected="true" class="border-b-2 border-transparent px-3 py-2 text-muted aria-selected:border-primary aria-selected:font-medium aria-selected:text-text">Caderno <span class="text-xs">(<span id="drafted-count">{{ $state['answer_sheet']['drafted'] }}</span> marcadas)</span></button>
                        <button type="button" role="tab" data-panel-tab="sheet" aria-selected="false" class="border-b-2 border-transparent px-3 py-2 text-muted aria-selected:border-primary aria-selected:font-medium aria-selected:text-text">Cartão-resposta <span class="text-xs">(<span id="answered-count">{{ $state['answer_sheet']['answered'] }}</span>/{{ $state['answer_sheet']['total'] }})</span></button>
                    </div>

                    {{-- Aba CADERNO: marcar a alternativa enquanto resolve (rascunho, não corrigido) --}}
                    <div data-panel-body="booklet" class="flex min-h-0 flex-1 flex-col">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-3 py-2 text-xs text-muted">
                            <span>Marque a alternativa que você escolheu em cada questão. Isto é o seu rascunho — <strong>não é corrigido</strong>.</span>
                            <button type="button" id="btn-transfer" class="btn-primary px-3 py-1 text-xs">Transferir para o cartão (<span id="untransferred-count">{{ $state['answer_sheet']['untransferred'] }}</span>)</button>
                        </div>
                        <ol id="booklet-marks" class="flex-1 overflow-y-auto p-2" aria-label="Marcações no caderno">
                            @foreach($state['answer_sheet']['answers'] as $a)
                                <li data-question="{{ $a['question_id'] }}" class="flex items-center gap-2 border-b border-border/60 px-1 py-1.5 last:border-0">
                                    <span class="w-9 shrink-0 text-right text-sm tabular-nums text-muted">{{ $a['question_number'] }}</span>
                                    <div role="group" aria-label="Questão {{ $a['question_number'] }} no caderno" class="flex gap-1.5">
                                        @foreach($E::OPTIONS as $opt)
                                            <button type="button" class="bubble draft" data-option="{{ $opt }}" aria-pressed="{{ $a['draft_option'] === $opt ? 'true' : 'false' }}" aria-label="Questão {{ $a['question_number'] }}, alternativa {{ $opt }} (caderno)">{{ $opt }}</button>
                                        @endforeach
                                    </div>
                                    @if($a['page'])<button type="button" class="ml-auto text-xs text-primary underline" data-goto-page="{{ $a['page'] }}" aria-label="Ir para a página {{ $a['page'] }}">p.{{ $a['page'] }}</button>@endif
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- Aba CARTÃO-RESPOSTA: única considerada na correção --}}
                    <div data-panel-body="sheet" hidden class="flex min-h-0 flex-1 flex-col">
                        <div class="flex items-center justify-between border-b border-border px-3 py-2 text-xs text-muted">
                            <span><span id="answered-count-2">{{ $state['answer_sheet']['answered'] }}</span> respondidas · <span id="blank-count">{{ $state['answer_sheet']['blank'] }}</span> em branco · {{ $state['answer_sheet']['total'] }} total</span>
                        </div>
                        <ol id="answer-sheet" class="flex-1 overflow-y-auto p-2" aria-label="Questões do cartão-resposta">
                            @foreach($state['answer_sheet']['answers'] as $a)
                                <li data-question="{{ $a['question_id'] }}" class="flex items-center gap-2 border-b border-border/60 px-1 py-1.5 last:border-0">
                                    <span class="w-9 shrink-0 text-right text-sm tabular-nums text-muted">{{ $a['question_number'] }}</span>
                                    <div role="group" aria-label="Questão {{ $a['question_number'] }}" class="flex gap-1.5">
                                        @foreach($E::OPTIONS as $opt)
                                            <button type="button" class="bubble" data-option="{{ $opt }}" aria-pressed="{{ $a['option'] === $opt ? 'true' : 'false' }}" aria-label="Questão {{ $a['question_number'] }}, alternativa {{ $opt }}">{{ $opt }}</button>
                                        @endforeach
                                    </div>
                                    <span class="ml-auto text-[10px] text-muted" data-draft-hint>{{ $a['draft_option'] ? 'caderno: '.$a['draft_option'] : '' }}</span>
                                </li>
                            @endforeach
                        </ol>
                        <p class="border-t border-border px-3 py-2 text-[11px] text-muted">{{ $D::ANSWER_SHEET_ONLY }}</p>
                    </div>
                </section>
            </div>
        @endif
    </div>
@endif
@endsection
