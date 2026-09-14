@extends('layouts.app')
@section('title', 'Perfil')
@section('content')
<h1 class="text-2xl font-semibold">Perfil</h1>
<form method="POST" action="{{ route('profile.update') }}" class="card mt-5 space-y-4">
    @csrf @method('PUT')
    <h2 class="font-semibold">Dados</h2>
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="label" for="name">Nome completo</label><input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div><label class="label" for="email">E-mail</label><input class="input" id="email" value="{{ $user->email }}" disabled></div>
        <div><label class="label" for="phone">Celular</label><input class="input" id="phone" name="phone" value="{{ old('phone', $user->phone) }}"></div>
        <div><label class="label" for="password">Nova senha (opcional)</label><input class="input" id="password" name="password" type="password" autocomplete="new-password"></div>
        <div><label class="label" for="password_confirmation">Confirmar nova senha</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></div>
    </div>
    <h2 class="pt-2 font-semibold">Acessibilidade</h2>
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="label" for="font_scale">Tamanho da fonte</label><select class="input" id="font_scale" name="font_scale">@foreach([90, 100, 115, 130, 150] as $v)<option value="{{ $v }}" @selected($user->font_scale == $v)>{{ $v }}%</option>@endforeach</select></div>
        <div><label class="label" for="theme">Tema</label><select class="input" id="theme" name="theme"><option value="light" @selected($user->theme === 'light')>Claro</option><option value="dark" @selected($user->theme === 'dark')>Escuro</option></select></div>
    </div>
    <p class="text-xs text-muted">As opções de acessibilidade alteram apenas a interface; o conteúdo original das provas (PDF) nunca é modificado.</p>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="marketing_email" value="1" @checked($consents->firstWhere('type', 'marketing_email')?->granted)> Aceito receber e-mails sobre plano de estudos, metas e promoções</label>
    <button class="btn-primary">Salvar</button>
</form>

<div class="card mt-4 space-y-3">
    <h2 class="font-semibold">Seus dados (LGPD)</h2>
    <p class="text-sm text-muted">Você pode consultar, exportar e excluir seus dados a qualquer momento.</p>
    <a href="{{ route('profile.export') }}" class="btn-secondary">Exportar meus dados (JSON)</a>
    <form method="POST" action="{{ route('profile.destroy') }}" data-confirm="Excluir sua conta? Seus dados pessoais serão anonimizados. Esta ação é irreversível." class="mt-4 rounded-xl border border-danger/40 p-4">
        @csrf @method('DELETE')
        <p class="text-sm font-medium">Excluir conta</p>
        <p class="text-xs text-muted">Cancele assinaturas ativas antes. Registros financeiros exigidos por lei são mantidos anonimizados.</p>
        <div class="mt-2 flex flex-wrap gap-2"><input class="input max-w-xs" name="password" type="password" placeholder="Confirme sua senha" aria-label="Senha" required><button class="btn-danger">Excluir minha conta</button></div>
    </form>
</div>
@endsection
