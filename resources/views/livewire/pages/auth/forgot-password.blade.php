<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state(['email' => '']);

rules(['email' => ['required', 'string', 'email']]);

$sendPasswordResetLink = function () {
    $this->validate();

    $status = Password::sendResetLink(
        $this->only('email')
    );

    if ($status != Password::RESET_LINK_SENT) {
        $this->addError('email', __($status));
        return;
    }

    $this->reset('email');
    Session::flash('status', __($status));
};

?>

<div class="w-full">
    {{-- Título da Operação --}}
    <div class="mb-8 text-center">
        <h2 class="font-game text-2xl font-black text-white uppercase italic tracking-tighter">
            Recuperar <span class="text-blue-500">Acesso</span>
        </h2>
        <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] mt-2 italic">
            Solicitação de Nova Credencial
        </p>
    </div>

    {{-- Instruções --}}
    <div class="bg-[#0b0d11] border border-white/5 rounded-[2rem] p-6 mb-8 text-center">
        <p class="text-xs font-bold text-slate-400 leading-relaxed italic">
            Esqueceu sua senha? Sem problemas. Informe seu e-mail e enviaremos um link para você configurar uma nova senha de acesso à Arena.
        </p>
    </div>

    {{-- Status de Sucesso (Link Enviado) --}}
    @if (session('status'))
        <div class="mb-8 bg-blue-500/10 border border-blue-500/20 rounded-2xl p-4 flex items-center gap-4 animate-pulse">
            <span class="text-xl">📧</span>
            <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest italic">
                Link de restauração enviado. Verifique sua caixa de entrada.
            </p>
        </div>
    @endif

    <form wire:submit="sendPasswordResetLink" class="space-y-6">
        {{-- Input de Email --}}
        <div class="relative group">
            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2 block italic">Endereço de E-mail</label>
            <div class="relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-600 text-lg group-focus-within:text-blue-500 transition-colors">📧</span>
                <input wire:model="email" type="email" required autofocus
                    class="w-full bg-[#0b0d11] border border-white/5 rounded-2xl py-4 pl-14 pr-6 text-white text-sm font-bold focus:border-blue-500/50 focus:ring-0 transition-all placeholder:text-slate-700" 
                    placeholder="seu@email.com">
            </div>
            @error('email') 
                <span class="text-[9px] font-black text-red-500 uppercase mt-2 ml-4 tracking-widest block">{{ $message }}</span> 
            @enderror
        </div>

        {{-- Botão de Ação --}}
        <div class="pt-2">
            <button type="submit" 
                class="group relative w-full bg-blue-600 hover:bg-blue-500 text-white py-5 rounded-2xl transition-all duration-300 active:scale-95 shadow-xl shadow-blue-600/20 overflow-hidden">
                <div class="relative z-10 flex items-center justify-center gap-3">
                    <span class="font-game text-sm font-black uppercase italic tracking-widest">Enviar Link de Recuperação</span>
                </div>
                
                {{-- Scanline Effect --}}
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
            </button>
        </div>

        {{-- Retorno --}}
        <div class="text-center pt-2">
            <a href="{{ route('auth.login') }}" wire:navigate class="text-[10px] font-black text-slate-600 hover:text-white uppercase tracking-[0.2em] italic transition-colors p-2">
                ← Voltar para o Login
            </a>
        </div>
    </form>
</div>