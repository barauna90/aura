@extends('layouts.admin')
@section('title', 'Conteúdo oficial')
@section('admin')
@php($E = \App\Support\Enem::class)
<h1 class="text-2xl font-semibold">Conteúdo oficial</h1>
<p class="text-sm text-muted">Importação de provas a partir dos PDFs do Inep e fluxo de auditoria. Nada é publicado sem validação automática e duas revisões humanas distintas.</p>
<div class="notice-warning mt-4"><strong>Regra inegociável.</strong> Só cadastre documentos oficiais do Inep/MEC com a URL de origem. Nunca transcreva questões “de memória”. O PDF é exibido ao aluno sem alterações; o gabarito é comparado por checksum antes de qualquer publicação.</div>

<div class="mt-5 grid gap-6 lg:grid-cols-[380px_1fr]">
    @can('admin')
    <form method="POST" action="{{ route('admin.content.store') }}" enctype="multipart/form-data" class="card space-y-3">
        @csrf
        <h2 class="font-semibold">1. Cadastrar prova</h2>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="label" for="year">Ano</label><input class="input" id="year" name="year" type="number" min="1998" required value="{{ old('year') }}"></div>
            <div><label class="label" for="day">Dia</label><select class="input" id="day" name="day"><option value="1">1</option><option value="2">2</option></select></div>
        </div>
        <div><label class="label" for="application">Aplicação</label><select class="input" id="application" name="application">@foreach($E::APPLICATIONS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div><label class="label" for="title">Título</label><input class="input" id="title" name="title" required placeholder="ENEM 2023 — 1º dia" value="{{ old('title') }}"></div>
        <div><label class="label" for="duration_minutes">Duração oficial desta edição/dia (min)</label><input class="input" id="duration_minutes" name="duration_minutes" type="number" min="30" required value="{{ old('duration_minutes') }}"><p class="mt-1 text-xs text-muted">Consulte o edital da edição. Não existe valor universal.</p></div>
        <fieldset><legend class="label">Áreas</legend><div class="flex flex-wrap gap-2 text-sm">@foreach($E::AREA_SHORT as $k => $v)<label class="flex items-center gap-1"><input type="checkbox" name="areas[]" value="{{ $k }}"> {{ $v }}</label>@endforeach</div></fieldset>
        <div><label class="label" for="source_url">URL oficial (Inep)</label><input class="input" id="source_url" name="source_url" type="url" required placeholder="https://download.inep.gov.br/…" value="{{ old('source_url') }}"></div>
        <div><label class="label" for="document_version">Versão do documento</label><input class="input" id="document_version" name="document_version" required placeholder="ex.: 2023-11-05 v1" value="{{ old('document_version') }}"></div>
        <div><label class="label" for="structure_note">Observação de estrutura (provas antigas)</label><input class="input" id="structure_note" name="structure_note" value="{{ old('structure_note') }}"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_free_sample" value="1"> Prova de amostra gratuita</label>
        <div><label class="label" for="source_pdf">PDF oficial (opcional aqui; obrigatório no caderno)</label><input class="input" id="source_pdf" name="source_pdf" type="file" accept="application/pdf"></div>
        <button class="btn-primary">Cadastrar como PENDING</button>
    </form>
    @endcan

    <div class="card">
        <h2 class="font-semibold">Provas cadastradas</h2>
        @if($exams->isEmpty())<p class="mt-2 text-sm text-muted">Nenhuma prova importada ainda.</p>@endif
        <ul class="mt-3 divide-y divide-border text-sm">
            @foreach($exams as $e)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <a href="{{ route('admin.content.show', $e) }}" class="hover:underline"><span class="font-medium">{{ $e->title }}</span> <span class="ml-2 text-xs text-muted">ENEM {{ $e->edition->year }} · v{{ $e->version }} · {{ $e->booklets_count }} caderno(s)</span></a>
                    <span class="flex gap-1"><span class="badge-{{ $e->review_status === 'VERIFIED' ? 'success' : ($e->review_status === 'REJECTED' ? 'danger' : 'warning') }}">{{ $e->review_status }}</span><span class="badge-neutral">{{ $E::STAGE_LABEL[$e->pipeline_stage] }}</span></span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
