<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Notification\PushSubscription;

new class extends Component
{
    public array $permissions = [
        'notifications' => false,
        'audio' => false,
        'location' => false,
    ];

    public bool $allGranted = false;
    public bool $isDismissed = false;
    public bool $showDebug = false;

    public function mount(): void
    {
        $this->checkPermissions();
        $this->isDismissed = session('permissions_dismissed', false);
        
        // Debug: mostra se está em ambiente local
        $this->showDebug = config('app.debug', false);
        
        \Log::info('🔍 Permissions Component Mount', [
            'isDismissed' => $this->isDismissed,
            'hasNotifications' => $this->permissions['notifications'],
            'showNotice' => $this->showNotice,
        ]);
    }

    #[Computed]
    public function showNotice(): bool
    {
        if ($this->isDismissed) {
            \Log::info('❌ Permissions: Dismissed na session');
            return false;
        }

        $user = Auth::user();
        if (!$user) {
            \Log::info('❌ Permissions: Sem usuário');
            return false;
        }

        $hasNotifications = PushSubscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        \Log::info('🔔 Permissions Check', [
            'hasNotifications' => $hasNotifications,
            'shouldShow' => !$hasNotifications,
        ]);

        return !$hasNotifications;
    }

    private function checkPermissions(): void
    {
        $user = Auth::user();
        if (!$user) return;

        $this->permissions['notifications'] = PushSubscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function dismiss(): void
    {
        session(['permissions_dismissed' => true]);
        $this->isDismissed = true;
        
        \Log::info('✅ Permissions: Dismissed e salvo na session');
    }

    public function resetForDebug(): void
    {
        session()->forget('permissions_dismissed');
        $this->isDismissed = false;
        $this->checkPermissions();
        
        \Log::info('🔄 Permissions: RESET realizado');
        
        $this->dispatch('notify', [
            'type' => 'info',
            'text' => '🔄 Banner de permissões resetado!'
        ]);
    }

    public function refreshStatus(): void
    {
        $this->checkPermissions();
        
        // Se todas as permissões foram concedidas, esconde o aviso
        if ($this->permissions['notifications']) {
            $this->dismiss();
        }
        
        \Log::info('🔄 Permissions: Status refreshed');
    }
}; ?>

<div>
    {{-- BOTÃO DE DEBUG (só aparece em ambiente local) --}}
    @if($showDebug)
        <div class="bg-yellow-500/10 border-b border-yellow-500/20 backdrop-blur-sm">
            <div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-[10px] font-black text-yellow-400 uppercase">🛠️ Debug Mode</span>
                    <span class="text-[9px] text-slate-400">
                        Dismissed: <strong class="{{ $isDismissed ? 'text-red-400' : 'text-green-400' }}">{{ $isDismissed ? 'SIM' : 'NÃO' }}</strong> | 
                        Show: <strong class="{{ $this->showNotice ? 'text-green-400' : 'text-red-400' }}">{{ $this->showNotice ? 'SIM' : 'NÃO' }}</strong> |
                        Notifications: <strong class="{{ $permissions['notifications'] ? 'text-green-400' : 'text-red-400' }}">{{ $permissions['notifications'] ? 'SIM' : 'NÃO' }}</strong>
                    </span>
                </div>
                <button 
                    wire:click="resetForDebug"
                    class="px-3 py-1 bg-yellow-600 hover:bg-yellow-500 text-white text-[9px] font-black uppercase rounded-lg transition-all">
                    🔄 Reset Banner
                </button>
            </div>
        </div>
    @endif

    @if($this->showNotice && !$isDismissed)
        <div 
            x-data="{
                permissions: @entangle('permissions').live,
                allGranted: @entangle('allGranted').live,
                loading: false,
                show: true,
                
                async checkAllPermissions() {
                    console.log('🔍 Verificando permissões...');
                    
                    if ('Notification' in window) {
                        this.permissions.notifications = Notification.permission === 'granted';
                        console.log('🔔 Notificações:', Notification.permission);
                    }
                    
                    this.permissions.audio = await this.checkAudioPermission();
                    console.log('🔊 Áudio:', this.permissions.audio);
                    
                    if ('geolocation' in navigator) {
                        try {
                            const result = await navigator.permissions.query({ name: 'geolocation' });
                            this.permissions.location = result.state === 'granted';
                            console.log('📍 Localização:', result.state);
                        } catch (e) {
                            this.permissions.location = false;
                            console.log('📍 Localização: erro', e);
                        }
                    }
                    
                    this.allGranted = this.permissions.notifications && this.permissions.audio && this.permissions.location;
                    console.log('✅ Todas concedidas?', this.allGranted);
                    
                    if (this.allGranted) {
                        console.log('⏳ Auto-fechando em 1.5s...');
                        setTimeout(() => {
                            this.show = false;
                            @this.dismiss();
                        }, 1500);
                    }
                },
                
                async checkAudioPermission() {
                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;
                        const audioCtx = new AudioContext();
                        const allowed = audioCtx.state === 'running';
                        audioCtx.close();
                        return allowed;
                    } catch {
                        return false;
                    }
                },
                
                async requestAllPermissions() {
                    console.log('🚀 Solicitando todas as permissões...');
                    this.loading = true;
                    
                    try {
                        if (!this.permissions.notifications) {
                            console.log('📢 Solicitando notificações...');
                            await this.requestNotifications();
                        }
                        
                        if (!this.permissions.audio) {
                            console.log('🔊 Solicitando áudio...');
                            await this.requestAudio();
                        }
                        
                        if (!this.permissions.location) {
                            console.log('📍 Solicitando localização...');
                            await this.requestLocation();
                        }
                        
                        await this.checkAllPermissions();
                        
                        if (this.allGranted) {
                            this.showSuccessMessage();
                            console.log('✅ Todas concedidas! Fechando...');
                            setTimeout(() => {
                                this.show = false;
                                @this.refreshStatus();
                            }, 1500);
                        }
                    } catch (error) {
                        console.error('❌ Erro ao solicitar permissões:', error);
                        this.showErrorMessage(error.message);
                    } finally {
                        this.loading = false;
                    }
                },
                
                async requestNotifications() {
                    if (typeof requestNotificationPermission === 'undefined') {
                        throw new Error('Sistema de notificações não disponível');
                    }
                    
                    await requestNotificationPermission();
                    this.permissions.notifications = Notification.permission === 'granted';
                    console.log('🔔 Resultado notificações:', Notification.permission);
                },
                
                async requestAudio() {
                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;
                        const audioCtx = new AudioContext();
                        
                        if (audioCtx.state === 'suspended') {
                            await audioCtx.resume();
                        }
                        
                        const oscillator = audioCtx.createOscillator();
                        const gainNode = audioCtx.createGain();
                        gainNode.gain.value = 0.001;
                        oscillator.connect(gainNode);
                        gainNode.connect(audioCtx.destination);
                        oscillator.start();
                        oscillator.stop(audioCtx.currentTime + 0.01);
                        
                        this.permissions.audio = true;
                        audioCtx.close();
                        console.log('🔊 Áudio ativado com sucesso');
                    } catch (error) {
                        console.error('❌ Erro ao ativar áudio:', error);
                        this.permissions.audio = false;
                    }
                },
                
                async requestLocation() {
                    return new Promise((resolve) => {
                        if (!('geolocation' in navigator)) {
                            console.log('📍 Geolocalização não disponível');
                            resolve(null);
                            return;
                        }
                        
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                this.permissions.location = true;
                                console.log('📍 Localização concedida:', position.coords);
                                resolve(position);
                            },
                            (error) => {
                                this.permissions.location = false;
                                console.log('📍 Localização negada:', error.message);
                                resolve(null);
                            },
                            { timeout: 5000, enableHighAccuracy: false }
                        );
                    });
                },
                
                showSuccessMessage() {
                    if (window.Livewire) {
                        Livewire.dispatch('notify', { 
                            type: 'success', 
                            text: '✅ Permissões ativadas!' 
                        });
                    }
                },
                
                showErrorMessage(message) {
                    if (window.Livewire) {
                        Livewire.dispatch('notify', { 
                            type: 'error', 
                            text: '❌ ' + message 
                        });
                    }
                },
                
                close() {
                    console.log('❌ Fechando manualmente...');
                    this.show = false;
                    @this.dismiss();
                }
            }"
            x-init="
                console.log('🎬 Permissions Banner inicializado');
                await checkAllPermissions();
            "
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform -translate-y-2"
            class="bg-indigo-600/5 border-b border-indigo-500/10 backdrop-blur-sm">
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5">
                <div class="flex items-center justify-between gap-3">
                    
                    {{-- Lado Esquerdo: Ícone e Texto Compacto --}}
                    <div class="flex items-center gap-2.5 flex-1 min-w-0">
                        <div class="relative flex-shrink-0">
                            <div class="w-8 h-8 rounded-lg bg-indigo-600/20 flex items-center justify-center text-indigo-400 border border-indigo-500/20">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <div x-show="!allGranted" class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-orange-500 rounded-full animate-pulse"></div>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] text-slate-300 font-bold uppercase tracking-tight truncate">
                                <span x-show="!allGranted">Ative permissões para melhor experiência</span>
                                <span x-show="allGranted" class="text-emerald-400">✓ Permissões ativadas com sucesso!</span>
                            </p>
                        </div>
                    </div>

                    {{-- Centro: Status Compacto (3 badges pequenos) --}}
                    <div x-show="!allGranted" class="hidden sm:flex items-center gap-1.5">
                        <div 
                            :class="permissions.notifications ? 'bg-emerald-500/20 border-emerald-500/40' : 'bg-slate-700/30 border-slate-600/40'"
                            class="flex items-center gap-1 px-2 py-1 rounded border transition-all duration-300"
                            title="Notificações">
                            <svg class="w-3 h-3" :class="permissions.notifications ? 'text-emerald-400' : 'text-slate-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span class="text-[8px] font-bold uppercase" :class="permissions.notifications ? 'text-emerald-400' : 'text-slate-500'">Push</span>
                        </div>

                        <div 
                            :class="permissions.audio ? 'bg-blue-500/20 border-blue-500/40' : 'bg-slate-700/30 border-slate-600/40'"
                            class="flex items-center gap-1 px-2 py-1 rounded border transition-all duration-300"
                            title="Áudio">
                            <svg class="w-3 h-3" :class="permissions.audio ? 'text-blue-400' : 'text-slate-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                            </svg>
                            <span class="text-[8px] font-bold uppercase" :class="permissions.audio ? 'text-blue-400' : 'text-slate-500'">Som</span>
                        </div>

                        <div 
                            :class="permissions.location ? 'bg-purple-500/20 border-purple-500/40' : 'bg-slate-700/30 border-slate-600/40'"
                            class="flex items-center gap-1 px-2 py-1 rounded border transition-all duration-300"
                            title="Localização">
                            <svg class="w-3 h-3" :class="permissions.location ? 'text-purple-400' : 'text-slate-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            <span class="text-[8px] font-bold uppercase" :class="permissions.location ? 'text-purple-400' : 'text-slate-500'">Local</span>
                        </div>
                    </div>

                    {{-- Lado Direito: Botão Compacto --}}
                    <div class="flex items-center gap-2">
                        <button 
                            x-show="!allGranted"
                            @click="requestAllPermissions()"
                            :disabled="loading"
                            class="group relative overflow-hidden px-4 py-1.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 disabled:from-indigo-800 disabled:to-purple-800 text-white text-[9px] font-black uppercase tracking-wider rounded-lg transition-all duration-300 flex items-center gap-2 shadow-sm disabled:cursor-not-allowed">
                            
                            <div class="absolute inset-0 bg-white/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                            
                            <span class="relative" x-text="loading ? 'Ativando...' : 'Ativar'"></span>
                            
                            <svg x-show="!loading" class="relative w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            
                            <div x-show="loading" class="relative w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                        </button>

                        {{-- Botão Fechar --}}
                        <button 
                            @click="close()"
                            class="text-slate-400 hover:text-white transition-colors p-1"
                            title="Fechar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>