<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

// Usando o layout principal para manter a atmosfera de Arena
layout('layouts.guest'); 

state('token')->locked();

state([
    'email' => fn () => request()->string('email')->value(),
    'password' => '',
    'password_confirmation' => ''
]);

rules([
    'token' => ['required'],
    'email' => ['required', 'string', 'email'],
    'password' => ['required', 'string', 'confirmed', Rules\Password::min(8)],
]);

$resetPassword = function () {
    $this->validate();

    $status = Password::reset(
        $this->only('email', 'password', 'password_confirmation', 'token'),
        function ($user) {
            $user->forceFill([
                'password' => Hash::make($this->password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        }
    );

    if ($status != Password::PASSWORD_RESET) {
        $this->addError('email', __($status));
        return;
    }

    Session::flash('status', __($status));
    $this->redirectRoute('login', navigate: true);
};

?>

<div class="w-full">
    {{-- Título da Operação --}}
    <div class="mb-8 text-center">
        <h2 class="font-game text-2xl font-black text-white uppercase italic tracking-tighter">
            Nova <span class="text-blue-500">Credencial</span>
        </h2>
        <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] mt-2 italic">
            Redefinição de Segurança Obrigatória
        </p>
    </div>

    <form wire:submit="resetPassword" class="space-y-5">
        
        {{-- Email (Read-only aesthetic) --}}
        <div class="relative group">
            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2 block italic">E-mail de Cadastro</label>
            <div class="relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-600 text-lg">📧</span>
                <input wire:model="email" type="email" required
                    class="w-full bg-[#0b0d11] border border-white/5 rounded-2xl py-4 pl-14 pr-6 text-white text-sm font-bold focus:border-blue-500/50 focus:ring-0 transition-all placeholder:text-slate-700" 
                    placeholder="seu@email.com">
            </div>
            @error('email') <span class="text-[9px] font-black text-red-500 uppercase mt-1 ml-4 tracking-widest">{{ $message }}</span> @enderror
        </div>

        {{-- Nova Senha --}}
        <div class="relative group">
            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2 block italic">Nova Senha</label>
            <div class="relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-600 text-lg">🔒</span>
                <input wire:model="password" type="password" required
                    class="w-full bg-[#0b0d11] border border-white/5 rounded-2xl py-4 pl-14 pr-6 text-white text-sm font-bold focus:border-blue-500/50 focus:ring-0 transition-all placeholder:text-slate-700"
                    placeholder="••••••••">
            </div>
            @error('password') <span class="text-[9px] font-black text-red-500 uppercase mt-1 ml-4 tracking-widest">{{ $message }}</span> @enderror
        </div>

        {{-- Confirmação --}}
        <div class="relative group">
            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4 mb-2 block italic">Confirmar Nova Senha</label>
            <div class="relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-600 text-lg">🛡️</span>
                <input wire:model="password_confirmation" type="password" required
                    class="w-full bg-[#0b0d11] border border-white/5 rounded-2xl py-4 pl-14 pr-6 text-white text-sm font-bold focus:border-blue-500/50 focus:ring-0 transition-all placeholder:text-slate-700"
                    placeholder="••••••••">
            </div>
        </div>

        {{-- Botão de Submissão --}}
        <div class="pt-4">
            <button type="submit" 
                class="group relative w-full bg-blue-600 hover:bg-blue-500 text-white py-5 rounded-2xl transition-all duration-300 active:scale-95 shadow-xl shadow-blue-600/20 overflow-hidden">
                <div class="relative z-10 flex items-center justify-center gap-3">
                    <span class="font-game text-sm font-black uppercase italic tracking-widest">Atualizar Acesso</span>
                    <span class="text-xl group-hover:translate-x-1 transition-transform">⚡</span>
                </div>
                
                {{-- Efeito de brilho ao passar o mouse --}}
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
            </button>
        </div>

        {{-- Link de retorno --}}
        <div class="text-center mt-6">
            <a href="{{ route('login') }}" wire:navigate class="text-[10px] font-black text-slate-600 hover:text-blue-500 uppercase tracking-widest italic transition-colors">
                ← Voltar para Identificação
            </a>
        </div>
    </form>
</div>