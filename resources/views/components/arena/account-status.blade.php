<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Propriedade Computada para verificar se o alerta deve ser exibido.
     * O Livewire 4 faz o cache inteligente dessa informação.
     */
    #[Computed]
    public function showNotice()
    {
        $user = Auth::user();
        if (!$user) return false;

        // Verifica se faltam dados ou se o selo de verificado ainda não foi aplicado
        $isMissingInfo = empty($user->nickname) || 
                         empty($user->phone_number) || 
                         empty($user->instagram) ||
                         empty($user->birth_date);
        
        return $isMissingInfo || !$user->is_verified;
    }
}; ?>

<div>
    @if($this->showNotice)
        <div class="bg-blue-600/10 border-b border-blue-500/10 backdrop-blur-xl transition-all duration-500 animate-in fade-in slide-in-from-top-2">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    
                    {{-- Lado Esquerdo: Ícone e Texto --}}
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-xl bg-blue-600/20 flex items-center justify-center text-blue-500 shadow-inner">
                                <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            {{-- Ponto de atenção --}}
                            <div class="absolute -top-1 -right-1 w-3 h-3 bg-blue-500 rounded-full border-2 border-[#0a0c10]"></div>
                        </div>
                        
                        <div>
                            <h4 class="text-[11px] font-black text-white uppercase italic tracking-widest leading-none">
                                Status: <span class="text-blue-500">Conta Pendente</span>
                            </h4>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter mt-1">
                                Complete seu perfil para liberar saques e o selo de verificado na arena.
                            </p>
                        </div>
                    </div>

                    {{-- Lado Direito: Botão de Ação --}}
                    <a href="{{ route('player.profile') }}" wire:navigate 
                       class="group relative w-full md:w-auto overflow-hidden px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-black uppercase italic tracking-[0.2em] rounded-xl transition-all duration-300 flex items-center justify-center gap-3 shadow-lg shadow-blue-600/20">
                        {{-- Efeito de brilho no hover --}}
                        <div class="absolute inset-0 bg-white/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                        
                        <span class="relative">Atualizar Perfil</span>
                        <svg class="relative w-4 h-4 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>