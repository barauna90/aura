<!DOCTYPE html>
<html lang="pt-BR" data-theme="{{ auth()->user()?->theme ?? 'dark' }}" style="--font-scale: {{ auth()->user()?->font_scale ?? 100 }}%">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Aura Simulados') · Aura Simulados</title>
    <meta name="description" content="@yield('description', 'Treine para o ENEM com provas oficiais, cronômetro real, cartão-resposta, correção automática e redação por competências.')">
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 32 32\'><defs><linearGradient id=\'g\' x1=\'0\' x2=\'1\'><stop stop-color=\'#7c5cff\'/><stop offset=\'1\' stop-color=\'#22d3ee\'/></linearGradient></defs><rect width=\'32\' height=\'32\' rx=\'8\' fill=\'#0d1326\'/><path d=\'M8 24 16 7l8 17h-4l-4-9-4 9z\' fill=\'url(#g)\'/></svg>') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#conteudo" class="skip-link">Pular para o conteúdo</a>
    @yield('body')
</body>
</html>
