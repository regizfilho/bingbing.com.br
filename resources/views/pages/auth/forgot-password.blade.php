<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->reset('email');
        session()->flash('status', __($status));
    }
}; ?>

<div class="min-h-screen flex flex-col justify-center items-center bg-[#05070a] px-4 relative overflow-hidden">
    {{-- Efeitos Visuais --}}
    <div class="absolute top-1/4 -left-20 w-80 h-80 bg-blue-600/10 blur-[120px] rounded-full"></div>
    <div class="absolute bottom-1/4 -right-20 w-80 h-80 bg-purple-600/10 blur-[120px] rounded-full"></div>

    <div class="w-full max-w-md relative z-10">
        {{-- Título --}}
        <div class="mb-10 text-center">
            <div class="inline-flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-gradient-to-tr from-blue-600 to-cyan-500 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <span class="text-white font-black text-2xl italic">?</span>
                </div>
            </div>
            <h2 class="text-3xl font-black text-white uppercase italic tracking-tighter leading-none">
                Recuperar <span class="text-blue-500">Acesso</span>
            </h2>
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] mt-3 italic">
                Esqueceu a senha? Relaxa, a gente ajuda.
            </p>
        </div>

        {{-- Card --}}
        <div class="bg-[#0b0d11]/80 backdrop-blur-xl border border-white/10 rounded-[2.5rem] p-8 sm:p-10 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 to-cyan-500"></div>

            <p class="text-xs font-bold text-slate-400 leading-relaxed italic text-center mb-8">
                Informe seu e-mail e enviaremos um link para você configurar uma nova senha de acesso à Arena.
            </p>

            @if (session('status'))
                <div class="mb-8 bg-blue-500/10 border border-blue-500/20 rounded-2xl p-4 flex items-center gap-3 animate-pulse">
                    <span class="text-blue-400">📧</span>
                    <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest italic">
                        Link enviado! Olhe sua caixa de entrada.
                    </p>
                </div>
            @endif

            <form wire:submit="sendPasswordResetLink" class="space-y-6">
                {{-- E-mail --}}
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">E-mail Cadastrado</label>
                    <div class="relative">
                        <input wire:model="email" type="email" required autofocus
                            placeholder="seu@email.com"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none font-medium text-sm">
                    </div>
                    @error('email') 
                        <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 block italic">{{ $message }}</span> 
                    @enderror
                </div>

                {{-- Botão Principal --}}
                <div class="pt-2">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white py-5 rounded-[1.5rem] font-black text-xs uppercase tracking-[0.2em] italic transition-all shadow-xl shadow-blue-600/20 active:scale-[0.98] flex justify-center items-center group">
                        <span wire:loading.remove wire:target="sendPasswordResetLink">Resetar Senha <span class="inline-block group-hover:translate-x-1 transition-transform ml-1">→</span></span>
                        <span wire:loading wire:target="sendPasswordResetLink" class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Enviando Solicitação...
                        </span>
                    </button>
                    
                    <div class="text-center mt-8 pt-6 border-t border-white/5">
                        <a class="text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-widest transition italic" href="{{ route('login') }}" wire:navigate>
                            ← Voltar para o Login
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <p class="text-center mt-10 text-[8px] font-black text-slate-700 uppercase tracking-[0.4em] italic">
            &copy; 2026 BingBing Social Club // Suporte ao Jogador
        </p>
    </div>
</div>