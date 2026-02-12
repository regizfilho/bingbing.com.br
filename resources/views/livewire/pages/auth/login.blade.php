<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\form;
use function Livewire\Volt\layout;

layout('layouts.guest');

form(LoginForm::class);

$login = function () {
    $this->validate();

    $this->form->authenticate();

    Session::regenerate();

    $this->redirectIntended(default: route('dashboard', absolute: false));
};

?>

<div class="min-h-screen flex flex-col justify-center items-center bg-[#05070a] px-4 relative overflow-hidden">
    {{-- Efeitos de fundo para consistência com a Landing --}}
    <div class="absolute top-1/4 -left-20 w-80 h-80 bg-blue-600/10 blur-[120px] rounded-full"></div>
    <div class="absolute bottom-1/4 -right-20 w-80 h-80 bg-purple-600/10 blur-[120px] rounded-full"></div>

    <div class="w-full max-w-md relative z-10">
        
        {{-- Logo/Branding --}}
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-gradient-to-tr from-blue-600 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <span class="text-white font-black text-2xl italic">B</span>
                </div>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase italic leading-none">
                LOGIN DE <span class="text-blue-500">ACESSO</span>
            </h1>
            <p class="text-slate-500 text-[10px] font-black uppercase tracking-[0.3em] mt-3 italic">
                Terminal de Operações BingBing
            </p>
        </div>

        {{-- Card Estilo "Dossiê/Glass" --}}
        <div class="bg-[#0b0d11]/80 backdrop-blur-xl border border-white/10 rounded-[2.5rem] p-8 sm:p-10 shadow-2xl relative overflow-hidden">
            {{-- Barra de Progresso Decorativa --}}
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 to-purple-600"></div>
            
            @if (session('status'))
                <div class="mb-6 text-[10px] font-black uppercase tracking-widest text-emerald-400 bg-emerald-500/5 p-4 rounded-xl border border-emerald-500/20 italic">
                    {{ session('status') }}
                </div>
            @endif

            <form wire:submit.prevent="login" class="space-y-6">
                
                {{-- E-mail --}}
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Identificação (E-mail)</label>
                    <input wire:model="form.email" type="email" autocomplete="username" required autofocus
                        placeholder="agente@bingbing.com"
                        class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none font-medium">
                    @error('form.email') 
                        <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 block italic">{{ $message }}</span> 
                    @enderror
                </div>

                {{-- Senha --}}
                <div class="space-y-2">
                    <div class="flex justify-between items-center ml-1">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest italic">Chave de Acesso</label>
                        @if (Route::has('password.request'))
                            <a class="text-[9px] font-black text-blue-500 uppercase tracking-widest hover:text-blue-400 transition" href="{{ route('password.request') }}">
                                Recuperar?
                            </a>
                        @endif
                    </div>
                    <input wire:model="form.password" type="password" autocomplete="current-password" required
                        placeholder="••••••••"
                        class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none font-medium">
                    @error('form.password') 
                        <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 block italic">{{ $message }}</span> 
                    @enderror
                </div>

                {{-- Lembrar-me --}}
                <div class="flex items-center ml-1">
                    <label for="remember" class="inline-flex items-center cursor-pointer group">
                        <input wire:model="form.remember" id="remember" type="checkbox" 
                            class="rounded border-white/10 text-blue-600 focus:ring-blue-500 bg-white/5 w-4 h-4">
                        <span class="ms-3 text-[10px] font-black text-slate-500 group-hover:text-slate-300 uppercase tracking-widest transition italic">Manter Sessão Ativa</span>
                    </label>
                </div>

                {{-- Botão Principal --}}
                <div class="pt-4">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white py-5 rounded-[1.5rem] font-black text-xs uppercase tracking-[0.2em] italic transition-all shadow-xl shadow-blue-600/20 active:scale-[0.98] flex justify-center items-center group">
                        <span wire:loading.remove>Entrar na Arena <span class="inline-block group-hover:translate-x-1 transition-transform ml-1">→</span></span>
                        <span wire:loading class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Sincronizando...
                        </span>
                    </button>
                    
                    <div class="text-center mt-8 pt-6 border-t border-white/5">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest italic">
                            Novo por aqui? 
                            <a class="text-blue-500 hover:text-blue-400 transition ml-1" href="{{ route('register') }}">
                                Criar Novo Perfil
                            </a>
                        </p>
                    </div>
                </div>
            </form>
        </div>
        
        {{-- Rodapé de Segurança --}}
        <div class="mt-8 text-center">
            <p class="text-[8px] font-black text-slate-700 uppercase tracking-[0.4em] italic">
                Criptografia de Ponta a Ponta // BingBing 2026
            </p>
        </div>
    </div>
</div>