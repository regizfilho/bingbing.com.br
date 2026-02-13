<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel Admin | {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body {
            background-color: #05070a;
            font-family: 'Inter', sans-serif;
        }

        [x-cloak] {
            display: none !important;
        }

        /* Scrollbar Profissional */
        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: #05070a;
        }

        ::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #334155;
        }
    </style>
</head>

<body class="antialiased text-slate-300">

    {{-- Wrapper Principal: Grid de 2 Colunas --}}
    <div class="flex min-h-screen">

        {{-- SIDEBAR FIXA (Não some) --}}
        <aside class="w-64 flex-shrink-0 bg-[#0a0c12] border-r border-white/5 flex flex-col sticky top-0 h-screen">

            {{-- Header Sidebar --}}
            <div class="h-16 flex items-center px-6 border-b border-white/5">
                <div class="flex items-center gap-3">
                    <div
                        class="w-7 h-7 bg-blue-600 rounded flex items-center justify-center font-bold text-white text-sm">
                        B²
                    </div>
                    <span class="text-white font-bold tracking-tight text-sm uppercase">BingBing <span
                            class="text-blue-500">PRO</span></span>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto p-4 space-y-6">

                {{-- Gestão --}}
                <div>
                    <h3 class="px-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4">
                        Gestão Operacional
                    </h3>

                    <div class="space-y-1">
                        <x-admin.sidebar.item route="admin.index" label="Dashboard" icon="home" />
                        <x-admin.sidebar.item route="admin.pages.index" label="Páginas" icon="document-text" />
                    </div>
                </div>

                {{-- Marketing --}}
                <div>
                    <h3 class="px-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4">
                        Marketing
                    </h3>

                    <x-admin.sidebar.group label="Cupons" icon="ticket" :active="request()->routeIs('admin.marketing.*')">
                        <x-admin.sidebar.item route="admin.marketing.coupon" label="Gerenciar Cupons" />
                        <x-admin.sidebar.item route="admin.marketing.coupon.analytics" label="Analytics" />
                    </x-admin.sidebar.group>
                </div>

                {{-- Financeiro --}}
                <div>
                    <h3 class="px-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4">
                        Financeiro
                    </h3>

                    <x-admin.sidebar.group label="Financeiro" icon="credit-card" :active="request()->routeIs('admin.finance.*')">
                        <x-admin.sidebar.item route="admin.finance.home" label="Estatisticas" />
                        <x-admin.sidebar.item route="admin.finance.packs" label="Pacotes" />
                        <x-admin.sidebar.item route="admin.finance.refound" label="Reembolso" />
                        <x-admin.sidebar.item route="admin.finance.credit" label="Créditos" />
                    </x-admin.sidebar.group>
                </div>

                {{-- Usuarios --}}
                <div>
                    <h3 class="px-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4">
                        Usuários
                    </h3>

                    <x-admin.sidebar.group label="Usuários" icon="credit-card" :active="request()->routeIs('admin.finance.*')">
                        <x-admin.sidebar.item route="admin.users.home" label="Usuários" />
                        <x-admin.sidebar.item route="admin.users.anaytics" label="Estatisticas" />
                        <x-admin.sidebar.item route="admin.users.live" label="Ao vivo" />
                    </x-admin.sidebar.group>
                </div>

                {{-- Sistema --}}
                <div>
                    <h3 class="px-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4">
                        Sistema
                    </h3>

                    <div class="space-y-1">
                        <x-admin.sidebar.item route="admin.security.index" label="Segurança" icon="shield" />
                    </div>
                </div>

            </nav>



            {{-- Footer Sidebar --}}
            <div class="p-4 border-t border-white/5 bg-black/10">
                <div class="flex items-center gap-3 px-2 py-1">
                    <div
                        class="w-8 h-8 rounded-full bg-slate-800 border border-white/10 overflow-hidden text-[10px] flex items-center justify-center font-bold">
                        ADM
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-white truncate">{{ auth()->user()->name }}</p>
                        <p class="text-[9px] text-slate-500 uppercase tracking-tighter">Administrador</p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- CONTEÚDO PRINCIPAL --}}
        <main class="flex-1 flex flex-col bg-[#05070a]">

            {{-- Topbar --}}
            <header
                class="h-16 border-b border-white/5 flex items-center justify-between px-8 bg-[#0a0c12]/50 backdrop-blur-md sticky top-0 z-10">
                <div class="flex items-center gap-4 text-xs font-medium text-slate-500">
                    <span>Admin</span>
                    <span class="text-slate-700">/</span>
                    <span class="text-blue-500 capitalize">{{ request()->segment(1) }}</span>
                </div>

                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}"
                        class="text-xs font-semibold text-slate-400 hover:text-white transition-colors">
                        Voltar para Arena
                    </a>
                    <div class="h-4 w-[1px] bg-white/10"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            class="text-xs font-bold text-red-500/80 hover:text-red-400 transition-colors uppercase">Deslogar</button>
                    </form>
                </div>
            </header>

            {{-- Slot de Conteúdo --}}
            <div class="p-8 max-w-7xl w-full mx-auto">
                @if (isset($header))
                    <div class="mb-8">
                        <h1 class="text-2xl font-bold text-white tracking-tight">{{ $header }}</h1>
                        <p class="text-sm text-slate-500 mt-1 italic">Gestão e controle do ecossistema BingBing.</p>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>

    </div>

    @livewireScripts
</body>

</html>
