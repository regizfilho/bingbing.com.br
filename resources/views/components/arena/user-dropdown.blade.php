<div class="flex items-center gap-6 pl-8 border-l border-white/5" x-data="{ open: false }">
    {{-- Identificação do Agente --}}
    <div class="hidden md:flex flex-col items-end gap-1">
        <div class="flex items-center gap-2">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
            </span>
            <span class="text-[8px] font-black text-blue-500/80 uppercase tracking-[0.3em] italic leading-none">Agente Ativo</span>
        </div>
        <span class="text-sm font-black text-white tracking-tighter uppercase italic leading-none bg-clip-text text-transparent bg-gradient-to-l from-white to-slate-500">
            {{ auth()->user()->nickname ?? auth()->user()->name }}
        </span>
    </div>

    {{-- Avatar com Container Geométrico --}}
    <div class="relative">
        <button @click="open = !open" 
            class="relative group focus:outline-none">
            
            {{-- Glow de Fundo --}}
            <div class="absolute inset-0 bg-blue-600 rounded-xl blur-xl opacity-0 group-hover:opacity-20 transition-opacity duration-500"></div>
            
            {{-- Moldura do Avatar --}}
            <div class="relative w-11 h-11 rounded-xl bg-[#0b0d11] border border-white/10 p-0.5 transition-all duration-300 group-hover:border-blue-500/50 group-hover:rotate-3">
                <div class="w-full h-full bg-[#05070a] rounded-[0.6rem] flex items-center justify-center overflow-hidden relative">
                    {{-- Se tiver avatar_path usa imagem, se não usa a inicial --}}
                    @if(auth()->user()->avatar_path)
                        <img src="{{ asset('storage/' . auth()->user()->avatar_path) }}" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-opacity">
                    @else
                        <span class="font-black text-lg text-slate-500 group-hover:text-blue-500 transition-colors italic uppercase">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </span>
                    @endif
                    
                    {{-- Overlay Scanline --}}
                    <div class="absolute inset-0 bg-[linear-gradient(rgba(18,16,16,0)_50%,rgba(0,0,0,0.25)_50%),linear-gradient(90deg,rgba(255,0,0,0.02),rgba(0,255,0,0.01),rgba(0,0,255,0.02))] bg-[length:100%_2px,3px_100%] pointer-events-none"></div>
                </div>
            </div>
        </button>

        {{-- Dropdown "HUD Style" --}}
        <div x-show="open" 
            @click.away="open = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            class="absolute right-0 mt-4 w-60 bg-[#0b0d11]/95 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-[0_25px_70px_rgba(0,0,0,0.8)] overflow-hidden z-[110]"
            style="display: none;">
            
            {{-- Header com Grid de Identificação --}}
            <div class="px-5 py-4 border-b border-white/5 bg-gradient-to-b from-white/[0.03] to-transparent">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[8px] font-black text-blue-500 uppercase tracking-[0.2em] italic">Dossiê do Usuário</span>
                    <span class="text-[8px] font-mono text-slate-600">ID:{{ substr(auth()->user()->id, 0, 8) }}</span>
                </div>
                <p class="text-xs font-bold text-white truncate opacity-90 tracking-tight">{{ auth()->user()->email }}</p>
            </div>

            <div class="p-2 space-y-0.5">
                {{-- Link: Perfil --}}
                <a href="{{ route('player.profile') }}" class="flex items-center justify-between px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-blue-600/10 hover:border-blue-500/20 border border-transparent transition-all group/item">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-slate-500 group-hover/item:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span class="text-[10px] font-black uppercase tracking-widest italic">Ver Perfil</span>
                    </div>
                    <span class="text-[8px] font-bold opacity-0 group-hover/item:opacity-100 transition-opacity text-blue-500">GO</span>
                </a>
                
                {{-- Link: Carteira --}}
                <a href="{{ route('wallet.index') }}" class="flex items-center justify-between px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-blue-600/10 hover:border-blue-500/20 border border-transparent transition-all group/item">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-slate-500 group-hover/item:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-[10px] font-black uppercase tracking-widest italic">Carteira</span>
                    </div>
                    <span class="text-[9px] font-black text-green-500">R$ {{ number_format(auth()->user()->wallet->balance ?? 0, 2, ',', '.') }}</span>
                </a>

                <div class="h-px bg-white/5 my-2 mx-4"></div>

                {{-- Logout --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-red-500/70 hover:text-red-500 hover:bg-red-500/5 transition-all group/logout">
                        <svg class="w-4 h-4 group-hover/logout:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="text-[10px] font-black uppercase tracking-widest italic text-left flex-1">Finalizar Sessão</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>