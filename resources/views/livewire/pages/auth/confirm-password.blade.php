<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state(['password' => '']);

rules(['password' => ['required', 'string']]);

$confirmPassword = function () {
    $this->validate();

    if (! Auth::guard('web')->validate([
        'email' => Auth::user()->email,
        'password' => $this->password,
    ])) {
        throw ValidationException::withMessages([
            'password' => __('A credencial informada está incorreta.'),
        ]);
    }

    session(['auth.password_confirmed_at' => time()]);

    $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
};

?>

<div class="w-full">
    {{-- Título de Segurança --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600/10 border border-blue-500/20 rounded-2xl mb-4 shadow-[0_0_20px_rgba(37,99,235,0.1)]">
            <span class="text-2xl text-blue-500">🔐</span>
        </div>
        <h2 class="font-game text-2xl font-black text-white uppercase italic tracking-tighter">
            Área <span class="text-blue-500">Restrita</span>
        </h2>
        <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] mt-2 italic">
            Confirme sua identidade para prosseguir
        </p>
    </div>

    {{-- Aviso de Segurança --}}
    <div class="bg-[#0b0d11] border border-white/5 rounded-2xl p-5 mb-8">
        <p class="text-[11px] font-bold text-slate-400 leading-relaxed text-center italic">
            Esta é uma zona segura da plataforma. Por favor, valide sua senha de acesso para liberar as funcionalidades críticas.
        </p>
    </div>

    <form wire:submit="confirmPassword" class="space-y-6">
        {{-- Input de Senha --}}
        <div class="relative group">
            <div class="flex justify-between items-center ml-4 mb-2">
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest italic">Senha do Agente</label>
            </div>
            
            <div class="relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-600 text-lg group-focus-within:text-blue-500 transition-colors">🔑</span>
                <input wire:model="password" type="password" required autofocus
                    class="w-full bg-[#0b0d11] border border-white/5 rounded-2xl py-4 pl-14 pr-6 text-white text-sm font-bold focus:border-blue-500/50 focus:ring-0 transition-all placeholder:text-slate-700" 
                    placeholder="••••••••">
            </div>
            
            @error('password') 
                <span class="text-[9px] font-black text-red-500 uppercase mt-2 ml-4 tracking-widest block">{{ $message }}</span> 
            @enderror
        </div>

        {{-- Botão de Confirmação --}}
        <div class="pt-2">
            <button type="submit" 
                class="group relative w-full bg-blue-600 hover:bg-blue-500 text-white py-5 rounded-2xl transition-all duration-300 active:scale-95 shadow-xl shadow-blue-600/20 overflow-hidden">
                <div class="relative z-10 flex items-center justify-center gap-3">
                    <span class="font-game text-sm font-black uppercase italic tracking-widest">Desbloquear Acesso</span>
                    <span class="text-xl group-hover:rotate-12 transition-transform">🔓</span>
                </div>
                
                {{-- Efeito de Varredura --}}
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
            </button>
        </div>
    </form>
</div>