@extends('layouts.app')
@section('title', $topic->name.' · Guia ENEM')
@section('content')
@php($E = \App\Support\Enem::class)
<a href="{{ route('guide', ['area' => $topic->area]) }}" class="text-sm text-muted hover:underline">← Guia ENEM · {{ $E::AREA_SHORT[$topic->area] ?? $topic->area }}</a>
<div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">{{ $topic->discipline }}</p>
        <h1 class="mt-1 text-2xl font-semibold">{{ $topic->name }}</h1>
        @if($topic->description)<p class="mt-2 max-w-3xl text-sm text-muted">{{ $topic->description }}</p>@endif
        <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
            @if($topic->recurrence >= 3)<span class="badge-warning">alta recorrência</span>@elseif($topic->recurrence >= 2)<span class="badge-neutral">recorrência média</span>@endif
            @if($topic->verified_count > 0)<span class="badge-success">{{ $topic->verified_count }} questões oficiais — {{ $recurrent }}</span>@endif
            @if($topic->matrix_skill)<span class="badge-primary">{{ $topic->matrix_skill }} — {{ $matrix }}</span>@endif
            @if($myErrors > 0)<a href="{{ route('notebook.index') }}" class="badge-danger">{{ $myErrors }} erro(s) seus neste tema</a>@endif
        </div>
    </div>
    <form method="POST" action="{{ route('guide.studied', $topic) }}">@csrf<button class="btn-{{ $studied ? 'secondary' : 'primary' }} px-4 py-2">{{ $studied ? '✓ Estudado — desmarcar' : 'Marcar como estudado' }}</button></form>
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-[1fr_1.2fr]">
    <div class="space-y-4">
        <div class="card">
            <h2 class="font-semibold">O que estudar neste tema</h2>
            @if($topic->subtopics)
                <ol class="mt-3 space-y-2 text-sm">
                    @foreach($topic->subtopics as $i => $s)
                        <li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">{{ $i + 1 }}</span><span>{{ $s }}</span></li>
                    @endforeach
                </ol>
            @else
                <p class="mt-2 text-sm text-muted">Roteiro detalhado ainda não cadastrado para este tema.</p>
            @endif
        </div>
        @if($topic->materials->isNotEmpty())
            <div class="card">
                <h2 class="font-semibold">Materiais verificados</h2>
                <ul class="mt-2 space-y-3 text-sm">@foreach($topic->materials as $m)<li><p class="font-medium">{{ $m->title }}</p><p class="whitespace-pre-wrap text-muted">{{ $m->body }}</p></li>@endforeach</ul>
            </div>
        @endif
        <div class="card">
            <h2 class="font-semibold">Treinar</h2>
            <p class="mt-1 text-sm text-muted">Pratique o tema com questões oficiais: faça um simulado por área de {{ $E::AREA_SHORT[$topic->area] ?? $topic->area }} e depois revise os erros no caderno.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('simulados') }}" class="btn-primary px-4 py-1.5">Simulado por área</a>
                <a href="{{ route('notebook.index') }}" class="btn-secondary px-4 py-1.5">Caderno de erros</a>
            </div>
        </div>
        @if($siblings->isNotEmpty())
            <div class="card">
                <h2 class="font-semibold">Outros temas de {{ $topic->discipline }}</h2>
                <ul class="mt-2 flex flex-wrap gap-1">@foreach($siblings as $s)<li><a href="{{ route('guide.show', $s) }}" class="badge-neutral hover:border-primary">{{ $s->name }}</a></li>@endforeach</ul>
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <form method="POST" action="{{ route('guide.videos.store', $topic) }}" class="card">
            @csrf
            <h2 class="font-semibold">Meus vídeos de estudo</h2>
            <p class="mt-1 text-sm text-muted">Cole o link de uma videoaula (YouTube, Vimeo ou qualquer site). Fica salvo só para você neste tema{{ $isStaff ? ' — ou, como equipe, recomendado para todos os alunos' : '' }}.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                <input type="url" name="url" value="{{ old('url') }}" required placeholder="https://www.youtube.com/watch?v=…" class="input" aria-label="Link do vídeo">
                <button class="btn-primary px-4">Adicionar</button>
            </div>
            <input type="text" name="title" value="{{ old('title') }}" maxlength="160" placeholder="Título (opcional)" class="input mt-2" aria-label="Título do vídeo">
            @if($isStaff)<label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="recommended" value="1"> Recomendar para todos os alunos</label>@endif
            @error('url')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
        </form>

        @forelse($videos as $v)
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ $v->title }} @if($v->is_recommended)<span class="badge-primary">recomendado</span>@endif</p>
                        <p class="text-xs text-muted">{{ $v->provider === 'LINK' ? parse_url($v->url, PHP_URL_HOST) : ucfirst(strtolower($v->provider)) }} · {{ $v->created_at->format('d/m/Y') }}@if($v->is_recommended && $v->user_id !== auth()->id()) · equipe Aura @endif</p>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ $v->url }}" target="_blank" rel="noreferrer noopener" class="text-primary underline">Abrir</a>
                        @if($v->user_id === auth()->id() || $isStaff)
                            <form method="POST" action="{{ route('guide.videos.destroy', $v) }}" data-confirm="Remover este vídeo?">@csrf @method('DELETE')<button class="text-danger underline">Remover</button></form>
                        @endif
                    </div>
                </div>
                @if($v->embedUrl())
                    <div class="mt-3 aspect-video w-full overflow-hidden rounded-xl bg-black">
                        <iframe src="{{ $v->embedUrl() }}" title="{{ $v->title }}" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" class="h-full w-full"></iframe>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted">Nenhum vídeo salvo neste tema ainda. Cole o link da sua videoaula favorita acima.</div>
        @endforelse
    </div>
</div>
@endsection
