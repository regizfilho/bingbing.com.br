<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $nickname = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $birth_date = '';

    public function register(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['required', 'string', 'max:30', 'unique:users,nickname', 'alpha_dash'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'birth_date' => ['required', 'date', 'before:-18 years', 'after:1920-01-01'],
        ], [
            'birth_date.before' => 'Você precisa ter +18 para entrar na arena.',
            'birth_date.after' => 'Data inválida.',
            'nickname.unique' => 'Esse apelido já está em jogo.',
            'nickname.alpha_dash' => 'Use apenas letras, números e traços.',
        ]);

        $user = User::create([
            'name' => $this->name,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'birth_date' => $this->birth_date,
            'role' => 'user',
            'status' => 'active',
        ]);

        $user->wallet()->create([]);

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="min-h-screen flex flex-col justify-center items-center bg-[#05070a] px-4 py-10 relative overflow-hidden">
    <div class="absolute top-1/4 -left-20 w-80 h-80 bg-blue-600/10 blur-[120px] rounded-full"></div>
    <div class="absolute bottom-1/4 -right-20 w-80 h-80 bg-purple-600/10 blur-[120px] rounded-full"></div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-gradient-to-tr from-blue-600 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <span class="text-white font-black text-2xl italic">B</span>
                </div>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase italic leading-none">
                Criar sua <span class="text-purple-500">Conta</span>
            </h1>
            <p class="text-slate-500 text-[10px] font-black uppercase tracking-[0.3em] mt-3 italic">
                Entre para o BingBing Social Club
            </p>
        </div>

        <div class="bg-[#0b0d11]/80 backdrop-blur-xl border border-white/10 rounded-[2.5rem] p-8 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-600 to-blue-600"></div>

            <form wire:submit="register" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Nome Real</label>
                        <input wire:model="name" type="text" required autofocus placeholder="Seu nome"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-purple-500 outline-none transition-all">
                        @error('name') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest italic">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Apelido no Jogo</label>
                        <input wire:model="nickname" type="text" required placeholder="Ex: @sortudo"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-purple-500 outline-none transition-all">
                        @error('nickname') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest italic">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">E-mail</label>
                    <input wire:model="email" type="email" required placeholder="seu@email.com"
                        class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-purple-500 outline-none transition-all">
                    @error('email') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest italic">{{ $message }}</span> @enderror
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Data de Nascimento</label>
                    <input wire:model="birth_date" type="date" required
                        class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-purple-500 outline-none transition-all [color-scheme:dark]">
                    @error('birth_date') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest italic">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Criar Senha</label>
                        <input wire:model="password" type="password" required placeholder="••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-purple-500 outline-none transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Repetir Senha</label>
                        <input wire:model="password_confirmation" type="password" required placeholder="••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-purple-500 outline-none transition-all">
                    </div>
                </div>
                @error('password') <span class="text-[9px] text-red-500 font-black uppercase tracking-widest italic">{{ $message }}</span> @enderror

                <div class="pt-4">
                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white py-5 rounded-[1.5rem] font-black text-xs uppercase tracking-[0.2em] italic transition-all shadow-xl shadow-purple-600/20 flex justify-center items-center group">
                        <span wire:loading.remove wire:target="register">Começar a Jogar <span class="inline-block group-hover:translate-x-1 transition-transform ml-1">→</span></span>
                        <span wire:loading wire:target="register" class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Preparando Arena...
                        </span>
                    </button>
                    
                    <div class="text-center mt-6 pt-6 border-t border-white/5">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest italic">
                            Já tem uma conta? 
                            <a class="text-purple-400 hover:text-purple-300 transition ml-1" href="{{ route('login') }}" wire:navigate>
                                Fazer Login
                            </a>
                        </p>
                    </div>
                </div>
            </form>
        </div>
        
        <p class="text-center mt-8 text-[8px] font-black text-slate-700 uppercase tracking-[0.4em] italic">
            &copy; 2026 BingBing Social Club // Diversão Levada a Sério
        </p>
    </div>
</div>