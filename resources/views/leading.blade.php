<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- SEO Meta Tags --}}
    <title>BingBing | Bingo Social Online – Crie, Jogue e Ganhe em Tempo Real</title>
    <meta name="description"
        content="Plataforma moderna de bingo social. Crie salas personalizadas, convide amigos, sorteie números em tempo real e concorra a prêmios. Junte-se à maior comunidade de bingo do Brasil.">
    <meta name="keywords"
        content="bingo online, bingo social, jogar bingo, criar sala de bingo, bingo com amigos, sorteios ao vivo">
    <meta name="author" content="BingBing Social Club">
    <meta name="robots" content="index, follow">

    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="BingBing – Bingo Social Online">
    <meta property="og:description"
        content="Crie sua sala, jogue com amigos e ganhe prêmios em tempo real. O novo jeito de jogar bingo.">
    <meta property="og:image" content="{{ asset('images/og-banner.jpg') }}">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="BingBing – Bingo Social Online">
    <meta name="twitter:description" content="Crie sua sala, jogue com amigos e ganhe prêmios em tempo real.">
    <meta name="twitter:image" content="{{ asset('images/og-banner.jpg') }}">

    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|space-grotesk:500,700" rel="stylesheet" />

    {{-- Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --primary: #2563eb;
            --secondary: #7c3aed;
        }

        [x-cloak] {
            display: none !important;
        }

        body {
            background-color: #020408;
            font-family: 'Inter', sans-serif;
            color: #f1f5f9;
            overflow-x: hidden;
        }

        .font-heading {
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: -0.02em;
        }

        .glass {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .gradient-text {
            background: linear-gradient(135deg, #60a5fa, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-glow {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100vw;
            height: 100vh;
            background: radial-gradient(circle at 50% 30%, rgba(37, 99, 235, 0.1), transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .stats-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), transparent);
            border-radius: 1.5rem;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            background: rgba(37, 99, 235, 0.05);
            border-color: rgba(37, 99, 235, 0.2);
        }

        @media (prefers-reduced-motion) {

            .animate-float,
            .stats-card:hover {
                animation: none;
                transition: none;
            }
        }
    </style>
</head>

<body class="antialiased">

    <div class="hero-glow"></div>

    {{-- Header --}}
    <nav class="fixed top-0 w-full z-[100] bg-[#020408]/80 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl flex items-center justify-center shadow-lg shadow-blue-600/20">
                    <span class="text-white font-bold text-xl">B²</span>
                </div>
                <span class="font-bold text-xl tracking-tight text-white">
                    Bing<span class="text-blue-500">Bing</span>
                </span>
            </div>

            <div class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-400">
                <a href="#como-funciona" class="hover:text-white transition">Como funciona</a>
                <a href="#recursos" class="hover:text-white transition">Recursos</a>
                <a href="#depoimentos" class="hover:text-white transition">Depoimentos</a>
            </div>

            <div class="flex items-center gap-4">
                @auth
                    <a href="{{ url('/dashboard') }}"
                        class="bg-blue-600 hover:bg-blue-500 px-6 py-2.5 rounded-xl text-sm font-medium text-white transition-all shadow-lg shadow-blue-600/20">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('auth.login') }}"
                        class="text-sm font-medium text-slate-400 hover:text-white transition">Entrar</a>
                    <a href="{{ route('auth.register') }}"
                        class="bg-blue-600 hover:bg-blue-500 px-6 py-2.5 rounded-xl text-sm font-medium text-white shadow-lg shadow-blue-600/20 transition-all">
                        Criar conta
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <main class="relative pt-36 pb-20 px-6">
        <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">

            <div class="space-y-8">
                <div
                    class="inline-flex items-center gap-2 bg-blue-500/10 px-4 py-2 rounded-full border border-blue-500/20">
                    <span class="relative flex h-2 w-2">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                    </span>
                    <span class="text-xs font-medium text-blue-400">+1.500 jogadores ativos agora</span>
                </div>

                <h1 class="font-heading text-5xl md:text-7xl font-bold text-white tracking-tight leading-[1.1]">
                    Bingo social <br>
                    <span class="gradient-text">em tempo real.</span>
                </h1>

                <p class="max-w-xl text-slate-400 text-lg leading-relaxed">
                    Crie salas personalizadas, convide quem quiser e jogue bingo de verdade.
                    Números sorteados ao vivo, prêmios instantâneos e diversão garantida.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 pt-4">
                    <a href="{{ route('auth.register') }}"
                        class="bg-blue-600 hover:bg-blue-500 text-white px-8 py-4 rounded-xl font-semibold transition-all shadow-xl shadow-blue-600/25 active:scale-[0.98] text-center">
                        Começar a jogar
                    </a>
                    {{-- <a href="#como-funciona" class="bg-white/5 hover:bg-white/10 text-white px-8 py-4 rounded-xl font-semibold transition-all text-center border border-white/10">
                        Ver demonstração
                    </a> --}}
                </div>

                {{-- Stats --}}
                <div class="grid grid-cols-3 gap-8 pt-8 border-t border-white/5">
                    <div>
                        <div class="text-2xl font-bold text-white">+50k</div>
                        <div class="text-xs text-slate-600 font-medium uppercase tracking-wider mt-1">Salas criadas
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-white">+200k</div>
                        <div class="text-xs text-slate-600 font-medium uppercase tracking-wider mt-1">Partidas</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-white">4.8★</div>
                        <div class="text-xs text-slate-600 font-medium uppercase tracking-wider mt-1">Avaliação</div>
                    </div>
                </div>
            </div>

            {{-- Hero Visual --}}
            <div class="relative hidden lg:block">
                <div class="absolute inset-0 bg-blue-600/10 blur-[100px] rounded-full"></div>
                <div class="relative glass p-8 rounded-[2.5rem] border border-white/5">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-blue-600/20 rounded-lg flex items-center justify-center">
                                <span class="text-blue-500 text-sm font-bold">B²</span>
                            </div>
                            <span class="text-xs font-medium text-slate-500">Arena #BR-4392</span>
                        </div>
                        <div class="flex gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="text-xs text-slate-500">12 jogando</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-5 gap-2 mb-6">
                        @foreach ([7, 22, 45, 61, 74, 12, 19, 33, 58, 69, 5, 27, 40, 52, 63, 15, 30, 44, 55, 71, 2, 25, 38, 49, 66] as $i => $n)
                            <div
                                class="aspect-square rounded-lg flex items-center justify-center font-medium {{ $i % 3 == 0 ? 'bg-blue-600 text-white' : 'bg-white/5 text-slate-500 border border-white/5' }}">
                                {{ $n }}
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl border border-white/5">
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-slate-500">Último número</span>
                            <span class="text-2xl font-bold text-blue-500">45</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            <span class="text-xs text-slate-500">Ao vivo</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Como Funciona --}}
    <section id="como-funciona" class="py-24 relative border-t border-white/5">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-sm font-medium text-blue-500 uppercase tracking-wider mb-3 block">Simples e
                    rápido</span>
                <h2 class="font-heading text-4xl md:text-5xl font-bold text-white tracking-tight mb-6">
                    Jogue bingo como nunca antes
                </h2>
                <p class="text-slate-400 text-lg">
                    Em três passos você já está jogando e concorrendo a prêmios.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center p-8">
                    <div
                        class="w-16 h-16 bg-blue-600/10 rounded-2xl flex items-center justify-center mx-auto mb-6 border border-blue-500/20">
                        <span class="text-3xl">1️⃣</span>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Crie sua sala</h3>
                    <p class="text-slate-500">Defina um nome, escolha a quantidade de cartelas e convide seus amigos
                        com um código único.</p>
                </div>

                <div class="text-center p-8">
                    <div
                        class="w-16 h-16 bg-blue-600/10 rounded-2xl flex items-center justify-center mx-auto mb-6 border border-blue-500/20">
                        <span class="text-3xl">2️⃣</span>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Marque os números</h3>
                    <p class="text-slate-500">Os números são sorteados em tempo real. Clique na cartela para marcar e
                        completar sua sequência.</p>
                </div>

                <div class="text-center p-8">
                    <div
                        class="w-16 h-16 bg-blue-600/10 rounded-2xl flex items-center justify-center mx-auto mb-6 border border-blue-500/20">
                        <span class="text-3xl">3️⃣</span>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">Ganhe prêmios</h3>
                    <p class="text-slate-500">Faça bingo, conquiste prêmios e suba no ranking. Quanto mais joga, mais
                        recompensas.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Recursos --}}
    <section id="recursos" class="py-24 bg-white/5">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-2 gap-16 items-center">
                <div>
                    <span class="text-sm font-medium text-blue-500 uppercase tracking-wider mb-3 block">Por que
                        BingBing?</span>
                    <h2 class="font-heading text-4xl md:text-5xl font-bold text-white tracking-tight mb-6">
                        Mais que um bingo,<br>uma comunidade.
                    </h2>
                    <p class="text-slate-400 text-lg mb-8">
                        Desenvolvemos a plataforma mais completa para quem leva o bingo a sério –
                        seja para diversão ou para organizar torneios com amigos.
                    </p>

                    <div class="space-y-4">
                        <div class="flex items-start gap-4">
                            <div
                                class="w-6 h-6 bg-blue-600/20 rounded-lg flex items-center justify-center flex-shrink-0 mt-1">
                                <span class="text-blue-500 text-sm">✓</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-white">Salas privadas</h4>
                                <p class="text-slate-500 text-sm">Crie salas com código de acesso e jogue apenas com
                                    quem você convidar.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div
                                class="w-6 h-6 bg-blue-600/20 rounded-lg flex items-center justify-center flex-shrink-0 mt-1">
                                <span class="text-blue-500 text-sm">✓</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-white">Prêmios personalizados</h4>
                                <p class="text-slate-500 text-sm">Você define os prêmios de cada rodada. Pode ser
                                    crédito, reconhecimento ou nada.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div
                                class="w-6 h-6 bg-blue-600/20 rounded-lg flex items-center justify-center flex-shrink-0 mt-1">
                                <span class="text-blue-500 text-sm">✓</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-white">Ranking completo</h4>
                                <p class="text-slate-500 text-sm">Histórico de todas as partidas, vitórias e hall da
                                    fama.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div class="glass p-8 rounded-[2.5rem] border border-white/5">
                        <div class="flex items-center gap-4 mb-6">
                            <div
                                class="w-12 h-12 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl flex items-center justify-center">
                                <span class="text-white font-bold text-xl">🏆</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-white">Ranking da semana</h4>
                                <p class="text-xs text-slate-600">Atualizado em tempo real</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div
                                class="flex items-center justify-between p-3 bg-white/5 rounded-xl border border-white/5">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-bold text-slate-600">1</span>
                                    <span class="font-medium text-white">@anabeatriz</span>
                                </div>
                                <span class="text-sm text-blue-500">27 vitórias</span>
                            </div>
                            <div
                                class="flex items-center justify-between p-3 bg-white/5 rounded-xl border border-white/5">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-bold text-slate-600">2</span>
                                    <span class="font-medium text-white">@carlosedu</span>
                                </div>
                                <span class="text-sm text-blue-500">23 vitórias</span>
                            </div>
                            <div
                                class="flex items-center justify-between p-3 bg-white/5 rounded-xl border border-white/5">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-bold text-slate-600">3</span>
                                    <span class="font-medium text-white">@mariana_r</span>
                                </div>
                                <span class="text-sm text-blue-500">19 vitórias</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Depoimentos --}}
    <section id="depoimentos" class="py-24">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-sm font-medium text-blue-500 uppercase tracking-wider mb-3 block">Depoimentos</span>
                <h2 class="font-heading text-4xl md:text-5xl font-bold text-white tracking-tight mb-6">
                    Quem usa, recomenda
                </h2>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div class="glass p-8 rounded-2xl border border-white/5">
                    <div class="flex items-center gap-1 mb-4">
                        <span class="text-yellow-500">★</span><span class="text-yellow-500">★</span><span
                            class="text-yellow-500">★</span><span class="text-yellow-500">★</span><span
                            class="text-yellow-500">★</span>
                    </div>
                    <p class="text-slate-300 mb-6">"Uso toda semana com meus amigos. Ficamos horas jogando e a
                        experiência é muito fluida, parece um jogo profissional."</p>
                    <div class="font-medium text-white">Ana Beatriz</div>
                    <div class="text-xs text-slate-600">+120 partidas</div>
                </div>

                <div class="glass p-8 rounded-2xl border border-white/5">
                    <div class="flex items-center gap-1 mb-4">
                        <span class="text-yellow-500">★</span><span class="text-yellow-500">★</span><span
                            class="text-yellow-500">★</span><span class="text-yellow-500">★</span><span
                            class="text-yellow-500">★</span>
                    </div>
                    <p class="text-slate-300 mb-6">"Organizo torneios no meu clube e o BingBing foi a ferramenta
                        perfeita. Todos conseguem jogar pelo celular sem complicação."</p>
                    <div class="font-medium text-white">Carlos Eduardo</div>
                    <div class="text-xs text-slate-600">Organizador</div>
                </div>

                <div class="glass p-8 rounded-2xl border border-white/5">
                    <div class="flex items-center gap-1 mb-4">
                        <span class="text-yellow-500">★</span><span class="text-yellow-500">★</span><span
                            class="text-yellow-500">★</span><span class="text-yellow-500">★</span><span
                            class="text-yellow-500">★</span>
                    </div>
                    <p class="text-slate-300 mb-6">"Simplesmente viciante. O sistema de ranking me faz querer jogar
                        cada vez mais para subir de posição."</p>
                    <div class="font-medium text-white">Mariana Rios</div>
                    <div class="text-xs text-slate-600">Top 10 ranking</div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA Final --}}
    <section class="py-16">
        <div class="max-w-5xl mx-auto px-6">
            <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-3xl p-12 md:p-16 text-center shadow-2xl">
                <h2 class="font-heading text-3xl md:text-5xl font-bold text-white tracking-tight mb-6">
                    Pronto para jogar?
                </h2>
                <p class="text-white/90 text-lg mb-8 max-w-xl mx-auto">
                    Crie sua conta gratuita e comece agora mesmo. Não precisa de cartão de crédito.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('auth.register') }}"
                        class="bg-white text-blue-600 hover:bg-white/90 px-8 py-4 rounded-xl font-semibold transition-all shadow-xl active:scale-[0.98]">
                        Criar conta gratuita
                    </a>
                    <a href="#como-funciona"
                        class="bg-white/10 text-white hover:bg-white/20 px-8 py-4 rounded-xl font-semibold transition-all border border-white/20">
                        Como funciona
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="pt-20 pb-12 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div>
                    <div class="flex items-center gap-2 mb-6">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                            <span class="text-white font-bold">B²</span>
                        </div>
                        <span class="font-bold text-lg text-white">BingBing</span>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Bingo social em tempo real. Crie, jogue e ganhe com seus amigos.
                    </p>
                </div>

                <div>
                    <h5 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Plataforma</h5>
                    <ul class="space-y-3 text-sm text-slate-500">
                        <li><a href="#como-funciona" class="hover:text-white transition">Como funciona</a></li>
                        <li><a href="#recursos" class="hover:text-white transition">Recursos</a></li>
                        <li><a href="#depoimentos" class="hover:text-white transition">Depoimentos</a></li>
                    </ul>
                </div>

                <div>
                    <h5 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Legal</h5>
                    <ul class="space-y-3 text-sm text-slate-500">
                        <li>
                            <a href="/termos-de-uso" class="hover:text-white transition">
                                Termos de uso
                            </a>
                        </li>
                        <li>
                            <a href="/politica-de-privacidade" class="hover:text-white transition">
                                Política de privacidade
                            </a>
                        </li>
                        <li>
                            <a href="/cookies" class="hover:text-white transition">
                                Cookies
                            </a>
                        </li>
                        <li><a href="mailton:contato@codepiper.com.br" class="hover:text-white transition">Contato</a>
                        </li>
                    </ul>
                </div>

                <div>
                    <h5 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Comunidade</h5>
                    <ul class="space-y-3 text-sm text-slate-500">
                        <li><a href="#" class="hover:text-white transition">Discord</a></li>
                        <li><a href="#" class="hover:text-white transition">Twitter</a></li>
                        <li><a href="#" class="hover:text-white transition">Instagram</a></li>
                    </ul>
                </div>
            </div>

            <livewire:footer />
            <x-cookie />
        </div>
    </footer>

</body>

</html>
