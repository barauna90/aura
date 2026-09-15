@extends('layouts.admin')
@section('title', 'Administração')
@section('admin')
@php($brl = fn ($c) => 'R$ '.number_format($c / 100, 2, ',', '.'))
<h1 class="text-2xl font-semibold">Dashboard administrativo</h1>
@unless($asaasReady)<div class="notice-warning mt-4">A chave de API do Asaas ainda não foi configurada. <a href="{{ route('admin.settings.index') }}" class="underline">Configurar agora</a> para habilitar cobranças e liberação automática de acesso.</div>@endunless
@unless(auth()->user()->hasRole('ADMIN'))<div class="notice-neutral mt-4">Perfil revisor: acesso ao fluxo de auditoria de conteúdo.</div>@else
<div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach([
        ['Usuários', $users, null], ['Assinantes ativos', $subscribers, "{$trials} em trial · {$pending} aguardando pagamento"],
        ['MRR', $brl($mrr_cents), null], ['Receita no mês', $brl($revenue_month_cents), null],
        ['Cancelamentos no mês', $cancellations_month, "Churn {$churn}%"], ['Cupons ativos', $coupons, null],
        ['Afiliados', $affiliates, null], ['Sessões (30 dias)', $sessions_30d, null],
        ['Questões cadastradas', $questions, null], ['Uso de IA no mês', $ai->calls, number_format($ai->tokens, 0, ',', '.').' tokens · '.$brl($ai->cost)],
    ] as [$l, $v, $h])
        <div class="stat"><p class="text-xs uppercase tracking-wide text-muted">{{ $l }}</p><p class="mt-1 text-2xl font-semibold">{{ $v }}</p>@if($h)<p class="text-xs text-muted">{{ $h }}</p>@endif</div>
    @endforeach
</div>
<div class="mt-4 grid gap-4 md:grid-cols-3">
    <div class="card"><h2 class="font-semibold">Provas por status</h2><ul class="mt-2 text-sm">@forelse($exams as $s => $n)<li class="flex justify-between py-1"><span>{{ $s }}</span><span>{{ $n }}</span></li>@empty<li class="text-muted">Nenhuma prova.</li>@endforelse</ul></div>
    <div class="card"><h2 class="font-semibold">Redações por status</h2><ul class="mt-2 text-sm">@forelse($essays as $s => $n)<li class="flex justify-between py-1"><span>{{ $s }}</span><span>{{ $n }}</span></li>@empty<li class="text-muted">Nenhuma redação.</li>@endforelse</ul></div>
    <div class="card"><h2 class="font-semibold">Bônus de indicação</h2><ul class="mt-2 text-sm">@forelse($commissions as $c)<li class="flex justify-between py-1"><span>{{ $c->status }}</span><span>{{ $c->n }} · {{ $brl($c->total) }}</span></li>@empty<li class="text-muted">Nenhum bônus.</li>@endforelse</ul></div>
</div>
<div class="card mt-4">
    <h2 class="font-semibold">Alertas do sistema</h2>
    @if($alerts->isEmpty())<p class="mt-2 text-sm text-muted">Nenhum alerta aberto.</p>@endif
    <ul class="mt-2 divide-y divide-border text-sm">
        @foreach($alerts as $a)<li class="flex flex-wrap items-center justify-between gap-2 py-2"><span><span class="badge-{{ $a->level === 'ERROR' ? 'danger' : ($a->level === 'WARN' ? 'warning' : 'neutral') }}">{{ $a->level }}</span> <span class="ml-2 text-muted">{{ $a->source }}</span> {{ $a->message }}</span><span class="text-xs text-muted">{{ $a->created_at->format('d/m/Y H:i') }}</span></li>@endforeach
    </ul>
</div>
@endunless
@endsection
