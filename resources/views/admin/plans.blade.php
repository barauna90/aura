@extends('layouts.admin')
@section('title', 'Planos')
@section('admin')
@php($brl = fn ($c) => 'R$ '.number_format($c / 100, 2, ',', '.'))
<h1 class="text-2xl font-semibold">Planos</h1>
<p class="text-sm text-muted">Preço, benefícios, limites, selo e destaque são configuráveis aqui — nunca no código. Não existe plano gratuito: sem assinatura ativa (ou bolsa) o aluno só acessa provas marcadas como amostra.</p>
<div class="mt-5 grid gap-4 md:grid-cols-3">
    @foreach($plans as $p)
        <div class="card">
            <div class="flex justify-between"><h2 class="font-semibold">{{ $p->name }} @if($p->badge)<span class="badge-warning">{{ $p->badge }}</span>@endif @if($p->is_featured)<span class="badge-primary">Destaque</span>@endif</h2><span class="text-xs text-muted">{{ $p->code }}</span></div>
            <p class="mt-1 text-2xl font-semibold">{{ $brl($p->price_cents) }}<span class="text-xs font-normal text-muted">/{{ $p->interval_months }} mês</span></p>
            <p class="text-xs text-muted">{{ $p->is_active ? 'ativo' : 'inativo' }}@if($p->trial_days > 0) · trial {{ $p->trial_days }} dias@endif</p>
            <ul class="mt-2 text-xs text-muted">@foreach($p->benefits as $b)<li>• {{ $b }}</li>@endforeach</ul>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-surface-2 p-2 text-xs">{{ json_encode($p->limits, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endforeach
</div>
<form method="POST" action="{{ route('admin.plans.store') }}" class="card mt-5 space-y-3">
    @csrf
    <h2 class="font-semibold">Criar ou editar plano (pelo código)</h2>
    <div class="grid gap-3 sm:grid-cols-3">
        <div><label class="label">Código</label><input class="input uppercase" name="code" required placeholder="ESTUDANTE"></div>
        <div><label class="label">Nome</label><input class="input" name="name" required></div>
        <div><label class="label">Preço (centavos)</label><input class="input" name="price_cents" type="number" min="0" required></div>
        <div><label class="label">Selo (ex.: PROMOÇÃO)</label><input class="input" name="badge" maxlength="30"></div>
        <div><label class="label">Dias de teste (0 = sem teste)</label><input class="input" name="trial_days" type="number" min="0" value="0"></div>
        <div><label class="label">Intervalo (meses)</label><input class="input" name="interval_months" type="number" min="1" value="1"></div>
        <div><label class="label">Ordem</label><input class="input" name="sort_order" type="number" min="0" value="9"></div>
    </div>
    <div><label class="label">Descrição</label><input class="input" name="description"></div>
    <div><label class="label">Benefícios (um por linha)</label><textarea class="input" name="benefits" rows="4"></textarea></div>
    <div><label class="label">Limites (JSON) — -1 = ilimitado</label><textarea class="input font-mono" name="limits" rows="4">{"fullExamsPerMonth": -1, "essaysPerMonth": 8, "studyPlan": true, "tutor": true, "errorNotebook": true, "intensive": false, "priorityEssay": false}</textarea></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Ativo</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1"> Plano em destaque na vitrine</label>
    <button class="btn-primary">Salvar plano</button>
</form>
@endsection
