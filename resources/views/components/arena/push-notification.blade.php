<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Notification\PushSubscription;

new class extends Component
{
    #[Computed]
    public function showNotice()
    {
        $user = Auth::user();
        if (!$user) return false;

        return !PushSubscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }
}; ?>

<div>
    @if($this->showNotice)
        <div class="bg-indigo-600/10 border-b border-indigo-500/10 backdrop-blur-xl transition-all duration-500 animate-in fade-in slide-in-from-top-2">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    
                    {{-- Lado Esquerdo: Ícone e Texto --}}
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-xl bg-indigo-600/20 flex items-center justify-center text-indigo-500 shadow-inner">
                                <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                            </div>
                            {{-- Ponto de atenção --}}
                            <div class="absolute -top-1 -right-1 w-3 h-3 bg-indigo-500 rounded-full border-2 border-[#0a0c10] animate-pulse"></div>
                        </div>
                        
                        <div>
                            <h4 class="text-[11px] font-black text-white uppercase italic tracking-widest leading-none">
                                Status: <span class="text-indigo-500">Notificações Desativadas</span>
                            </h4>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter mt-1">
                                Ative para receber atualizações importantes sobre seus jogos, competições e ranking.
                            </p>
                        </div>
                    </div>

                    {{-- Lado Direito: Botão de Ação --}}
                    <button 
                        x-data="{ 
                            loading: false,
                            async activate() {
                                this.loading = true;
                                try {
                                    if (typeof requestNotificationPermission === 'undefined') {
                                        throw new Error('Função não encontrada. Recarregue a página.');
                                    }
                                    await requestNotificationPermission();
                                    @this.$refresh();
                                } catch (error) {
                                    console.error('Erro:', error);
                                    alert('Erro ao ativar: ' + error.message);
                                } finally {
                                    this.loading = false;
                                }
                            }
                        }"
                        @click="activate()"
                        :disabled="loading"
                       class="group relative w-full md:w-auto overflow-hidden px-6 py-3 bg-indigo-600 hover:bg-indigo-500 disabled:bg-indigo-800 disabled:cursor-not-allowed text-white text-[10px] font-black uppercase italic tracking-[0.2em] rounded-xl transition-all duration-300 flex items-center justify-center gap-3 shadow-lg shadow-indigo-600/20">
                        {{-- Efeito de brilho no hover --}}
                        <div class="absolute inset-0 bg-white/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                        
                        <span class="relative" x-text="loading ? 'Ativando...' : 'Ativar Notificações'"></span>
                        <svg x-show="!loading" class="relative w-4 h-4 transform group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <div x-show="loading" class="relative w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>