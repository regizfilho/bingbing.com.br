<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Game\Game;
use App\Models\Game\Winner;
use App\Models\Game\Prize;
use App\Models\GameAudio;
use Illuminate\Support\Str;

new #[Layout('layouts.display')] class extends Component {
    public string $gameUuid;
    public Game $game;

    public array $currentDraws = [];
    public array $drawnNumbers = [];
    public array $roundWinners = [];
    public array $prizes = [];
    public $lastDrawId = null;

    public bool $audioEnabled = true;
    public bool $showAudioSettings = false;

    private int $cachedDrawCount = 0;
    private int $cachedWinnerCount = 0;

    public function mount(string $uuid): void
    {
        $this->gameUuid = $uuid;
        $this->reloadBaseGame();
        $this->loadGameData();

        $this->cachedDrawCount = count($this->drawnNumbers);
        $this->cachedWinnerCount = count($this->roundWinners);
        
        $this->dispatch('clear-audio-cache');

        \Log::info('🎬 Display: Montado', [
            'game_id' => $this->game->id,
            'initial_draws' => $this->cachedDrawCount,
            'initial_winners' => $this->cachedWinnerCount,
        ]);
    }

    public function hydrate(): void
    {
        // Apenas recarrega relacionamentos necessários
    }

    #[On('echo:game.{game.uuid},.GameUpdated')]
    public function handleGameUpdate(): void
    {
        \Log::info('=== DISPLAY: GameUpdated recebido ===');

        $oldStatus = $this->game->status;
        $oldDrawCount = $this->cachedDrawCount;
        $oldWinnerCount = $this->cachedWinnerCount;

        \Log::info('Display: Valores do CACHE', [
            'oldDrawCount' => $oldDrawCount,
            'oldWinners' => $oldWinnerCount,
        ]);

        // Recarrega o jogo com configurações de áudio
        $this->reloadBaseGame();
        $this->loadGameData();

        $newDrawCount = count($this->drawnNumbers);
        $newWinnerCount = count($this->roundWinners);

        \Log::info('Display: Valores NOVOS', [
            'newDrawCount' => $newDrawCount,
            'newWinners' => $newWinnerCount,
        ]);

        // ── SOM DE MUDANÇA DE STATUS ────────────────────────────────
        if ($oldStatus !== $this->game->status) {
            if ($this->game->status === 'active') {
                $this->dispatchAudioEvent('system', 'inicio_partida');
            } elseif ($this->game->status === 'finished') {
                $this->dispatchAudioEvent('system', 'fim_partida');
            }
        }

        // ── SOM DE NÚMERO SORTEADO ──────────────────────────────────
        if ($newDrawCount > $oldDrawCount) {
            \Log::info('🎵 Display: DISPARANDO SOM DE NÚMERO!', [
                'old' => $oldDrawCount,
                'new' => $newDrawCount,
            ]);
            $this->dispatchAudioEvent('number');
        }

        // ── SOM DE VENCEDOR ─────────────────────────────────────────
        if ($newWinnerCount > $oldWinnerCount) {
            \Log::info('🏆 Display: DISPARANDO SOM DE VENCEDOR!', [
                'old' => $oldWinnerCount,
                'new' => $newWinnerCount,
            ]);
            $this->dispatchAudioEvent('winner');
        }

        $this->cachedDrawCount = $newDrawCount;
        $this->cachedWinnerCount = $newWinnerCount;

        // Limpa cache do frontend
        $this->dispatch('clear-audio-cache');
    }

    /**
     * Dispara evento de áudio com ID do GameAudio correto
     */
    private function dispatchAudioEvent(string $category, ?string $systemName = null): void
    {
        if ($category === 'system' && $systemName) {
            // Para sons de sistema, usa o nome direto
            $this->dispatch('play-sound', type: 'system', name: $systemName);
            return;
        }

        // Para sons de número e vencedor, busca a configuração salva
        $audioSetting = $this->game->audioSettings()
            ->where('audio_category', $category)
            ->where('is_enabled', true)
            ->first();

        if (!$audioSetting) {
            \Log::warning("⚠️ Nenhuma configuração de áudio para categoria: {$category}");
            return;
        }

        $audio = $audioSetting->audio;

        if (!$audio) {
            \Log::warning("⚠️ GameAudio não encontrado para setting ID: {$audioSetting->id}");
            return;
        }

        \Log::info("✅ Disparando áudio", [
            'category' => $category,
            'audio_id' => $audio->id,
            'audio_name' => $audio->name,
            'audio_type' => $audio->audio_type,
        ]);

        $this->dispatch('play-sound', [
            'type' => $category,
            'audioId' => $audio->id,
            'name' => $audio->name,
            'audioType' => $audio->audio_type,
            'filePath' => $audio->file_path,
            'ttsText' => $audio->tts_text,
            'ttsVoice' => $audio->tts_voice,
            'ttsLanguage' => $audio->tts_language,
            'ttsRate' => $audio->tts_rate,
            'ttsPitch' => $audio->tts_pitch,
            'ttsVolume' => $audio->tts_volume,
        ]);
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

        $this->drawnNumbers = $this->game->draws()
            ->where('round_number', $round)
            ->orderBy('number', 'asc')
            ->pluck('number')
            ->toArray();

        $drawsRecords = $this->game->draws()
            ->where('round_number', $round)
            ->get()
            ->sortByDesc('id')
            ->values();

        $latest = $drawsRecords->first();
        $this->lastDrawId = $latest ? $latest->id : null;

        $this->currentDraws = $drawsRecords->take(10)->map(function ($draw) {
            return [
                'id' => $draw->id,
                'number' => $draw->number,
                'created_at' => $draw->created_at->format('H:i:s'),
                'unique_key' => "draw-{$draw->id}-" . Str::random(4),
            ];
        })->toArray();

        $this->roundWinners = Winner::where('game_id', $this->game->id)
            ->where('round_number', $round)
            ->with('user')
            ->latest()
            ->get()
            ->map(fn($w) => ['name' => $w->user->name])
            ->toArray();

        $this->prizes = Prize::where('game_id', $this->game->id)
            ->orderBy('position', 'asc')
            ->get()
            ->map(function ($p) {
                $winnerEntry = Winner::where('prize_id', $p->id)->with('user')->first();
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'position' => $p->position,
                    'winner' => $winnerEntry ? $winnerEntry->user->name : null,
                ];
            })
            ->toArray();
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

    #[Computed]
    public function statusColor(): string
    {
        return match ($this->game->status) {
            'active' => 'blue',
            'waiting' => 'amber',
            'finished' => 'slate',
            'paused' => 'red',
            default => 'blue',
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
            default => 'RASCUNHO',
        };
    }
};
?>

<!-- Flag global para permitir áudio SOMENTE nesta tela -->
<script>
    window.isControlPanel = false;
    window.isPublicDisplay = true;
    console.log('=== DISPLAY PÚBLICO CARREGADO - Áudio PERMITIDO ===');
</script>

<div
    class="min-h-screen w-full bg-[#05070a] text-slate-200 px-2 xs:px-3 sm:px-4 md:px-6 lg:px-8 xl:px-10 py-3 xs:py-4 sm:py-5 md:py-6 lg:py-8 overflow-x-hidden font-sans italic">

    <!-- Aviso de clique para ativar áudio -->
    <div x-data="{ show: true }" x-show="show" x-transition
        class="fixed top-20 left-1/2 -translate-x-1/2 z-[600] bg-yellow-500/10 border border-yellow-500/20 rounded-xl px-6 py-3 backdrop-blur-md shadow-xl">
        <div class="flex items-center gap-3">
            <span class="text-2xl">🔊</span>
            <div>
                <p class="text-sm font-bold text-yellow-400">Clique na tela para ativar o áudio</p>
                <p class="text-xs text-slate-400">Obrigatório pelo navegador</p>
            </div>
            <button @click="show = false" class="ml-4 text-slate-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Botão de configurações de áudio -->
    <div class="fixed top-3 right-3 z-[600]">
        <button wire:click="toggleAudioSettings"
            class="w-12 h-12 rounded-full flex items-center justify-center transition-all bg-indigo-600/20 border-indigo-500/30 border backdrop-blur-md shadow-lg hover:scale-110">
            <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
            </svg>
        </button>
    </div>

    <!-- Painel de configurações de áudio -->
    @if($showAudioSettings)
        <div class="fixed inset-0 z-[700] flex items-center justify-center bg-black/80 backdrop-blur-sm animate-fade-in"
            wire:click="toggleAudioSettings">
            <div class="bg-[#0b0d11] border border-white/10 rounded-2xl p-6 max-w-md w-full mx-4 shadow-2xl"
                wire:click.stop>
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white uppercase tracking-tight">Controle de Áudio</h3>
                    <button wire:click="toggleAudioSettings" class="text-slate-400 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-5">
                    <!-- Toggle Principal -->
                    <div class="flex items-center justify-between p-4 bg-[#161920] rounded-xl border border-white/5">
                        <span class="text-sm font-bold text-white uppercase tracking-wider">Som Ativado</span>
                        <button wire:click="toggleAudio"
                            class="w-14 h-7 rounded-full relative transition-all {{ $audioEnabled ? 'bg-blue-600' : 'bg-slate-600' }}">
                            <div
                                class="absolute top-1 left-1 w-5 h-5 bg-white rounded-full transition-transform {{ $audioEnabled ? 'translate-x-7' : '' }}">
                            </div>
                        </button>
                    </div>

                    <!-- Info -->
                    <div class="p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                        <div class="flex items-start gap-3">
                            <span class="text-2xl">ℹ️</span>
                            <div>
                                <p class="text-sm font-bold text-blue-400 mb-2">Áudio Configurado pelo Criador</p>
                                <p class="text-xs text-slate-400">
                                    Os sons são configurados pelo criador da sala no painel de controle. 
                                    Você pode apenas ativar/desativar o áudio aqui.
                                </p>
                            </div>
                        </div>
                    </div>

                    @if($this->game->package->is_free)
                        <!-- Aviso de Plano Gratuito -->
                        <div class="p-4 bg-orange-500/10 border border-orange-500/20 rounded-xl">
                            <div class="flex items-start gap-3">
                                <span class="text-2xl">🔇</span>
                                <div>
                                    <p class="text-sm font-bold text-orange-400 mb-2">Áudio Não Disponível</p>
                                    <p class="text-xs text-slate-400">
                                        Esta sala usa o plano gratuito. 
                                        Controle de áudio está disponível apenas em planos pagos.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Mostra configurações atuais -->
                        <div class="space-y-3">
                            @php
                                $numberSetting = $this->game->audioSettings()->where('audio_category', 'number')->first();
                                $winnerSetting = $this->game->audioSettings()->where('audio_category', 'winner')->first();
                            @endphp

                            @if($numberSetting && $numberSetting->audio)
                                <div class="p-4 bg-[#161920] rounded-xl border border-white/5">
                                    <p class="text-xs font-bold text-slate-400 uppercase mb-2">🎵 Som de Número</p>
                                    <p class="text-sm font-bold text-white">{{ $numberSetting->audio->name }}</p>
                                    <p class="text-xs text-blue-400 mt-1">
                                        {{ $numberSetting->audio->audio_type === 'mp3' ? '🎵 Efeito Sonoro' : '🎤 ' . $numberSetting->audio->tts_voice }}
                                    </p>
                                </div>
                            @endif

                            @if($winnerSetting && $winnerSetting->audio)
                                <div class="p-4 bg-[#161920] rounded-xl border border-white/5">
                                    <p class="text-xs font-bold text-slate-400 uppercase mb-2">🏆 Som de Vencedor</p>
                                    <p class="text-sm font-bold text-white">{{ $winnerSetting->audio->name }}</p>
                                    <p class="text-xs text-purple-400 mt-1">
                                        {{ $winnerSetting->audio->audio_type === 'mp3' ? '🎵 Efeito Sonoro' : '🎤 ' . $winnerSetting->audio->tts_voice }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-6 pt-5 border-t border-white/10 text-center text-xs text-slate-500 font-semibold uppercase tracking-wider">
                    Sons tocam automaticamente durante a partida
                </div>
            </div>
        </div>
    @endif

    <!-- Loading indicator -->
    <div wire:loading class="fixed top-3 xs:top-4 left-1/2 -translate-x-1/2 z-[500] animate-fade-in">
        <div class="flex items-center gap-2 xs:gap-3 bg-blue-600/10 border border-blue-500/20 px-3 xs:px-4 sm:px-5 py-1.5 xs:py-2 rounded-full backdrop-blur-md shadow-xl animate-pulse-slow">
            <div class="w-1.5 xs:w-2 h-1.5 xs:h-2 bg-blue-500 rounded-full animate-ping"></div>
            <span class="text-[8px] xs:text-[9px] font-black text-blue-500 uppercase tracking-wider xs:tracking-widest">
                Sincronizando...
            </span>
        </div>
    </div>

    <!-- Header -->
    <header class="max-w-[2000px] mx-auto mb-4 xs:mb-5 sm:mb-6 lg:mb-8 flex flex-col lg:flex-row justify-between gap-3 xs:gap-4 lg:gap-6 animate-slide-down">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 xs:gap-3 mb-2 xs:mb-2.5 lg:mb-3 flex-wrap">
                <div class="h-[2px] w-6 xs:w-8 sm:w-10 lg:w-12 bg-blue-600 animate-expand flex-shrink-0"></div>
                <span class="text-blue-500 font-black tracking-[0.2em] xs:tracking-[0.3em] lg:tracking-[0.4em] uppercase text-[7px] xs:text-[8px] sm:text-[9px]">
                    Arena Display
                </span>

                @if($game->status === 'active')
                    <div class="flex items-center gap-1.5 xs:gap-2 px-2 xs:px-2.5 sm:px-3 py-1 bg-red-600/10 border border-red-500/20 rounded-md animate-fade-in flex-shrink-0">
                        <div class="w-1 xs:w-1.5 h-1 xs:h-1.5 bg-red-500 rounded-full animate-pulse"></div>
                        <span class="text-[7px] xs:text-[8px] font-black text-red-500 uppercase tracking-widest">LIVE</span>
                    </div>
                @endif
            </div>

            <h1 class="text-xl xs:text-2xl sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl 2xl:text-7xl font-black text-white tracking-tighter uppercase leading-none animate-slide-up break-words">
                {{ Str::limit($game->name, 50) }}
            </h1>
        </div>

        <div class="flex flex-row xs:flex-wrap lg:flex-nowrap items-center justify-between xs:justify-start gap-3 xs:gap-4 lg:gap-6 bg-[#0b0d11] border border-white/5 p-3 xs:p-4 lg:p-5 rounded-xl xs:rounded-2xl shadow-2xl animate-slide-up flex-shrink-0">
            <div class="text-right border-r border-white/10 pr-3 xs:pr-4 lg:pr-6">
                <p class="text-[7px] xs:text-[8px] sm:text-[9px] font-black text-slate-500 uppercase tracking-wider xs:tracking-widest mb-1">
                    Rodada
                </p>
                <p class="text-base xs:text-lg sm:text-xl lg:text-2xl xl:text-3xl font-black text-white leading-none whitespace-nowrap">
                    {{ str_pad($game->current_round, 2, '0', STR_PAD_LEFT) }}
                    <span class="text-slate-700 text-[10px] xs:text-xs sm:text-sm">/{{ $game->max_rounds }}</span>
                </p>
            </div>

            <div>
                <p class="text-[7px] xs:text-[8px] sm:text-[9px] font-black text-slate-500 uppercase tracking-wider xs:tracking-widest mb-1.5 xs:mb-2">
                    Status
                </p>
                <div class="px-3 xs:px-4 lg:px-5 py-1 xs:py-1.5 lg:py-2 rounded-full text-[7px] xs:text-[8px] sm:text-[9px] font-black uppercase tracking-widest border whitespace-nowrap
                    bg-{{ $this->statusColor }}-600 border-{{ $this->statusColor }}-500 text-white animate-pulse-slow">
                    {{ $this->statusLabel }}
                </div>
            </div>
        </div>
    </header>

    @if ($game->status === 'finished')
        <div class="fixed inset-0 z-[400] flex items-center justify-center bg-[#05070a]/95 backdrop-blur-xl animate-fade-in p-4 sm:p-6 lg:p-8">
            <div class="w-full max-w-[90vw] sm:max-w-[80vw] lg:max-w-[70vw] xl:max-w-[60vw] 2xl:max-w-[50vw] max-h-[85vh] overflow-hidden animate-zoom-in">
                <div class="text-center mb-6 xs:mb-8 sm:mb-10 lg:mb-12 animate-slide-down">
                    <div class="inline-flex items-center justify-center gap-3 xs:gap-4 mb-4 xs:mb-5 sm:mb-6">
                        <div class="h-[2px] w-8 xs:w-12 sm:w-16 lg:w-20 bg-gradient-to-r from-transparent via-yellow-500 to-yellow-500"></div>
                        <span class="text-yellow-500 font-black tracking-[0.3em] xs:tracking-[0.4em] uppercase text-[9px] xs:text-[10px] sm:text-[11px]">
                            Partida Finalizada
                        </span>
                        <div class="h-[2px] w-8 xs:w-12 sm:w-16 lg:w-20 bg-gradient-to-l from-transparent via-yellow-500 to-yellow-500"></div>
                    </div>

                    <h2 class="text-3xl xs:text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl font-black text-white tracking-tighter uppercase leading-none mb-3 xs:mb-4 animate-title-glow">
                        🏆 Vencedores 🏆
                    </h2>

                    <p class="text-xs xs:text-sm sm:text-base lg:text-lg text-slate-400 font-bold uppercase tracking-widest">
                        {{ $game->name }}
                    </p>
                </div>

                <div class="bg-[#0b0d11] border-2 border-yellow-500/20 rounded-2xl xs:rounded-3xl sm:rounded-[2.5rem] p-4 xs:p-6 sm:p-8 lg:p-10 shadow-2xl shadow-yellow-500/10 max-h-[55vh] overflow-y-auto no-scrollbar">
                    @if(count(array_filter($prizes, fn($p) => $p['winner'])) > 0)
                        <div class="space-y-3 xs:space-y-4 sm:space-y-5 lg:space-y-6">
                            @foreach ($prizes as $index => $prize)
                                @if($prize['winner'])
                                    <div style="animation-delay: {{ $index * 150 }}ms;"
                                        class="group relative overflow-hidden bg-gradient-to-br from-[#161920] to-[#0b0d11] border-2 border-yellow-500/30 rounded-xl xs:rounded-2xl sm:rounded-3xl p-4 xs:p-5 sm:p-6 lg:p-8 transition-all duration-500 hover:scale-[1.02] hover:border-yellow-500/60 hover:shadow-2xl hover:shadow-yellow-500/20 animate-winner-slide">

                                        <div class="absolute inset-0 bg-gradient-to-r from-yellow-500/0 via-yellow-500/5 to-yellow-500/0 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                                        <div class="absolute -top-2 -left-2 xs:-top-3 xs:-left-3 w-12 xs:w-14 sm:w-16 lg:w-20 h-12 xs:h-14 sm:h-16 lg:h-20 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-full flex items-center justify-center shadow-lg shadow-yellow-500/50 animate-bounce-subtle z-10">
                                            <span class="text-base xs:text-lg sm:text-xl lg:text-2xl font-black text-[#05070a]">
                                                #{{ $prize['position'] }}
                                            </span>
                                        </div>

                                        <div class="relative flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4 ml-10 xs:ml-12 sm:ml-14 lg:ml-16">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 xs:gap-3 mb-2 xs:mb-2.5 sm:mb-3">
                                                    <span class="text-yellow-500 text-xl xs:text-2xl sm:text-3xl lg:text-4xl animate-pulse">🏆</span>
                                                    <h3 class="text-sm xs:text-base sm:text-lg lg:text-xl xl:text-2xl font-black text-yellow-500 uppercase tracking-tight truncate">
                                                        {{ $prize['name'] }}
                                                    </h3>
                                                </div>

                                                <div class="bg-white/5 border border-white/10 rounded-lg xs:rounded-xl sm:rounded-2xl px-3 xs:px-4 sm:px-6 py-2 xs:py-3 sm:py-4 backdrop-blur-sm">
                                                    <p class="text-[8px] xs:text-[9px] sm:text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">
                                                        Vencedor
                                                    </p>
                                                    <p class="text-lg xs:text-xl sm:text-2xl lg:text-3xl xl:text-4xl font-black text-white uppercase leading-none truncate animate-text-shine">
                                                        {{ $prize['winner'] }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="hidden sm:flex items-center justify-center w-16 lg:w-20 xl:w-24 h-16 lg:h-20 xl:h-24 bg-yellow-500/10 border border-yellow-500/30 rounded-2xl lg:rounded-3xl flex-shrink-0 animate-float">
                                                <span class="text-3xl lg:text-4xl xl:text-5xl animate-spin-slow-reverse">⭐</span>
                                            </div>
                                        </div>

                                        <div class="absolute top-0 right-0 w-20 xs:w-24 sm:w-32 h-20 xs:h-24 sm:h-32 opacity-20 pointer-events-none">
                                            <div class="absolute top-2 right-2 w-2 h-2 bg-yellow-400 rounded-full animate-confetti-1"></div>
                                            <div class="absolute top-6 right-8 w-1.5 h-1.5 bg-yellow-300 rounded-full animate-confetti-2"></div>
                                            <div class="absolute top-10 right-4 w-2.5 h-2.5 bg-yellow-500 rounded-full animate-confetti-3"></div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12 xs:py-16 sm:py-20">
                            <span class="text-6xl xs:text-7xl sm:text-8xl mb-4 xs:mb-6 block animate-bounce-subtle">😔</span>
                            <p class="text-lg xs:text-xl sm:text-2xl font-bold text-slate-400 uppercase">
                                Nenhum vencedor registrado
                            </p>
                        </div>
                    @endif
                </div>

                <div class="mt-6 xs:mt-8 sm:mt-10 text-center animate-slide-up">
                    <div class="inline-flex items-center gap-2 xs:gap-3 bg-white/5 border border-white/10 rounded-full px-4 xs:px-5 sm:px-6 py-2 xs:py-2.5 sm:py-3 backdrop-blur-sm">
                        <div class="w-2 xs:w-2.5 h-2 xs:h-2.5 bg-emerald-500 rounded-full animate-pulse"></div>
                        <span class="text-[9px] xs:text-[10px] sm:text-[11px] font-black text-slate-400 uppercase tracking-widest">
                            Partida encerrada • Rodada {{ $game->current_round }}/{{ $game->max_rounds }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($game->status === 'active')
        <div class="max-w-[2000px] mx-auto">
            <div class="xl:hidden space-y-4 xs:space-y-5 sm:space-y-6">
                @if (!empty($currentDraws))
                    <div wire:key="main-display-mobile-{{ $lastDrawId }}"
                        class="relative group flex justify-center animate-zoom-in py-4 xs:py-6">
                        <div class="absolute inset-0 bg-blue-600/30 blur-[60px] xs:blur-[80px] sm:blur-[100px] rounded-full animate-pulse-glow"></div>
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-600/0 via-blue-600/20 to-blue-600/0 blur-2xl animate-spin-slow"></div>

                        <div class="relative 
                                w-[min(85vw,320px)] xs:w-[min(75vw,360px)] sm:w-[min(65vw,400px)] md:w-[min(55vw,440px)]
                                h-[min(85vw,320px)] xs:h-[min(75vw,360px)] sm:h-[min(65vw,400px)] md:h-[min(55vw,440px)]
                                rounded-full 
                                flex flex-col items-center justify-center 
                                border-2 border-white/10 
                                bg-gradient-to-b from-[#161920] to-[#0b0d11]
                                shadow-2xl
                                animate-float">
                            <span class="text-[8px] xs:text-[9px] sm:text-[10px] md:text-[11px] font-black text-blue-500 uppercase tracking-[0.3em] xs:tracking-[0.4em] sm:tracking-[0.5em] mb-2 xs:mb-2.5 sm:mb-3 animate-pulse">
                                Sorteado
                            </span>

                            <div class="text-[4rem] xs:text-[5rem] sm:text-[6.5rem] md:text-[8rem] font-black text-white leading-none tracking-tighter drop-shadow-2xl animate-number-pop">
                                {{ str_pad($currentDraws[0]['number'], 2, '0', STR_PAD_LEFT) }}
                            </div>

                            <div class="absolute bottom-3 xs:bottom-4 sm:bottom-5 md:bottom-6 px-3 xs:px-4 sm:px-5 py-1.5 xs:py-2 bg-white/5 rounded-full border border-white/10 backdrop-blur-sm animate-slide-up">
                                <span class="text-[7px] xs:text-[8px] sm:text-[9px] font-black text-slate-500 uppercase tracking-wider xs:tracking-widest whitespace-nowrap">
                                    {{ count($drawnNumbers) }} de 75
                                </span>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="bg-[#0b0d11] border border-white/5 rounded-xl xs:rounded-2xl sm:rounded-3xl p-3 xs:p-4 sm:p-5 animate-slide-right">
                    <h3 class="text-[8px] xs:text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] xs:tracking-[0.25em] sm:tracking-[0.3em] mb-3 xs:mb-4">
                        Últimos Números
                    </h3>

                    <div class="grid grid-cols-4 gap-2 xs:gap-2.5 sm:gap-3">
                        @foreach (collect($currentDraws)->take(8) as $index => $draw)
                            <div wire:key="{{ $draw['unique_key'] }}-mobile" style="animation-delay: {{ $index * 50 }}ms;"
                                class="aspect-square rounded-lg xs:rounded-xl sm:rounded-2xl flex items-center justify-center transition-all duration-700 animate-scale-in
                                     {{ $index === 0 ? 'bg-gradient-to-br from-blue-600 to-blue-700 shadow-lg shadow-blue-600/50 scale-105 animate-bounce-subtle' : 'bg-[#161920] opacity-50' }}">
                                <span class="text-lg xs:text-xl sm:text-2xl font-black text-white">
                                    {{ str_pad($draw['number'], 2, '0', STR_PAD_LEFT) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-[#0b0d11] border border-white/5 rounded-xl xs:rounded-2xl sm:rounded-3xl p-2.5 xs:p-3 sm:p-4 md:p-5 animate-slide-up">
                    <div class="grid grid-cols-8 xs:grid-cols-10 sm:grid-cols-12 md:grid-cols-15 gap-1 xs:gap-1.5 sm:gap-2">
                        @foreach (range(1, 75) as $num)
                            @php
                                $isDrawn = in_array($num, $drawnNumbers);
                                $drawIndex = $isDrawn ? array_search($num, array_reverse($drawnNumbers)) : null;
                            @endphp
                            <div wire:key="grid-mobile-{{ $num }}"
                                style="{{ $isDrawn ? 'animation-delay: ' . ($drawIndex * 30) . 'ms;' : '' }}" class="aspect-square rounded xs:rounded-md sm:rounded-lg flex items-center justify-center text-[8px] xs:text-[9px] sm:text-[10px] md:text-xs font-black transition-all duration-700
                                     {{ $isDrawn
                        ? 'bg-gradient-to-br from-blue-600 to-blue-700 text-white shadow-md shadow-blue-600/30 animate-grid-pop'
                        : 'bg-[#161920] text-slate-700' }}">
                                {{ str_pad($num, 2, '0', STR_PAD_LEFT) }}
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-[#0b0d11] border border-white/5 rounded-xl xs:rounded-2xl sm:rounded-3xl p-3 xs:p-4 sm:p-5 animate-slide-left">
                    <h3 class="text-[8px] xs:text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] xs:tracking-[0.25em] sm:tracking-[0.3em] mb-3 xs:mb-4">
                        Premiação
                    </h3>

                    <div class="space-y-2.5 xs:space-y-3 sm:space-y-4 max-h-[40vh] xs:max-h-[45vh] sm:max-h-[50vh] overflow-y-auto no-scrollbar">
                        @foreach ($prizes as $index => $prize)
                            <div style="animation-delay: {{ $index * 100 }}ms;" class="p-2.5 xs:p-3 sm:p-4 rounded-lg xs:rounded-xl sm:rounded-2xl border transition-all duration-500 animate-slide-up
                                    {{ $prize['winner']
                        ? 'bg-gradient-to-br from-emerald-600 to-emerald-700 border-emerald-500 opacity-80 shadow-lg shadow-emerald-600/30 animate-prize-win'
                        : 'bg-[#161920] border-white/5' }}">
                                <span class="text-[7px] xs:text-[8px] sm:text-[9px] font-black uppercase text-blue-500">
                                    Slot #{{ $prize['position'] }}
                                </span>
                                <h4 class="text-xs xs:text-sm sm:text-base font-black text-white uppercase truncate mt-1">
                                    {{ $prize['name'] }}
                                </h4>
                                @if($prize['winner'])
                                    <div class="mt-1.5 xs:mt-2 text-[8px] xs:text-[9px] sm:text-[10px] font-black text-white uppercase flex items-center gap-1.5 xs:gap-2 animate-slide-up">
                                        <span class="animate-bounce-subtle">🏆</span>
                                        <span class="truncate">{{ $prize['winner'] }}</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="hidden xl:grid grid-cols-12 gap-6 2xl:gap-8">
                <div class="col-span-3 space-y-6 animate-slide-right">
                    <div class="bg-[#0b0d11] border border-white/5 rounded-[2.5rem] p-6 2xl:p-8">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-6">
                            Últimos Números
                        </h3>

                        <div class="grid grid-cols-4 gap-3">
                            @foreach (collect($currentDraws)->take(8) as $index => $draw)
                                <div wire:key="{{ $draw['unique_key'] }}" style="animation-delay: {{ $index * 50 }}ms;"
                                    class="aspect-square rounded-2xl flex items-center justify-center transition-all duration-700 animate-scale-in
                                         {{ $index === 0 ? 'bg-gradient-to-br from-blue-600 to-blue-700 shadow-lg shadow-blue-600/50 scale-105 animate-bounce-subtle' : 'bg-[#161920] opacity-50' }}">
                                    <span class="text-2xl font-black text-white">
                                        {{ str_pad($draw['number'], 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-span-6 flex flex-col items-center justify-center animate-zoom-in">
                    @if (!empty($currentDraws))
                        <div wire:key="main-display-{{ $lastDrawId }}" class="relative group mb-10">
                            <div class="absolute inset-0 bg-blue-600/30 blur-[120px] rounded-full animate-pulse-glow"></div>
                            <div class="absolute inset-0 bg-gradient-to-r from-blue-600/0 via-blue-600/20 to-blue-600/0 blur-2xl animate-spin-slow"></div>

                            <div class="relative 
                                    w-[480px] h-[480px] 2xl:w-[560px] 2xl:h-[560px]
                                    rounded-full 
                                    flex flex-col items-center justify-center 
                                    border-2 border-white/10 
                                    bg-gradient-to-b from-[#161920] to-[#0b0d11]
                                    shadow-2xl
                                    animate-float">
                                <span class="text-[11px] 2xl:text-[12px] font-black text-blue-500 uppercase tracking-[0.5em] mb-3 animate-pulse">
                                    Sorteado
                                </span>

                                <div class="text-[12rem] 2xl:text-[14rem] font-black text-white leading-none tracking-tighter drop-shadow-2xl animate-number-pop">
                                    {{ str_pad($currentDraws[0]['number'], 2, '0', STR_PAD_LEFT) }}
                                </div>

                                <div class="absolute bottom-6 px-5 py-2 bg-white/5 rounded-full border border-white/10 backdrop-blur-sm animate-slide-up">
                                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">
                                        {{ count($drawnNumbers) }} de 75
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="w-full bg-[#0b0d11] border border-white/5 rounded-[2.5rem] p-6 2xl:p-8 animate-slide-up">
                        <div class="grid grid-cols-15 gap-2">
                            @foreach (range(1, 75) as $num)
                                @php
                                    $isDrawn = in_array($num, $drawnNumbers);
                                    $drawIndex = $isDrawn ? array_search($num, array_reverse($drawnNumbers)) : null;
                                @endphp
                                <div wire:key="grid-{{ $num }}"
                                    style="{{ $isDrawn ? 'animation-delay: ' . ($drawIndex * 30) . 'ms;' : '' }}" class="aspect-square rounded-lg flex items-center justify-center text-xs font-black transition-all duration-700
                                         {{ $isDrawn
                            ? 'bg-gradient-to-br from-blue-600 to-blue-700 text-white shadow-md shadow-blue-600/30 animate-grid-pop'
                            : 'bg-[#161920] text-slate-700' }}">
                                    {{ str_pad($num, 2, '0', STR_PAD_LEFT) }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-span-3 space-y-6 animate-slide-left">
                    <div class="bg-[#0b0d11] border border-white/5 rounded-[2.5rem] p-6 2xl:p-8">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-6">
                            Premiação
                        </h3>

                        <div class="space-y-4 max-h-[60vh] overflow-y-auto no-scrollbar">
                            @foreach ($prizes as $index => $prize)
                                <div style="animation-delay: {{ $index * 100 }}ms;" class="p-4 rounded-2xl border transition-all duration-500 animate-slide-up
                                        {{ $prize['winner']
                            ? 'bg-gradient-to-br from-emerald-600 to-emerald-700 border-emerald-500 opacity-80 shadow-lg shadow-emerald-600/30 animate-prize-win'
                            : 'bg-[#161920] border-white/5' }}">
                                    <span class="text-[9px] font-black uppercase text-blue-500">
                                        Slot #{{ $prize['position'] }}
                                    </span>
                                    <h4 class="text-base font-black text-white uppercase truncate mt-1">
                                        {{ $prize['name'] }}
                                    </h4>
                                    @if($prize['winner'])
                                        <div class="mt-2 text-[10px] font-black text-white uppercase flex items-center gap-2 animate-slide-up">
                                            <span class="animate-bounce-subtle">🏆</span>
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

    <!-- Script de inicialização -->
    <script>
        console.log('=== SCRIPT DO DISPLAY CARREGADO ===');

        document.addEventListener('livewire:init', () => {
            console.log('=== Livewire inicializado no display ===');
            console.log('AudioManager disponível?', typeof window.audioManager !== 'undefined');

            Livewire.on('audio-toggle', (data) => {
                console.log('🔊 Audio toggle recebido:', data);
                if (window.audioManager) {
                    window.audioManager.audioEnabled = data.enabled;
                    if (!data.enabled) window.audioManager.stopAll();
                } else {
                    console.error('❌ AudioManager não encontrado!');
                }
            });

            Livewire.on('play-sound', (data) => {
                console.log('🎵 Play sound RECEBIDO no display:', data);

                if (!window.audioManager) {
                    console.error('❌ AudioManager NÃO EXISTE!');
                    return;
                }

                // Extrai dados do evento
                const audioData = Array.isArray(data) ? data[0] : data;

                console.log('📦 Dados do áudio:', {
                    type: audioData.type,
                    audioId: audioData.audioId,
                    name: audioData.name,
                    audioType: audioData.audioType
                });

                // Toca o som usando AudioManager
                window.audioManager.playById(audioData);
            });
        });

        setTimeout(() => {
            console.log('=== Verificação AudioManager após 2s ===');
            if (window.audioManager) {
                console.log('AudioEnabled:', window.audioManager.audioEnabled);
            } else {
                console.error('AudioManager ainda não carregado');
            }
        }, 2000);
    </script>

    <!-- Estilos -->
    <style>
        * {
            -webkit-tap-highlight-color: transparent;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        @media (min-width: 1280px) {
            .grid-cols-15 {
                grid-template-columns: repeat(15, minmax(0, 1fr));
            }
        }

        @media (min-width: 768px) and (max-width: 1279px) {
            .md\:grid-cols-15 {
                grid-template-columns: repeat(15, minmax(0, 1fr));
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideRight {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes slideLeft {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes zoomIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.5); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes numberPop {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes gridPop {
            0% { transform: scale(0.5); opacity: 0; }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        @keyframes bounceSubtle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        @keyframes pulseGlow {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }

        @keyframes pulseSlow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        @keyframes expand {
            from { width: 0; }
            to { width: 100%; }
        }

        @keyframes spinSlow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes spinSlowReverse {
            from { transform: rotate(0deg); }
            to { transform: rotate(-360deg); }
        }

        @keyframes prizeWin {
            0%, 100% { transform: scale(1); }
            25% { transform: scale(1.03); }
            50% { transform: scale(1); }
            75% { transform: scale(1.03); }
        }

        @keyframes titleGlow {
            0%, 100% { text-shadow: 0 0 20px rgba(234, 179, 8, 0.3), 0 0 40px rgba(234, 179, 8, 0.2), 0 0 60px rgba(234, 179, 8, 0.1); }
            50% { text-shadow: 0 0 30px rgba(234, 179, 8, 0.5), 0 0 60px rgba(234, 179, 8, 0.3), 0 0 90px rgba(234, 179, 8, 0.2); }
        }

        @keyframes textShine {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }

        @keyframes winnerSlide {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes confetti1 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.8; }
            50% { transform: translate(-5px, -10px) rotate(180deg); opacity: 1; }
        }

        @keyframes confetti2 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.6; }
            50% { transform: translate(8px, -15px) rotate(-180deg); opacity: 1; }
        }

        @keyframes confetti3 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.7; }
            50% { transform: translate(-8px, -8px) rotate(90deg); opacity: 1; }
        }

        @media (max-width: 640px) {
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-4px); }
            }

            @keyframes slideDown, @keyframes slideUp, @keyframes slideRight, @keyframes slideLeft {
                from { opacity: 0; transform: translate(0, 10px); }
                to { opacity: 1; transform: translate(0, 0); }
            }
        }

        .animate-fade-in { animation: fadeIn 0.5s ease-out; }
        .animate-slide-down { animation: slideDown 0.6s ease-out; }
        .animate-slide-up { animation: slideUp 0.6s ease-out; }
        .animate-slide-right { animation: slideRight 0.6s ease-out; }
        .animate-slide-left { animation: slideLeft 0.6s ease-out; }
        .animate-zoom-in { animation: zoomIn 0.8s ease-out; }
        .animate-scale-in { animation: scaleIn 0.4s ease-out; }
        .animate-number-pop { animation: numberPop 0.7s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .animate-grid-pop { animation: gridPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .animate-float { animation: float 3s ease-in-out infinite; }
        .animate-bounce-subtle { animation: bounceSubtle 2s ease-in-out infinite; }
        .animate-pulse-glow { animation: pulseGlow 2s ease-in-out infinite; }
        .animate-pulse-slow { animation: pulseSlow 3s ease-in-out infinite; }
        .animate-expand { animation: expand 0.8s ease-out; }
        .animate-spin-slow { animation: spinSlow 8s linear infinite; }
        .animate-spin-slow-reverse { animation: spinSlowReverse 12s linear infinite; }
        .animate-prize-win { animation: prizeWin 1s ease-in-out; }
        .animate-title-glow { animation: titleGlow 2s ease-in-out infinite; }
        .animate-winner-slide { animation: winnerSlide 0.6s ease-out; }
        .animate-confetti-1 { animation: confetti1 3s ease-in-out infinite; }
        .animate-confetti-2 { animation: confetti2 3.5s ease-in-out infinite; }
        .animate-confetti-3 { animation: confetti3 4s ease-in-out infinite; }
        .animate-text-shine {
            background: linear-gradient(90deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,1) 50%, rgba(255,255,255,0.8) 100%);
            background-size: 200% auto;
            background-clip: text;
            -webkit-background-clip: text;
            animation: textShine 3s linear infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        @media (max-width: 1279px) {
            html {
                -webkit-text-size-adjust: 100%;
                touch-action: manipulation;
            }
        }

        .touch-device {
            -webkit-overflow-scrolling: touch;
        }
    </style>
</div>