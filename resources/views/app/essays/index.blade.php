@extends('layouts.app')
@section('title', 'Redação')
@section('content')
@php($E = \App\Support\Enem::class)
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">Redação</h1>
        <p class="text-sm text-muted">Treine com as propostas oficiais das edições anteriores e receba uma correção simulada pelas cinco competências, feita por dois avaliadores independentes.</p>
    </div>
    <div class="stat min-w-56">
        <p class="text-xs uppercase text-muted">Correções este mês</p>
        <p class="mt-1 text-2xl font-semibold">{{ $used }} <span class="text-sm font-normal text-muted">de {{ $limit === -1 ? 'ilimitadas' : $limit }}</span></p>
        @if($limit === 0)<a href="{{ route('subscription.index') }}" class="text-xs text-primary underline">Assine um plano para enviar redações</a>@elseif($limit !== -1)<div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-primary" style="width: {{ min(100, $limit ? $used / $limit * 100 : 0) }}%"></div></div>@endif
    </div>
</div>
<div class="notice-neutral mt-4">{{ $notice }}</div>

<div class="mt-4 grid gap-3 sm:grid-cols-3">
    <div class="stat"><p class="text-xs uppercase text-muted">Redações enviadas</p><p class="mt-1 text-2xl font-semibold">{{ $essays->whereNotNull('submitted_at')->count() }}</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Melhor nota simulada</p><p class="mt-1 text-2xl font-semibold">{{ $best ?? '—' }}<span class="text-sm font-normal text-muted">/1000</span></p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Média simulada</p><p class="mt-1 text-2xl font-semibold">{{ $average ?? '—' }}<span class="text-sm font-normal text-muted">/1000</span></p></div>
</div>

<section class="mt-8">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <h2 class="font-semibold">Propostas oficiais</h2>
        <p class="text-xs text-muted">Tema e textos motivadores exatamente como no caderno do Inep. Você lê a proposta na página oficial e escreve na folha de 30 linhas.</p>
    </div>
    @if($prompts->isEmpty())<div class="mt-3 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted">Nenhuma proposta oficial verificada ainda.</div>@endif
    <div class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        @foreach($prompts as $p)
            <div class="card flex flex-col">
                <div class="flex items-center justify-between gap-2"><p class="text-xs text-muted">ENEM {{ $p->exam->edition->year }} · 1º dia</p>@if($done->contains($p->id))<span class="badge-success">já escrevi</span>@endif</div>
                <p class="mt-1 flex-1 font-medium">“{{ $p->theme }}”</p>
                <p class="mt-2 text-xs text-muted">Até {{ $p->max_lines }} linhas · texto dissertativo-argumentativo · proposta de intervenção obrigatória</p>
                <form method="POST" action="{{ route('essays.from_prompt', $p) }}" class="mt-3">@csrf<button class="btn-primary px-4 py-1.5">{{ $done->contains($p->id) ? 'Escrever de novo' : 'Escrever' }}</button></form>
            </div>
        @endforeach
    </div>
</section>

<section class="mt-8">
    <h2 class="font-semibold">Minhas redações</h2>
    @if($essays->isEmpty())<div class="mt-3 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted">Você ainda não escreveu nenhuma redação. Escolha uma proposta acima para começar.</div>@endif
    <ul class="mt-3 space-y-2">
        @foreach($essays as $e)
            <li class="card flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                <div><p class="font-medium">{{ $e->prompt->theme }}</p><p class="text-xs text-muted">ENEM {{ $e->prompt->exam->edition->year }} · {{ ($e->submitted_at ?? $e->created_at)->format('d/m/Y') }} · {{ $e->exam_session_id ? 'prova completa' : 'treino avulso' }}</p></div>
                <div class="flex items-center gap-3">
                    @if($e->finalResult)
                        <div class="flex items-center gap-1" title="Competências 1 a 5">@foreach($e->finalResult->competency_scores ?? [] as $c => $score)<span class="rounded bg-surface-2 px-1.5 py-0.5 text-[10px] tabular-nums">C{{ $c }} {{ $score }}</span>@endforeach</div>
                        <span class="font-semibold">{{ $e->finalResult->total }}</span>
                    @endif
                    <span class="badge-{{ $e->status === 'EVALUATED' ? 'success' : ($e->status === 'FAILED' ? 'danger' : 'primary') }}">{{ ['DRAFT' => 'Rascunho', 'SUBMITTED' => 'Em correção', 'EVALUATING' => 'Em correção', 'EVALUATED' => 'Corrigida', 'FAILED' => 'Falhou'][$e->status] ?? $e->status }}</span>
                    <a href="{{ $e->status === 'DRAFT' ? route('essays.edit', $e) : route('essays.report', $e) }}" class="text-primary underline">{{ $e->status === 'DRAFT' ? 'Continuar' : 'Relatório' }}</a>
                </div>
            </li>
        @endforeach
    </ul>
</section>

<section class="mt-8 grid gap-4 lg:grid-cols-[1.2fr_1fr]">
    <div class="card">
        <h2 class="font-semibold">Como a correção funciona</h2>
        <p class="mt-1 text-sm text-muted">Cada competência vale de 0 a 200 pontos, em seis níveis ({{ implode(' · ', $E::ESSAY_LEVELS) }}). A nota final é a soma das cinco — até 1000.</p>
        <ol class="mt-3 space-y-2 text-sm">
            @foreach($E::ESSAY_COMPETENCIES as $n => $desc)
                <li class="flex gap-3 rounded-xl border border-border p-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">C{{ $n }}</span><span>{{ $desc }}</span></li>
            @endforeach
        </ol>
        <p class="mt-3 text-xs text-muted">Dois avaliadores independentes (A e B) corrigem o texto; se as notas divergirem além do limite, um terceiro (C) desempata — como na correção oficial. Redações com até 7 linhas, fora do tema, fora do tipo textual ou com identificação recebem zero, conforme as instruções da edição.</p>
    </div>
    <div class="card">
        <h2 class="font-semibold">Estrutura que funciona</h2>
        <ul class="mt-3 space-y-3 text-sm">
            <li><p class="font-medium">1. Introdução (4–6 linhas)</p><p class="text-muted">Contextualize o tema, apresente a tese e antecipe os dois argumentos que vai desenvolver.</p></li>
            <li><p class="font-medium">2. Desenvolvimento 1 (7–9 linhas)</p><p class="text-muted">Primeiro argumento com repertório sociocultural (dado, autor, lei, obra) ligado explicitamente ao tema.</p></li>
            <li><p class="font-medium">3. Desenvolvimento 2 (7–9 linhas)</p><p class="text-muted">Segundo argumento, com conectivos entre parágrafos e dentro deles (C4).</p></li>
            <li><p class="font-medium">4. Conclusão com proposta de intervenção (5–7 linhas)</p><p class="text-muted">Agente + ação + meio/modo + finalidade + detalhamento — respeitando os direitos humanos (C5).</p></li>
        </ul>
        <div class="notice-warning mt-3 text-xs">Erros que mais custam pontos: fugir do tema, não apresentar proposta de intervenção completa, copiar os textos motivadores e usar linguagem informal.</div>
    </div>
</section>
@endsection
