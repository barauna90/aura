@extends('layouts.app')
@section('title', 'Guia ENEM')
@section('content')
@php($E = \App\Support\Enem::class)
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">Guia ENEM — o que estudar</h1>
        <p class="text-sm text-muted">Temas organizados por eixo e disciplina, com o detalhamento do que cai em cada um. Clique em um tema para ver o roteiro, marcar como estudado e guardar seus vídeos de estudo.</p>
    </div>
    <div class="stat min-w-56">
        <p class="text-xs uppercase text-muted">Seu progresso</p>
        <p class="mt-1 text-2xl font-semibold">{{ $progress['done'] }} <span class="text-sm font-normal text-muted">de {{ $progress['total'] }} temas</span></p>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-primary" style="width: {{ $progress['percent'] }}%"></div></div>
    </div>
</div>
<div class="notice-neutral mt-4">Nunca afirmamos que um conteúdo “vai cair”. Indicamos o que é recorrente nas provas analisadas e o que é relevante na Matriz de Referência.</div>

<form method="GET" class="mt-4 flex flex-wrap items-center gap-2">
    @if($area)<input type="hidden" name="area" value="{{ $area }}">@endif
    <input type="search" name="q" value="{{ $q }}" placeholder="Buscar tema (ex.: função, ecologia, crase)" class="input max-w-xs" aria-label="Buscar tema">
    <button class="btn-secondary px-3 py-1.5">Buscar</button>
    <div class="flex flex-wrap gap-1">
        <a href="{{ route('guide', $q ? ['q' => $q] : []) }}" class="btn-{{ $area ? 'secondary' : 'primary' }} px-3 py-1.5">Todos</a>
        @foreach($E::AREA_SHORT as $k => $v)<a href="{{ route('guide', array_filter(['area' => $k, 'q' => $q])) }}" class="btn-{{ $area === $k ? 'primary' : 'secondary' }} px-3 py-1.5">{{ $v }}</a>@endforeach
    </div>
</form>

@if($topics->isEmpty())<div class="mt-6 rounded-2xl border border-dashed border-border p-8 text-center text-sm text-muted">Nenhum tema encontrado.</div>@endif
@foreach($topics as $a => $disciplines)
    <section class="mt-8">
        <h2 class="text-lg font-semibold">{{ $E::AREA_LABEL[$a] ?? $a }}</h2>
        @foreach($disciplines as $discipline => $list)
            <div class="mt-4">
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-muted">{{ $discipline }} <span class="font-normal normal-case">· {{ $list->count() }} temas</span></h3>
                <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($list as $t)
                        <a href="{{ route('guide.show', $t) }}" class="card block transition hover:border-primary {{ isset($studied[$t->id]) ? 'border-success/40 bg-success/5' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <h4 class="font-medium">{{ $t->name }}</h4>
                                @if(isset($studied[$t->id]))<span class="badge-success shrink-0">✓ estudado</span>@endif
                            </div>
                            @if($t->description)<p class="mt-1 line-clamp-2 text-sm text-muted">{{ $t->description }}</p>@endif
                            @if($t->subtopics)<p class="mt-2 line-clamp-1 text-xs text-muted">{{ implode(' · ', array_slice($t->subtopics, 0, 4)) }}{{ count($t->subtopics) > 4 ? ' …' : '' }}</p>@endif
                            <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
                                @if($t->recurrence >= 3)<span class="badge-warning">alta recorrência</span>@endif
                                @if($t->verified_count > 0)<span class="badge-success">{{ $t->verified_count }} questões oficiais</span>@endif
                                @if($t->matrix_skill)<span class="badge-primary">{{ $t->matrix_skill }}</span>@endif
                                @if($t->my_videos_count > 0)<span class="badge-neutral">▶ {{ $t->my_videos_count }} vídeo(s)</span>@endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
@endforeach
@endsection
