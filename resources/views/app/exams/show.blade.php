@extends('layouts.app')
@section('title', $exam->title)
@section('content')
@php($E = \App\Support\Enem::class)
@php($D = \App\Support\Disclaimers::class)
<h1 class="text-2xl font-semibold">{{ $exam->title }}</h1>
<p class="text-sm text-muted">ENEM {{ $exam->edition->year }} · Duração oficial desta edição: {{ intdiv($exam->duration_minutes, 60) }}h{{ $exam->duration_minutes % 60 ? ' '.($exam->duration_minutes % 60).'min' : '' }}</p>

<div class="notice-neutral mt-5">{{ $structureNote }}</div>
<div class="notice-warning mt-3 md:hidden">{{ $D::MOBILE_RECOMMENDATION }}</div>

<form method="POST" action="{{ route('exams.start', $exam) }}" class="mt-5 grid gap-4 lg:grid-cols-3">
    @csrf
    <input type="hidden" name="device" value="desktop" id="device-field">
    <script>document.getElementById('device-field').value = window.matchMedia('(max-width: 900px)').matches ? 'mobile' : 'desktop';</script>
    <div class="card space-y-5 lg:col-span-2">
        <fieldset>
            <legend class="font-semibold">Modo de realização</legend>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <label class="cursor-pointer rounded-2xl border border-border p-4 has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                    <input type="radio" name="mode" value="PROVA_REAL" class="sr-only" checked>
                    <p class="font-medium">Modo Prova Real</p>
                    <p class="mt-1 text-sm text-muted">Cronômetro oficial, sem pausa, sem dicas, sem IA, sem correção durante a prova. Encerramento automático ao fim do tempo.</p>
                </label>
                <label class="cursor-pointer rounded-2xl border border-border p-4 has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                    <input type="radio" name="mode" value="ESTUDO" class="sr-only">
                    <p class="font-medium">Modo Estudo</p>
                    <p class="mt-1 text-sm text-muted">Pausar, continuar depois, escolher áreas e fazer anotações.</p>
                    <p class="mt-2 text-xs font-medium text-warning">{{ $D::STUDY_MODE_NOTICE }}</p>
                </label>
            </div>
        </fieldset>

        @if($exam->has_foreign_language)
            <fieldset>
                <legend class="font-semibold">Língua estrangeira</legend>
                <p class="text-sm text-muted">Somente as questões da língua escolhida serão corrigidas.</p>
                <div class="mt-2 flex gap-2">
                    @foreach($E::LANGUAGES as $k => $v)
                        <label class="cursor-pointer rounded-xl border border-border px-4 py-2 text-sm has-[:checked]:border-accent has-[:checked]:bg-accent/10"><input type="radio" name="language" value="{{ $k }}" class="sr-only" required> {{ $v }}</label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        @if($exam->booklets->count() > 1)
            <fieldset>
                <legend class="font-semibold">Caderno</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($exam->booklets as $b)
                        <label class="cursor-pointer rounded-xl border border-border px-4 py-2 text-sm has-[:checked]:border-accent has-[:checked]:bg-accent/10"><input type="radio" name="booklet_id" value="{{ $b->id }}" class="sr-only" @checked($loop->first)> {{ $b->label }}</label>
                    @endforeach
                </div>
            </fieldset>
        @else
            <input type="hidden" name="booklet_id" value="{{ $exam->booklets->first()?->id }}">
        @endif

        <fieldset>
            <legend class="font-semibold">Modo Estudo: resolver apenas algumas áreas (opcional)</legend>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach(array_intersect($exam->areas, $E::OBJECTIVE_AREAS) as $a)
                    <label class="cursor-pointer rounded-xl border border-border px-3 py-1.5 text-sm has-[:checked]:border-accent has-[:checked]:bg-accent/10"><input type="checkbox" name="areas[]" value="{{ $a }}" class="sr-only"> {{ $E::AREA_SHORT[$a] }}</label>
                @endforeach
            </div>
        </fieldset>

        <div class="notice-primary"><strong>Sobre o cartão-resposta.</strong> {{ $D::ANSWER_SHEET_ONLY }} Você navega pelo caderno oficial à esquerda e transfere as respostas para o cartão-resposta à direita.</div>
        <button class="btn-primary px-6 py-3 text-base" @disabled($exam->booklets->isEmpty())>Iniciar prova</button>
    </div>

    <div class="card space-y-3 text-sm">
        <h2 class="font-semibold">Proveniência</h2>
        <p><span class="text-muted">Fonte:</span> <a href="{{ $exam->source->source_url }}" target="_blank" rel="noreferrer" class="text-primary underline">Inep/MEC</a></p>
        <p><span class="text-muted">Versão do documento:</span> {{ $exam->source->document_version }}</p>
        <p class="break-all"><span class="text-muted">Checksum:</span> {{ substr($exam->source->checksum, 0, 16) }}…</p>
        <p><span class="text-muted">Status:</span> <span class="badge-success">Verificada</span></p>
        <hr class="border-border">
        @foreach($exam->booklets as $b)<p>{{ $b->label }}: {{ $b->page_count }} páginas · {{ $b->questions_count }} questões</p>@endforeach
        @if($exam->has_essay && $exam->essayPrompt?->review_status === 'VERIFIED')<p><span class="text-muted">Redação:</span> proposta oficial incluída</p>@endif
    </div>
</form>
@endsection
