@props(['route', 'label', 'icon', 'active' => false])

<a href="{{ route($route) }}" 
    {{ $attributes->merge(['class' => 'group relative px-5 py-3 rounded-2xl transition-all duration-300 flex items-center gap-3 overflow-hidden ' . 
    ($active 
        ? 'bg-blue-600/10 text-blue-400 shadow-[inset_0_0_20px_rgba(37,99,235,0.05)]' 
        : 'text-slate-500 hover:text-slate-200 hover:bg-white/[0.03]')]) }}>
    
    {{-- Glow de fundo para item ativo --}}
    @if($active)
        <div class="absolute inset-0 bg-gradient-to-r from-blue-600/5 to-transparent pointer-events-none"></div>
    @endif

    {{-- Container do Ícone --}}
    <span class="relative z-10 text-xl transition-all duration-500 {{ $active ? 'scale-110' : 'opacity-40 group-hover:opacity-100 group-hover:scale-110' }}">
        {{ $icon }}
    </span>
    
    {{-- Label com tracking tático --}}
    <span class="relative z-10 text-[10px] font-black uppercase tracking-[0.25em] italic transition-colors duration-300">
        {{ $label }}
    </span>

    {{-- Indicador de Estado Ativo (Cyber Line) --}}
    @if($active)
        {{-- Barra lateral ou inferior sutil --}}
        <div class="absolute left-0 top-1/4 h-1/2 w-[2px] bg-blue-500 rounded-full shadow-[0_0_12px_#3b82f6]"></div>
        
        {{-- Brilho de base --}}
        <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-12 h-[2px] bg-gradient-to-r from-transparent via-blue-500 to-transparent opacity-50"></div>
    @endif

    {{-- Efeito de Hover (Luz de Varredura) --}}
    <div class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/[0.02] to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
</a>