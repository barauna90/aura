@extends('layouts.app')
@section('title', 'Personalizar estudo')
@section('content')
<div class="mx-auto max-w-xl">
    <h1 class="text-2xl font-semibold">Vamos personalizar seu estudo</h1>
    <p class="text-sm text-muted">Quatro perguntas rápidas. Depois, faça seu diagnóstico com questões oficiais.</p>
    <form method="POST" action="{{ route('onboarding') }}" class="card mt-5 space-y-4">
        @csrf
        <div><label class="label" for="goal">Qual seu objetivo?</label><select class="input" id="goal" name="goal">@foreach(['Medicina', 'Direito', 'Engenharia', 'Licenciaturas', 'Outro'] as $g)<option @selected(old('goal', auth()->user()->goal) === $g)>{{ $g }}</option>@endforeach</select></div>
        <div><label class="label" for="target_exam_date">Quando pretende fazer o ENEM?</label><input class="input" id="target_exam_date" name="target_exam_date" type="date" value="{{ old('target_exam_date', auth()->user()->target_exam_date?->toDateString()) }}"></div>
        <div><label class="label" for="weekly_hours">Quantas horas consegue estudar por semana?</label><input class="input" id="weekly_hours" name="weekly_hours" type="number" min="1" max="80" value="{{ old('weekly_hours', auth()->user()->weekly_hours ?? 10) }}" required></div>
        <div><label class="label" for="main_difficulty">Qual sua maior dificuldade?</label><input class="input" id="main_difficulty" name="main_difficulty" value="{{ old('main_difficulty', auth()->user()->main_difficulty) }}" placeholder="Ex.: matemática, tempo de prova, redação…"></div>
        <button class="btn-primary w-full py-3 text-base">Salvar e fazer diagnóstico</button>
    </form>
</div>
@endsection
