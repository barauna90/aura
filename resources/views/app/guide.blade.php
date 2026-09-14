@extends('layouts.app')
@section('title', 'Guia ENEM')
@section('content')
@php($E = \App\Support\Enem::class)
<h1 class="text-2xl font-semibold">Guia ENEM — o que estudar</h1>
<p class="text-sm text-muted">Organizado pelos grandes eixos da prova, com base na Matriz de Referência e no conteúdo efetivamente presente nas provas oficiais.</p>
<div class="notice-neutral mt-4">Nunca afirmamos que um conteúdo “vai cair”. Indicamos o que é recorrente nas provas analisadas e o que é relevante na Matriz de Referência.</div>
<div class="mt-4 flex flex-wrap gap-1">
    <a href="{{ route('guide') }}" class="btn-{{ $area ? 'secondary' : 'primary' }} px-3 py-1.5">Todos</a>
    @foreach($E::AREA_SHORT as $k => $v)<a href="?area={{ $k }}" class="btn-{{ $area === $k ? 'primary' : 'secondary' }} px-3 py-1.5">{{ $v }}</a>@endforeach
</div>
@if($topics->isEmpty())<div class="mt-6 rounded-2xl border border-dashed border-border p-8 text-center text-sm text-muted">Nenhum tópico verificado ainda.</div>@endif
@foreach($topics as $a => $list)
    <section class="mt-6">
        <h2 class="mb-3 font-semibold">{{ $E::AREA_LABEL[$a] ?? $a }}</h2>
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            @foreach($list as $t)
                <div class="card">
                    <p class="text-xs text-muted">{{ $t->discipline }}</p>
                    <h3 class="font-medium">{{ $t->name }}</h3>
                    @if($t->description)<p class="mt-1 text-sm text-muted">{{ $t->description }}</p>@endif
                    <div class="mt-2 flex flex-wrap gap-1">
                        @if($t->verified_count > 0)<span class="badge-success">{{ $t->verified_count }} questões oficiais — {{ $recurrent }}</span>@endif
                        @if($t->matrix_skill)<span class="badge-primary">{{ $t->matrix_skill }} — {{ $matrix }}</span>@endif
                    </div>
                    @if($t->materials->isNotEmpty())<ul class="mt-2 text-sm text-[#c4b5fd]">@foreach($t->materials as $m)<li>{{ $m->title }}</li>@endforeach</ul>@endif
                </div>
            @endforeach
        </div>
    </section>
@endforeach
@endsection
