@extends('layouts.guest')
@section('title', 'Redefinir senha')
@section('content')
<h1 class="text-xl font-semibold">Redefinir senha</h1>
<form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div><label class="label" for="email">E-mail</label><input class="input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required></div>
    <div><label class="label" for="password">Nova senha</label><input class="input" id="password" name="password" type="password" required minlength="8"></div>
    <div><label class="label" for="password_confirmation">Confirmar senha</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" required></div>
    <button class="btn-primary w-full">Salvar nova senha</button>
</form>
@endsection
