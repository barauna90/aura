@extends('layouts.app')
@section('title', 'Caderno de erros')
@section('content')
@php($E = \App\Support\Enem::class)
<h1 class="text-2xl font-semibold">Meu caderno de erros</h1>
<p class="text-sm text-muted">Questões oficiais que você errou, com revisão por repetição espaçada.</p>

<form method="GET" class="card mt-5 grid gap-3 sm:grid-cols-4">
    <div><label class="label" for="area">Área</label><select class="input" id="area" name="area"><option value="">Todas</option>@foreach($E::OBJECTIVE_AREAS as $a)<option value="{{ $a }}" @selected(($filters['area'] ?? null) === $a)>{{ $E::AREA_SHORT[$a] }}</option>@endforeach</select></div>
    <div><label class="label" for="year">Ano</label><input class="input" id="year" name="year" type="number" value="{{ $filters['year'] ?? '' }}" placeholder="Todos"></div>
    <div class="flex items-end"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="due" value="1" @checked(!empty($filters['due']))> Só as de hoje</label></div>
    <div class="flex items-end"><button class="btn-secondary w-full">Filtrar</button></div>
</form>

@if($entries->isEmpty())<div class="mt-6 rounded-2xl border border-dashed border-border p-8 text-center text-sm text-muted">Nenhuma questão no caderno de erros. Questões erradas em provas entram aqui automaticamente.</div>@endif

<ul class="mt-6 space-y-3">
    @foreach($entries as $e)
        @php($q = $e->question)
        @php($res = \App\Services\Study\ErrorNotebookService::resolutionOf($e))
        <li class="card">
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge-neutral">{{ $E::AREA_SHORT[$q->area] }}</span>
                    <span class="font-medium">Questão {{ $q->original_number }} · ENEM {{ $q->booklet->exam->edition->year }}</span>
                    <span class="text-muted">{{ $q->booklet->exam->title }}</span>@if($q->page_number)<span class="text-xs text-muted">p.{{ $q->page_number }}</span>@endif
                    @if($q->classification?->review_status === 'VERIFIED' && $q->classification->topic)<span class="badge-primary">{{ $q->classification->topic->name }}</span>@endif
                </div>
                <div class="text-xs text-muted">{{ $e->reviewed ? 'Revisada' : 'Não revisada' }} · próxima revisão {{ $e->next_review_at?->format('d/m/Y') ?? '—' }}</div>
            </div>
            <p class="mt-2 text-sm">Gabarito oficial: <strong>{{ $q->officialAnswer?->review_status === 'VERIFIED' ? ($q->officialAnswer->annulled ? 'Anulada' : $q->officialAnswer->correct) : '—' }}</strong></p>
            <p class="mt-1 text-sm text-muted">{{ $res['body'] ?? $res['notice'] }}</p>
            <div class="mt-3 grid gap-3 md:grid-cols-[1fr_auto]">
                <form method="POST" action="{{ route('notebook.note', $e) }}">@csrf @method('PUT')
                    <textarea name="note" rows="2" class="input" placeholder="Minha anotação sobre esta questão…" aria-label="Anotação">{{ $e->note }}</textarea>
                    <button class="btn-secondary mt-2 px-3 py-1 text-xs">Salvar anotação</button>
                </form>
                <div class="text-sm">
                    <p class="mb-1 text-xs text-muted">Como foi rever esta questão?</p>
                    <div class="flex gap-1">
                        @foreach([1 => 'Errei de novo', 3 => 'Com dificuldade', 5 => 'Fácil'] as $qv => $l)
                            <form method="POST" action="{{ route('notebook.review', $e) }}">@csrf<input type="hidden" name="quality" value="{{ $qv }}"><button class="btn-secondary px-3 py-1 text-xs">{{ $l }}</button></form>
                        @endforeach
                    </div>
                </div>
            </div>
        </li>
    @endforeach
</ul>
<div class="mt-4">{{ $entries->links() }}</div>
@endsection
