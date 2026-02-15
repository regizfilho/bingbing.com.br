<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('layouts.components.head')
</head>

<body class="antialiased bg-[#05070a] text-slate-200">

    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-[#05070a]/95 border-b border-white/10 shadow-lg">
        <div class="px-3 sm:px-4">
            <div class="flex items-center justify-between h-14">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 bg-blue-600 rounded-lg flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-sm">B²</span>
                    </div>
                    <span class="font-semibold text-white text-xs sm:text-sm tracking-wider hidden sm:inline">BINGBING</span>
                </div>

                <a href="{{ route('dashboard') }}" 
                    class="flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-1.5 sm:py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-all text-xs sm:text-sm font-medium active:scale-95">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span class="hidden xs:inline">Dashboard</span>
                    <span class="xs:hidden">Voltar</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="pt-14 min-h-screen">
        {{ $slot }}
    </main>

    @livewireScripts

    <style>
        @media (min-width: 475px) {
            .xs\:inline {
                display: inline;
            }
            .xs\:hidden {
                display: none;
            }
        }
    </style>
</body>

</html>