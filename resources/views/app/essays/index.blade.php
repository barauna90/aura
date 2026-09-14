@extends('layouts.app')
@section('title', 'Redação')
@section('content')
<h1 class="text-2xl font-semibold">Redação</h1>
<p class="text-sm text-muted">Treine com propostas oficiais e receba uma correção simulada pelas cinco competências.</p>
<div class="notice-neutral mt-4">{{ $notice }}</div>

<section class="mt-6">
    <h2 class="mb-3 font-semibold">Propostas oficiais</h2>
    @if($prompts->isEmpty())<div class="rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted">Nenhuma proposta oficial verificada ainda.</div>@endif
    <div class="grid gap-3 md:grid-cols-2">
        @foreach($prompts as $p)
            <div class="card">
                <p class="text-xs text-muted">ENEM {{ $p->exam->edition->year }} · {{ $p->exam->title }}</p>
                <p class="mt-1 font-medium">{{ $p->theme }}</p>
                <p class="mt-1 text-xs text-muted">Até {{ $p->max_lines }} linhas</p>
                <form method="POST" action="{{ route('essays.from_prompt', $p) }}" class="mt-3">@csrf<button class="btn-primary px-4 py-1.5">Escrever</button></form>
            </div>
        @endforeach
    </div>
</section>

<section class="mt-8">
    <h2 class="mb-3 font-semibold">Minhas redações</h2>
    @if($essays->isEmpty())<div class="rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted">Você ainda não escreveu nenhuma redação.</div>@endif
    <ul class="space-y-2">
        @foreach($essays as $e)
            <li class="card flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                <div><p class="font-medium">{{ $e->prompt->theme }}</p><p class="text-xs text-muted">ENEM {{ $e->prompt->exam->edition->year }} · {{ ($e->submitted_at ?? $e->created_at)->format('d/m/Y') }} · {{ $e->exam_session_id ? 'prova completa' : 'treino avulso' }}</p></div>
                <div class="flex items-center gap-3">
                    @if($e->finalResult)<span class="font-semibold">{{ $e->finalResult->total }}</span>@endif
                    <span class="badge-{{ $e->status === 'EVALUATED' ? 'success' : ($e->status === 'FAILED' ? 'danger' : 'primary') }}">{{ $e->status }}</span>
                    <a href="{{ $e->status === 'DRAFT' ? route('essays.edit', $e) : route('essays.report', $e) }}" class="text-[#c4b5fd] underline">{{ $e->status === 'DRAFT' ? 'Continuar' : 'Relatório' }}</a>
                </div>
            </li>
        @endforeach
    </ul>
</section>
@endsection
