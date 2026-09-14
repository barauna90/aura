@extends('layouts.admin')
@section('title', $exam->title)
@section('admin')
@php($E = \App\Support\Enem::class)
@php($isAdmin = auth()->user()->hasRole('ADMIN'))
<a href="{{ route('admin.content.index') }}" class="text-sm text-muted hover:underline">← Conteúdo oficial</a>
<div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">{{ $exam->title }}</h1>
        <p class="text-xs text-muted">ENEM {{ $exam->edition->year }} · {{ $E::APPLICATIONS[$exam->application] }} · {{ $exam->day }}º dia · {{ $exam->duration_minutes }} min · v{{ $exam->version }}</p>
        <p class="text-xs text-muted">Fonte: <a href="{{ $exam->source->source_url }}" target="_blank" rel="noreferrer" class="underline">{{ $exam->source->source_url }}</a> · {{ $exam->source->document_version }} · checksum {{ substr($exam->source->checksum, 0, 12) }}…</p>
    </div>
    <span class="flex gap-1"><span class="badge-{{ $exam->review_status === 'VERIFIED' ? 'success' : ($exam->review_status === 'REJECTED' ? 'danger' : 'warning') }}">{{ $exam->review_status }}</span><span class="badge-neutral">{{ $E::STAGE_LABEL[$exam->pipeline_stage] }}</span></span>
</div>

<div class="card mt-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold">Fluxo de auditoria</h2>
        <button type="button" class="btn-secondary px-3 py-1.5" id="btn-validate" data-url="{{ route('admin.content.validate', $exam) }}">Validar agora</button>
    </div>
    <div id="validation-result" hidden class="notice-neutral mt-3"></div>
    <ol class="mt-3 flex flex-wrap gap-2 text-xs">
        @php($idx = array_search($exam->pipeline_stage, $E::PIPELINE_STAGES, true))
        @foreach($E::PIPELINE_STAGES as $i => $s)
            <li class="rounded-full border px-3 py-1 {{ $i <= $idx ? 'border-success bg-success/10 text-success' : 'border-border text-muted' }}">{{ $E::STAGE_LABEL[$s] }}</li>
        @endforeach
    </ol>
    @if($nextStage && $exam->review_status !== 'REJECTED')
        <form method="POST" action="{{ route('admin.content.advance', $exam) }}" class="mt-3 flex flex-wrap items-center gap-2">
            @csrf<input type="hidden" name="target" value="{{ $nextStage }}">
            <button class="btn-primary px-4 py-1.5" @disabled($nextStage === 'PUBLISHED' && !$isAdmin)>Avançar para: {{ $E::STAGE_LABEL[$nextStage] }}</button>
            <span class="text-xs text-muted">Revisão 1 e 2 exigem pessoas diferentes. Publicar exige ADMIN e reverifica checksum do PDF e do gabarito.</span>
        </form>
    @endif
    <form method="POST" action="{{ route('admin.content.reject', $exam) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf<div><label class="label" for="reject-reason">Rejeitar (motivo)</label><input class="input" id="reject-reason" name="reason" required></div><button class="btn-danger px-3 py-1.5">Rejeitar</button></form>
</div>

<script>
document.getElementById('btn-validate').addEventListener('click', async (e) => {
    const out = document.getElementById('validation-result');
    out.hidden = false; out.textContent = 'Validando…';
    const r = await fetch(e.target.dataset.url, { headers: { Accept: 'application/json' } }).then((x) => x.json());
    const problems = [...r.structural, ...r.integrity];
    out.className = r.ok ? 'notice-success mt-3' : 'notice-danger mt-3';
    out.innerHTML = r.ok ? '<strong>Validação OK</strong> — estrutura e integridade conferem com o documento oficial.' : '<strong>Bloqueios encontrados:</strong><ul class="list-disc pl-5">' + problems.map((p) => `<li>${p.message}</li>`).join('') + '</ul>';
});
</script>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="card">
        <h2 class="font-semibold">Cadernos ({{ $exam->booklets->count() }})</h2>
        <ul class="mt-3 space-y-3">
            @foreach($exam->booklets as $b)
                <li class="rounded-xl border border-border p-3 text-sm">
                    <div class="flex flex-wrap justify-between gap-2"><span>{{ $b->label }} · {{ $b->page_count }} páginas · {{ $b->questions_count }} questões</span><span class="flex gap-1"><span class="badge-{{ $b->review_status === 'VERIFIED' ? 'success' : 'warning' }}">{{ $b->review_status }}</span>@if($b->answerSets->isNotEmpty())<span class="badge-primary">gabarito {{ $b->answerSets->count() }}</span>@endif</span></div>
                    <p class="mt-1 text-xs text-muted">checksum {{ substr($b->pdf_checksum, 0, 16) }}…</p>
                    @if($isAdmin)
                    <details class="mt-2"><summary class="cursor-pointer text-[#c4b5fd]">Registrar gabarito oficial deste caderno</summary>
                        <form method="POST" action="{{ route('admin.content.answer_key', $b) }}" enctype="multipart/form-data" class="mt-2 space-y-2">
                            @csrf
                            <div><label class="label">URL do gabarito oficial</label><input class="input" name="source_url" type="url" required></div>
                            <div><label class="label">Versão</label><input class="input" name="document_version" required></div>
                            <div><label class="label">Linhas: número;área;letra (X = anulada);idioma (INGLES/ESPANHOL, opcional);página (opcional)</label><textarea class="input font-mono" name="answers_csv" rows="6" required placeholder="1;LINGUAGENS;B;INGLES;2&#10;6;LINGUAGENS;E;;3&#10;46;HUMANAS;A"></textarea></div>
                            <div><label class="label">PDF do gabarito (opcional)</label><input class="input" name="gabarito_pdf" type="file" accept="application/pdf"></div>
                            <button class="btn-primary px-3 py-1.5">Registrar gabarito</button>
                        </form>
                    </details>
                    @endif
                </li>
            @endforeach
        </ul>
        @if($isAdmin)
        <details class="mt-3"><summary class="cursor-pointer text-sm text-[#c4b5fd]">Adicionar caderno (PDF oficial)</summary>
            <form method="POST" action="{{ route('admin.content.booklet', $exam) }}" enctype="multipart/form-data" class="mt-2 space-y-2">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label">Cor</label><select class="input" name="color">@foreach($E::BOOKLET_COLORS as $c)<option>{{ $c }}</option>@endforeach</select></div>
                    <div><label class="label">Páginas</label><input class="input" name="page_count" type="number" min="1" required></div>
                </div>
                <div><label class="label">Rótulo</label><input class="input" name="label" required placeholder="Caderno 1 — Azul"></div>
                <div><label class="label">URL oficial do caderno</label><input class="input" name="source_url" type="url" required></div>
                <div><label class="label">Versão</label><input class="input" name="document_version" required></div>
                <div><label class="label">PDF oficial</label><input class="input" name="pdf" type="file" accept="application/pdf" required></div>
                <button class="btn-primary px-3 py-1.5">Importar caderno</button>
            </form>
        </details>
        @endif
    </div>

    <div class="space-y-4">
        <div class="card">
            <h2 class="font-semibold">Redação</h2>
            @if($exam->essayPrompt)<p class="mt-1 text-sm">{{ $exam->essayPrompt->theme }} <span class="badge-{{ $exam->essayPrompt->review_status === 'VERIFIED' ? 'success' : 'warning' }}">{{ $exam->essayPrompt->review_status }}</span></p>@else<p class="mt-1 text-sm text-muted">Nenhuma proposta cadastrada.</p>@endif
            @if($isAdmin)
            <details class="mt-2"><summary class="cursor-pointer text-sm text-[#c4b5fd]">Registrar proposta oficial</summary>
                <form method="POST" action="{{ route('admin.content.essay_prompt', $exam) }}" class="mt-2 space-y-2">
                    @csrf
                    <div><label class="label">Tema (exatamente como no documento oficial)</label><input class="input" name="theme" required></div>
                    <div><label class="label">Textos motivadores (separe com uma linha contendo apenas ---)</label><textarea class="input" name="texts" rows="8" required></textarea></div>
                    <div class="grid grid-cols-2 gap-2"><div><label class="label">URL oficial</label><input class="input" name="source_url" type="url" required></div><div><label class="label">Versão</label><input class="input" name="document_version" required></div></div>
                    <div><label class="label">Linhas da folha oficial</label><input class="input" name="max_lines" type="number" value="30"></div>
                    <button class="btn-primary px-3 py-1.5">Registrar proposta</button>
                </form>
            </details>
            @endif
        </div>

        <div class="card">
            <h2 class="font-semibold">Regras de nota zero — ENEM {{ $exam->edition->year }}</h2>
            <ul class="mt-2 text-sm">@forelse($exam->edition->zeroRules as $r)<li class="flex justify-between border-b border-border py-1"><span><code>{{ $r->code }}</code> {{ $r->description }}</span><span class="badge-{{ $r->review_status === 'VERIFIED' ? 'success' : 'warning' }}">{{ $r->review_status }}</span></li>@empty<li class="text-muted">Nenhuma regra cadastrada para esta edição — a correção só aplicará regras cadastradas e verificadas.</li>@endforelse</ul>
            @if($isAdmin)
            <details class="mt-2"><summary class="cursor-pointer text-sm text-[#c4b5fd]">Cadastrar regras (com fonte oficial)</summary>
                <form method="POST" action="{{ route('admin.content.zero_rules', $exam->edition->year) }}" class="mt-2 space-y-2">
                    @csrf
                    @foreach(['EM_BRANCO', 'INSUFICIENTE', 'FUGA_TEMA', 'NAO_DISSERTATIVO', 'IDENTIFICACAO', 'LINGUA_ESTRANGEIRA', 'ANULACAO_DELIBERADA', 'DESCONECTADO'] as $i => $code)
                        <div class="grid grid-cols-[130px_1fr_1fr] gap-2 text-xs"><input class="input" name="rules[{{ $i }}][code]" value="{{ $code }}" readonly><input class="input" name="rules[{{ $i }}][description]" placeholder="Descrição conforme o edital/cartilha"><input class="input" name="rules[{{ $i }}][source_url]" type="url" placeholder="URL do documento oficial"></div>
                    @endforeach
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="verified" value="1"> Marcar como verificadas (conferi na fonte)</label>
                    <button class="btn-primary px-3 py-1.5">Salvar regras</button>
                </form>
            </details>
            @endif
        </div>

        @if($isAdmin)
        <div class="card">
            <h2 class="font-semibold">Alterar metadados (gera versão + volta para auditoria)</h2>
            <form method="POST" action="{{ route('admin.content.update', $exam) }}" class="mt-2 grid gap-2 sm:grid-cols-2">
                @csrf @method('PUT')
                <div><label class="label">Duração (min)</label><input class="input" name="duration_minutes" type="number" value="{{ $exam->duration_minutes }}"></div>
                <div><label class="label">Título</label><input class="input" name="title" value="{{ $exam->title }}"></div>
                <div class="sm:col-span-2"><label class="label">Motivo (obrigatório)</label><input class="input" name="reason" required></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_free_sample" value="1" @checked($exam->is_free_sample)> Amostra gratuita</label>
                <div class="text-right"><button class="btn-secondary px-3 py-1.5">Salvar alteração</button></div>
            </form>
        </div>
        @endif

        <div class="card">
            <h2 class="font-semibold">Histórico de versões</h2>
            <ul class="mt-2 text-sm">@forelse($versions as $v)<li class="border-b border-border py-1"><span class="text-muted">v{{ $v->version }} · {{ $v->created_at->format('d/m/Y H:i') }} · {{ $v->author->name }}</span><br>{{ $v->reason }}</li>@empty<li class="text-muted">Sem alterações registradas.</li>@endforelse</ul>
        </div>
    </div>
</div>
@endsection
