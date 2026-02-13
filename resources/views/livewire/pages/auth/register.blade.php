<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state([
    'name' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => ''
]);

rules([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
    'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
]);

$register = function () {
    $this->validate();

    $user = User::create([
        'name' => $this->name,
        'email' => $this->email,
        'password' => Hash::make($this->password),
    ]);

    Auth::login($user);

    $this->redirect(route('dashboard', absolute: false));
};

?>

<div class="min-h-screen flex flex-col justify-center items-center bg-[#05070a] px-4 py-10 relative overflow-hidden">
    {{-- Efeitos de Neon Consistentes --}}
    <div class="absolute top-1/4 -left-20 w-80 h-80 bg-blue-600/10 blur-[120px] rounded-full"></div>
    <div class="absolute bottom-1/4 -right-20 w-80 h-80 bg-purple-600/10 blur-[120px] rounded-full"></div>

    <div class="w-full max-w-md relative z-10">
        
        {{-- Cabeçalho --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-gradient-to-tr from-blue-600 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <span class="text-white font-black text-2xl italic">B</span>
                </div>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase italic leading-none">
                Nova <span class="text-purple-500">Credencial</span>
            </h1>
            <p class="text-slate-500 text-[10px] font-black uppercase tracking-[0.3em] mt-3 italic">
                Criação de Perfil Operacional
            </p>
        </div>

        {{-- Card Dossiê --}}
        <div class="bg-[#0b0d11]/80 backdrop-blur-xl border border-white/10 rounded-[2.5rem] p-8 shadow-2xl relative overflow-hidden">
            {{-- Accent superior --}}
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-600 to-blue-600"></div>

            <form wire:submit="register" class="space-y-5">
                
                {{-- Nome Completo --}}
                <div class="space-y-1">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Codinome / Nome</label>
                    <input wire:model="name" type="text" required autofocus
                        placeholder="Ex: Player One"
                        class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-3.5 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all outline-none">
                    @error('name') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 block italic">{{ $message }}</span> @enderror
                </div>

                {{-- E-mail --}}
                <div class="space-y-1">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">E-mail de Contato</label>
                    <input wire:model="email" type="email" required
                        placeholder="agente@operacao.com"
                        class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-3.5 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all outline-none">
                    @error('email') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 block italic">{{ $message }}</span> @enderror
                </div>

                {{-- Grid de Senhas para compacidade --}}
                <div class="grid grid-cols-1 gap-5">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Definir Senha</label>
                        <input wire:model="password" type="password" required
                            placeholder="••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-3.5 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all outline-none">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Confirmar Chave</label>
                        <input wire:model="password_confirmation" type="password" required
                            placeholder="••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-3.5 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all outline-none">
                    </div>
                </div>
                @error('password') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 block italic">{{ $message }}</span> @enderror

                {{-- Botão Principal --}}
                <div class="pt-4">
                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white py-5 rounded-[1.5rem] font-black text-xs uppercase tracking-[0.2em] italic transition-all shadow-xl shadow-purple-600/20 active:scale-[0.98] flex justify-center items-center group">
                        <span wire:loading.remove>Inicializar Registro <span class="inline-block group-hover:translate-x-1 transition-transform ml-1">→</span></span>
                        <span wire:loading class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Registrando...
                        </span>
                    </button>
                    
                    <div class="text-center mt-8 pt-6 border-t border-white/5">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest italic">
                            Já possui credenciais? 
                            <a class="text-purple-400 hover:text-purple-300 transition ml-1" href="{{ route('auth.login') }}">
                                Acessar Terminal
                            </a>
                        </p>
                    </div>
                </div>
            </form>
        </div>
        
        <p class="text-center mt-10 text-[8px] font-black text-slate-700 uppercase tracking-[0.4em] italic">
            &copy; {{ date('Y') }} BingBing Social Club // Unidade de Diversão Social
        </p>
    </div>
</div>