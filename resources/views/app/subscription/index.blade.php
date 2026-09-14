@extends('layouts.app')
@section('title', 'Minha assinatura')
@section('content')
@php($brl = fn ($c) => 'R$ '.number_format($c / 100, 2, ',', '.'))
@php($tone = ['ACTIVE' => 'success', 'TRIALING' => 'primary', 'PENDING' => 'warning', 'PAST_DUE' => 'warning', 'CANCELED' => 'neutral', 'EXPIRED' => 'neutral', 'REFUNDED' => 'danger', 'SUSPENDED' => 'danger'])
@php($sub = $subscription)
<h1 class="text-2xl font-semibold">Minha assinatura</h1>
<p class="text-sm text-muted">Sem taxas escondidas. Cancele quando quiser; o acesso continua até o fim do período pago.</p>

<div class="card mt-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-muted">Acesso atual</p>
            <p class="text-lg font-semibold">{{ $access['plan_name'] }} <span class="badge-{{ $access['tier'] === 'PREMIUM' ? 'success' : 'neutral' }}">{{ $access['tier'] === 'PREMIUM' ? 'Premium' : 'Gratuito' }}</span></p>
            <p class="text-xs text-muted">{{ ['SCHOLARSHIP' => 'Bolsa de estudos', 'SUBSCRIPTION' => 'Assinatura', 'FREE_PLAN' => 'Plano gratuito'][$access['source']] }}@if($access['valid_until']) · válido até {{ $access['valid_until']->format('d/m/Y') }}@endif</p>
        </div>
        @if($sub)
            <div class="text-right text-sm">
                <span class="badge-{{ $tone[$sub->status] ?? 'neutral' }}">{{ $sub->status }}</span>
                <p class="mt-1 text-xs text-muted">{{ $sub->plan->name }} · período até {{ $sub->current_period_end->format('d/m/Y') }}</p>
                @if($sub->status === 'PENDING')<p class="mt-1 text-xs text-warning">Aguardando confirmação do pagamento.</p>@endif
                @if(in_array($sub->status, ['ACTIVE', 'TRIALING', 'PAST_DUE', 'PENDING']) && !$sub->cancel_at_period_end)
                    <form method="POST" action="{{ route('subscription.cancel') }}" data-confirm="Cancelar a assinatura? Você mantém o acesso até o fim do período já pago." class="mt-2">@csrf<button class="btn-secondary px-3 py-1 text-xs">Cancelar assinatura</button></form>
                @endif
                @if($sub->cancel_at_period_end)<p class="mt-1 text-xs text-warning">Cancelamento agendado para o fim do período.</p>@endif
            </div>
        @endif
    </div>
    <p class="mt-3 text-xs text-muted">Limites: provas completas/mês {{ $access['limits']['fullExamsPerMonth'] === -1 ? 'ilimitadas' : $access['limits']['fullExamsPerMonth'] }} · correções de redação/mês {{ $access['limits']['essaysPerMonth'] === -1 ? 'ilimitadas' : $access['limits']['essaysPerMonth'] }}</p>
</div>

@if(!$sub || !in_array($sub->status, ['ACTIVE', 'TRIALING']))
    <form method="POST" action="{{ route('subscription.checkout') }}" class="card mt-4 space-y-4">
        @csrf
        <h2 class="font-semibold">Assinar</h2>
        @unless($gatewayReady)<div class="notice-warning">O pagamento ainda não está configurado nesta plataforma. Fale com o suporte.</div>@endunless
        <div class="grid gap-3 md:grid-cols-3">
            @foreach($plans as $p)
                <label class="cursor-pointer rounded-2xl border border-border p-4 has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                    <input type="radio" name="plan" value="{{ $p->code }}" class="sr-only" @checked($loop->first) required>
                    <p class="font-medium">{{ $p->name }}</p>
                    <p class="text-2xl font-semibold">{{ $brl($p->price_cents) }}<span class="text-xs font-normal text-muted">/mês</span></p>
                    @if($p->trial_days > 0)<p class="text-xs text-success">{{ $p->trial_days }} dias grátis</p>@endif
                    <ul class="mt-2 text-xs text-muted">@foreach($p->benefits as $b)<li>• {{ $b }}</li>@endforeach</ul>
                </label>
            @endforeach
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><label class="label" for="billing_type">Forma de pagamento</label><select class="input" id="billing_type" name="billing_type"><option value="PIX">PIX</option><option value="CREDIT_CARD">Cartão de crédito (recorrente)</option><option value="BOLETO">Boleto</option></select></div>
            <div><label class="label" for="cpf">CPF</label><input class="input" id="cpf" name="cpf" value="{{ old('cpf') }}" required inputmode="numeric" placeholder="000.000.000-00"><p class="mt-1 text-xs text-muted">Exigido pelo Asaas para emitir a cobrança. Guardamos apenas um hash.</p></div>
            <div><label class="label" for="phone">Celular (opcional)</label><input class="input" id="phone" name="phone" value="{{ old('phone', auth()->user()->phone) }}" inputmode="tel"></div>
            <div><label class="label" for="coupon">Cupom (opcional)</label><div class="flex gap-2"><input class="input uppercase" id="coupon" name="coupon" value="{{ old('coupon') }}"><button type="button" id="coupon-check" data-url="{{ route('subscription.coupon') }}" class="btn-secondary px-3">Aplicar</button></div></div>
        </div>
        @if($referralDiscount > 0)<div class="notice-success">Você entrou por indicação: <strong>{{ $brl($referralDiscount) }} de desconto</strong> serão aplicados na primeira cobrança.</div>@endif
        <div id="coupon-result" hidden class="notice-neutral"></div>
        <button class="btn-primary px-6 py-3 text-base" @disabled(!$gatewayReady)>Assinar e gerar cobrança</button>
        <p class="text-xs text-muted">O acesso premium é liberado automaticamente assim que o pagamento for confirmado pelo Asaas.</p>
    </form>
@endif

<div class="card mt-4">
    <h2 class="font-semibold">Pagamentos</h2>
    <ul class="mt-2 divide-y divide-border text-sm">
        @forelse($payments as $p)
            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                <span>{{ $p->created_at->format('d/m/Y') }} · {{ $p->billing_type }}@if($p->due_date) · vence {{ $p->due_date->format('d/m/Y') }}@endif</span>
                <span class="flex items-center gap-2">{{ $brl($p->amount_cents - $p->discount_cents) }} <span class="badge-{{ $p->status === 'CONFIRMED' ? 'success' : ($p->status === 'PENDING' ? 'warning' : 'danger') }}">{{ $p->status }}</span>
                    @if($p->status === 'PENDING')<a href="{{ route('subscription.payment', $p) }}" class="text-[#c4b5fd] underline">Pagar</a>@endif</span>
            </li>
        @empty
            <li class="py-2 text-muted">Nenhum pagamento.</li>
        @endforelse
    </ul>
</div>
@endsection
