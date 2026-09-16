@extends('layouts.app')
@section('title', 'Simulados')
@section('content')
@php($E = \App\Support\Enem::class)
<h1 class="text-2xl font-semibold">Simulados</h1>
<p class="text-sm text-muted">Todos os simulados são montados com as provas oficiais do Inep publicadas na plataforma — nenhuma questão é inventada. Escolha o formato que cabe no seu dia.</p>
@if($errors->any())<div class="notice-danger mt-4">{{ $errors->first() }}</div>@endif

{{-- Por área --}}
<section class="mt-6 grid gap-4 lg:grid-cols-[1.3fr_1fr]">
    <form method="POST" action="{{ route('simulados.start') }}" class="card">
        @csrf
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Simulado por área</p>
        <h2 class="mt-1 text-lg font-semibold">Treine uma área de cada vez</h2>
        <p class="text-sm text-muted">Só as questões daquela área de uma prova oficial, no Modo Estudo (pode pausar). Correção pelo gabarito oficial ao encerrar.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div>
                <p class="label">Área</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($E::OBJECTIVE_AREAS as $i => $a)
                        <label class="cursor-pointer rounded-xl border border-border px-3 py-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                            <input type="radio" name="area" value="{{ $a }}" class="sr-only" @checked($i === 0)>
                            <span class="font-medium">{{ $E::AREA_SHORT[$a] }}</span>
                            <span class="block text-[11px] text-muted">{{ $byArea[$a]['sessions'] }} treino(s){{ $byArea[$a]['percent'] !== null ? ' · média '.$byArea[$a]['percent'].'%' : '' }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="space-y-3">
                <div><label class="label" for="sim-year">Edição</label><select id="sim-year" name="year" class="input">@foreach($years as $y)<option value="{{ $y }}">ENEM {{ $y }}</option>@endforeach</select></div>
                <div><label class="label" for="sim-lang">Língua estrangeira (só para Linguagens)</label><select id="sim-lang" name="language" class="input">@foreach($E::LANGUAGES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <button class="btn-primary w-full py-2.5" @disabled($years->isEmpty())>Começar simulado por área</button>
            </div>
        </div>
        <p class="mt-3 text-xs text-muted">{{ $studyNotice }}</p>
    </form>

    <div class="card">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Prova completa</p>
        <h2 class="mt-1 text-lg font-semibold">Modo Prova Real</h2>
        <p class="text-sm text-muted">Cronômetro oficial da edição, sem pausa, com cartão-resposta e encerramento automático. O treino mais fiel ao dia da prova.</p>
        @unless($canFullExam)<div class="notice-warning mt-3 text-xs">Assine um plano para fazer provas completas. <a href="{{ route('subscription.index') }}" class="underline">Ver planos</a></div>@endunless
        <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto text-sm">
            @foreach($exams as $e)
                <li class="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-1.5"><span>ENEM {{ $e->edition->year }} · {{ $e->day }}º dia <span class="text-xs text-muted">({{ $e->duration_minutes >= 330 ? '5h30' : '5h' }})</span></span><a href="{{ route('exams.show', $e) }}" class="btn-secondary px-3 py-1 text-xs">Abrir</a></li>
            @endforeach
        </ul>
    </div>
</section>

{{-- Maratona --}}
<section class="mt-6 card">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Maratona ENEM</p>
            <h2 class="mt-1 text-lg font-semibold">Os dois dias de uma edição, como no fim de semana da prova</h2>
        </div>
        <p class="text-xs text-muted">Marca ✓ quando o dia foi concluído no Modo Prova Real.</p>
    </div>
    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($marathon as $year => $m)
            <div class="rounded-xl border border-border p-3 text-sm">
                <p class="font-medium">ENEM {{ $year }}</p>
                <div class="mt-2 flex gap-2">
                    @foreach([1, 2] as $d)
                        @php($exam = $m['exams'][$d] ?? null)
                        @php($done = $m['done'][$d] ?? null)
                        @if($exam)
                            <a href="{{ $done ? route('sessions.result', $done) : route('exams.show', $exam) }}" class="flex flex-1 items-center justify-between rounded-lg border px-2 py-1.5 text-xs {{ $done ? 'border-success/40 bg-success/10' : 'border-border hover:border-primary' }}">
                                <span>{{ $d }}º dia</span><span>{{ $done ? '✓ '.$done->result->percent.'%' : 'fazer' }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- Modo Intensivo --}}
<section class="mt-6 card {{ $intensive ? '' : 'relative overflow-hidden' }}">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Modo Intensivo ENEM</p>
            <h2 class="mt-1 text-lg font-semibold">Rotina semanal de simulados</h2>
            <p class="text-sm text-muted">Uma área por dia no Modo Estudo, redação na sexta e prova completa no sábado. A semana começa na segunda.</p>
        </div>
        @if($intensive)<span class="badge-success">Incluído no seu plano</span>@else<span class="badge-warning">Plano Intensivo</span>@endif
    </div>
    <div class="mt-3 grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
        @foreach($week as $slot)
            <div class="rounded-xl border p-3 text-sm {{ $slot['done'] ? 'border-success/40 bg-success/10' : 'border-border' }}">
                <p class="text-xs text-muted">{{ $slot['day'] }}</p>
                <p class="font-medium">{{ $slot['area'] ? $E::AREA_SHORT[$slot['area']] : 'Prova completa' }}</p>
                <p class="text-[11px] text-muted">{{ $slot['mode'] === 'PROVA_REAL' ? 'Modo Prova Real' : ($slot['area'] === 'REDACAO' ? 'Enviar uma redação' : 'Modo Estudo') }}</p>
                <p class="mt-1 text-xs {{ $slot['done'] ? 'text-success' : 'text-muted' }}">{{ $slot['done'] ? '✓ feito' : 'pendente' }}</p>
            </div>
        @endforeach
    </div>
    @unless($intensive)
        <div class="absolute inset-0 flex items-center justify-center bg-bg/70 backdrop-blur-[2px]">
            <div class="max-w-md rounded-2xl border border-border bg-surface p-5 text-center shadow-xl">
                <p class="font-semibold">Disponível no Plano Intensivo</p>
                <p class="mt-1 text-sm text-muted">Rotina semanal guiada, 15 correções de redação por mês e prioridade na correção.</p>
                <a href="{{ route('subscription.index') }}" class="btn-primary mt-3 px-5 py-2">Conhecer o Plano Intensivo</a>
            </div>
        </div>
    @endunless
</section>

{{-- Histórico --}}
<section class="mt-6">
    <h2 class="font-semibold">Meus últimos simulados</h2>
    @if($sessions->isEmpty())<div class="mt-3 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted">Nenhum simulado ainda. Comece por um simulado por área.</div>@endif
    <ul class="mt-3 space-y-2">
        @foreach($sessions as $s)
            <li class="card flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                <div>
                    <p class="font-medium">ENEM {{ $s->exam->edition->year }} · {{ $s->exam->day }}º dia @if($s->selected_areas)<span class="text-xs text-muted">· {{ collect($s->selected_areas)->map(fn ($a) => $E::AREA_SHORT[$a] ?? $a)->join(', ') }}</span>@endif</p>
                    <p class="text-xs text-muted">{{ $s->created_at->format('d/m/Y H:i') }} · {{ $s->mode === 'PROVA_REAL' ? 'Modo Prova Real' : 'Modo Estudo' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    @if($s->result)<span class="font-semibold">{{ $s->result->correct }}/{{ $s->result->total_questions }} <span class="text-xs font-normal text-muted">({{ $s->result->percent }}%)</span></span>@endif
                    <span class="badge-{{ $s->isFinished() ? 'success' : ($s->status === 'PAUSED' ? 'warning' : 'primary') }}">{{ ['CREATED' => 'Não iniciado', 'IN_PROGRESS' => 'Em andamento', 'PAUSED' => 'Pausado', 'FINISHED' => 'Concluído', 'EXPIRED' => 'Tempo esgotado'][$s->status] ?? $s->status }}</span>
                    <a href="{{ $s->isFinished() ? route('sessions.result', $s) : route('sessions.show', $s) }}" class="text-primary underline">{{ $s->isFinished() ? 'Resultado' : 'Continuar' }}</a>
                </div>
            </li>
        @endforeach
    </ul>
</section>
@endsection
