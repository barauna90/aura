@extends('layouts.guest')
@section('title', 'Entrar')
@section('content')
<h1 class="text-xl font-semibold">Entrar</h1>
<p class="mt-1 text-sm text-muted">Bem-vindo de volta. Bora continuar evoluindo?</p>
<form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
    @csrf
    <div><label class="label" for="email">E-mail</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus></div>
    <div><label class="label" for="password">Senha</label><input class="input" id="password" name="password" type="password" required autocomplete="current-password"></div>
    <div class="flex items-center justify-between text-sm">
        <label class="flex items-center gap-2 text-muted"><input type="checkbox" name="remember"> Lembrar de mim</label>
        <a href="{{ route('password.request') }}" class="text-primary hover:underline">Esqueci a senha</a>
    </div>
    <button class="btn-primary w-full">Entrar</button>
</form>
<p class="mt-4 text-center text-sm text-muted">Não tem conta? <a href="{{ route('register') }}" class="text-primary hover:underline">Criar conta</a></p>
@endsection
