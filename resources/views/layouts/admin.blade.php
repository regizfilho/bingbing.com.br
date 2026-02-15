<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('layouts.components.head')
</head>

<body class="antialiased bg-[#05070a] text-slate-200">

    {{-- Wrapper Principal --}}
    <div class="min-h-screen flex flex-col lg:flex-row">

        {{-- SIDEBAR --}}
        <aside x-data="{ mobileOpen: false }"
            class="lg:w-72 flex-shrink-0 bg-[#0a0c12] border-b lg:border-b-0 lg:border-r border-white/5 flex flex-col lg:sticky lg:top-0 lg:h-screen">
            {{-- Header Sidebar - Mobile Toggle --}}
            <div class="h-16 lg:h-20 flex items-center justify-between px-6 border-b border-white/5">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 bg-gradient-to-br from-blue-600 to-cyan-500 rounded-2xl flex items-center justify-center font-black text-white text-sm shadow-xl">
                        B²
                    </div>
                    <div>
                        <span class="text-white font-black tracking-tight text-sm uppercase italic block leading-none">
                            BingBing <span class="text-blue-500">PRO</span>
                        </span>
                        <span class="text-[8px] text-slate-500 uppercase tracking-widest font-black">Admin Panel</span>
                    </div>
                </div>

                {{-- Mobile Menu Toggle --}}
                <button @click="mobileOpen = !mobileOpen"
                    class="lg:hidden w-10 h-10 bg-white/5 border border-white/10 rounded-xl flex items-center justify-center text-white hover:bg-white/10 transition-all">
                    <svg x-show="!mobileOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="mobileOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Navigation - Desktop sempre visível, Mobile togglable --}}
            <nav x-show="mobileOpen || window.innerWidth >= 1024" x-cloak
                class="flex-1 overflow-y-auto p-6 space-y-8 custom-scrollbar">
                {{-- Gestão --}}
                <div>
                    <h3 class="px-2 text-[9px] font-black text-slate-600 uppercase tracking-[0.2em] mb-4 italic">
                        Gestão Operacional
                    </h3>

                    <div class="space-y-2">
                        <x-admin.sidebar.item route="admin.index" label="Dashboard" icon="home" />
                        <x-admin.sidebar.item route="admin.pages.index" label="Páginas" icon="document-text" />
                        <x-admin.sidebar.item route="admin.notification.push" label="Notificações" icon="bell" />
                    </div>
                </div>

                {{-- Marketing --}}
                <div>
                    <h3 class="px-2 text-[9px] font-black text-slate-600 uppercase tracking-[0.2em] mb-4 italic">
                        Marketing
                    </h3>

                    <x-admin.sidebar.group label="Cupons" icon="ticket" :active="request()->routeIs('admin.marketing.*')">
                        <x-admin.sidebar.item route="admin.marketing.coupon" label="Gerenciar Cupons" />
                        <x-admin.sidebar.item route="admin.marketing.coupon.analytics" label="Analytics" />
                    </x-admin.sidebar.group>
                </div>

                {{-- Financeiro --}}
                <div>
                    <h3 class="px-2 text-[9px] font-black text-slate-600 uppercase tracking-[0.2em] mb-4 italic">
                        Financeiro
                    </h3>

                    <x-admin.sidebar.group label="Financeiro" icon="credit-card" :active="request()->routeIs('admin.finance.*')">
                        <x-admin.sidebar.item route="admin.finance.home" label="Estatísticas" />
                        <x-admin.sidebar.item route="admin.finance.packs" label="Pacotes" />
                        <x-admin.sidebar.item route="admin.finance.refound" label="Reembolso" />
                        <x-admin.sidebar.item route="admin.finance.credit" label="Créditos" />
                        <x-admin.sidebar.item route="admin.finance.gift" label="Gift Card" />
                    </x-admin.sidebar.group>
                </div>

                {{-- Usuários --}}
                <div>
                    <h3 class="px-2 text-[9px] font-black text-slate-600 uppercase tracking-[0.2em] mb-4 italic">
                        Usuários
                    </h3>

                    <x-admin.sidebar.group label="Usuários" icon="user-group" :active="request()->routeIs('admin.users.*')">
                        <x-admin.sidebar.item route="admin.users.home" label="Usuários" />
                        <x-admin.sidebar.item route="admin.users.anaytics" label="Estatísticas" />
                        <x-admin.sidebar.item route="admin.users.live" label="Ao Vivo" />
                    </x-admin.sidebar.group>
                </div>

                {{-- Sistema --}}
                <div>
                    <h3 class="px-2 text-[9px] font-black text-slate-600 uppercase tracking-[0.2em] mb-4 italic">
                        Sistema
                    </h3>

                    <div class="space-y-2">
                        <x-admin.sidebar.item route="admin.security.index" label="Segurança" icon="shield-check" />
                    </div>
                </div>
            </nav>

            {{-- Footer Sidebar --}}
            <div class="p-6 border-t border-white/5 bg-black/20">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-600 to-cyan-500 border border-blue-500/20 overflow-hidden flex items-center justify-center font-black text-white text-sm shadow-xl">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-black text-white truncate uppercase italic">{{ auth()->user()->name }}
                        </p>
                        <p class="text-[9px] text-slate-500 uppercase tracking-widest font-black">Administrador</p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- CONTEÚDO PRINCIPAL --}}
        <main class="flex-1 flex flex-col bg-[#05070a] min-h-screen">

            {{-- Topbar --}}
            <header
                class="h-16 lg:h-20 border-b border-white/5 flex items-center justify-between px-4 lg:px-8 bg-[#0a0c12]/80 backdrop-blur-md sticky top-0 z-10">
                <div class="flex items-center gap-3 lg:gap-4 text-xs font-black uppercase italic text-slate-500">
                    <span>Admin</span>
                    <span class="text-slate-700">/</span>
                    <span class="text-blue-500 capitalize">{{ request()->segment(2) ?? 'Dashboard' }}</span>
                </div>

                <div class="flex items-center gap-3 lg:gap-6">
                    <a href="{{ route('dashboard') }}"
                        class="hidden lg:flex items-center gap-2 px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-xs font-black uppercase italic text-slate-400 hover:text-white hover:border-white/20 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Arena
                    </a>

                    {{-- Mobile: Apenas ícone --}}
                    <a href="{{ route('dashboard') }}"
                        class="lg:hidden w-10 h-10 bg-white/5 border border-white/10 rounded-xl flex items-center justify-center text-slate-400 hover:text-white transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </a>

                    <div class="h-6 w-[1px] bg-white/10"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            class="hidden lg:block px-4 py-2 bg-red-500/10 border border-red-500/20 rounded-xl text-xs font-black text-red-500 hover:bg-red-500/20 hover:border-red-500/30 transition-all uppercase italic">
                            Deslogar
                        </button>

                        {{-- Mobile: Apenas ícone --}}
                        <button
                            class="lg:hidden w-10 h-10 bg-red-500/10 border border-red-500/20 rounded-xl flex items-center justify-center text-red-500 hover:bg-red-500/20 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>
            </header>

            {{-- Slot de Conteúdo --}}
            <div class="p-4 lg:p-8 max-w-7xl w-full mx-auto flex-1">
                @if (isset($header))
                    <div class="mb-8 lg:mb-12">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-2 h-2 bg-blue-600 rounded-full animate-pulse"></div>
                            <span class="text-[10px] font-black text-blue-500 uppercase tracking-[0.5em] italic">
                                Painel de Controle
                            </span>
                        </div>
                        <h1
                            class="text-4xl lg:text-6xl font-black text-white tracking-tighter uppercase italic leading-none">
                            {{ $header }}
                        </h1>
                        <p class="text-sm text-slate-500 mt-3 italic">Gestão e controle do ecossistema BingBing.</p>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>

    </div>

    @livewireScripts

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #0a0c12;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>

    <x-cookie />
</body>

</html>
