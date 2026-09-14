@extends('layouts.base')

@section('body')
<div class="glow-bg flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <a href="{{ route('landing') }}" class="mb-6 inline-block"><x-logo size="lg" /></a>
        <div class="card-glass">
            @if(session('status'))<div class="notice-success mb-4" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())
                <div class="notice-danger mb-4" role="alert"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </div>
        <p class="mt-6 text-center text-xs text-muted">{{ \App\Support\Disclaimers::INDEPENDENCE }}</p>
    </div>
</div>
@endsection
