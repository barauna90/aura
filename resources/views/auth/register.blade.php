@extends('layouts.guest')
@section('title', 'Criar conta')
@section('content')
<h1 class="text-xl font-semibold">Criar conta gratuita</h1>
<p class="mt-1 text-sm text-muted">Provas oficiais, cartão-resposta e correção. Sem cartão de crédito.</p>
<form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
    @csrf
    <div><label class="label" for="name">Nome completo</label><input class="input" id="name" name="name" value="{{ old('name') }}" required minlength="2" autocomplete="name"></div>
    <div><label class="label" for="email">E-mail</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
    <div><label class="label" for="password">Senha</label><input class="input" id="password" name="password" type="password" required minlength="8" autocomplete="new-password"><p class="mt-1 text-xs text-muted">Mínimo de 8 caracteres.</p></div>
    <div><label class="label" for="password_confirmation">Confirmar senha</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
    <div><label class="label" for="referral_code">Código de indicação (opcional)</label><input class="input uppercase" id="referral_code" name="referral_code" value="{{ old('referral_code', $referralCode) }}"></div>
    <label class="flex items-start gap-2 text-xs text-muted"><input type="checkbox" name="accept_terms" value="1" required class="mt-0.5"> Li e aceito os <a href="{{ route('landing') }}#termos" class="underline">termos de uso</a> e a <a href="{{ route('landing') }}#privacidade" class="underline">política de privacidade</a> (LGPD).</label>
    <button class="btn-primary w-full">Criar conta</button>
</form>
<p class="mt-4 text-center text-sm text-muted">Já tem conta? <a href="{{ route('login') }}" class="text-[#c4b5fd] hover:underline">Entrar</a></p>
@endsection
