<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        try {
            $status = Password::sendResetLink(['email' => $this->email]);

            if ($status !== Password::RESET_LINK_SENT) {
                Log::info('Password reset link failed', [
                    'email' => $this->email,
                    'status' => $status,
                    'ip' => request()->ip(),
                ]);

                $this->addError('email', __($status));
                return;
            }

            Log::info('Password reset link sent', [
                'email' => $this->email,
                'ip' => request()->ip(),
            ]);

            $this->reset('email');
            session()->flash('status', __($status));

        } catch (\Exception $e) {
            Log::error('Password reset request failed', [
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip(),
            ]);

            $this->addError('email', 'Erro ao enviar link de recuperação.');
        }
    }
};
?>

<div class="min-h-screen flex flex-col justify-center items-center bg-[#020408] px-4 relative overflow-hidden">
    {{-- Efeitos de fundo --}}
    <div class="absolute top-1/4 -left-32 w-96 h-96 bg-cyan-600/10 blur-[140px] rounded-full animate-pulse"></div>
    <div class="absolute bottom-1/4 -right-32 w-96 h-96 bg-blue-600/10 blur-[140px] rounded-full animate-pulse"></div>

    {{-- Grid background --}}
    <div
        class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px] [mask-image:radial-gradient(ellipse_80%_50%_at_50%_50%,black,transparent)]">
    </div>

    <div class="w-full max-w-md relative z-10">

        {{-- Header --}}
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-3 mb-6">
                <div
                    class="w-14 h-14 bg-gradient-to-br from-cyan-600 via-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-2xl shadow-cyan-500/30 relative">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
                        </path>
                    </svg>
                    <div class="absolute inset-0 bg-gradient-to-br from-cyan-400/20 to-transparent rounded-2xl"></div>
                </div>
            </div>
            <h1 class="text-4xl md:text-5xl font-black text-white tracking-tight mb-3">
                Recuperar senha
            </h1>
            <p class="text-slate-400 text-sm font-medium max-w-sm mx-auto">
                Sem problemas! Enviaremos um link de redefinição para seu e-mail
            </p>
        </div>

        {{-- Card --}}
        <div
            class="bg-[#0a0d14]/90 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden">
            {{-- Gradient border top --}}
            <div
                class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-transparent via-cyan-500 to-transparent">
            </div>

            {{-- Success message --}}
            @if (session('status'))
                <div
                    class="mb-8 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-5 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-emerald-500/20 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-emerald-400 text-sm mb-1">E-mail enviado com sucesso!</h3>
                            <p class="text-xs text-emerald-400/80 leading-relaxed">
                                Verifique sua caixa de entrada e clique no link para redefinir sua senha.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <form wire:submit="sendPasswordResetLink" class="space-y-6">

                {{-- E-mail --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">
                        E-mail cadastrado
                    </label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500 group-focus-within:text-cyan-500 transition-colors"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207">
                                </path>
                            </svg>
                        </div>
                        <input wire:model="email" type="email" required autofocus placeholder="seu@email.com"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl pl-12 pr-5 py-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50 transition-all outline-none font-medium text-sm">
                    </div>
                    @error('email')
                        <div class="flex items-center gap-2 mt-2 ml-1">
                            <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-xs text-red-400 font-medium">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                {{-- Info box --}}
                <div class="bg-blue-500/5 border border-blue-500/10 rounded-2xl p-4 flex items-start gap-3">
                    <div class="w-5 h-5 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Você receberá um e-mail com instruções para criar uma nova senha. O link é válido por 60
                        minutos.
                    </p>
                </div>

                {{-- Submit button --}}
                <div class="pt-2">
                    <button type="submit"
                        class="relative w-full bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white py-4 rounded-2xl font-bold text-sm transition-all shadow-xl shadow-cyan-600/25 active:scale-[0.98] flex justify-center items-center group overflow-hidden">
                        <div
                            class="absolute inset-0 bg-gradient-to-r from-cyan-400/0 via-white/20 to-cyan-400/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000">
                        </div>
                        <span wire:loading.remove wire:target="sendPasswordResetLink"
                            class="relative flex items-center gap-2">
                            Enviar link de recuperação
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </span>
                        <span wire:loading wire:target="sendPasswordResetLink" class="relative flex items-center">
                            <svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Enviando e-mail...
                        </span>
                    </button>

                    {{-- Back to login --}}
                    <div class="mt-6 text-center">
                        <a class="inline-flex items-center gap-2 text-sm font-semibold text-slate-400 hover:text-white transition-colors"
                            href="{{ route('auth.login') }}" wire:navigate>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
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
