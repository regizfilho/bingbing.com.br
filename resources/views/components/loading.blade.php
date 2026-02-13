@props([
    'target' => null, 
    'message' => 'PROCESSANDO...'
])

{{-- 
    O segredo aqui é o wire:loading.target. 
    Se você passar 'updateProfile, avatar', o Livewire entende a lista.
--}}
<div 
    {{ $target ? "wire:target=$target" : "" }}
    wire:loading
    class="fixed inset-0 z-[10000] flex items-center justify-center backdrop-blur-md bg-[#05070a]/80 selection:none"
>
    <div class="relative flex flex-col items-center">
        {{-- Glow de fundo dinâmico --}}
        <div class="absolute inset-0 bg-blue-600/20 blur-[100px] rounded-full animate-pulse"></div>

        {{-- Loader --}}
        <div class="relative w-24 h-24 mb-6">
            <div class="absolute inset-0 border-t-2 border-b-2 border-blue-500 rounded-full animate-spin"></div>
            <div class="absolute inset-2 border-l-2 border-r-2 border-cyan-400 rounded-full animate-[spin_1.5s_linear_infinite_reverse] opacity-50"></div>
            <div class="absolute inset-[35%] bg-blue-600 rounded-sm animate-pulse shadow-[0_0_15px_#3b82f6]"></div>
            
            {{-- Decorativos laterais --}}
            <div class="absolute -inset-4 border-l border-blue-500/30 animate-pulse"></div>
            <div class="absolute -inset-4 border-r border-blue-500/30 animate-pulse delay-75"></div>
        </div>

        {{-- Texto com efeito de digitação/scan --}}
        <div class="relative flex flex-col items-center">
            <div class="flex items-center gap-3">
                <span class="w-1 h-4 bg-blue-500 animate-bounce"></span>
                <span class="text-[12px] font-black text-white uppercase tracking-[0.4em] italic drop-shadow-[0_0_8px_rgba(58,130,246,0.6)]">
                    {{ $message }}
                </span>
                <span class="w-1 h-4 bg-blue-500 animate-[bounce_1s_infinite_0.5s]"></span>
            </div>

            {{-- Scanline --}}
            <div class="mt-4 w-64 h-[2px] bg-white/5 overflow-hidden relative border-x border-white/20">
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-blue-500 to-transparent w-full -translate-x-full animate-[shimmer_1.5s_infinite]"></div>
            </div>
            
            {{-- Info técnica --}}
            <div class="mt-4 flex gap-4 opacity-40">
                <span class="text-[7px] font-mono text-cyan-400 uppercase tracking-widest animate-pulse">Auth_Secure: OK</span>
                <span class="text-[7px] font-mono text-cyan-400 uppercase tracking-widest animate-pulse delay-150">Buffer_Sync: 100%</span>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes shimmer {
        100% { transform: translateX(100%); }
    }
</style>