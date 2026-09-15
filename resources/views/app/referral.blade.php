@extends('layouts.app')
@section('title', 'Indique e ganhe')
@section('content')
@php($brl = fn ($c) => 'R$ '.number_format($c / 100, 2, ',', '.'))
@php($tone = ['AVAILABLE' => 'success', 'REQUESTED' => 'warning', 'PAID' => 'success', 'CANCELED' => 'danger', 'REVERSED' => 'danger'])
@php($ctone = ['PENDING' => 'neutral', 'VALIDATED' => 'success', 'CANCELED' => 'danger', 'REVERSED' => 'danger'])
@php($clabel = ['PENDING' => 'Em validação', 'VALIDATED' => 'Validada', 'CANCELED' => 'Não efetivada', 'REVERSED' => 'Estornada'])
<div class="grid items-center gap-6 md:grid-cols-[1.4fr_1fr]">
    <div>
        <h1 class="text-2xl font-semibold">Indique {{ $milestone_referrals }} amigos e resgate {{ $brl($milestone_reward_cents) }}</h1>
        <p class="text-sm text-muted">Compartilhe seu link ou código. Quem se cadastrar com ele ganha <strong>{{ $brl($referred_discount_cents) }} de desconto</strong> na assinatura. A cada <strong>{{ $milestone_referrals }} indicados que assinarem e tiverem o pagamento confirmado</strong>, você resgata <strong>{{ $brl($milestone_reward_cents) }}</strong>. Cada indicação passa por {{ $validation_days }} dias de validação: se o indicado desistir ou houver estorno nesse prazo, ela não conta.</p>
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

<div class="card mt-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold">Progresso para o próximo bônus</h2>
        <span class="text-sm text-muted">{{ $progress['done'] }} de {{ $progress['per_milestone'] }} indicações validadas · faltam {{ $progress['missing'] }}</span>
    </div>
    <div class="mt-3 grid gap-2" style="grid-template-columns: repeat({{ $progress['per_milestone'] }}, minmax(0, 1fr))" role="img" aria-label="{{ $progress['done'] }} de {{ $progress['per_milestone'] }} indicações validadas">
        @for($i = 1; $i <= $progress['per_milestone']; $i++)
            <div class="h-3 rounded-full {{ $i <= $progress['done'] ? 'bg-primary' : 'bg-surface-2 border border-border' }}"></div>
        @endfor
    </div>
    @if($pending_conversions > 0)<p class="mt-2 text-xs text-muted">{{ $pending_conversions }} indicação(ões) com pagamento confirmado aguardando o prazo de validação.</p>@endif
</div>

<div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="stat"><p class="text-xs uppercase text-muted">Cliques</p><p class="mt-1 text-2xl font-semibold">{{ $clicks }}</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Cadastros</p><p class="mt-1 text-2xl font-semibold">{{ $signups }}</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Assinaturas ativas</p><p class="mt-1 text-2xl font-semibold">{{ $subscriptions }}</p><p class="text-xs text-muted">Conversão {{ $conversion }}%</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Indicações validadas</p><p class="mt-1 text-2xl font-semibold">{{ $validated_conversions }}</p><p class="text-xs text-muted">{{ $pending_conversions }} em validação</p></div>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-3">
    <div class="stat"><p class="text-xs uppercase text-muted">Bônus disponível</p><p class="mt-1 text-2xl font-semibold text-success">{{ $brl($available_cents) }}</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Saque solicitado</p><p class="mt-1 text-2xl font-semibold">{{ $brl($requested_cents) }}</p></div>
    <div class="stat"><p class="text-xs uppercase text-muted">Total recebido</p><p class="mt-1 text-2xl font-semibold">{{ $brl($paid_cents) }}</p></div>
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
    <h2 class="font-semibold">Suas indicações</h2>
    <ul class="mt-3 divide-y divide-border text-sm">
        @forelse($conversions as $c)
            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                <span>{{ $c->created_at->format('d/m/Y') }} · {{ $c->referredUser->name }} @if($c->status === 'PENDING' && $c->available_at)<span class="ml-2 text-xs text-muted">valida em {{ $c->available_at->format('d/m/Y') }}</span>@endif</span>
                <span class="flex items-center gap-2">@if($c->blocked_reason)<span class="text-xs text-muted">{{ $c->blocked_reason }}</span>@endif<span class="badge-{{ $ctone[$c->status] }}">{{ $clabel[$c->status] }}</span></span>
            </li>
        @empty
            <li class="py-2 text-muted">Nenhum indicado com pagamento confirmado ainda.</li>
        @endforelse
    </ul>
</div>

<div class="card mt-4">
    <h2 class="font-semibold">Histórico de bônus</h2>
    <ul class="mt-3 divide-y divide-border text-sm">
        @forelse($history as $c)
            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                <span>{{ $c->created_at->format('d/m/Y') }} · {{ $brl($c->amount_cents) }} <span class="ml-2 text-xs text-muted">{{ $c->conversions_count }} indicações</span></span>
                <span class="flex items-center gap-2">@if($c->blocked_reason)<span class="text-xs text-muted">{{ $c->blocked_reason }}</span>@endif<span class="badge-{{ $tone[$c->status] }}">{{ $c->status }}</span></span>
            </li>
        @empty
            <li class="py-2 text-muted">Nenhum bônus ainda.</li>
        @endforelse
    </ul>
</div>
<div class="notice-neutral mt-4">Indicações são verificadas contra autoindicação, contas duplicadas e estornos. Indicações suspeitas podem ser bloqueadas.</div>
@endsection
