@extends('layouts.app')
@section('title', 'Ajuda')
@section('content')
<h1 class="text-2xl font-semibold">Ajuda</h1>

<div id="professor" class="card mt-5 space-y-3">
    <h2 class="font-semibold">Professor ENEM IA</h2>
    <p class="text-sm text-muted">Pergunte “por que errei?”, “que matéria preciso revisar?” ou “como melhorar minha competência 3?”. As respostas usam seus resultados, resoluções validadas e a base de documentos oficiais. Indisponível durante o Modo Prova Real.</p>
    <form method="POST" action="{{ route('help.ask') }}" class="space-y-3">
        @csrf
        <div><label class="label" for="session_id">Sobre qual prova? (opcional)</label>
            <select class="input" id="session_id" name="session_id"><option value="">— nenhuma —</option>@foreach($sessions as $s)<option value="{{ $s->id }}" @selected(request('session') == $s->id || old('session_id') == $s->id)>{{ $s->exam->title }} ({{ $s->finished_at?->format('d/m/Y') }})</option>@endforeach</select></div>
        <textarea class="input" name="question" rows="3" placeholder="Sua pergunta…" aria-label="Pergunta ao Professor IA" required minlength="5">{{ old('question') }}</textarea>
        <button class="btn-primary">Perguntar</button>
    </form>
    @if($answer)
        <div class="rounded-xl border border-border bg-surface-2/60 p-4 text-sm">
            <p class="whitespace-pre-wrap">{{ $answer['answer'] }}</p>
            <p class="mt-2 text-xs text-muted">{{ $answer['used_official_base'] ? 'Resposta apoiada em documentos oficiais da base.' : 'Nenhum documento oficial da base foi usado nesta resposta.' }}</p>
        </div>
    @endif
</div>

<div id="bolsas" class="card mt-4 space-y-2">
    <h2 class="font-semibold">Programa de bolsas</h2>
    <p class="text-sm text-muted">Estudantes sem condições de pagar podem receber acesso gratuito por 30, 90, 180 ou 365 dias, ou acesso integral, inclusive por meio de vagas patrocinadas por empresas e instituições.@if($supportEmail) Envie um pedido para <a href="mailto:{{ $supportEmail }}" class="underline">{{ $supportEmail }}</a> com uma breve descrição da sua situação.@endif</p>
</div>

<div id="estrategia" class="card mt-4 space-y-2">
    <h2 class="font-semibold">Estratégia ENEM</h2>
    <ul class="list-disc space-y-1 pl-5 text-sm text-muted">
        <li>Gestão do tempo: distribua o tempo por área e reserve minutos para transferir as respostas ao cartão.</li>
        <li>Cartão-resposta: só o que está marcado nele é corrigido. Transfira aos poucos, não no final.</li>
        <li>Redação: planeje tese, argumentos e proposta de intervenção antes de escrever na folha.</li>
        <li>Ordem de resolução: comece pela área em que você rende melhor para ganhar ritmo.</li>
        <li>Resistência: faça provas completas no Modo Prova Real para treinar as horas de duração.</li>
    </ul>
    <p class="text-xs text-muted">Estratégias ajudam na organização; nenhuma delas garante nota.</p>
</div>

<div id="termos" class="card mt-4 space-y-2">
    <h2 class="font-semibold">Termos, privacidade e avisos</h2>
    <div class="notice-neutral">{{ $independence }}</div>
    <p class="text-sm text-muted">{{ \App\Support\Disclaimers::RESULTS_EDUCATIONAL }} {{ $scoreNotice }}</p>
    <p class="text-sm text-muted">Coletamos o mínimo necessário: e-mail, nome, respostas e textos que você produz. Você pode exportar ou excluir seus dados em <a href="{{ route('profile.edit') }}" class="underline">Perfil</a>. Não vendemos dados. Comunicações por e-mail só com seu consentimento.</p>
</div>
@endsection
