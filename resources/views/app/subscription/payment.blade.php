@extends('layouts.app')
@section('title', 'Pagamento')
@section('content')
@php($brl = fn ($c) => 'R$ '.number_format($c / 100, 2, ',', '.'))
<h1 class="text-2xl font-semibold">Conclua o pagamento</h1>
<p class="text-sm text-muted">{{ $payment->subscription?->plan->name }} · {{ $brl($payment->amount_cents - $payment->discount_cents) }}@if($payment->discount_cents > 0) <span class="text-success">(desconto de {{ $brl($payment->discount_cents) }})</span>@endif @if($payment->due_date)· vencimento {{ $payment->due_date->format('d/m/Y') }}@endif</p>

@if($payment->status === 'CONFIRMED')
    <div class="notice-success mt-5"><strong>Pagamento confirmado.</strong> Seu acesso premium está liberado. <a href="{{ route('dashboard') }}" class="underline">Ir para o início</a></div>
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
@else
    <div class="notice-danger mt-5">Esta cobrança está com status <strong>{{ $payment->status }}</strong>. <a href="{{ route('subscription.index') }}" class="underline">Voltar</a></div>
@endif
@endsection
