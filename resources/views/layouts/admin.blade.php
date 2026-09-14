@extends('layouts.app')
@section('content')
@php($items = [
    ['admin.dashboard', 'Visão geral', 'ADMIN'],
    ['admin.content.index', 'Conteúdo oficial', 'REVIEWER'],
    ['admin.plans.index', 'Planos', 'ADMIN'],
    ['admin.coupons.index', 'Cupons', 'ADMIN'],
    ['admin.referrals.index', 'Indicações', 'ADMIN'],
    ['admin.scholarships.index', 'Bolsas', 'ADMIN'],
    ['admin.users.index', 'Usuários', 'ADMIN'],
    ['admin.settings.index', 'Configurações', 'ADMIN'],
])
<nav aria-label="Menu administrativo" class="mb-6 flex flex-wrap gap-2 border-b border-border pb-3">
    @foreach($items as [$route, $label, $role])
        @continue(!auth()->user()->hasRole($role) && $route !== 'admin.dashboard')
        <a href="{{ route($route) }}" class="rounded-xl px-3 py-1.5 text-sm {{ request()->routeIs(str_replace('.index', '', $route).'*') ? 'bg-primary text-white' : 'border border-border hover:bg-surface' }}">{{ $label }}</a>
    @endforeach
</nav>
@yield('admin')
@endsection
