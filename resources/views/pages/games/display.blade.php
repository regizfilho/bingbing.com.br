<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Game\Game;
use App\Models\Game\Winner;
use App\Models\Game\Prize;
use App\Models\GameAudio;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

new #[Layout('layouts.display')] class extends Component {
    public string $gameUuid;
    public Game $game;

    public array $currentDraws = [];
    public array $drawnNumbers = [];
    public array $roundWinners = [];
    public array $prizes = [];
    public $lastDrawId = null;

    public bool $audioEnabled = false;
    public bool $showAudioSettings = false;
    public bool $darkMode = true;

    public int $cachedDrawCount = 0;
    public int $cachedWinnerCount = 0;

    public function mount(string $uuid): void
    {
        $this->gameUuid = $uuid;
        
        // Carregar preferências do cookie
        $this->darkMode = request()->cookie('display_dark_mode', 'true') === 'true';
        $this->audioEnabled = request()->cookie('display_audio_enabled', 'false') === 'true';
        
        $this->reloadBaseGame();
        $this->loadGameData();

        $this->cachedDrawCount = count($this->drawnNumbers);
        $this->cachedWinnerCount = count($this->roundWinners);

        $this->dispatch('clear-audio-cache');
        $this->dispatch('audio-toggle', enabled: $this->audioEnabled);
    }

    #[On('echo:game.{gameUuid},.GameUpdated')]
    public function handleGameUpdate(): void
    {
        try {
            $oldStatus = $this->game->status;
            $oldDrawCount = $this->cachedDrawCount;
            $oldWinnerCount = $this->cachedWinnerCount;

            $this->reloadBaseGame();
            $this->loadGameData();

            $newDrawCount = count($this->drawnNumbers);
            $newWinnerCount = count($this->roundWinners);

            if ($oldStatus !== $this->game->status) {
                if ($this->game->status === 'active') {
                    $this->dispatchAudioEvent('system', 'inicio_partida');
                } elseif ($this->game->status === 'finished') {
                    $this->dispatchAudioEvent('system', 'fim_partida');
                    
                    // Limpar cookies quando o jogo terminar
                    cookie()->queue(cookie()->forget('display_dark_mode'));
                    cookie()->queue(cookie()->forget('display_audio_enabled'));
                }
            }

            if ($newDrawCount > $oldDrawCount) {
                $this->dispatchAudioEvent('number');
            }

            if ($newWinnerCount > $oldWinnerCount) {
                $this->dispatchAudioEvent('winner');
            }

            $this->cachedDrawCount = $newDrawCount;
            $this->cachedWinnerCount = $newWinnerCount;

            $this->dispatch('clear-audio-cache');
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            Log::error('Display update failed', [
                'game_uuid' => $this->gameUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function dispatchAudioEvent(string $category, ?string $systemName = null): void
    {
        if ($category === 'system' && $systemName) {
            $this->dispatch('play-sound', type: 'system', name: $systemName);
            return;
        }

        $audioSetting = $this->game->audioSettings()->where('audio_category', $category)->where('is_enabled', true)->first();

        if (!$audioSetting || !$audioSetting->audio) {
            return;
        }

        $audio = $audioSetting->audio;

        $this->dispatch('play-sound', type: $category, audioId: $audio->id, name: $audio->name, audioType: $audio->audio_type, filePath: $audio->file_path, ttsText: $audio->tts_text, ttsVoice: $audio->tts_voice, ttsLanguage: $audio->tts_language, ttsRate: $audio->tts_rate, ttsPitch: $audio->tts_pitch, ttsVolume: $audio->tts_volume);
    }

    private function reloadBaseGame(): void
    {
        $this->game = Game::where('uuid', $this->gameUuid)
            ->with(['draws', 'players', 'creator', 'package', 'audioSettings.audio'])
            ->firstOrFail();
    }

    private function loadGameData(): void
    {
        $round = $this->game->current_round;
        $this->drawnNumbers = $this->game->draws()->where('round_number', $round)->orderBy('number', 'asc')->pluck('number')->toArray();
        $drawsRecords = $this->game->draws()->where('round_number', $round)->get()->sortByDesc('id')->values();
        $latest = $drawsRecords->first();
        $this->lastDrawId = $latest ? $latest->id : null;

        $this->currentDraws = $drawsRecords->take(10)->map(function ($draw) {
            return [
                'id' => $draw->id,
                'number' => $draw->number,
                'created_at' => $draw->created_at->format('H:i:s'),
                'unique_key' => "draw-{$draw->id}-{$draw->number}",
            ];
        })->toArray();

        $this->roundWinners = Winner::where('game_id', $this->game->id)->where('round_number', $round)->with('user')->latest()->get()->map(fn($w) => ['name' => $w->user->name])->toArray();

        $this->prizes = Prize::where('game_id', $this->game->id)->orderBy('position', 'asc')->get()->map(function ($p) {
            $winnerEntry = Winner::where('prize_id', $p->id)->with('user')->first();
            return [
                'id' => $p->id,
                'name' => $p->name,
                'position' => $p->position,
                'winner' => $winnerEntry ? $winnerEntry->user->name : null,
            ];
        })->toArray();
    }

    public function toggleAudio(): void 
    { 
        $this->audioEnabled = !$this->audioEnabled;
        

        $this->dispatch('audio-toggle', enabled: $this->audioEnabled);
    }

    public function toggleAudioSettings(): void 
    { 
        $this->showAudioSettings = !$this->showAudioSettings; 
    }

    public function toggleDarkMode(): void
    {
        $this->darkMode = !$this->darkMode;
        
        // Salvar no cookie (expira em 24 horas)
        cookie()->queue('display_dark_mode', $this->darkMode ? 'true' : 'false', 1440);
    }

    #[Computed]
    public function statusColor(): string 
    {
        return match ($this->game->status) { 
            'active' => 'emerald', 
            'waiting' => 'amber', 
            'finished' => 'slate', 
            'paused' => 'red', 
            default => 'blue' 
        };
    }

    #[Computed]
    public function statusLabel(): string 
    {
        return match ($this->game->status) { 
            'active' => 'AO VIVO', 
            'waiting' => 'AGUARDANDO', 
            'finished' => 'FINALIZADA', 
            'paused' => 'PAUSADA', 
            default => 'RASCUNHO' 
        };
    }
};
?>

<div wire:key="display-root-{{ $game->id }}" class="{{ $darkMode ? 'dark bg-slate-950' : 'bg-slate-50' }} min-h-screen w-full text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-x-hidden">

    <script>
        window.isControlPanel = false;
        window.isPublicDisplay = true;
    </script>

    {{-- Controles Flutuantes --}}
    <div class="fixed top-4 right-4 z-50 flex items-center gap-3">
        <button wire:click="toggleDarkMode" 
            class="w-12 h-12 rounded-full flex items-center justify-center backdrop-blur-xl border-2 shadow-2xl transition-all duration-300 hover:scale-110
            {{ $darkMode ? 'bg-slate-900/90 border-slate-700 text-amber-400' : 'bg-white/90 border-slate-300 text-slate-700' }}">
            @if($darkMode)
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/></svg>
            @else
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
            @endif
        </button>

        <button wire:click="toggleAudio" 
            class="relative w-12 h-12 rounded-full flex items-center justify-center backdrop-blur-xl border-2 shadow-2xl transition-all duration-300 hover:scale-110
            {{ $audioEnabled ? ($darkMode ? 'bg-blue-600/90 border-blue-500' : 'bg-blue-600/90 border-blue-500') : ($darkMode ? 'bg-slate-900/90 border-slate-700' : 'bg-white/90 border-slate-300') }}
            {{ $audioEnabled ? 'text-white' : ($darkMode ? 'text-slate-500' : 'text-slate-400') }}">
            @if($audioEnabled)
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
            @else
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
            @endif
            @if(!$audioEnabled)
                <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-red-500 border-2 {{ $darkMode ? 'border-slate-950' : 'border-slate-50' }} rounded-full"></div>
            @endif
        </button>

        <button wire:click="toggleAudioSettings" 
            class="w-12 h-12 rounded-full flex items-center justify-center backdrop-blur-xl border-2 shadow-2xl transition-all duration-300 hover:scale-110
            {{ $darkMode ? 'bg-slate-900/90 border-slate-700 text-slate-400' : 'bg-white/90 border-slate-300 text-slate-600' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </button>
    </div>

    {{-- Modal de Configurações de Áudio --}}
    @if ($showAudioSettings)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-md animate-fade-in" wire:click="toggleAudioSettings">
            <div class="{{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-200' }} border-2 rounded-3xl p-8 max-w-md w-full mx-4 shadow-2xl" wire:click.stop>
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold uppercase tracking-tight">Configurações</h3>
                    <button wire:click="toggleAudioSettings" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="{{ $darkMode ? 'bg-slate-800' : 'bg-slate-100' }} rounded-2xl p-4 flex items-center justify-between">
                        <span class="text-sm font-bold uppercase tracking-wider">Som Ativado</span>
                        <button wire:click="toggleAudio" class="relative w-14 h-7 rounded-full transition-colors {{ $audioEnabled ? 'bg-blue-600' : ($darkMode ? 'bg-slate-600' : 'bg-slate-300') }}">
                            <div class="absolute top-1 left-1 w-5 h-5 bg-white rounded-full transition-transform {{ $audioEnabled ? 'translate-x-7' : '' }} shadow-lg"></div>
                        </button>
                    </div>

                    @if(!$audioEnabled)
                        <div class="p-4 bg-amber-500/10 border-2 border-amber-500/30 rounded-2xl">
                            <p class="text-sm font-bold text-amber-600 dark:text-amber-400 mb-1">💡 Áudio Desativado</p>
                            <p class="text-xs text-slate-600 dark:text-slate-400">Clique no botão acima para ativar o som.</p>
                        </div>
                    @endif

                    @if ($this->game->package->is_free)
                        <div class="p-4 bg-orange-500/10 border-2 border-orange-500/30 rounded-2xl">
                            <p class="text-sm font-bold text-orange-600 dark:text-orange-400 mb-1">Plano Gratuito</p>
                            <p class="text-xs text-slate-600 dark:text-slate-400">Áudio personalizado disponível apenas em planos pagos.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @php
                                $numberSetting = $this->game->audioSettings()->where('audio_category', 'number')->first();
                                $winnerSetting = $this->game->audioSettings()->where('audio_category', 'winner')->first();
                            @endphp
                            @if ($numberSetting && $numberSetting->audio)
                                <div class="{{ $darkMode ? 'bg-slate-800' : 'bg-slate-100' }} rounded-2xl p-4">
                                    <p class="text-xs font-bold text-slate-500 mb-1 uppercase">Som de Sorteio</p>
                                    <p class="text-sm font-semibold">{{ $numberSetting->audio->name }}</p>
                                </div>
                            @endif
                            @if ($winnerSetting && $winnerSetting->audio)
                                <div class="{{ $darkMode ? 'bg-slate-800' : 'bg-slate-100' }} rounded-2xl p-4">
                                    <p class="text-xs font-bold text-slate-500 mb-1 uppercase">Som de Vencedor</p>
                                    <p class="text-sm font-semibold">{{ $winnerSetting->audio->name }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Loading Indicator --}}
    <div wire:loading class="fixed top-6 left-1/2 -translate-x-1/2 z-40">
        <div class="flex items-center gap-3 {{ $darkMode ? 'bg-blue-600/20 border-blue-500/30' : 'bg-blue-50 border-blue-300' }} border-2 px-6 py-3 rounded-full backdrop-blur-xl shadow-2xl animate-pulse">
            <div class="w-2 h-2 bg-blue-500 rounded-full animate-ping"></div>
            <span class="text-xs font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest">Atualizando</span>
        </div>
    </div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-10 max-h-screen overflow-y-auto">
        {{-- Header --}}
        <header wire:key="header-{{ $game->id }}-{{ $game->status }}" class="mb-8 lg:mb-12">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="h-1 w-12 bg-blue-600 rounded-full"></div>
                        <span class="text-blue-600 dark:text-blue-400 font-black tracking-[0.3em] uppercase text-[10px]">Arena</span>
                        @if ($game->status === 'active')
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-red-500/10 border-2 border-red-500/30 rounded-lg">
                                <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                                <span class="text-[9px] font-black text-red-600 dark:text-red-400 uppercase tracking-widest">Live</span>
                            </div>
                        @endif
                    </div>
                    <h1 class="text-3xl sm:text-4xl lg:text-6xl xl:text-7xl font-black uppercase leading-none break-words tracking-tight text-slate-900 dark:text-white">
                        {{ $game->name }}
                    </h1>
                </div>

                <div class="{{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-3xl p-6 shadow-2xl">
                    <div class="flex items-center gap-8">
                        <div class="text-center">
                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Rodada</p>
                            <p class="text-3xl lg:text-4xl font-black leading-none text-slate-900 dark:text-white">
                                {{ str_pad($game->current_round, 2, '0', STR_PAD_LEFT) }}
                                <span class="text-lg text-slate-500">/{{ $game->max_rounds }}</span>
                            </p>
                        </div>
                        <div class="h-12 w-px {{ $darkMode ? 'bg-slate-700' : 'bg-slate-300' }}"></div>
                        <div class="text-center">
                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Status</p>
                            <div class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border-2 
                                bg-{{ $this->statusColor }}-500/10 border-{{ $this->statusColor }}-500/30 text-{{ $this->statusColor }}-700 dark:text-{{ $this->statusColor }}-400">
                                {{ $this->statusLabel }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Game Finished View --}}
        @if ($game->status === 'finished')
            <div wire:key="finished-{{ $game->id }}" class="max-w-6xl mx-auto">
                <div class="text-center mb-12">
                    <div class="inline-flex items-center gap-4 mb-6">
                        <div class="h-1 w-16 bg-gradient-to-r from-transparent to-amber-500 rounded-full"></div>
                        <span class="text-amber-600 dark:text-amber-400 font-black tracking-[0.4em] uppercase text-xs">Encerrado</span>
                        <div class="h-1 w-16 bg-gradient-to-l from-transparent to-amber-500 rounded-full"></div>
                    </div>
                    <h2 class="text-5xl sm:text-6xl lg:text-8xl font-black uppercase leading-none mb-4 bg-gradient-to-r from-amber-500 via-yellow-500 to-amber-500 bg-clip-text text-transparent">
                        Vencedores
                    </h2>
                </div>

                <div class="{{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-3xl p-8 lg:p-12 shadow-2xl">
                    @if (count(array_filter($prizes, fn($p) => $p['winner'])) > 0)
                        <div class="space-y-6">
                            @foreach ($prizes as $index => $prize)
                                @if ($prize['winner'])
                                    <div wire:key="prize-fin-{{ $prize['id'] }}" 
                                        class="{{ $darkMode ? 'bg-slate-800' : 'bg-slate-100' }} rounded-3xl p-6 lg:p-8 border-2 border-amber-500/30 hover:border-amber-500/50 transition-all duration-300">
                                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <span class="text-3xl">{{ $index === 0 ? '🥇' : ($index === 1 ? '🥈' : ($index === 2 ? '🥉' : '🏆')) }}</span>
                                                    <h3 class="text-xl lg:text-2xl font-black text-amber-600 dark:text-amber-400 uppercase">#{{ $prize['position'] }}</h3>
                                                </div>
                                                <p class="text-lg font-bold text-slate-700 dark:text-slate-300 mb-3">{{ $prize['name'] }}</p>
                                                <div class="{{ $darkMode ? 'bg-slate-900' : 'bg-white' }} border-2 {{ $darkMode ? 'border-slate-700' : 'border-slate-300' }} rounded-2xl px-6 py-4">
                                                    <p class="text-2xl lg:text-3xl font-black uppercase text-slate-900 dark:text-white">{{ $prize['winner'] }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="py-20 text-center">
                            <div class="text-6xl mb-4 opacity-20">🏆</div>
                            <p class="text-xl font-bold text-slate-500 uppercase">Nenhum vencedor</p>
                        </div>
                    @endif
                </div>
            </div>

        {{-- Active Game View --}}
        @elseif ($game->status === 'active')
            <div wire:key="active-{{ $game->id }}-{{ count($drawnNumbers) }}" class="space-y-8">
                
                {{-- Mobile/Tablet Layout --}}
                <div class="xl:hidden space-y-6">
                    {{-- Current Number --}}
                    @if (!empty($currentDraws))
                        <div wire:key="mobile-num-{{ $currentDraws[0]['number'] }}" class="relative">
                            <div class="absolute inset-0 {{ $darkMode ? 'bg-blue-600/20' : 'bg-blue-300/50' }} blur-3xl rounded-full"></div>
                            <div class="relative {{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-3xl p-8 shadow-2xl">
                                <div class="text-center">
                                    <p class="text-xs font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest mb-4">Último Sorteado</p>
                                    <div class="text-8xl sm:text-9xl font-black leading-none bg-gradient-to-br from-blue-600 to-blue-400 bg-clip-text text-transparent">
                                        {{ str_pad($currentDraws[0]['number'], 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                    <p class="text-xs font-bold text-slate-500 mt-4">{{ count($drawnNumbers) }} de 75</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Recent Numbers --}}
                    <div class="{{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-3xl p-6 shadow-2xl">
                        <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-4">Últimos Números</h3>
                        <div class="grid grid-cols-4 sm:grid-cols-6 gap-3">
                            @foreach (collect($currentDraws)->take(8) as $index => $draw)
                                <div wire:key="mobile-recent-{{ $draw['id'] }}" 
                                    class="aspect-square rounded-2xl flex items-center justify-center transition-all duration-500
                                    {{ $index === 0 ? 'bg-blue-600 text-white scale-105 shadow-lg' : ($darkMode ? 'bg-slate-800 text-slate-400' : 'bg-slate-200 text-slate-700') }}">
                                    <span class="text-xl font-black">{{ str_pad($draw['number'], 2, '0', STR_PAD_LEFT) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Number Grid --}}
                    <div class="{{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-3xl p-6 shadow-2xl">
                        <div class="grid grid-cols-10 gap-1.5">
                            @foreach (range(1, 75) as $num)
                                @php $isDrawn = in_array($num, $drawnNumbers); @endphp
                                <div wire:key="mobile-grid-{{ $num }}" 
                                    class="aspect-square rounded-lg flex items-center justify-center text-[11px] font-black transition-all duration-500
                                    {{ $isDrawn ? 'bg-blue-600 text-white shadow-md scale-105' : ($darkMode ? 'bg-slate-800 text-slate-600' : 'bg-slate-200 text-slate-500') }}">
                                    {{ str_pad($num, 2, '0', STR_PAD_LEFT) }}
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Prizes --}}
                    <div class="{{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-3xl p-6 shadow-2xl">
                        <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-4">Premiação</h3>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            @foreach ($prizes as $prize)
                                <div wire:key="mobile-prize-{{ $prize['id'] }}" 
                                    class="p-4 rounded-2xl border-2 transition-all duration-500
                                    {{ $prize['winner'] ? 'bg-emerald-500/10 border-emerald-500/30' : ($darkMode ? 'bg-slate-800 border-slate-700' : 'bg-slate-100 border-slate-300') }}">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <span class="text-[10px] font-black uppercase text-blue-600 dark:text-blue-400">#{{ $prize['position'] }}</span>
                                            <h4 class="text-sm font-black uppercase mt-1 text-slate-900 dark:text-white">{{ $prize['name'] }}</h4>
                                            @if ($prize['winner'])
                                                <div class="mt-2 flex items-center gap-2 text-xs font-black text-emerald-700 dark:text-emerald-400">
                                                    <span>🏆</span>
                                                    <span class="truncate">{{ $prize['winner'] }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Desktop/TV Layout --}}
                <div class="hidden xl:grid grid-cols-12 gap-8">
                    {{-- Left Column --}}
                    <div class="col-span-3 space-y-6">
                        <div class="{{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-3xl p-6 shadow-2xl">
                            <h3 class="text-xs font-black text-slate-500 uppercase tracking-[0.3em] mb-6">Últimos</h3>
                            <div class="grid grid-cols-3 gap-3">
                                @foreach (collect($currentDraws)->take(9) as $index => $draw)
                                    <div wire:key="desktop-recent-{{ $draw['id'] }}" 
                                        class="aspect-square rounded-2xl flex items-center justify-center transition-all duration-500
                                        {{ $index === 0 ? 'bg-blue-600 text-white scale-110 shadow-lg col-span-3 text-4xl' : ($darkMode ? 'bg-slate-800 text-slate-400 text-xl' : 'bg-slate-200 text-slate-700 text-xl') }} font-black">
                                        {{ str_pad($draw['number'], 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="{{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-3xl p-6 shadow-2xl">
                            <h3 class="text-xs font-black text-slate-500 uppercase tracking-[0.3em] mb-6">Vencedores</h3>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                @forelse ($roundWinners as $winner)
                                    <div class="{{ $darkMode ? 'bg-slate-800' : 'bg-slate-100' }} rounded-2xl p-4 flex items-center gap-3">
                                        <div class="w-10 h-10 bg-blue-600/20 rounded-full flex items-center justify-center text-blue-600 dark:text-blue-400 font-black">
                                            {{ substr($winner['name'], 0, 1) }}
                                        </div>
                                        <p class="text-sm font-black uppercase truncate flex-1 text-slate-900 dark:text-white">{{ $winner['name'] }}</p>
                                    </div>
                                @empty
                                    <p class="text-center text-xs text-slate-500 font-bold py-8">Aguardando...</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Center Column --}}
                    <div class="col-span-6 flex flex-col items-center">
                        @if (!empty($currentDraws))
                            <div wire:key="desktop-main-{{ $currentDraws[0]['number'] }}" class="relative mb-12 group">
                                <div class="absolute inset-0 {{ $darkMode ? 'bg-blue-600/30' : 'bg-blue-300/50' }} blur-[100px] rounded-full"></div>
                                <div class="relative w-[450px] h-[450px] 2xl:w-[550px] 2xl:h-[550px] {{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-full flex flex-col items-center justify-center shadow-2xl">
                                    <span class="text-xs font-black text-blue-600 dark:text-blue-400 uppercase tracking-[0.5em] mb-6">Sorteado</span>
                                    <div class="text-[12rem] 2xl:text-[14rem] font-black leading-none bg-gradient-to-br from-blue-600 to-blue-400 bg-clip-text text-transparent">
                                        {{ str_pad($currentDraws[0]['number'], 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                    <div class="absolute bottom-8 px-6 py-2 {{ $darkMode ? 'bg-slate-800/90 border-slate-700' : 'bg-white/90 border-slate-300' }} border-2 rounded-full backdrop-blur-xl">
                                        <span class="text-xs font-black text-slate-500 uppercase tracking-widest">{{ count($drawnNumbers) }} / 75</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="{{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-3xl p-8 shadow-2xl w-full">
                            <div class="grid grid-cols-15 gap-2">
                                @foreach (range(1, 75) as $num)
                                    @php $isDrawn = in_array($num, $drawnNumbers); @endphp
                                    <div wire:key="desktop-grid-{{ $num }}" 
                                        class="aspect-square rounded-xl flex items-center justify-center text-sm font-black transition-all duration-500
                                        {{ $isDrawn ? 'bg-gradient-to-br from-blue-600 to-blue-500 text-white shadow-lg scale-105' : ($darkMode ? 'bg-slate-800 text-slate-600' : 'bg-slate-200 text-slate-500') }}">
                                        {{ str_pad($num, 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Right Column --}}
                    <div class="col-span-3">
                        <div class="{{ $darkMode ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300' }} border-2 rounded-3xl p-6 shadow-2xl">
                            <h3 class="text-xs font-black text-slate-500 uppercase tracking-[0.3em] mb-6">Premiação</h3>
                            <div class="space-y-4 max-h-[70vh] overflow-y-auto">
                                @foreach ($prizes as $prize)
                                    <div wire:key="desktop-prize-{{ $prize['id'] }}" 
                                        class="p-5 rounded-3xl border-2 transition-all duration-500
                                        {{ $prize['winner'] ? 'bg-emerald-500/10 border-emerald-500/30' : ($darkMode ? 'bg-slate-800 border-slate-700' : 'bg-slate-100 border-slate-300') }}">
                                        <span class="text-[10px] font-black uppercase text-blue-600 dark:text-blue-400">#{{ $prize['position'] }}</span>
                                        <h4 class="text-base font-black uppercase truncate mt-1 text-slate-900 dark:text-white">{{ $prize['name'] }}</h4>
                                        @if ($prize['winner'])
                                            <div class="mt-3 flex items-center gap-2 text-xs font-black text-emerald-700 dark:text-emerald-400">
                                                <span>🏆</span>
                                                <span class="truncate">{{ $prize['winner'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('audio-toggle', (data) => {
                if (window.audioManager) { 
                    window.audioManager.audioEnabled = data.enabled; 
                    if (!data.enabled) window.audioManager.stopAll(); 
                }
            });

            Livewire.on('play-sound', (data) => {
                if (!window.audioManager) return;
                const audioData = Array.isArray(data) ? data[0] : data;
                window.audioManager.playById(audioData);
            });
        });

        setTimeout(() => { 
            if (!window.audioManager) {
                console.error('[Display] AudioManager not loaded');
            }
        }, 2000);
    </script>

    <style>
        @keyframes fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</div>