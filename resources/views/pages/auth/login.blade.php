<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        try {
            $this->validate();
            
            $this->form->authenticate();
            
            Session::regenerate();

            Log::info('User logged in', [
                'user_id' => auth()->id(),
                'email' => auth()->user()->email,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $this->redirectIntended(default: route('dashboard', absolute: false));

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Login attempt failed - validation', [
                'email' => $this->form->email ?? null,
                'ip' => request()->ip(),
                'errors' => $e->errors(),
            ]);

            throw $e;

        } catch (\Exception $e) {
            Log::error('Login process failed', [
                'email' => $this->form->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip(),
            ]);

            $this->addError('email', 'Erro ao processar login. Tente novamente.');
        }
    }
};
?>

<div class="min-h-screen flex flex-col justify-center items-center bg-[#020408] px-4 relative overflow-hidden">
    {{-- Efeitos de fundo --}}
    <div class="absolute top-1/4 -left-32 w-96 h-96 bg-blue-600/10 blur-[140px] rounded-full animate-pulse"></div>
    <div class="absolute bottom-1/4 -right-32 w-96 h-96 bg-purple-600/10 blur-[140px] rounded-full animate-pulse"></div>
    
    {{-- Grid background --}}
    <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px] [mask-image:radial-gradient(ellipse_80%_50%_at_50%_50%,black,transparent)]"></div>

    <div class="w-full max-w-md relative z-10">
        
        {{-- Header --}}
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-3 mb-6">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-600 via-blue-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-2xl shadow-blue-500/30 relative">
                    <span class="text-white font-black text-2xl">B²</span>
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-400/20 to-transparent rounded-2xl"></div>
                </div>
            </div>
            <h1 class="text-4xl md:text-5xl font-black text-white tracking-tight mb-3">
                Bem-vindo de volta
            </h1>
            <p class="text-slate-400 text-sm font-medium">
                Acesse sua conta e continue jogando
            </p>
        </div>

        {{-- Card --}}
        <div class="bg-[#0a0d14]/90 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden">
            {{-- Gradient border top --}}
            <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-transparent via-blue-500 to-transparent"></div>
            
            {{-- Success message --}}
            @if (session('status'))
                <div class="mb-6 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-4 flex items-center gap-3">
                    <div class="w-8 h-8 bg-emerald-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-emerald-400">{{ session('status') }}</p>
                </div>
            @endif

            <form wire:submit="login" class="space-y-6">
                
                {{-- E-mail --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                        E-mail
                    </label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                            </svg>
                        </div>
                        <input 
                            wire:model="form.email" 
                            type="email" 
                            autocomplete="username" 
                            required 
                            autofocus
                            placeholder="seu@email.com"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl pl-12 pr-5 py-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50 transition-all outline-none font-medium text-sm"
                        >
                    </div>
                    @error('form.email') 
                        <div class="flex items-center gap-2 mt-2 ml-1">
                            <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-xs text-red-400 font-medium">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                {{-- Senha --}}
                <div class="space-y-2">
                    <div class="flex justify-between items-center ml-1">
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                            Senha
                        </label>
                        @if (Route::has('auth.forgot-password'))
                            <a 
                                class="text-xs font-semibold text-blue-500 hover:text-blue-400 transition-colors" 
                                href="{{ route('auth.forgot-password') }}" 
                                wire:navigate
                            >
                                Esqueceu a senha?
                            </a>
                        @endif
                    </div>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <input 
                            wire:model="form.password" 
                            type="password" 
                            autocomplete="current-password" 
                            required
                            placeholder="••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl pl-12 pr-5 py-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50 transition-all outline-none font-medium text-sm"
                        >
                    </div>
                    @error('form.password') 
                        <div class="flex items-center gap-2 mt-2 ml-1">
                            <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-xs text-red-400 font-medium">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="flex items-center pt-2">
                    <label for="remember" class="inline-flex items-center cursor-pointer group">
                        <input 
                            wire:model="form.remember" 
                            id="remember" 
                            type="checkbox" 
                            class="rounded-lg border-white/20 text-blue-600 focus:ring-blue-500 focus:ring-offset-0 focus:ring-2 bg-white/5 w-5 h-5 transition-all cursor-pointer"
                        >
                        <span class="ml-3 text-sm font-medium text-slate-400 group-hover:text-slate-300 transition-colors">
                            Manter conectado
                        </span>
                    </label>
                </div>

                {{-- Submit button --}}
                <div class="pt-4">
                    <button 
                        type="submit" 
                        class="relative w-full bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-600 text-white py-4 rounded-2xl font-bold text-sm transition-all shadow-xl shadow-blue-600/25 active:scale-[0.98] flex justify-center items-center group overflow-hidden"
                    >
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-400/0 via-white/20 to-blue-400/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000"></div>
                        <span wire:loading.remove wire:target="login" class="relative flex items-center gap-2">
                            Entrar na plataforma
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </span>
                        <span wire:loading wire:target="login" class="relative flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Autenticando...
                        </span>
                    </button>
                    
                    {{-- Divider --}}
                    <div class="relative my-8">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-white/10"></div>
                        </div>
                        <div class="relative flex justify-center text-xs">
                            <span class="bg-[#0a0d14] px-4 text-slate-500 font-medium uppercase tracking-wider">Novo por aqui?</span>
                        </div>
                    </div>
                    
                    {{-- Register link --}}
                    <a 
                        class="block w-full text-center bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 text-white py-4 rounded-2xl font-semibold text-sm transition-all" 
                        href="{{ route('auth.register') }}" 
                        wire:navigate
                    >
                        Criar nova conta
                    </a>
                </div>
            </form>
        </div>

    </div>

    <x-footer />
</div>