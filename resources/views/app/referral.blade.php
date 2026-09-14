@extends('layouts.app')
@section('title', 'Indique e ganhe')
@section('content')
@php($brl = fn ($c) => 'R$ '.number_format($c / 100, 2, ',', '.'))
@php($tone = ['PENDING' => 'neutral', 'APPROVED' => 'primary', 'AVAILABLE' => 'success', 'REQUESTED' => 'warning', 'PAID' => 'success', 'CANCELED' => 'danger', 'REVERSED' => 'danger'])
<div class="grid items-center gap-6 md:grid-cols-[1.4fr_1fr]">
    <div>
        <h1 class="text-2xl font-semibold">Indique amigos e ganhe comissões</h1>
        <p class="text-sm text-muted">Compartilhe seu link. Quando um indicado assina e o pagamento é confirmado, você recebe comissão após o período de validação.</p>
        <div class="card mt-4">
            <p class="text-sm text-muted">Seu link exclusivo</p>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <code class="rounded-lg bg-surface-2 px-3 py-2 text-sm">{{ $link }}</code>
                <button type="button" class="btn-secondary px-3 py-1.5" data-copy="{{ $link }}">Copiar</button>
            </div>
            <p class="mt-2 text-xs text-muted">Código: {{ $code }}</p>
        </div>
    </div>
    <img src="{{ asset('images/referral-friends.png') }}" alt="" class="mx-auto hidden max-h-64 md:block" loading="lazy">
</div>

<div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="stat"><p class="text-xs uppercase text-muted">Cliques</p><p class="mt-1 text-2xl font-semibold">{{ $clicks }}</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Cadastros</p><p class="mt-1 text-2xl font-semibold">{{ $signups }}</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Assinaturas</p><p class="mt-1 text-2xl font-semibold">{{ $subscriptions }}</p><p class="text-xs text-muted">Conversão {{ $conversion }}%</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Total recebido</p><p class="mt-1 text-2xl font-semibold">{{ $brl($paid_cents) }}</p></div>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-3">
    <div class="stat"><p class="text-xs uppercase text-muted">Comissão pendente</p><p class="mt-1 text-2xl font-semibold">{{ $brl($pending_cents) }}</p><p class="text-xs text-muted">Em validação</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Comissão disponível</p><p class="mt-1 text-2xl font-semibold text-success">{{ $brl($available_cents) }}</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Saque solicitado</p><p class="mt-1 text-2xl font-semibold">{{ $brl($requested_cents) }}</p></div>
</div>

<form method="POST" action="{{ route('referral.withdraw') }}" class="card mt-4">
    @csrf
    <h2 class="font-semibold">Solicitar saque</h2>
    <p class="text-sm text-muted">Valor mínimo: {{ $brl($min_withdrawal_cents) }} · via PIX</p>
    <div class="mt-3 flex flex-wrap items-end gap-2">
        <div class="min-w-64"><label class="label" for="pix_key">Chave PIX</label><input class="input" id="pix_key" name="pix_key" required></div>
        <button class="btn-primary" @disabled($available_cents < $min_withdrawal_cents)>Solicitar {{ $brl($available_cents) }}</button>
    </div>
</form>

<div class="card mt-4">
    <h2 class="font-semibold">Histórico de comissões</h2>
    <ul class="mt-3 divide-y divide-border text-sm">
        @forelse($history as $c)
            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                <span>{{ $c->created_at->format('d/m/Y') }} · {{ $brl($c->amount_cents) }} @if($c->status === 'PENDING' && $c->available_at)<span class="ml-2 text-xs text-muted">libera em {{ $c->available_at->format('d/m/Y') }}</span>@endif</span>
                <span class="flex items-center gap-2">@if($c->blocked_reason)<span class="text-xs text-muted">{{ $c->blocked_reason }}</span>@endif<span class="badge-{{ $tone[$c->status] }}">{{ $c->status }}</span></span>
            </li>
        @empty
            <li class="py-2 text-muted">Nenhuma comissão ainda.</li>
        @endforelse
    </ul>
</div>
<div class="notice-neutral mt-4">Indicações são verificadas contra autoindicação, contas duplicadas e estornos. Comissões suspeitas podem ser bloqueadas.</div>
@endsection
