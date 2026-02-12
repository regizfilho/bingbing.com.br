<div class="flex items-center gap-6 pl-8 border-l border-white/10" x-data="{ open: false }">
    {{-- Identificação do Agente --}}
    <div class="hidden md:flex flex-col items-end">
        <div class="flex items-center gap-2 mb-1">
            <span class="flex h-1.5 w-1.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.8)] animate-pulse"></span>
            <span class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] italic leading-none">Status: Operacional</span>
        </div>
        <span class="text-sm font-black text-white tracking-tighter uppercase italic leading-none">
            {{ auth()->user()->name }}
        </span>
    </div>

    {{-- Avatar com Efeito de Glow --}}
    <div class="relative">
        <button @click="open = !open" 
            class="relative group focus:outline-none transition-transform active:scale-95">
            
            {{-- Anel de Identificação Ativa --}}
            <div class="absolute -inset-1.5 bg-gradient-to-tr from-blue-600 to-purple-600 rounded-2xl blur-md opacity-0 group-hover:opacity-40 transition-opacity duration-500"></div>
            
            <div class="relative w-12 h-12 rounded-2xl bg-[#0b0d11] border border-white/10 p-1 flex items-center justify-center overflow-hidden">
                {{-- Background do Avatar --}}
                <div class="w-full h-full bg-gradient-to-br from-slate-800 to-slate-900 rounded-[0.9rem] flex items-center justify-center font-black text-lg text-blue-500 group-hover:text-white group-hover:scale-110 transition-all duration-300 italic">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>
                
                {{-- Overlay de Varredura --}}
                <div class="absolute inset-0 bg-gradient-to-t from-blue-500/10 to-transparent opacity-0 group-hover:opacity-100 pointer-events-none"></div>
            </div>
        </button>

        {{-- Dropdown Menu Estilo "Dossiê" --}}
        <div x-show="open" 
            @click.away="open = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            class="absolute right-0 mt-4 w-56 bg-[#0b0d11]/95 backdrop-blur-xl border border-white/10 rounded-[1.8rem] shadow-[0_20px_50px_rgba(0,0,0,0.5)] overflow-hidden z-[110]"
            style="display: none;">
            
            {{-- Header do Dropdown --}}
            <div class="px-5 py-4 border-b border-white/5 bg-white/[0.02]">
                <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic">Acesso Restrito</p>
                <p class="text-xs font-bold text-white truncate">{{ auth()->user()->email }}</p>
            </div>

            <div class="p-2 space-y-1">
                {{-- Links de Ação --}}
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all group/item">
                    <span class="text-lg group-hover/item:scale-110 transition-transform">📂</span>
                    <span class="text-[10px] font-black uppercase tracking-widest italic">Meu Perfil</span>
                </a>
                
                <a href="{{ route('wallet.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all group/item">
                    <span class="text-lg group-hover/item:scale-110 transition-transform">💎</span>
                    <span class="text-[10px] font-black uppercase tracking-widest italic">Créditos</span>
                </a>

                <div class="h-px bg-white/5 my-2"></div>

                {{-- Componente de Logout customizado para manter o estilo --}}
                <div class="px-1 pb-1">
                    <livewire:pages.auth.logout-button />
                </div>
            </div>
        </div>
    </div>
</div>