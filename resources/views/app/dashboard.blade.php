@extends('layouts.app')
@section('title', 'Início')
@section('content')
@php($o = $overview)
@php($short = \App\Support\Enem::AREA_SHORT)
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">Olá, {{ auth()->user()->firstName() }}! 👋 Bora continuar evoluindo?</h1>
        <p class="text-sm text-muted">Acompanhe sua evolução e siga o plano de hoje.</p>
    </div>
    <span class="badge-{{ $access['tier'] === 'PREMIUM' ? 'success' : 'neutral' }}">{{ $access['plan_name'] }}</span>
</div>

@unless(auth()->user()->onboarding_done)
    <div class="notice-primary mt-5 flex flex-wrap items-center justify-between gap-3">
        <span>Conte seu objetivo para personalizar seu plano.</span><a href="{{ route('onboarding') }}" class="btn-primary">Responder (2 minutos)</a>
    </div>
@endunless

@if($first_access)
    <div class="card mt-5">
        <h2 class="font-semibold">Comece por aqui</h2>
        <ol class="mt-3 grid gap-2 text-sm md:grid-cols-2">
            @foreach($start_here as $i => $s)<li class="flex gap-2"><span class="font-semibold text-[#c4b5fd]">{{ $i + 1 }}.</span> {{ $s }}</li>@endforeach
        </ol>
        <a href="{{ route('exams.index') }}" class="btn-primary mt-4">Fazer meu primeiro diagnóstico</a>
    </div>
@endif

<div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Provas concluídas</p><p class="mt-1 text-2xl font-semibold">{{ $o['exams_completed'] }}</p></div>
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Questões respondidas</p><p class="mt-1 text-2xl font-semibold">{{ $o['questions_answered'] }}</p></div>
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Taxa de acertos</p><p class="mt-1 text-2xl font-semibold">{{ $o['accuracy'] !== null ? $o['accuracy'].'%' : '—' }}</p><p class="text-xs text-muted">Pelo gabarito oficial</p></div>
    <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">Horas estudadas</p><p class="mt-1 text-2xl font-semibold">{{ $o['hours_studied'] }}h</p><p class="text-xs text-muted">{{ $o['study_days'] }} dia(s) de estudo</p></div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        <h2 class="font-semibold">Evolução por área</h2>
        <div class="mt-4 space-y-3">
            @foreach($o['by_area'] as $a)
                <div>
                    <div class="mb-1 flex justify-between text-xs text-muted"><span>{{ $short[$a['area']] }}</span><span>{{ $a['percent'] !== null ? $a['percent'].'%' : 'sem dados' }}</span></div>
                    <div class="h-2 rounded-full bg-surface-2" role="progressbar" aria-valuenow="{{ $a['percent'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $short[$a['area']] }}"><div class="h-2 rounded-full bg-gradient-to-r from-[#7c5cff] to-[#22d3ee]" style="width: {{ $a['percent'] ?? 0 }}%"></div></div>
                </div>
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap gap-4 text-sm">
            <span>Área mais forte: <strong>{{ $o['strongest_area'] ? $short[$o['strongest_area']] : '—' }}</strong></span>
            <span>Precisa de atenção: <strong>{{ $o['weakest_area'] ? $short[$o['weakest_area']] : '—' }}</strong></span>
        </div>
    </div>
    <div class="card">
        <h2 class="font-semibold">Redações</h2>
        <p class="mt-2 text-3xl font-semibold">{{ $o['essay']['count'] }}</p>
        <p class="text-sm text-muted">{{ $o['essay']['average'] !== null ? 'Média simulada: '.$o['essay']['average'] : 'Nenhuma correção simulada ainda.' }}</p>
        @if(count($o['essay']['series']) >= 2)<p class="mt-2 text-sm">Evolução: {{ $o['essay']['series'][0]['total'] }} → {{ end($o['essay']['series'])['total'] }}</p>@endif
        <a href="{{ route('essays.index') }}" class="btn-secondary mt-4">Treinar redação</a>
    </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="card">
        <h2 class="font-semibold">Seu plano de hoje</h2>
        @if($today_tasks->isEmpty())
            <p class="mt-2 text-sm text-muted">Nenhuma tarefa para hoje. <a href="{{ route('study.plan') }}" class="text-[#c4b5fd] underline">Gerar plano de estudos</a>.</p>
        @else
            <ul class="mt-3 space-y-2 text-sm">
                @foreach($today_tasks as $t)
                    <li class="flex items-center justify-between rounded-xl border border-border px-3 py-2"><span class="{{ $t->status === 'DONE' ? 'line-through text-muted' : '' }}"><x-icon name="check" class="mr-1 inline h-4 w-4 {{ $t->status === 'DONE' ? 'text-success' : 'text-muted' }}" />{{ $t->title }}</span><span class="text-xs text-muted">{{ $t->minutes }} min</span></li>
                @endforeach
            </ul>
            <a href="{{ route('study.plan') }}" class="mt-3 inline-block text-sm text-[#c4b5fd] underline">Ver plano completo</a>
        @endif
    </div>
    <div class="card">
        <h2 class="font-semibold">Continuar estudando</h2>
        @if($continue_session)
            <p class="mt-2 text-sm text-muted">Você tem uma prova em andamento: {{ $continue_session->exam->title }}.</p>
            <a href="{{ route('sessions.show', $continue_session) }}" class="btn-primary mt-3">Retomar prova</a>
        @elseif($last_session)
            <p class="mt-2 text-sm">Última prova: <strong>{{ $last_session->exam->title }}</strong>@if($last_session->result) — {{ $last_session->result->percent }}% de acertos @endif</p>
            <div class="mt-3 flex gap-2"><a href="{{ route('sessions.result', $last_session) }}" class="btn-secondary">Ver resultado</a><a href="{{ route('exams.index') }}" class="btn-primary">Nova prova</a></div>
        @else
            <a href="{{ route('exams.index') }}" class="btn-primary mt-3">Escolher uma prova oficial</a>
        @endif
        @if($goals->isNotEmpty())<p class="mt-4 text-xs text-muted">Próxima meta: {{ str_replace('_', ' ', strtolower($goals->first()->kind)) }} — {{ $goals->first()->target }}</p>@endif
    </div>
</div>
@endsection
