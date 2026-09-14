@extends('layouts.app')
@section('title', 'Simulados de Treinamento')
@section('content')
<h1 class="text-2xl font-semibold">Simulados de Treinamento</h1>
<p class="text-sm text-muted">Seção separada das provas oficiais. Nada aqui é apresentado como questão do ENEM.</p>
<div class="notice-warning mt-4">Os simulados de treinamento ainda não foram disponibilizados. Enquanto isso, use as provas oficiais em <a href="{{ route('exams.index') }}" class="underline">Provas anteriores</a>.</div>
@endsection
