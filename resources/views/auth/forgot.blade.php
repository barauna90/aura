@extends('layouts.guest')
@section('title', 'Recuperar senha')
@section('content')
<h1 class="text-xl font-semibold">Recuperar senha</h1>
<p class="mt-1 text-sm text-muted">Informe seu e-mail e enviaremos um link para redefinir a senha.</p>
<form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
    @csrf
    <div><label class="label" for="email">E-mail</label><input class="input" id="email" name="email" type="email" required autofocus></div>
    <button class="btn-primary w-full">Enviar link</button>
</form>
<p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="text-[#c4b5fd] hover:underline">Voltar para entrar</a></p>
@endsection
