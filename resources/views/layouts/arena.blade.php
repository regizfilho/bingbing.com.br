<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>ARENA RANKING | {{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=orbitron:400,700|inter:400,700,900" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body {
            background-color: #0b0d11;
            font-family: 'Inter', sans-serif;
        }
        .font-arena { font-family: 'Orbitron', sans-serif; }
        
        /* Custom Scrollbar futurista */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0b0d11; }
        ::-webkit-scrollbar-thumb { 
            background: #1e293b; 
            border-radius: 10px;
            border: 2px solid #0b0d11;
        }
        ::-webkit-scrollbar-thumb:hover { background: #3b82f6; }
    </style>
</head>

<body class="antialiased text-slate-200">

    <nav class="sticky top-0 z-[50] bg-[#0b0d11]/80 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <div class="flex items-center gap-10">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 group">
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center shadow-[0_0_15px_rgba(37,99,235,0.5)] group-hover:scale-110 transition">
                            <span class="text-white font-black italic text-xl">B</span>
                        </div>
                        <span class="font-arena font-bold text-lg tracking-tighter text-white uppercase group-hover:text-blue-400 transition">
                            Arena<span class="text-blue-500">Bingo</span>
                        </span>
                    </a>

                    <div class="hidden md:flex items-center gap-1">
                        @php
                            $links = [
                                'dashboard' => 'Home',
                                'rankings.index' => 'Rankings',
                                'wallet.index' => 'Créditos'
                            ];
                        @endphp

                        @foreach($links as $route => $label)
                        <a href="{{ route($route) }}" 
                           class="px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all
                           {{ request()->routeIs($route) ? 'text-blue-400 bg-blue-500/10' : 'text-slate-500 hover:text-white hover:bg-white/5' }}">
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <div class="hidden sm:flex flex-col items-end">
                        <span class="text-xs font-black text-white uppercase tracking-tight">{{ auth()->user()->name }}</span>
                        <span class="text-[9px] font-bold text-blue-500 uppercase tracking-widest">Guerreiro Nível 1</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl border-2 border-white/10 p-0.5 shadow-lg">
                        <div class="w-full h-full bg-slate-800 rounded-[0.9rem] flex items-center justify-center font-black text-blue-400">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main>
        {{ $slot }}
    </main>

    <footer class="py-12 border-t border-white/5 text-center mt-20">
        <p class="text-slate-600 text-[10px] font-black uppercase tracking-[0.4em]">
            &copy; {{ date('Y') }} Arena Bingo League - Domine o Tabuleiro
        </p>
    </footer>

    @livewireScripts
</body>
</html>