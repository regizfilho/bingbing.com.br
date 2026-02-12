<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="font-sans antialiased bg-gray-100">

    <!-- Navbar -->
    <nav class="bg-white border-b shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">

            <!-- Logo + Menu -->
            <div class="flex items-center gap-8">
                <a href="{{ route('dashboard') }}" class="text-xl font-bold text-blue-600">
                    {{ config('app.name') }}
                </a>

                <div class="hidden md:flex gap-2">
                    <a href="{{ route('dashboard') }}"
                        class="px-3 py-2 rounded-md text-sm font-medium transition
                       {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                        Dashboard
                    </a>

                    <a href="{{ route('wallet.index') }}"
                        class="px-3 py-2 rounded-md text-sm font-medium transition
                       {{ request()->routeIs('wallet.*') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                        Carteira
                    </a>

                    <a href="{{ route('games.index') }}"
                        class="px-3 py-2 rounded-md text-sm font-medium transition
                       {{ request()->routeIs('games.*') && !request()->routeIs('games.join') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                        Jogos
                    </a>

                    <a href="{{ route('rankings.index') }}"
                        class="px-3 py-2 rounded-md text-sm font-medium transition
                       {{ request()->routeIs('rankings.*') ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                        Rankings
                    </a>
                </div>
            </div>

            <!-- User -->
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium text-gray-700">
                    {{ auth()->user()->name }}
                </span>

                {{-- <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm text-red-600 hover:underline">
                        Sair
                    </button>
                </form> --}}
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    @if (isset($header))
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 text-xl font-semibold text-gray-800">
                {{ $header }}
            </div>
        </header>
    @endif

    <!-- Content -->
    <main class="py-8">
        {{ $slot }}
    </main>

    @livewireScripts

    @livewireScripts
</body>

</html>
