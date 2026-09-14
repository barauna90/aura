@extends('layouts.app')
@section('title', 'Escrever redação')
@section('content')
@php($p = $essay->prompt)
<h1 class="text-2xl font-semibold">Redação</h1>
<p class="text-sm text-muted">{{ $p->exam->title }} · ENEM {{ $p->exam->edition->year }}</p>

<div id="essay-editor" class="mt-4" data-essay-id="{{ $essay->id }}" data-max-lines="{{ $p->max_lines }}" data-draft-url="{{ route('essays.draft', $essay) }}">
    <div role="tablist" class="flex flex-wrap gap-1 border-b border-border">
        <button type="button" role="tab" data-tab="proposta" aria-selected="true" class="px-4 py-2 text-sm aria-selected:border-b-2 aria-selected:border-primary aria-selected:font-medium aria-selected:text-text text-muted">Proposta e textos motivadores</button>
        <button type="button" role="tab" data-tab="rascunho" aria-selected="false" class="px-4 py-2 text-sm aria-selected:border-b-2 aria-selected:border-primary aria-selected:font-medium aria-selected:text-text text-muted">Rascunho</button>
        <button type="button" role="tab" data-tab="folha" aria-selected="false" class="px-4 py-2 text-sm aria-selected:border-b-2 aria-selected:border-primary aria-selected:font-medium aria-selected:text-text text-muted">Folha de redação</button>
    </div>

    <div data-panel="proposta" class="card mt-4 space-y-4">
        <h2 class="text-lg font-semibold">{{ $p->theme }}</h2>
        @foreach($p->motivating_texts ?? [] as $i => $t)
            <article class="rounded-xl border border-border bg-surface-2/60 p-4 text-sm">
                <h3 class="font-medium">{{ $t['title'] ?? 'Texto '.($i + 1) }}</h3>
                <p class="mt-2 whitespace-pre-wrap">{{ $t['body'] }}</p>
            </article>
        @endforeach
        <p class="text-xs text-muted">Proposta transcrita do documento oficial: <a href="{{ $p->source->source_url }}" target="_blank" rel="noreferrer" class="text-[#c4b5fd] underline">fonte Inep</a></p>
    </div>

    <div data-panel="rascunho" hidden class="card mt-4">
        <p class="mb-2 text-sm text-muted">Espaço livre para planejar. O rascunho não é enviado nem avaliado.</p>
        <textarea id="essay-draft" rows="18" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off" class="input font-mono" aria-label="Folha de rascunho"></textarea>
    </div>

    <div data-panel="folha" hidden class="card mt-4">
        <form method="POST" action="{{ route('essays.submit', $essay) }}" data-confirm="Enviar a redação para avaliação simulada? Depois do envio o texto não pode ser alterado.">
            @csrf
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                <span id="essay-lines" class="text-muted">0 / {{ $p->max_lines }} linhas</span>
                <span id="essay-save-state" class="text-xs text-muted">Salvo automaticamente</span>
            </div>
            <textarea id="essay-sheet" name="text" rows="{{ $p->max_lines + 1 }}" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off" class="essay-sheet input resize-none px-4 text-base" aria-label="Folha de redação" aria-describedby="folha-hint">{{ $essay->draft_text }}</textarea>
            <p id="folha-hint" class="mt-2 text-xs text-muted">Sem corretor ortográfico, sem sugestões e sem IA — como na prova. Uma linha da folha corresponde a uma quebra de linha aqui.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <button id="essay-submit" class="btn-primary">Enviar para avaliação simulada</button>
                <button type="button" id="essay-save-now" class="btn-secondary">Salvar agora</button>
            </div>
            <div class="notice-neutral mt-4">{{ $notice }}</div>
        </form>
    </div>
</div>
@endsection
