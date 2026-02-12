<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BingBing | A Nova Era do Bingo Social</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,600,700,800|space-grotesk:500,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --primary: #2563eb; --secondary: #7c3aed; }
        [x-cloak] { display: none !important; }
        body { background-color: #020408; font-family: 'Plus Jakarta Sans', sans-serif; color: #f1f5f9; overflow-x: hidden; }
        .font-heading { font-family: 'Space Grotesk', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .gradient-border { position: relative; border-radius: 2rem; background: linear-gradient(135deg, var(--primary), var(--secondary)); padding: 1px; }
        .gradient-border-inner { background: #020408; border-radius: calc(2rem - 1px); }
        .hero-glow { position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 100vw; height: 100vh; background: radial-gradient(circle at 50% 30%, rgba(37, 99, 235, 0.15), transparent 70%); pointer-events: none; }
        .animate-float { animation: float 6s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }
    </style>
</head>
<body class="antialiased">

    <div class="hero-glow"></div>

    {{-- Header --}}
    <nav class="fixed top-0 w-full z-[100] bg-[#020408]/60 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-tr from-blue-600 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <span class="text-white font-black text-xl italic">B</span>
                </div>
                <span class="font-bold text-2xl tracking-tighter text-white uppercase italic">
                    Bing<span class="text-blue-500">Bing</span>
                </span>
            </div>

            <div class="hidden md:flex items-center gap-8 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                <a href="#features" class="hover:text-blue-500 transition">Protocolos</a>
                <a href="#ranking" class="hover:text-blue-500 transition">Elite</a>
                <a href="#about" class="hover:text-blue-500 transition">O Sistema</a>
            </div>

            <div class="flex items-center gap-4">
                @auth
                    <a href="{{ url('/dashboard') }}" class="glass px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-white/10 transition">Console</a>
                @else
                    <a href="{{ route('login') }}" class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-white transition">Login</a>
                    <a href="{{ route('register') }}" class="bg-blue-600 hover:bg-blue-500 px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-white shadow-xl shadow-blue-600/20 transition-all active:scale-95">
                        Registrar
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Main Hero --}}
    <main class="relative pt-44 pb-20 px-6">
        <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
            
            <div class="text-left space-y-8">
                <div class="inline-flex items-center gap-2 bg-blue-500/10 border border-blue-500/20 px-4 py-1.5 rounded-full">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                    </span>
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-400">Sistema Operacional v2.6 Online</span>
                </div>

                <h1 class="font-heading text-6xl md:text-8xl font-bold text-white tracking-tighter leading-[0.9] uppercase italic">
                    DOMINE A <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-500 via-purple-500 to-blue-400">ARENA SOCIAL.</span>
                </h1>

                <p class="max-w-xl text-slate-400 text-lg font-medium leading-relaxed">
                    Esqueça o bingo de papel. No <span class="text-white">BingBing</span>, você cria operações de alto impacto, sorteia prêmios em tempo real e lidera o ranking de elite com seus amigos.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 pt-4">
                    <a href="{{ route('register') }}" class="bg-white text-black px-10 py-5 rounded-2xl font-black uppercase text-xs tracking-[0.2em] italic hover:bg-blue-500 hover:text-white transition-all shadow-2xl active:scale-95 text-center">
                        Inicializar Missão
                    </a>
                    <a href="{{ route('rankings.index') }}" class="glass px-10 py-5 rounded-2xl font-black uppercase text-xs tracking-[0.2em] italic hover:bg-white/10 transition-all text-center">
                        Ver Hall da Fama
                    </a>
                </div>

                <div class="flex items-center gap-6 pt-8 border-t border-white/5">
                    <div>
                        <div class="text-2xl font-bold text-white italic">+150k</div>
                        <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Partidas Realizadas</div>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div>
                        <div class="text-2xl font-bold text-white italic">75ms</div>
                        <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Latência de Sorteio</div>
                    </div>
                </div>
            </div>

            {{-- Elemento Visual: Mockup da Cartela --}}
            <div class="relative hidden lg:block">
                <div class="absolute inset-0 bg-blue-600/20 blur-[100px] rounded-full animate-pulse"></div>
                <div class="animate-float">
                    <div class="gradient-border">
                        <div class="gradient-border-inner p-8">
                            <div class="flex justify-between items-center mb-6">
                                <div class="text-[10px] font-black text-blue-500 uppercase tracking-widest italic">Live Preview // Arena</div>
                                <div class="flex gap-1">
                                    <div class="w-2 h-2 rounded-full bg-red-500"></div>
                                    <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                                    <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-5 gap-3">
                                @foreach([7, 22, 45, 61, 74, 12, 19, 33, 58, 69, 5, 27, 40, 52, 63, 15, 30, 44, 55, 71, 2, 25, 38, 49, 66] as $n)
                                    <div class="aspect-square rounded-xl flex items-center justify-center font-bold text-lg {{ in_array($n, [7, 45, 69, 5, 63]) ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/40 scale-110' : 'bg-white/5 text-slate-600 border border-white/5' }}">
                                        {{ $n }}
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-8 p-4 bg-white/[0.02] border border-white/5 rounded-2xl flex justify-between items-center">
                                <div class="text-[10px] font-black text-slate-500 uppercase italic">Próxima Bola</div>
                                <div class="text-2xl font-black text-white italic">#42</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Features Section --}}
    <section id="features" class="py-32 relative border-t border-white/5">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-20 space-y-4">
                <h2 class="font-heading text-4xl font-bold uppercase italic tracking-tighter">Protocolos de <span class="text-blue-500">Operação</span></h2>
                <p class="text-slate-500 text-sm font-bold uppercase tracking-widest">Tecnologia de ponta para sua diversão</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @php
                    $features = [
                        ['icon' => '🛰️', 'title' => 'Real-Time Sync', 'desc' => 'Números sorteados via WebSockets em milissegundos para todos os dispositivos.'],
                        ['icon' => '🛡️', 'title' => 'Anti-Cheat Engine', 'desc' => 'Validação criptográfica de cartelas para garantir que cada Bingo seja legítimo.'],
                        ['icon' => '💎', 'title' => 'Economia Social', 'desc' => 'Sistema de créditos, wallet e pacotes exclusivos para hosts profissionais.']
                    ];
                @endphp

                @foreach($features as $f)
                    <div class="glass p-10 rounded-[2.5rem] hover:border-blue-500/50 transition-all group">
                        <div class="text-5xl mb-8 group-hover:scale-110 transition-transform duration-500">{{ $f['icon'] }}</div>
                        <h3 class="text-xl font-bold text-white uppercase italic tracking-tighter mb-4">{{ $f['title'] }}</h3>
                        <p class="text-slate-500 text-sm leading-relaxed">{{ $f['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-20">
        <div class="max-w-5xl mx-auto px-6">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-[3rem] p-12 md:p-20 text-center relative overflow-hidden shadow-2xl shadow-blue-500/20">
                <div class="absolute inset-0 bg-black/20"></div>
                <div class="relative z-10 space-y-8">
                    <h2 class="font-heading text-4xl md:text-6xl font-bold text-white uppercase italic leading-none">
                        PRONTO PARA <br> COMANDAR A ARENA?
                    </h2>
                    <p class="text-white/80 font-medium text-lg max-w-xl mx-auto">
                        Crie sua conta agora e ganhe créditos de bônus para inicializar seu primeiro protocolo de bingo.
                    </p>
                    <a href="{{ route('register') }}" class="inline-block bg-white text-black px-12 py-5 rounded-2xl font-black uppercase text-xs tracking-[0.2em] italic hover:scale-105 transition-all shadow-xl">
                        Acessar Terminal Agora
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-20 border-t border-white/5 text-center">
        <div class="flex items-center justify-center gap-3 mb-8 grayscale opacity-50">
             <div class="w-8 h-8 bg-white rounded-lg"></div>
             <span class="font-bold text-lg tracking-tighter text-white uppercase italic">BingBing</span>
        </div>
        <p class="text-slate-600 text-[10px] font-black uppercase tracking-[0.5em]">
            &copy; {{ date('Y') }} BingBing Social Club // Protocolo de Diversão Segura
        </p>
    </footer>

</body>
</html>