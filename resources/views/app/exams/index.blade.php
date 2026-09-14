@extends('layouts.app')
@section('title', 'Provas anteriores')
@section('content')
@php($E = \App\Support\Enem::class)
<h1 class="text-2xl font-semibold">Provas anteriores</h1>
<p class="text-sm text-muted">Cadernos e gabaritos oficiais publicados pelo Inep. Cada prova mantém a estrutura da sua edição.</p>

<form method="GET" class="card mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
    <div><label class="label" for="year">Ano</label><select class="input" id="year" name="year"><option value="">Todos</option>@foreach($years as $y)<option value="{{ $y }}" @selected(($filters['year'] ?? null) == $y)>{{ $y }}</option>@endforeach</select></div>
    <div><label class="label" for="day">Dia</label><select class="input" id="day" name="day"><option value="">Todos</option><option value="1" @selected(($filters['day'] ?? null) == 1)>1º dia</option><option value="2" @selected(($filters['day'] ?? null) == 2)>2º dia</option></select></div>
    <div><label class="label" for="area">Área</label><select class="input" id="area" name="area"><option value="">Todas</option>@foreach($E::AREA_SHORT as $k => $v)<option value="{{ $k }}" @selected(($filters['area'] ?? null) === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label class="label" for="application">Aplicação</label><select class="input" id="application" name="application"><option value="">Todas</option>@foreach($E::APPLICATIONS as $k => $v)<option value="{{ $k }}" @selected(($filters['application'] ?? null) === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="flex items-end"><button class="btn-secondary w-full">Filtrar</button></div>
</form>

@if($exams->isEmpty())
    <div class="mt-6 rounded-2xl border border-dashed border-border p-8 text-center">
        <p class="font-medium">Nenhuma prova oficial publicada para estes filtros.</p>
        <p class="mt-1 text-sm text-muted">As provas passam por importação a partir dos PDFs do Inep e por dupla revisão humana antes de aparecerem aqui.</p>
    </div>
@endif

<ul class="mt-6 grid gap-4 md:grid-cols-2">
    @foreach($exams as $e)
        <li>
            <a href="{{ route('exams.show', $e) }}" class="card block h-full transition hover:border-primary">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs text-muted">ENEM {{ $e->edition->year }} · {{ $E::APPLICATIONS[$e->application] }} · {{ $e->day }}º dia</p>
                        <h2 class="mt-1 font-semibold">{{ $e->title }}</h2>
                    </div>
                    @if($e->is_free_sample)<span class="badge-success">Gratuita</span>@endif
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach($e->areas as $a)<span class="badge-neutral">{{ $E::AREA_SHORT[$a] ?? $a }}</span>@endforeach
                    @if($e->has_foreign_language)<span class="badge-primary">Inglês/Espanhol</span>@endif
                </div>
                <p class="mt-3 text-sm text-muted">Duração oficial: {{ intdiv($e->duration_minutes, 60) }}h{{ $e->duration_minutes % 60 ? ' '.($e->duration_minutes % 60).'min' : '' }} · {{ $e->booklets->count() }} caderno(s)</p>
                <p class="mt-1 text-xs text-muted">Fonte: Inep · versão {{ $e->source->document_version }}</p>
            </a>
        </li>
    @endforeach
</ul>
@endsection
