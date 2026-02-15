<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
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

    public function resetPassword()
    {
        try {
            $validated = $this->validate([
                'token' => ['required'],
                'email' => ['required', 'email'],
                'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            ], [
                'password.confirmed' => 'As senhas não coincidem.',
            ]);

            $status = Password::reset(
                [
                    'email' => $this->email,
                    'password' => $this->password,
                    'password_confirmation' => $this->password_confirmation,
                    'token' => $this->token,
                ],
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($user));
                    
                    Log::info('Password reset successful', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'ip' => request()->ip(),
                    ]);
                }
            );

            if ($status !== Password::PASSWORD_RESET) {
                Log::warning('Password reset failed', [
                    'email' => $this->email,
                    'status' => $status,
                    'ip' => request()->ip(),
                ]);
                
                $this->addError('email', __($status));
                return;
            }

            session()->flash('status', 'Senha redefinida com sucesso! Você já pode fazer login.');
            
            return redirect()->route('auth.login');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Password reset validation failed', [
                'email' => $this->email,
                'ip' => request()->ip(),
                'errors' => $e->errors(),
            ]);

            throw $e;

        } catch (\Exception $e) {
            Log::error('Password reset process failed', [
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip(),
            ]);

            $this->addError('email', 'Erro ao redefinir senha. Tente novamente.');
        }
    }
};
?>

<div class="min-h-screen flex flex-col justify-center items-center bg-[#020408] px-4 relative overflow-hidden">
    {{-- Efeitos de fundo --}}
    <div class="absolute top-1/4 -left-32 w-96 h-96 bg-emerald-600/10 blur-[140px] rounded-full animate-pulse"></div>
    <div class="absolute bottom-1/4 -right-32 w-96 h-96 bg-blue-600/10 blur-[140px] rounded-full animate-pulse"></div>
    
    {{-- Grid background --}}
    <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px] [mask-image:radial-gradient(ellipse_80%_50%_at_50%_50%,black,transparent)]"></div>

    <div class="w-full max-w-md relative z-10">
        
        {{-- Header --}}
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-3 mb-6">
                <div class="w-14 h-14 bg-gradient-to-br from-emerald-600 via-emerald-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-2xl shadow-emerald-500/30 relative">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-400/20 to-transparent rounded-2xl"></div>
                </div>
            </div>
            <h1 class="text-4xl md:text-5xl font-black text-white tracking-tight mb-3">
                Criar nova senha
            </h1>
            <p class="text-slate-400 text-sm font-medium">
                Digite sua nova senha de acesso
            </p>
        </div>

        {{-- Card --}}
        <div class="bg-[#0a0d14]/90 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden">
            {{-- Gradient border top --}}
            <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-transparent via-emerald-500 to-transparent"></div>

            <form wire:submit.prevent="resetPassword" class="space-y-6">
                
                {{-- E-mail (readonly) --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                        E-mail
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                            </svg>
                        </div>
                        <input 
                            wire:model="email" 
                            type="email" 
                            required 
                            readonly
                            class="w-full bg-white/5 border border-white/10 rounded-2xl pl-12 pr-5 py-4 text-slate-400 outline-none font-medium text-sm cursor-not-allowed"
                        >
                    </div>
                    @error('email') 
                        <div class="flex items-center gap-2 mt-2 ml-1">
                            <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-xs text-red-400 font-medium">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                {{-- Nova Senha --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                        Nova senha
                    </label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500 group-focus-within:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <input 
                            wire:model="password" 
                            type="password" 
                            required 
                            autofocus
                            placeholder="••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl pl-12 pr-5 py-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500/50 transition-all outline-none font-medium text-sm"
                        >
                    </div>
                    @error('password') 
                        <div class="flex items-center gap-2 mt-2 ml-1">
                            <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-xs text-red-400 font-medium">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                {{-- Confirmação de Senha --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                        Confirmar nova senha
                    </label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500 group-focus-within:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <input 
                            wire:model="password_confirmation" 
                            type="password" 
                            required
                            placeholder="••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl pl-12 pr-5 py-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500/50 transition-all outline-none font-medium text-sm"
                        >
                    </div>
                </div>

                {{-- Password requirements --}}
                <div class="bg-slate-500/5 border border-slate-500/10 rounded-2xl p-4">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Requisitos da senha:</h4>
                    <ul class="space-y-2">
                        <li class="flex items-center gap-2 text-xs text-slate-400">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Mínimo de 8 caracteres
                        </li>
                        <li class="flex items-center gap-2 text-xs text-slate-400">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Recomendado: letras, números e símbolos
                        </li>
                    </ul>
                </div>

                {{-- Submit button --}}
                <div class="pt-2">
                    <button 
                        type="submit" 
                        wire:loading.attr="disabled"
                        class="relative w-full bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-600 text-white py-4 rounded-2xl font-bold text-sm transition-all shadow-xl shadow-emerald-600/25 active:scale-[0.98] flex justify-center items-center group overflow-hidden disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <div class="absolute inset-0 bg-gradient-to-r from-emerald-400/0 via-white/20 to-emerald-400/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000"></div>
                        <span wire:loading.remove wire:target="resetPassword" class="relative flex items-center gap-2">
                            Redefinir senha
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </span>
                        <span wire:loading wire:target="resetPassword" class="relative flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Salvando nova senha...
                        </span>
                    </button>
                    
                    {{-- Back to login --}}
                    <div class="mt-6 text-center">
                        <a 
                            class="inline-flex items-center gap-2 text-sm font-semibold text-slate-400 hover:text-white transition-colors" 
                            href="{{ route('auth.login') }}" 
                            wire:navigate
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Voltar para o login
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
    </div>

    <x-footer />
</div>