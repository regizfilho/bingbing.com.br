<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            [
                'token' => $this->token,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation
            ],
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));
            return;
        }

        session()->flash('status', __($status));
        $this->redirect(route('auth.login'), navigate: true);
    }
}; ?>

<div class="min-h-screen flex flex-col justify-center items-center bg-[#05070a] px-4 relative overflow-hidden">
    <div class="absolute top-1/4 -left-20 w-80 h-80 bg-blue-600/10 blur-[120px] rounded-full"></div>
    <div class="absolute bottom-1/4 -right-20 w-80 h-80 bg-purple-600/10 blur-[120px] rounded-full"></div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-gradient-to-tr from-blue-600 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <span class="text-white font-black text-2xl italic">R</span>
                </div>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase italic leading-none">
                Nova <span class="text-blue-500">Senha</span>
            </h1>
            <p class="text-slate-500 text-[10px] font-black uppercase tracking-[0.3em] mt-3 italic">
                Redefinição de Segurança Obrigatória
            </p>
        </div>

        <div class="bg-[#0b0d11]/80 backdrop-blur-xl border border-white/10 rounded-[2.5rem] p-8 sm:p-10 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 to-purple-600"></div>

            <form wire:submit="resetPassword" class="space-y-6">
                {{-- E-mail (Apenas leitura visual) --}}
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">E-mail de Cadastro</label>
                    <input wire:model="email" type="email" required readonly
                        class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 text-slate-400 focus:outline-none font-medium text-sm cursor-not-allowed">
                    @error('email') 
                        <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 block italic">{{ $message }}</span> 
                    @enderror
                </div>

                {{-- Nova Senha --}}
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Nova Senha</label>
                    <input wire:model="password" type="password" required autofocus
                        placeholder="••••••••"
                        class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none font-medium text-sm">
                    @error('password') 
                        <span class="text-[9px] text-red-500 font-black uppercase tracking-widest mt-1 block italic">{{ $message }}</span> 
                    @enderror
                </div>

                {{-- Confirmação --}}
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest italic ml-1">Confirmar Nova Senha</label>
                    <input wire:model="password_confirmation" type="password" required
                        placeholder="••••••••"
                        class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none font-medium text-sm">
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white py-5 rounded-[1.5rem] font-black text-xs uppercase tracking-[0.2em] italic transition-all shadow-xl shadow-blue-600/20 active:scale-[0.98] flex justify-center items-center group">
                        <span wire:loading.remove wire:target="resetPassword">Atualizar Acesso <span class="inline-block group-hover:translate-x-1 transition-transform ml-1">⚡</span></span>
                        <span wire:loading wire:target="resetPassword" class="flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Salvando Nova Chave...
                        </span>
                    </button>
                    
                    <div class="text-center mt-8 pt-6 border-t border-white/5">
                        <a class="text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-widest transition italic" href="{{ route('auth.login') }}" wire:navigate>
                            ← Voltar para Identificação
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="mt-8 text-center">
            <p class="text-[8px] font-black text-slate-700 uppercase tracking-[0.4em] italic">
                BingBing Security Protocol // 2026
            </p>
        </div>
    </div>
</div>