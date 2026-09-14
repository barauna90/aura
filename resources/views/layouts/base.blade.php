<!DOCTYPE html>
<html lang="pt-BR" data-theme="{{ auth()->user()?->theme ?? 'dark' }}" style="--font-scale: {{ auth()->user()?->font_scale ?? 100 }}%">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Aura Simulados') · Aura Simulados</title>
    <meta name="description" content="@yield('description', 'Treine para o ENEM com provas oficiais, cronômetro real, cartão-resposta, correção automática e redação por competências.')">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-180.png') }}">
    <meta property="og:image" content="{{ asset('images/logo-full.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#conteudo" class="skip-link">Pular para o conteúdo</a>
    @yield('body')
</body>
</html>
