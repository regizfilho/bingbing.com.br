<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? $game->name ?? 'Bingo ao Vivo' }}</title>

    <!-- Evita zoom indesejado em TV/monitor -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Figtree', system-ui, sans-serif;
            color: white;
        }
        .tv-container {
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
        }
    </style>
</head>
<body class="antialiased">

    <div class="tv-container">
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>