@extends('layouts.admin')
@section('title', 'Usuários')
@section('admin')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold">Usuários</h1>
    <form method="GET" class="flex gap-2"><input class="input" name="q" value="{{ $q }}" placeholder="Buscar por nome ou e-mail" aria-label="Buscar"><button class="btn-secondary">Buscar</button></form>
</div>
<div class="card mt-5 overflow-x-auto">
    <table class="table"><thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Assinatura</th><th>Cadastro</th><th>Ações</th></tr></thead><tbody>
        @foreach($users as $u)
            <tr class="{{ $u->trashed() ? 'opacity-50' : '' }}">
                <td>{{ $u->name }}</td><td>{{ $u->email }}</td><td><span class="badge-neutral">{{ $u->role }}</span></td>
                <td>{{ $u->subscriptions->first()?->plan->name ?? '—' }}</td><td>{{ $u->created_at->format('d/m/Y') }}</td>
                <td>
                    @unless($u->trashed())
                    <form method="POST" action="{{ route('admin.users.update', $u) }}" class="flex flex-wrap items-center gap-1">@csrf @method('PUT')
                        <select class="input px-2 py-1 text-xs" name="role">@foreach(\App\Models\User::ROLES as $r)<option @selected($u->role === $r)>{{ $r }}</option>@endforeach</select>
                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" @checked($u->is_active)> ativo</label>
                        <button class="btn-secondary px-2 py-1 text-xs">Salvar</button>
                    </form>
                    @endunless
                </td>
            </tr>
        @endforeach
    </tbody></table>
    <div class="mt-3">{{ $users->links() }}</div>
</div>
@endsection
