<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'BingBing') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body { 
            background: #0a0c10; 
            font-family: 'Inter', sans-serif; 
            color: #e2e8f0;
        }
        
        .glass-nav { 
            background: #0f1117;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03); 
            position: sticky;
            top: 0;
            z-index: 100;
        }

        [x-cloak] { display: none !important; }
        
        ::-webkit-scrollbar { width: 3px; height: 3px; }
        ::-webkit-scrollbar-track { background: #0a0c10; }
        ::-webkit-scrollbar-thumb { background: #2a2e3a; border-radius: 3px; }
    </style>
</head>
<body class="antialiased bg-[#0a0c10] text-slate-200">
    
    {{-- Navigation --}}
    <nav class="glass-nav">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                {{-- Brand --}}
                <div class="flex items-center gap-8">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                            <span class="text-white font-bold text-lg">B²</span>
                        </div>
                        <span class="font-semibold text-white text-sm tracking-wider">BINGBING</span>
                    </a>

                    {{-- Desktop Navigation --}}
                    <div class="hidden lg:flex items-center gap-1">
                        <x-arena.nav-item route="dashboard" label="Dashboard" :active="request()->routeIs('dashboard')" />
                        <x-arena.nav-item route="games.index" label="Partidas" :active="request()->routeIs('games.*') && !request()->routeIs('games.create')" />
                        <x-arena.nav-item route="wallet.index" label="Carteira" :active="request()->routeIs('wallet.*')" />
                        <x-arena.nav-item route="rankings.index" label="Ranking" :active="request()->routeIs('rankings.*')" />
                    </div>
                </div>

                {{-- Right Side --}}
                <div class="flex items-center gap-3">
                    <a href="{{ route('games.create') }}" 
                        class="hidden sm:flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition-all text-sm font-medium">
                        <span class="text-lg leading-none">+</span>
                        <span>Criar partida</span>
                    </a>
                    
                    <x-arena.user-dropdown />
                </div>
            </div>

            {{-- Mobile Navigation --}}
            <div class="lg:hidden flex items-center justify-between py-2 border-t border-white/5 mt-2">
                <div class="flex items-center gap-1 overflow-x-auto pb-1 no-scrollbar">
                    <x-arena.nav-item route="dashboard" label="Dashboard" :active="request()->routeIs('dashboard')" />
                    <x-arena.nav-item route="games.index" label="Partidas" :active="request()->routeIs('games.*')" />
                    <x-arena.nav-item route="wallet.index" label="Carteira" :active="request()->routeIs('wallet.*')" />
                    <x-arena.nav-item route="rankings.index" label="Ranking" :active="request()->routeIs('rankings.*')" />
                </div>
                <a href="{{ route('games.create') }}" 
                    class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 rounded-lg text-sm font-medium whitespace-nowrap ml-2">
                    <span class="text-lg leading-none">+</span>
                    <span class="text-xs">Criar</span>
                </a>
            </div>
        </div>
    </nav>

    {{-- Page Header --}}
    @if (isset($header))
        <div class="border-b border-white/5 bg-[#0f1117]/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-white">
                    {{ $header }}
                </h1>
            </div>
        </div>
    @endif

    {{-- Main Content --}}
    <main class="min-h-[calc(100vh-200px)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {{ $slot }}
        </div>
    </main>

    {{-- Footer --}}
    <footer class="border-t border-white/5 mt-12 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-xs text-slate-600">
                    © {{ date('Y') }} BingBing Social Club
                </div>
                <div class="flex gap-4 text-xs text-slate-600">
                    <span>v2.0</span>
                    <span>•</span>
                    <span>SSL</span>
                    <span>•</span>
                    <span>Anticheat</span>
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts
    
    <style>
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</body>
</html>