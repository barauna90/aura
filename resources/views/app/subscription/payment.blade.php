@extends('layouts.app')
@section('title', 'Pagamento')
@section('content')
@php($brl = fn ($c) => 'R$ '.number_format($c / 100, 2, ',', '.'))
<h1 class="text-2xl font-semibold">Conclua o pagamento</h1>
<p class="text-sm text-muted">{{ $payment->subscription?->plan->name }} · {{ $brl($payment->amount_cents - $payment->discount_cents) }}@if($payment->discount_cents > 0) <span class="text-success">(desconto de {{ $brl($payment->discount_cents) }})</span>@endif @if($payment->due_date)· vencimento {{ $payment->due_date->format('d/m/Y') }}@endif</p>

@if(session('status'))<div class="notice-success mt-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice-danger mt-4">{{ $errors->first() }}</div>@endif

@if($payment->status === 'CONFIRMED')
    <div class="notice-success mt-5"><strong>Pagamento confirmado.</strong> Seu acesso está liberado. <a href="{{ auth()->user()->onboarding_done ? route('dashboard') : route('onboarding') }}" class="btn-primary ml-2 px-4 py-1.5">{{ auth()->user()->onboarding_done ? 'Ir para o início' : 'Começar: montar meu plano de estudos' }}</a></div>
@elseif($payment->status === 'PENDING')
    <div id="payment-waiting" class="card mt-5 grid gap-6 md:grid-cols-2">
        <div>
            @if($payment->billing_type === 'PIX')
                <h2 class="font-semibold">Pague com PIX</h2>
                @if($pixImage)<img src="data:image/png;base64,{{ $pixImage }}" alt="QR Code PIX" class="mt-3 h-56 w-56 rounded-xl bg-white p-2">@endif
                @if($payment->pix_payload)
                    <p class="mt-3 text-xs text-muted">PIX copia-e-cola:</p>
                    <code class="mt-1 block break-all rounded-lg bg-surface-2 p-3 text-xs">{{ $payment->pix_payload }}</code>
                    <button type="button" class="btn-secondary mt-2 px-3 py-1.5" data-copy="{{ $payment->pix_payload }}">Copiar código</button>
                @endif
            @elseif($payment->billing_type === 'BOLETO')
                <h2 class="font-semibold">Pague o boleto</h2>
                @if($payment->bank_slip_url)<a href="{{ $payment->bank_slip_url }}" target="_blank" rel="noreferrer" class="btn-primary mt-3">Abrir boleto</a>@endif
                <p class="mt-2 text-xs text-muted">A compensação do boleto pode levar até 3 dias úteis.</p>
            @else
                <h2 class="font-semibold">Pague com cartão</h2>
                <p class="mt-2 text-sm text-muted">Os dados do cartão são informados na página segura do Asaas — nunca passam pela nossa plataforma.</p>
            @endif
            @if($payment->invoice_url)<a href="{{ $payment->invoice_url }}" target="_blank" rel="noreferrer" class="btn-secondary mt-4">Abrir fatura no Asaas</a>@endif
        </div>
        <div class="notice-primary self-start">
            <p><strong>Liberação automática.</strong> Assim que o Asaas confirmar o pagamento, seu acesso premium é ativado e esta página é atualizada sozinha.</p>
            <p class="mt-2 text-xs text-muted">Status atual: aguardando pagamento. Você também pode acompanhar em <a href="{{ route('subscription.index') }}" class="underline">Minha assinatura</a>.</p>
        </div>
    </div>
    @if($otherPlans->isNotEmpty())
        <div class="card mt-4">
            <h2 class="font-semibold">Quer trocar de plano?</h2>
            <p class="text-sm text-muted">Você ainda não pagou, então pode mudar. Esta cobrança é cancelada e uma nova é gerada com o valor do plano escolhido{{ $referralDiscount > 0 ? ' (o desconto de indicação de '.$brl($referralDiscount).' continua valendo)' : '' }}.</p>
            <div class="mt-3 grid gap-3 md:grid-cols-{{ min(3, $otherPlans->count()) }}">
                @foreach($otherPlans as $p)
                    <form method="POST" action="{{ route('subscription.change_plan') }}" class="flex flex-col rounded-2xl border border-border p-4" data-confirm="Trocar para o {{ $p->name }}? A cobrança atual será cancelada e uma nova, de {{ $brl(max(0, $p->price_cents - $referralDiscount)) }}, será gerada.">
                        @csrf
                        <input type="hidden" name="plan" value="{{ $p->code }}">
                        <p class="font-medium">{{ $p->name }} @if($p->badge)<span class="badge-warning">{{ $p->badge }}</span>@endif</p>
                        <p class="text-2xl font-semibold">{{ $brl($p->price_cents) }}<span class="text-xs font-normal text-muted">/mês</span></p>
                        @if($referralDiscount > 0)<p class="text-xs text-success">Primeira cobrança: {{ $brl(max(0, $p->price_cents - $referralDiscount)) }}</p>@endif
                        <ul class="mt-2 flex-1 text-xs text-muted">@foreach($p->benefits as $b)<li>• {{ $b }}</li>@endforeach</ul>
                        <button class="btn-secondary mt-3 px-3 py-1.5 text-xs">Trocar para {{ $p->name }}</button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif
@else
    <div class="notice-danger mt-5">Esta cobrança está com status <strong>{{ $payment->status }}</strong>. <a href="{{ route('subscription.index') }}" class="underline">Voltar</a></div>
@endif
@endsection
