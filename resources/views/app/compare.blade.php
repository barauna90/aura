@extends('layouts.app')
@section('title', 'Comparação')
@section('content')
@php($E = \App\Support\Enem::class)
<div class="flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="text-2xl font-semibold">Comparação</h1><p class="text-sm text-muted">{{ $a->exam->title }} ({{ $a->finished_at?->format('d/m/Y') }}) → {{ $b->exam->title }} ({{ $b->finished_at?->format('d/m/Y') }})</p></div>
    <a href="{{ route('performance') }}" class="btn-secondary">Voltar</a>
</div>
<div class="card mt-5">
    <table class="table">
        <thead><tr><th>Área</th><th>Anterior</th><th>Atual</th><th>Evolução</th></tr></thead>
        <tbody>
            @foreach($rows as $r)
                <tr>
                    <td class="font-medium">{{ $E::AREA_SHORT[$r['area']] ?? $r['area'] }} {{ $r['area'] === 'REDACAO' ? '(nota simulada)' : '(acertos)' }}</td>
                    <td>{{ $r['before'] ?? '—' }}</td><td>{{ $r['after'] ?? '—' }}</td>
                    <td class="font-semibold {{ $r['delta'] === null ? '' : ($r['delta'] > 0 ? 'text-success' : ($r['delta'] < 0 ? 'text-danger' : '')) }}">{{ $r['delta'] === null ? '—' : ($r['delta'] > 0 ? '+'.$r['delta'] : $r['delta']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
