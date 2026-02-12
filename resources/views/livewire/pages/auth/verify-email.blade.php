<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\layout;

layout('layouts.guest');

$sendVerification = function () {
    if (Auth::user()->hasVerifiedEmail()) {
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
        return;
    }

    Auth::user()->sendEmailVerificationNotification();
    Session::flash('status', 'verification-link-sent');
};

$logout = function (Logout $logout) {
    $logout();
    $this->redirect('/', navigate: true);
};

?>

<div class="w-full">
    {{-- Título de Operação --}}
    <div class="mb-8 text-center">
        <h2 class="font-game text-2xl font-black text-white uppercase italic tracking-tighter">
            Validar <span class="text-blue-500">Identidade</span>
        </h2>
        <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] mt-2 italic">
            Procedimento de Segurança Pendente
        </p>
    </div>

    {{-- Box de Mensagem Tática --}}
    <div class="bg-[#0b0d11] border border-white/5 rounded-[2rem] p-6 mb-6">
        <p class="text-xs font-bold text-slate-400 leading-relaxed text-center italic">
            Obrigado por se juntar à Arena! Antes de começar, confirme seu e-mail clicando no link que acabamos de enviar. Se não recebeu, podemos gerar um novo acesso.
        </p>
    </div>

    {{-- Alerta de Sucesso (Link Enviado) --}}
    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-4 flex items-center gap-3">
            <span class="text-emerald-500">📡</span>
            <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest italic">
                Novo link de acesso enviado ao seu endereço.
            </p>
        </div>
    @endif

    <div class="space-y-4">
        {{-- Botão Principal --}}
        <button wire:click="sendVerification" 
            class="group relative w-full bg-blue-600 hover:bg-blue-500 text-white py-5 rounded-2xl transition-all duration-300 active:scale-95 shadow-xl shadow-blue-600/20 overflow-hidden">
            <div class="relative z-10 flex items-center justify-center gap-3">
                <span class="font-game text-sm font-black uppercase italic tracking-widest">Reenviar Link</span>
                <span class="text-xl group-hover:scale-110 transition-transform">📧</span>
            </div>
            
            {{-- Efeito de Scanline --}}
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
        </button>

        {{-- Logout / Cancelar --}}
        <div class="text-center">
            <button wire:click="logout" 
                class="text-[10px] font-black text-slate-600 hover:text-red-500 uppercase tracking-[0.2em] italic transition-colors p-2">
                Encerrar Sessão e Sair
            </button>
        </div>
    </div>
</div>