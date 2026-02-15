<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
        try {
            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'nickname' => ['required', 'string', 'max:30', 'unique:users,nickname', 'alpha_dash'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
                'birth_date' => ['required', 'date', 'before:-18 years', 'after:1920-01-01'],
            ], [
                'birth_date.before' => 'Você precisa ter pelo menos 18 anos.',
                'birth_date.after' => 'Data inválida.',
                'nickname.unique' => 'Esse apelido já está sendo usado.',
                'nickname.alpha_dash' => 'Use apenas letras, números, - e _',
                'email.unique' => 'Este e-mail já está cadastrado.',
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

            Log::info('User registered', [
                'user_id' => $user->id,
                'email' => $user->email,
                'nickname' => $user->nickname,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $this->redirect(route('dashboard', absolute: false), navigate: true);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Registration attempt failed - validation', [
                'email' => $this->email,
                'nickname' => $this->nickname,
                'ip' => request()->ip(),
                'errors' => $e->errors(),
            ]);

            throw $e;

        } catch (\Exception $e) {
            Log::error('Registration process failed', [
                'email' => $this->email,
                'nickname' => $this->nickname,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip(),
            ]);

            session()->flash('error', 'Erro ao criar conta. Tente novamente.');
            $this->redirect(route('register', absolute: false));
        }
    }
};
?>

<div class="min-h-screen flex flex-col justify-center items-center bg-[#020408] px-4 py-10 relative overflow-hidden">
    {{-- Efeitos de fundo --}}
    <div class="absolute top-1/4 -left-32 w-96 h-96 bg-purple-600/10 blur-[140px] rounded-full animate-pulse"></div>
    <div class="absolute bottom-1/4 -right-32 w-96 h-96 bg-blue-600/10 blur-[140px] rounded-full animate-pulse"></div>
    
    {{-- Grid background --}}
    <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px] [mask-image:radial-gradient(ellipse_80%_50%_at_50%_50%,black,transparent)]"></div>

    <div class="w-full max-w-lg relative z-10">
        
        {{-- Header --}}
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-3 mb-6">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-600 via-purple-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-2xl shadow-purple-500/30 relative">
                    <span class="text-white font-black text-2xl">B²</span>
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-400/20 to-transparent rounded-2xl"></div>
                </div>
            </div>
            <h1 class="text-4xl md:text-5xl font-black text-white tracking-tight mb-3">
                Junte-se ao BingBing
            </h1>
            <p class="text-slate-400 text-sm font-medium">
                Crie sua conta e comece a jogar agora mesmo
            </p>
        </div>

        {{-- Card --}}
        <div class="bg-[#0a0d14]/90 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden">
            {{-- Gradient border top --}}
            <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-transparent via-purple-500 to-transparent"></div>

            <form wire:submit="register" class="space-y-5">
                
                {{-- Nome e Nickname --}}
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                            Nome completo
                        </label>
                        <input 
                            wire:model="name" 
                            type="text" 
                            required 
                            autofocus 
                            placeholder="João Silva"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3.5 text-white text-sm placeholder:text-slate-600 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 outline-none transition-all font-medium"
                        >
                        @error('name') 
                            <div class="flex items-center gap-2 mt-2 ml-1">
                                <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-xs text-red-400 font-medium">{{ $message }}</span>
                            </div>
                        @enderror
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                            Apelido
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="text-slate-500 font-medium">@</span>
                            </div>
                            <input 
                                wire:model="nickname" 
                                type="text" 
                                required 
                                placeholder="sortudo"
                                class="w-full bg-white/5 border border-white/10 rounded-2xl pl-9 pr-4 py-3.5 text-white text-sm placeholder:text-slate-600 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 outline-none transition-all font-medium"
                            >
                        </div>
                        @error('nickname') 
                            <div class="flex items-center gap-2 mt-2 ml-1">
                                <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-xs text-red-400 font-medium">{{ $message }}</span>
                            </div>
                        @enderror
                    </div>
                </div>

                {{-- E-mail --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                        E-mail
                    </label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500 group-focus-within:text-purple-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                            </svg>
                        </div>
                        <input 
                            wire:model="email" 
                            type="email" 
                            required 
                            placeholder="seu@email.com"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl pl-12 pr-4 py-3.5 text-white text-sm placeholder:text-slate-600 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 outline-none transition-all font-medium"
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

                {{-- Data de Nascimento --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                        Data de nascimento
                    </label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500 group-focus-within:text-purple-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <input 
                            wire:model="birth_date" 
                            type="date" 
                            required
                            class="w-full bg-white/5 border border-white/10 rounded-2xl pl-12 pr-4 py-3.5 text-white text-sm focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 outline-none transition-all [color-scheme:dark] font-medium"
                        >
                    </div>
                    @error('birth_date') 
                        <div class="flex items-center gap-2 mt-2 ml-1">
                            <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-xs text-red-400 font-medium">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                {{-- Senha e Confirmação --}}
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                            Senha
                        </label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-slate-500 group-focus-within:text-purple-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <input 
                                wire:model="password" 
                                type="password" 
                                required 
                                placeholder="••••••••"
                                class="w-full bg-white/5 border border-white/10 rounded-2xl pl-12 pr-4 py-3.5 text-white text-sm placeholder:text-slate-600 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 outline-none transition-all font-medium"
                            >
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                            Confirmar senha
                        </label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-slate-500 group-focus-within:text-purple-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <input 
                                wire:model="password_confirmation" 
                                type="password" 
                                required 
                                placeholder="••••••••"
                                class="w-full bg-white/5 border border-white/10 rounded-2xl pl-12 pr-4 py-3.5 text-white text-sm placeholder:text-slate-600 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 outline-none transition-all font-medium"
                            >
                        </div>
                    </div>
                </div>
                @error('password') 
                    <div class="flex items-center gap-2 mt-2 ml-1">
                        <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-xs text-red-400 font-medium">{{ $message }}</span>
                    </div>
                @enderror

                {{-- Submit button --}}
                <div class="pt-4">
                    <button 
                        type="submit" 
                        class="relative w-full bg-gradient-to-r from-purple-600 to-purple-500 hover:from-purple-500 hover:to-purple-600 text-white py-4 rounded-2xl font-bold text-sm transition-all shadow-xl shadow-purple-600/25 active:scale-[0.98] flex justify-center items-center group overflow-hidden"
                    >
                        <div class="absolute inset-0 bg-gradient-to-r from-purple-400/0 via-white/20 to-purple-400/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000"></div>
                        <span wire:loading.remove wire:target="register" class="relative flex items-center gap-2">
                            Criar minha conta
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </span>
                        <span wire:loading wire:target="register" class="relative flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Criando conta...
                        </span>
                    </button>
                    
                    {{-- Divider --}}
                    <div class="relative my-8">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-white/10"></div>
                        </div>
                        <div class="relative flex justify-center text-xs">
                            <span class="bg-[#0a0d14] px-4 text-slate-500 font-medium uppercase tracking-wider">Já tem uma conta?</span>
                        </div>
                    </div>
                    
                    {{-- Login link --}}
                    <a 
                        class="block w-full text-center bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 text-white py-4 rounded-2xl font-semibold text-sm transition-all" 
                        href="{{ route('auth.login') }}" 
                        wire:navigate
                    >
                        Fazer login
                    </a>
                </div>
            </form>
        </div>
        
    </div>

    <x-footer />
</div>