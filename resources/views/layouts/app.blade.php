@extends('layouts.base')

@section('body')
@php($user = auth()->user())
@php($menu = \App\Support\Enem::MENU)
<div class="min-h-screen lg:grid lg:grid-cols-[250px_1fr]">
    <aside class="hidden border-r border-border bg-bg-2/70 lg:flex lg:flex-col">
        <div class="px-5 py-5">
            <a href="{{ route('dashboard') }}"><x-logo /></a>
        </div>
        <nav aria-label="Menu principal" class="flex-1 space-y-0.5 overflow-y-auto px-3">
            @foreach($menu as $item)
                <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => request()->routeIs($item['route'].'*') || request()->routeIs(str_replace('.index', '', $item['route']).'.*')]) @if(request()->routeIs($item['route'].'*')) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                </a>
            @endforeach
            @if($user->isStaff())
                <a href="{{ route('admin.dashboard') }}" @class(['nav-link mt-3 border border-border', 'nav-link-active' => request()->routeIs('admin.*')]) ><x-icon name="cog" /> Administração</a>
            @endif
        </nav>
        <div class="border-t border-border p-4 text-xs text-muted">
            <p class="truncate font-medium text-text">{{ $user->name }}</p>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="mt-1 text-[#c4b5fd] hover:underline">Sair</button></form>
        </div>
    </aside>

    <div class="flex min-h-screen flex-col">
        <header class="flex items-center justify-between border-b border-border bg-bg-2/70 px-4 py-3 lg:hidden">
            <a href="{{ route('dashboard') }}"><x-logo /></a>
            <button class="btn-secondary px-3 py-1.5" data-toggle="menu-mobile" aria-expanded="false" aria-controls="menu-mobile">Menu</button>
        </header>
        <div id="menu-mobile" hidden class="border-b border-border bg-bg-2 p-3 lg:hidden">
            <nav class="grid gap-0.5 sm:grid-cols-2">
                @foreach($menu as $item)
                    <a href="{{ route($item['route']) }}" class="nav-link"><x-icon :name="$item['icon']" /> {{ $item['label'] }}</a>
                @endforeach
                @if($user->isStaff())<a href="{{ route('admin.dashboard') }}" class="nav-link"><x-icon name="cog" /> Administração</a>@endif
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="px-3 pt-2">@csrf<button class="text-sm text-[#c4b5fd]">Sair</button></form>
        </div>

        <main id="conteudo" class="flex-1 px-4 py-6 md:px-8 md:py-8">
            <div class="mx-auto max-w-6xl">
                @if(session('status'))<div class="notice-success mb-5" role="status">{{ session('status') }}</div>@endif
                @if(session('error'))<div class="notice-danger mb-5" role="alert">{{ session('error') }}</div>@endif
                @if($errors->any())
                    <div class="notice-danger mb-5" role="alert">
                        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
        <footer class="border-t border-border px-4 py-4 text-xs text-muted md:px-8">
            <p>{{ \App\Support\Disclaimers::INDEPENDENCE }}</p>
            <p class="mt-1">{{ \App\Support\Disclaimers::RESULTS_EDUCATIONAL }}</p>
        </footer>
    </div>
</div>
@endsection
