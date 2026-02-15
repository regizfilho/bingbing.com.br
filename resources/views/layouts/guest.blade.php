<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel Arena') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600,900&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* Scrollbar customizada para manter o clima tech */
            ::-webkit-scrollbar { width: 5px; }
            ::-webkit-scrollbar-track { background: #0b0d11; }
            ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
            ::-webkit-scrollbar-thumb:hover { background: #3b82f6; }
            
            body {
                background-color: #0b0d11;
                /* Adiciona um sutil ruído ou padrão de grade se desejar */
                background-image: radial-gradient(circle, rgba(255,255,255,0.02) 1px, transparent 1px);
                background-size: 30px 30px;
            }
        </style>
    </head>
    <body class="font-sans antialiased text-slate-200">
        <div class="min-h-screen">
            {{ $slot }}
        </div>
    </body>

    <x-cookie />
</html>