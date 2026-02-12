<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Arena Bingo') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=orbitron:700,900|inter:400,600,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body { background: #05070a; font-family: 'Inter', sans-serif; color: #94a3b8; }
        .font-game { font-family: 'Orbitron', sans-serif; }
        
        /* Efeito de Vidro na Nav */
        .glass-nav { 
            background: rgba(5, 7, 10, 0.75); 
            backdrop-filter: blur(12px) saturate(180%); 
            border-bottom: 1px solid rgba(255, 255, 255, 0.05); 
        }

        /* Iluminação de Fundo Dinâmica */
        .bg-glow {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 50% -20%, rgba(37, 99, 235, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 0% 100%, rgba(124, 58, 237, 0.08) 0%, transparent 40%);
            pointer-events: none;
            z-index: 0;
        }

        [x-cloak] { display: none !important; }
        
        /* Custom Scrollbar Tática */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #05070a; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #3b82f6; }
    </style>
</head>

<body class="antialiased selection:bg-blue-600/30 selection:text-blue-400 overflow-x-hidden">
    
    {{-- Camada de Brilho Atmosférico --}}
    <div class="bg-glow"></div>

    {{-- Navegação Principal --}}
    <nav class="glass-nav  top-0 z-[100]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">

                {{-- Lado Esquerdo: Branding e Operações --}}
                <div class="flex items-center gap-10">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                        <div class="relative">
                            <div class="absolute -inset-2 bg-blue-600/20 rounded-full blur-xl opacity-0 group-hover:opacity-100 transition duration-500"></div>
                            <div class="relative w-10 h-10 bg-gradient-to-tr from-blue-700 to-blue-500 rounded-xl flex items-center justify-center shadow-[0_0_20px_rgba(37,99,235,0.3)] transition-all group-hover:scale-110 group-hover:rotate-3">
                                <span class="text-white font-black italic text-xl">B</span>
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-game text-sm font-black tracking-[0.2em] text-white uppercase italic leading-none">
                                BING<span class="text-blue-500">BING</span>
                            </span>
                            <span class="text-[8px] font-bold text-slate-500 uppercase tracking-[0.3em] mt-1">Social Club</span>
                        </div>
                    </a>

                    <div class="hidden lg:flex items-center gap-1 p-1.5 bg-white/5 rounded-2xl border border-white/5">
                        <x-arena.nav-item route="dashboard" label="Home" icon="🏠" :active="request()->routeIs('dashboard')" />
                        <x-arena.nav-item route="wallet.index" label="Carteira" icon="💳" :active="request()->routeIs('wallet.*')" />
                        <x-arena.nav-item route="games.index" label="Jogos" icon="🎮" :active="request()->routeIs('games.*')" />
                        <x-arena.nav-item route="rankings.index" label="Rankings" icon="🏆" :active="request()->routeIs('rankings.*')" />
                    </div>
                </div>

                {{-- Lado Direito: Perfil & Notificações --}}
                <div class="flex items-center gap-4">
                    {{-- Botão de ação rápida para usuários comuns (Ex: Recarregar) --}}
                    <a href="{{ route('wallet.index') }}" class="hidden sm:flex items-center gap-2 px-4 py-2 bg-blue-600/10 hover:bg-blue-600/20 border border-blue-500/20 rounded-xl transition-all group">
                        <span class="text-blue-500 group-hover:scale-110 transition-transform">💎</span>
                        <span class="text-[10px] font-black text-white uppercase tracking-widest italic">Recarregar</span>
                    </a>
                    
                    <x-arena.user-dropdown />
                </div>
                
            </div>
        </div>
    </nav>

    {{-- Header de Contexto --}}
    @if (isset($header))
        <header class="relative pt-12 pb-6 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="flex items-center gap-4 mb-3">
                    <div class="h-[1px] w-12 bg-gradient-to-r from-blue-500 to-transparent"></div>
                    <span class="text-[10px] font-black text-blue-500/60 uppercase tracking-[0.5em] italic">Sistema de Arena</span>
                </div>
                <h2 class="font-game text-5xl font-black text-white uppercase italic tracking-tighter leading-none">
                    {{ $header }}
                </h2>
            </div>
            {{-- Linha de grade sutil de fundo apenas no header --}}
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-[0.03] pointer-events-none"></div>
        </header>
    @endif

    {{-- Conteúdo Principal --}}
    <main class="relative z-10 min-h-screen">
        <div class="max-w-7xl mx-auto">
            {{ $slot }}
        </div>
    </main>

    {{-- Footer Operacional --}}
    <footer class="py-16 border-t border-white/5 mt-20 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 relative z-10 text-center">
            <div class="flex justify-center gap-8 mb-8 opacity-20 group">
                <span class="grayscale hover:grayscale-0 transition duration-500 cursor-default">🔒 SSL ENCRYPTED</span>
                <span class="grayscale hover:grayscale-0 transition duration-500 cursor-default">⚡ HIGH PERFORMANCE</span>
                <span class="grayscale hover:grayscale-0 transition duration-500 cursor-default">🛡️ ANTI-CHEAT</span>
            </div>
            <p class="font-game text-[9px] tracking-[0.6em] text-slate-600 uppercase italic">
                &copy; {{ date('Y') }} BINGBING SOCIAL CLUB // Protocolo de Diversão v2.0
            </p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>