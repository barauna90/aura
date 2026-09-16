@extends('layouts.app')
@section('title', 'Minha assinatura')
@section('content')
@php($brl = fn ($c) => 'R$ '.number_format($c / 100, 2, ',', '.'))
@php($tone = ['ACTIVE' => 'success', 'TRIALING' => 'primary', 'PENDING' => 'warning', 'PAST_DUE' => 'warning', 'CANCELED' => 'neutral', 'EXPIRED' => 'neutral', 'REFUNDED' => 'danger', 'SUSPENDED' => 'danger'])
@php($sub = $subscription)
<h1 class="text-2xl font-semibold">{{ $access['tier'] === 'PREMIUM' ? 'Minha assinatura' : 'Escolha seu plano para começar' }}</h1>
<p class="text-sm text-muted">{{ $access['tier'] === 'PREMIUM' ? 'Sem taxas escondidas. Cancele quando quiser; o acesso continua até o fim do período pago.' : 'O acesso a provas, simulados, redação e guia é liberado automaticamente assim que o pagamento for confirmado. Sem taxas escondidas; cancele quando quiser.' }}</p>

<div class="card mt-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-muted">Acesso atual</p>
            <p class="text-lg font-semibold">{{ $access['plan_name'] }} <span class="badge-{{ $access['tier'] === 'PREMIUM' ? 'success' : 'warning' }}">{{ $access['tier'] === 'PREMIUM' ? 'Ativa' : 'Sem acesso' }}</span></p>
            <p class="text-xs text-muted">{{ ['STAFF' => 'Acesso integral da equipe', 'SCHOLARSHIP' => 'Bolsa de estudos', 'SUBSCRIPTION' => 'Assinatura', 'NO_SUBSCRIPTION' => 'Assine um plano para liberar as provas, simulados e correções'][$access['source']] }}@if($access['valid_until']) · válido até {{ $access['valid_until']->format('d/m/Y') }}@endif</p>
        </div>
        @if($sub)
            <div class="text-right text-sm">
                <span class="badge-{{ $tone[$sub->status] ?? 'neutral' }}">{{ $sub->status }}</span>
                <p class="mt-1 text-xs text-muted">{{ $sub->plan->name }} · período até {{ $sub->current_period_end->format('d/m/Y') }}</p>
                @if($sub->status === 'PENDING')
                    <p class="mt-1 text-xs text-warning">Aguardando confirmação do pagamento.</p>
                    @php($pendingPayment = $payments->first(fn ($p) => $p->subscription_id === $sub->id && $p->status === 'PENDING'))
                    @if($pendingPayment)<a href="{{ route('subscription.payment', $pendingPayment) }}" class="btn-primary mt-2 px-3 py-1 text-xs">Pagar ou trocar de plano</a>@endif
                @endif
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
    <form method="POST" action="{{ route('subscription.checkout') }}" class="card mt-4 space-y-4" id="checkout-form" data-referral-discount="{{ $referralDiscount }}">
        @csrf
        <h2 class="font-semibold">Escolha seu plano</h2>
        <p class="text-sm text-muted">Você pode mudar de ideia: o valor abaixo atualiza na hora e, mesmo depois de gerar a cobrança, dá para trocar de plano antes de pagar.</p>
        @unless($gatewayReady)<div class="notice-warning">O pagamento ainda não está configurado nesta plataforma. Fale com o suporte.</div>@endunless
        <div class="grid gap-3 md:grid-cols-3">
            @foreach($plans as $p)
                <label class="relative cursor-pointer rounded-2xl border {{ $p->is_featured ? 'border-primary/60' : 'border-border' }} p-4 has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                    <input type="radio" name="plan" value="{{ $p->code }}" class="sr-only" data-price="{{ $p->price_cents }}" data-name="{{ $p->name }}" @checked($p->is_featured || ($loop->first && !$plans->contains('is_featured', true))) required>
                    @if($p->badge)<span class="absolute -top-2.5 left-3 rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold uppercase text-white">{{ $p->badge }}</span>@endif
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
        <div id="order-summary" class="rounded-2xl border border-primary/40 bg-primary/5 p-4 text-sm" aria-live="polite">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">Resumo do pedido</p>
            <dl class="mt-2 space-y-1">
                <div class="flex justify-between"><dt>Plano <span data-summary="name" class="font-medium"></span></dt><dd data-summary="price"></dd></div>
                <div class="flex justify-between text-success" data-summary-row="referral" hidden><dt>Desconto de indicação</dt><dd data-summary="referral"></dd></div>
                <div class="flex justify-between text-success" data-summary-row="coupon" hidden><dt>Cupom</dt><dd data-summary="coupon"></dd></div>
                <div class="mt-2 flex justify-between border-t border-border pt-2 text-base font-semibold"><dt>Total da primeira cobrança</dt><dd data-summary="total"></dd></div>
            </dl>
            <p class="mt-2 text-xs text-muted">Depois, <span data-summary="recurring"></span> por mês. Cancele quando quiser.</p>
        </div>
        <button class="btn-primary px-6 py-3 text-base" @disabled(!$gatewayReady)>Assinar <span data-summary="button"></span> e gerar cobrança</button>
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
                    @if($p->status === 'PENDING')<a href="{{ route('subscription.payment', $p) }}" class="text-primary underline">Pagar</a>@endif</span>
            </li>
        @empty
            <li class="py-2 text-muted">Nenhum pagamento.</li>
        @endforelse
    </ul>
</div>
@endsection
