<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Game\Game;
use App\Models\Game\GameAudioSetting;
use App\Models\Game\Winner;
use App\Models\Game\Card;
use App\Models\Game\Prize;
use App\Events\GameUpdated;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\GameAudio;

new class extends Component {
    public Game $game;
    public bool $isCreator = false;
    public Collection $winningCards;
    public ?int $lastDrawnNumber = null;
    public array $drawnNumbersList = [];
    public int $drawnCount = 0;
    public bool $showNoPrizesWarning = false;
    public bool $isPaused = false;
    public $willRefund = false;
    public int $tempSeconds = 3;

    // ── CONTROLES DE ÁUDIO ──────────────────────────────────────
    public bool $audioEnabled = true;
    public ?int $selected_number_sound_id = null;
    public ?int $selected_winner_sound_id = null;
    public bool $showAudioSettings = false;

    // Travas de segurança
    public bool $isDrawing = false;
    public bool $isProcessingAction = false;

    public function mount(string $uuid): void
    {
        $user = auth()->user();

        $this->game = Game::where('uuid', $uuid)
            ->with([
                'creator:id,name,wallet_id', 
                'package:id,name,max_players,is_free,cost_credits', 
                'prizes:id,game_id,name,description,position,is_claimed,uuid', 
                'players.user:id,name',
                'audioSettings.audio'
            ])
            ->firstOrFail();

        $this->isCreator = $this->game->creator_id === $user->id;

        if (!$this->isCreator && !$this->game->players()->where('user_id', $user->id)->exists()) {
            abort(403, 'Acesso negado.');
        }

        $this->winningCards = collect();
        $this->tempSeconds = $this->game->auto_draw_seconds ?? 3;

        // ── CARREGA CONFIGURAÇÕES DE ÁUDIO SALVAS ──────────────
        $this->loadAudioSettings();
        $this->loadGameData();
    }

    private function loadAudioSettings(): void
    {
        // Busca configurações salvas no banco
        $numberSetting = $this->game->audioSettings()
            ->where('audio_category', 'number')
            ->where('is_enabled', true)
            ->first();

        $winnerSetting = $this->game->audioSettings()
            ->where('audio_category', 'winner')
            ->where('is_enabled', true)
            ->first();

        if ($numberSetting) {
            $this->selected_number_sound_id = $numberSetting->game_audio_id;
            $this->audioEnabled = true;
        } else {
            // Fallback para default do banco
            $defaultNumber = GameAudio::active()
                ->where('type', 'player')
                ->where('is_default', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%Número%')
                      ->orWhere('name', 'like', '%numero%');
                })
                ->first();
            
            $this->selected_number_sound_id = $defaultNumber?->id;
        }

        if ($winnerSetting) {
            $this->selected_winner_sound_id = $winnerSetting->game_audio_id;
        } else {
            $defaultWinner = GameAudio::active()
                ->where('type', 'player')
                ->where('is_default', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%Vencedor%')
                      ->orWhere('name', 'like', '%vencedor%');
                })
                ->first();

            $this->selected_winner_sound_id = $defaultWinner?->id;
        }

        \Log::info('🔊 Áudio carregado no controle', [
            'game_id' => $this->game->id,
            'number_sound_id' => $this->selected_number_sound_id,
            'winner_sound_id' => $this->selected_winner_sound_id,
            'audio_enabled' => $this->audioEnabled,
        ]);
    }

    #[Computed]
    public function canUseCustomAudio(): bool
    {
        return !$this->game->package->is_free;
    }

    #[Computed]
    public function selectedNumberSound()
    {
        if (!$this->selected_number_sound_id) return null;
        return GameAudio::find($this->selected_number_sound_id);
    }

    #[Computed]
    public function selectedWinnerSound()
    {
        if (!$this->selected_winner_sound_id) return null;
        return GameAudio::find($this->selected_winner_sound_id);
    }

    public function updatedSelectedNumberSoundId(): void
    {
        if (!$this->canUseCustomAudio) return;

        if ($this->selected_number_sound_id) {
            $this->game->setAudioForCategory('number', $this->selected_number_sound_id, true);
            $this->dispatch('notify', type: 'success', text: 'Som de número atualizado!');
            
            \Log::info('✅ Som de número atualizado', [
                'game_id' => $this->game->id,
                'audio_id' => $this->selected_number_sound_id,
            ]);

            // Notifica display público para recarregar configurações
            broadcast(new GameUpdated($this->game))->toOthers();
        }
    }

    public function updatedSelectedWinnerSoundId(): void
    {
        if (!$this->canUseCustomAudio) return;

        if ($this->selected_winner_sound_id) {
            $this->game->setAudioForCategory('winner', $this->selected_winner_sound_id, true);
            $this->dispatch('notify', type: 'success', text: 'Som de vencedor atualizado!');
            
            \Log::info('✅ Som de vencedor atualizado', [
                'game_id' => $this->game->id,
                'audio_id' => $this->selected_winner_sound_id,
            ]);

            broadcast(new GameUpdated($this->game))->toOthers();
        }
    }

    public function toggleAudioEnabled(): void
    {
        if (!$this->canUseCustomAudio) {
            $this->dispatch('notify', type: 'warning', text: 'Controle de áudio exclusivo para planos pagos.');
            return;
        }

        $this->audioEnabled = !$this->audioEnabled;

        // Atualiza todas as configurações de áudio
        $this->game->audioSettings()->update(['is_enabled' => $this->audioEnabled]);

        $msg = $this->audioEnabled ? 'Áudio ativado!' : 'Áudio desativado!';
        $this->dispatch('notify', type: 'info', text: $msg);

        broadcast(new GameUpdated($this->game))->toOthers();
    }

    public function testNumberSound(): void
    {
        if (!$this->canUseCustomAudio) return;
        
        $audio = $this->selectedNumberSound;
        if ($audio) {
            $this->dispatch('play-sound', type: 'number', audioId: $audio->id);
        }
    }

    public function testWinnerSound(): void
    {
        if (!$this->canUseCustomAudio) return;
        
        $audio = $this->selectedWinnerSound;
        if ($audio) {
            $this->dispatch('play-sound', type: 'winner', audioId: $audio->id);
        }
    }

    #[On('echo:game.{game.uuid},.GameUpdated')]
    public function handleGameUpdate(): void
    {
        $this->game->unsetRelations();
        $this->game = Game::where('uuid', $this->game->uuid)
            ->with([
                'creator:id,name,wallet_id', 
                'package:id,name,max_players,is_free,cost_credits', 
                'prizes:id,game_id,name,description,position,is_claimed,uuid', 
                'players.user:id,name', 
                'players.cards', 
                'draws', 
                'winners.user', 
                'winners.prize',
                'audioSettings.audio'
            ])
            ->firstOrFail();

        $this->loadGameData();
    }

    #[On('game-updated')]
    public function refreshGame(): void
    {
        $this->handleGameUpdate();
    }

    public function hydrate(): void
    {
        $this->game->unsetRelations();
        $this->game->load([
            'prizes', 
            'package', 
            'players.cards', 
            'players.user:id,name', 
            'winners.user', 
            'draws' => fn($q) => $q->where('round_number', $this->game->current_round),
            'audioSettings.audio'
        ]);
        $this->loadGameData();
    }

    private function loadGameData(): void
    {
        $this->loadDrawData();
        $this->checkWinningCards();
    }

    #[Computed]
    public function creatorBalance(): int
    {
        return $this->game->creator->wallet->balance ?? 0;
    }

    #[Computed]
    public function packageCost(): int
    {
        return $this->game->package->cost_credits ?? 0;
    }

    #[Computed]
    public function willRefund(): bool
    {
        return $this->game->status === 'finished' && ($this->game->players->isEmpty() || $this->game->winners->isEmpty());
    }

    #[Computed]
    public function nextAvailablePrize()
    {
        return $this->game->prizes->where('is_claimed', false)->sortBy('position')->first();
    }

    private function loadDrawData(): void
    {
        $draws = $this->game->draws->where('round_number', $this->game->current_round)->sortByDesc('id')->values();
        $this->lastDrawnNumber = $draws->first()?->number;
        $this->drawnNumbersList = $draws->pluck('number')->toArray();
        $this->drawnCount = $draws->count();
    }

    public function drawNumber(): void
    {
        if ($this->isDrawing) return;

        if (!$this->isCreator || $this->game->status !== 'active') return;

        if ($this->drawnCount >= 75) return;

        $this->isDrawing = true;

        try {
            $draw = $this->game->drawNumber();

            if (!$draw) {
                $this->dispatch('notify', type: 'error', text: 'Erro ao sortear número.');
                $this->isDrawing = false;
                return;
            }

            $this->finishAction("Número {$draw->number} sorteado!");

            usleep(500000);

            // Dispara som no display público (não aqui)
            // O evento GameUpdated já notifica o display
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao sortear número: ' . $e->getMessage());
        } finally {
            $this->isDrawing = false;
        }
    }

    private function checkWinningCards(): void
    {
        if ($this->game->status !== 'active') {
            $this->winningCards = collect();
            $this->showNoPrizesWarning = false;
            return;
        }

        $allWinningCards = $this->game->checkWinningCards() ?? collect();
        $alreadyWinnerIds = DB::table('winners')
            ->where('game_id', $this->game->id)
            ->where('round_number', $this->game->current_round)
            ->pluck('card_id')
            ->toArray();

        $this->winningCards = $allWinningCards->whereNotIn('id', $alreadyWinnerIds)->values();

        if ($this->winningCards->isNotEmpty()) {
            $this->isPaused = true;
        }

        $this->showNoPrizesWarning = $this->winningCards->isNotEmpty() && !$this->game->hasAvailablePrizes();
    }

    public function claimPrize(string $cardUuid, ?string $prizeUuid = null): void
    {
        if ($this->isProcessingAction) return;

        if (!$this->isCreator || $this->game->status !== 'active') {
            $this->dispatch('notify', type: 'error', text: 'Ação não permitida.');
            return;
        }

        $this->isProcessingAction = true;

        try {
            $card = Card::where('uuid', $cardUuid)->first();
            $prize = $prizeUuid ? Prize::where('uuid', $prizeUuid)->where('is_claimed', false)->first() : null;

            if (!$card) {
                $this->dispatch('notify', type: 'error', text: 'Cartela não encontrada.');
                return;
            }

            $existingWinner = Winner::where('game_id', $this->game->id)
                ->where('card_id', $card->id)
                ->where('round_number', $this->game->current_round)
                ->exists();

            if ($existingWinner) {
                $this->dispatch('notify', type: 'warning', text: 'Esta cartela já foi premiada nesta rodada.');
                return;
            }

            DB::transaction(function () use ($card, $prize) {
                if ($prize) {
                    $prize->update([
                        'is_claimed' => true,
                        'winner_card_id' => $card->id,
                        'claimed_at' => now(),
                    ]);
                }

                Winner::create([
                    'uuid' => (string) Str::uuid(),
                    'game_id' => $this->game->id,
                    'card_id' => $card->id,
                    'user_id' => $card->player->user_id,
                    'prize_id' => $prize?->id,
                    'round_number' => $this->game->current_round,
                    'won_at' => now(),
                ]);

                $pushService = app(\App\Services\PushNotificationService::class);

                if ($prize) {
                    $message = \App\Services\NotificationMessages::bingoWinner($this->game->name, $prize->name);
                    $pushService->notifyUser($card->player->user_id, $message['title'], $message['body'], route('games.play', $this->game->uuid));
                } else {
                    $message = [
                        'title' => '🏅 Bingo de Honra!',
                        'body' => "Você completou uma cartela em {$this->game->name}!",
                    ];
                    $pushService->notifyUser($card->player->user_id, $message['title'], $message['body'], route('games.play', $this->game->uuid));
                }

                $otherPlayerIds = $this->game->players->where('user_id', '!=', $card->player->user_id)->pluck('user_id')->toArray();

                if (!empty($otherPlayerIds)) {
                    $winnerName = $card->player->user->name;
                    $prizeText = $prize ? $prize->name : 'Bingo de Honra';

                    $message = [
                        'title' => '🎉 Novo Vencedor!',
                        'body' => "{$winnerName} ganhou: {$prizeText}",
                    ];

                    foreach ($otherPlayerIds as $playerId) {
                        $pushService->notifyUser($playerId, $message['title'], $message['body'], route('games.play', $this->game->uuid));
                    }
                }
            });

            $this->finishAction($prize ? 'Prêmio concedido!' : 'Bingo de Honra registrado!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao conceder prêmio: ' . $e->getMessage());
        } finally {
            $this->isProcessingAction = false;
        }
    }

    private function finishAction(string $msg): void
    {
        $this->game->refresh();
        $this->loadGameData();

        broadcast(new GameUpdated($this->game))->toOthers();
        $this->dispatch('game-updated')->self();

        $this->dispatch('notify', type: 'success', text: $msg);
    }

    public function startNextRound(): void
    {
        if ($this->isProcessingAction) return;

        if (!$this->isCreator) {
            $this->dispatch('notify', type: 'error', text: 'Apenas o criador pode iniciar rodadas.');
            return;
        }

        if ($this->game->current_round >= $this->game->max_rounds) {
            $this->dispatch('notify', type: 'error', text: 'Esta é a última rodada.');
            return;
        }

        $this->isProcessingAction = true;

        try {
            $proxRodada = (int) ($this->game->current_round + 1);

            DB::transaction(function () use ($proxRodada) {
                DB::table('games')
                    ->where('id', $this->game->id)
                    ->update([
                        'current_round' => $proxRodada,
                        'updated_at' => now(),
                    ]);

                $players = DB::table('players')->where('game_id', $this->game->id)->get();
                $cardsPer = (int) ($this->game->cards_per_player ?? 1);

                foreach ($players as $player) {
                    DB::table('cards')->where('player_id', $player->id)->where('round_number', $proxRodada)->delete();
                    for ($i = 0; $i < $cardsPer; $i++) {
                        DB::table('cards')->insert([
                            'uuid' => (string) Str::uuid(),
                            'game_id' => $this->game->id,
                            'player_id' => $player->id,
                            'round_number' => $proxRodada,
                            'numbers' => json_encode($this->game->generateCardNumbers()),
                            'marked' => json_encode([]),
                            'is_bingo' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

            $pushService = app(\App\Services\PushNotificationService::class);
            $playerIds = $this->game->players->pluck('user_id')->toArray();

            if (!empty($playerIds)) {
                $message = [
                    'title' => "🔄 Rodada {$proxRodada} Iniciada!",
                    'body' => "{$this->game->name} - Novas cartelas disponíveis",
                ];

                foreach ($playerIds as $playerId) {
                    $pushService->notifyUser($playerId, $message['title'], $message['body'], route('games.play', $this->game->uuid));
                }
            }

            $this->finishAction("Rodada {$proxRodada} iniciada!");
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao iniciar rodada: ' . $e->getMessage());
        } finally {
            $this->isProcessingAction = false;
        }
    }

    public function startGame(): void
    {
        if ($this->isProcessingAction) return;

        if (!$this->isCreator || $this->game->status !== 'waiting') {
            $this->dispatch('notify', type: 'error', text: 'Não é possível iniciar a partida.');
            return;
        }

        if ($this->game->players()->count() === 0) {
            $this->dispatch('notify', type: 'error', text: 'Aguarde pelo menos um jogador entrar.');
            return;
        }

        $this->isProcessingAction = true;

        try {
            $this->game->update(['status' => 'active', 'started_at' => now()]);

            $pushService = app(\App\Services\PushNotificationService::class);
            $playerIds = $this->game->players->pluck('user_id')->toArray();

            if (!empty($playerIds)) {
                $message = [
                    'title' => '🎮 Partida Iniciada!',
                    'body' => "{$this->game->name} - O jogo começou agora!",
                ];

                foreach ($playerIds as $playerId) {
                    $pushService->notifyUser($playerId, $message['title'], $message['body'], route('games.play', $this->game->uuid));
                }
            }

            $this->finishAction('Partida iniciada!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao iniciar partida: ' . $e->getMessage());
        } finally {
            $this->isProcessingAction = false;
        }
    }

    public function finishGame(): void
    {
        if ($this->isProcessingAction) return;

        if (!$this->isCreator) {
            $this->dispatch('notify', type: 'error', text: 'Apenas o criador pode finalizar a partida.');
            return;
        }

        $this->isProcessingAction = true;

        try {
            $this->game->update(['status' => 'finished', 'finished_at' => now()]);

            $pushService = app(\App\Services\PushNotificationService::class);
            $playerIds = $this->game->players->pluck('user_id')->toArray();

            if (!empty($playerIds)) {
                $message = [
                    'title' => '🏁 Partida Finalizada',
                    'body' => "{$this->game->name} - Confira os resultados finais!",
                ];

                foreach ($playerIds as $playerId) {
                    $pushService->notifyUser($playerId, $message['title'], $message['body'], route('games.play', $this->game->uuid));
                }
            }

            $this->finishAction('Partida finalizada!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao finalizar partida: ' . $e->getMessage());
        } finally {
            $this->isProcessingAction = false;
        }
    }

    public function togglePause(): void
    {
        if (!$this->isCreator) {
            $this->dispatch('notify', type: 'error', text: 'Apenas o criador pode pausar/retomar.');
            return;
        }

        $this->isPaused = !$this->isPaused;

        if (!$this->isPaused) {
            $this->game->refresh();
        }

        $msg = $this->isPaused ? 'Sorteio pausado!' : 'Sorteio retomado!';
        $this->dispatch('notify', type: 'info', text: $msg);
    }

    public function updateDrawSpeed(): void
    {
        if (!$this->isCreator) {
            $this->dispatch('notify', type: 'error', text: 'Apenas o criador pode alterar a velocidade.');
            return;
        }

        if ($this->tempSeconds < 2) $this->tempSeconds = 2;
        if ($this->tempSeconds > 60) $this->tempSeconds = 60;

        $this->game->update(['auto_draw_seconds' => $this->tempSeconds]);
        $this->dispatch('notify', type: 'success', text: "Intervalo atualizado para {$this->tempSeconds}s");
    }

    public function autoDraw(): void
    {
        if (!$this->isCreator || $this->game->status !== 'active' || $this->game->draw_mode !== 'automatic') {
            return;
        }

        if ($this->isPaused || $this->winningCards->isNotEmpty()) return;
        if ($this->drawnCount >= 75) return;

        $this->drawNumber();
    }

    #[Computed]
    public function isGameMaster(): bool
    {
        return $this->isCreator;
    }

    #[Computed]
    public function numberSounds()
    {
        return GameAudio::active()
            ->where('type', 'player')
            ->where(function($q) {
                $q->where('name', 'like', '%Número%')
                  ->orWhere('name', 'like', '%numero%')
                  ->orWhere('name', 'like', '%Number%');
            })
            ->orderBy('order')
            ->get();
    }

    #[Computed]
    public function winnerSounds()
    {
        return GameAudio::active()
            ->where('type', 'player')
            ->where(function($q) {
                $q->where('name', 'like', '%Vencedor%')
                  ->orWhere('name', 'like', '%vencedor%')
                  ->orWhere('name', 'like', '%Winner%');
            })
            ->orderBy('order')
            ->get();
    }
};
?>

<!-- Bloqueio de áudio local nesta tela (controle/criador) -->
<script>
    window.isControlPanel = true;
    window.isPublicDisplay = false;
    console.log('=== TELA DE CONTROLE: Áudio bloqueado localmente ===');
</script>

<div class="min-h-screen bg-[#05070a] text-slate-200">
    <div class="flex flex-col lg:flex-row min-h-screen relative">

        {{-- Sidebar --}}
        <aside
            class="w-full lg:w-80 xl:w-96 bg-[#0b0d11] border-b lg:border-b-0 lg:border-r border-white/10 p-6 lg:sticky lg:top-0 lg:h-screen overflow-y-auto">
            <div class="space-y-6">

                {{-- Header --}}
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ $game->name }}</h1>
                    <div class="mt-3 flex items-center gap-3 flex-wrap">
                        <span
                            class="px-3 py-1 rounded-lg text-xs font-semibold border
                            @if ($game->status === 'active') bg-green-500/10 text-green-400 border-green-500/20
                            @elseif($game->status === 'waiting') bg-yellow-500/10 text-yellow-400 border-yellow-500/20
                            @elseif($game->status === 'finished') bg-slate-500/10 text-slate-400 border-white/10
                            @else bg-blue-500/10 text-blue-400 border-blue-500/20 @endif">
                            {{ ucfirst($game->status) }}
                        </span>
                        <span class="text-xs text-slate-400 font-semibold">
                            Rodada {{ $game->current_round }}/{{ $game->max_rounds }}
                        </span>
                    </div>
                </div>

                {{-- Saldo --}}
                @if ($isCreator)
                    <div class="bg-[#161920] border border-white/10 rounded-xl p-4">
                        <div class="text-xs text-blue-400 font-semibold mb-1">Saldo Disponível</div>
                        <div class="text-2xl font-bold text-white">
                            {{ number_format($this->creatorBalance, 0, ',', '.') }} C$
                        </div>
                        @if (!$game->package->is_free && $game->status !== 'finished')
                            <div class="mt-2 pt-2 border-t border-white/10">
                                <div class="text-xs text-slate-400">
                                    Custo: {{ number_format($this->packageCost, 0, ',', '.') }} C$
                                </div>
                                @if ($this->willRefund)
                                    <div class="text-xs text-green-400 font-semibold mt-1">
                                        ✓ Reembolso automático
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Código --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Código de Acesso</label>
                    <div
                        class="bg-[#161920] rounded-lg px-4 py-3 text-blue-400 font-bold tracking-widest text-lg border border-white/10">
                        {{ $game->invite_code }}
                    </div>
                </div>

                {{-- Ações --}}
                @if ($isCreator)
                    <div class="pt-6 border-t border-white/10 space-y-3">
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false" type="button"
                                class="w-full bg-blue-600/20 hover:bg-blue-600/30 text-white py-3 rounded-lg font-semibold text-sm flex items-center justify-center gap-2 transition border border-blue-500/20">
                                <span>📺</span> Transmitir Arena
                            </button>

                            <div x-show="open" x-transition
                                class="absolute bottom-full left-0 mb-2 w-full bg-[#161920] rounded-lg shadow-2xl border border-white/10 py-2 z-50">
                                <button
                                    @click="window.open('https://wa.me/?text=' + encodeURIComponent('Participe do bingo {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}. Veja: {{ route('games.display', $game) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300 transition">
                                    <span class="text-xl">📱</span> WhatsApp
                                </button>
                                <button
                                    @click="window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('Jogue bingo: {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}') + '&url=' + encodeURIComponent('{{ route('games.display', $game) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300 transition">
                                    <span class="text-xl">🐦</span> Twitter/X
                                </button>
                                <button
                                    @click="navigator.clipboard.writeText('{{ route('games.display', $game) }}').then(() => { open = false; })"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300 transition">
                                    <span class="text-xl">📋</span> Copiar Link
                                </button>
                            </div>
                        </div>

                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false" type="button"
                                class="w-full bg-green-500/20 hover:bg-green-500/30 text-white py-3 rounded-lg font-semibold text-sm flex items-center justify-center gap-2 transition border border-green-500/20">
                                <span>👥</span> Convocar Jogadores
                            </button>

                            <div x-show="open" x-transition
                                class="absolute bottom-full left-0 mb-2 w-full bg-[#161920] rounded-lg shadow-2xl border border-white/10 py-2 z-50">
                                <button
                                    @click="window.open('https://wa.me/?text=' + encodeURIComponent('Entre no bingo {{ addslashes($game->name) }} com o código {{ $game->invite_code }}: {{ route('games.join', $game->invite_code) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300 transition">
                                    <span class="text-xl">📱</span> WhatsApp
                                </button>
                                <button
                                    @click="window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('Entre no bingo {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}') + '&url=' + encodeURIComponent('{{ route('games.join', $game->invite_code) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300 transition">
                                    <span class="text-xl">🐦</span> Twitter/X
                                </button>
                                <button
                                    @click="navigator.clipboard.writeText('{{ route('games.join', $game->invite_code) }}').then(() => { open = false; });"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300 transition">
                                    <span class="text-xl">📋</span> Copiar Link
                                </button>
                            </div>
                        </div>

                        <a href="{{ route('games.display', $game) }}" target="_blank"
                            class="w-full bg-purple-500/20 hover:bg-purple-500/30 text-white py-3 rounded-lg font-semibold text-sm flex items-center justify-center gap-2 transition border border-purple-500/20">
                            <span>📺</span> Visor Público
                        </a>

                        @if ($game->status === 'waiting')
                            <button wire:click="startGame" wire:loading.attr="disabled"
                                class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-semibold text-sm transition active:scale-95 disabled:opacity-50">
                                <span wire:loading.remove wire:target="startGame">Iniciar Partida</span>
                                <span wire:loading wire:target="startGame">Iniciando...</span>
                            </button>
                        @endif

                        @if ($game->status === 'active' && $game->current_round < $game->max_rounds)
                            <button wire:click="startNextRound" wire:loading.attr="disabled"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-semibold text-sm transition active:scale-95 disabled:opacity-50">
                                <span wire:loading.remove wire:target="startNextRound">Próxima Rodada
                                    ({{ $game->current_round + 1 }}/{{ $game->max_rounds }})</span>
                                <span wire:loading wire:target="startNextRound">Iniciando...</span>
                            </button>
                        @endif

                        @if ($game->status !== 'finished')
                            <button wire:click="finishGame" wire:confirm="Deseja finalizar a partida agora?"
                                wire:loading.attr="disabled"
                                class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg font-semibold text-sm transition active:scale-95 disabled:opacity-50">
                                <span wire:loading.remove wire:target="finishGame">Encerrar Partida</span>
                                <span wire:loading wire:target="finishGame">Encerrando...</span>
                            </button>
                        @endif

                        {{-- ── CONTROLES DE ÁUDIO (APENAS PLANOS PAGOS) ────────── --}}
                        @if ($this->canUseCustomAudio)
                            <div class="bg-gradient-to-br from-indigo-600/10 to-purple-600/10 border-2 border-indigo-500/30 rounded-xl p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                                        <span>🔊</span> Áudio do Display
                                    </h4>
                                    <button wire:click="$toggle('showAudioSettings')"
                                        class="text-indigo-400 hover:text-indigo-300 transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="{{ $showAudioSettings ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Toggle Principal --}}
                                <div class="flex items-center justify-between p-3 bg-[#0b0d11] rounded-lg border border-white/5 mb-3">
                                    <span class="text-xs font-semibold text-slate-300">Áudio Ativado</span>
                                    <button wire:click="toggleAudioEnabled"
                                        class="w-12 h-6 rounded-full relative transition-all {{ $audioEnabled ? 'bg-indigo-600' : 'bg-slate-600' }}">
                                        <div class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full transition-transform {{ $audioEnabled ? 'translate-x-6' : '' }}"></div>
                                    </button>
                                </div>

                                @if($showAudioSettings && $audioEnabled)
                                    <div class="space-y-3 animate-fade-in">
                                        {{-- Som para Número --}}
                                        <div class="p-3 bg-[#0b0d11] rounded-lg border border-white/5">
                                            <label class="block text-xs font-semibold text-blue-400 mb-2 flex items-center justify-between">
                                                <span>🎵 Som para Número</span>
                                                <button wire:click="testNumberSound" type="button"
                                                    class="text-[10px] bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded font-bold transition">
                                                    Testar
                                                </button>
                                            </label>
                                            <select wire:model.live="selected_number_sound_id"
                                                class="w-full bg-[#161920] border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                                                @foreach ($this->numberSounds as $sound)
                                                    <option value="{{ $sound->id }}">
                                                        {{ $sound->name }}
                                                        ({{ $sound->audio_type === 'mp3' ? '🎵 Efeito' : '🎤 Voz' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if($this->selectedNumberSound)
                                                <p class="mt-2 text-[10px] text-purple-400">
                                                    @if($this->selectedNumberSound->audio_type === 'tts')
                                                        📢 {{ $this->selectedNumberSound->tts_voice }}
                                                    @else
                                                        🎵 Efeito Sonoro
                                                    @endif
                                                </p>
                                            @endif
                                        </div>

                                        {{-- Som para Vencedor --}}
                                        <div class="p-3 bg-[#0b0d11] rounded-lg border border-white/5">
                                            <label class="block text-xs font-semibold text-purple-400 mb-2 flex items-center justify-between">
                                                <span>🏆 Som para Vencedor</span>
                                                <button wire:click="testWinnerSound" type="button"
                                                    class="text-[10px] bg-purple-600 hover:bg-purple-700 text-white px-2 py-1 rounded font-bold transition">
                                                    Testar
                                                </button>
                                            </label>
                                            <select wire:model.live="selected_winner_sound_id"
                                                class="w-full bg-[#161920] border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500">
                                                @foreach ($this->winnerSounds as $sound)
                                                    <option value="{{ $sound->id }}">
                                                        {{ $sound->name }}
                                                        ({{ $sound->audio_type === 'mp3' ? '🎵 Efeito' : '🎤 Voz' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if($this->selectedWinnerSound)
                                                <p class="mt-2 text-[10px] text-purple-400">
                                                    @if($this->selectedWinnerSound->audio_type === 'tts')
                                                        📢 {{ $this->selectedWinnerSound->tts_voice }}
                                                    @else
                                                        🎵 Efeito Sonoro
                                                    @endif
                                                </p>
                                            @endif
                                        </div>

                                        {{-- Info --}}
                                        <div class="p-2 bg-indigo-500/10 border border-indigo-500/20 rounded-lg">
                                            <p class="text-[10px] text-indigo-300 text-center font-semibold">
                                                💡 Sons tocam automaticamente no display público
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            {{-- Card de Upgrade --}}
                            <div class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 border-2 border-orange-500/30 rounded-xl p-5 relative overflow-hidden">
                                <div class="absolute top-0 right-0 w-20 h-20 bg-orange-600/10 rounded-full blur-2xl"></div>
                                <div class="relative">
                                    <div class="flex items-center gap-3 mb-3">
                                        <span class="text-2xl">🔊</span>
                                        <h4 class="text-sm font-bold text-white uppercase tracking-wider">Controle de Áudio</h4>
                                    </div>
                                    <div class="mb-3 px-3 py-2 bg-orange-600/20 border border-orange-500/30 rounded-lg">
                                        <p class="text-[10px] text-orange-300 font-bold uppercase text-center">
                                            🔒 Exclusivo Planos Pagos
                                        </p>
                                    </div>
                                    <p class="text-xs text-slate-400 mb-4">
                                        Personalize sons de sorteio e vitória com vozes profissionais ou efeitos exclusivos.
                                    </p>
                                    <a href="{{ route('wallet.index') }}"
                                        class="block text-center bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-500 hover:to-red-500 text-white py-2 rounded-lg font-bold text-xs uppercase tracking-wider transition shadow-lg">
                                        ⭐ Fazer Upgrade
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 p-6 lg:p-8 overflow-y-auto">

            {{-- Aviso Prêmios --}}
            @if ($showNoPrizesWarning)
                <div class="mb-6 bg-orange-500/10 border border-orange-500/20 rounded-xl p-6">
                    <div class="flex items-start gap-4">
                        <div class="text-3xl text-orange-400">⚠️</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-orange-400 mb-2">Alerta: Prêmios Esgotados</h3>
                            <p class="text-slate-400 text-sm mb-4">
                                Todos os prêmios foram distribuídos, mas há cartelas vencedoras. Adicione mais prêmios
                                ou avance para a próxima rodada.
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <a href="{{ route('games.edit', $game) }}"
                                    class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-semibold text-xs transition">
                                    Adicionar Prêmios
                                </a>
                                @if ($game->canStartNextRound())
                                    <button wire:click="startNextRound"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold text-xs transition">
                                        Próxima Rodada
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Centro de Comando --}}
            @if ($game->status === 'active')
                <section class="mb-10 bg-[#0b0d11] border border-white/10 rounded-xl p-6"
                    wire:key="control-{{ $game->current_round }}-{{ $isPaused ? 'p' : 'a' }}"
                    @if ($game->draw_mode === 'automatic' && !$isPaused && !$this->winningCards->isNotEmpty()) wire:poll.visible.{{ $game->auto_draw_seconds ?? 3 }}s="autoDraw" @endif>

                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-white flex items-center gap-2">
                            <span class="text-2xl">🕹️</span> Centro de Comando
                        </h2>

                        <div class="flex items-center gap-2 bg-white/5 px-3 py-1 rounded-lg border border-white/10">
                            <div class="text-xs font-semibold text-slate-400">Progresso</div>
                            <div class="w-16 bg-[#161920] rounded-full h-1.5 overflow-hidden border border-white/10">
                                <div class="bg-blue-500 h-full transition-all duration-500"
                                    style="width: {{ ($drawnCount / 75) * 100 }}%"></div>
                            </div>
                            <span
                                class="text-xs font-bold text-blue-400">{{ round(($drawnCount / 75) * 100) }}%</span>
                        </div>
                    </div>

                    @if ($game->draw_mode === 'manual')
                        <button wire:click="drawNumber" wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            class="relative w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-lg font-bold text-sm mb-6 transition active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                            @if ($drawnCount >= 75 || $isDrawing) disabled @endif>
                            <div wire:loading wire:target="drawNumber"
                                class="absolute left-6 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </div>
                            <span wire:loading.remove wire:target="drawNumber">Sortear Número</span>
                            <span wire:loading wire:target="drawNumber">Sorteando...</span>
                        </button>
                    @else
                        <div class="bg-[#161920] border border-blue-500/20 rounded-xl p-5 mb-6">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    @if (!$isPaused)
                                        <div class="flex h-4 w-4 rounded-full bg-green-500 animate-pulse"></div>
                                        <div>
                                            <div class="text-sm font-bold text-green-400">Modo Automático</div>
                                            <div class="text-xs text-slate-400 font-semibold">Próximo em
                                                {{ $game->auto_draw_seconds ?? 3 }}s</div>
                                        </div>
                                    @else
                                        <div class="flex h-4 w-4 rounded-full bg-red-500"></div>
                                        <div>
                                            <div class="text-sm font-bold text-red-400">Pausado</div>
                                            @if ($this->winningCards->isNotEmpty())
                                                <div
                                                    class="text-xs bg-red-500/10 text-red-400 px-2 py-0.5 rounded mt-1 font-semibold border border-red-500/20">
                                                    ⚠️ Bingo Detectado
                                                </div>
                                            @else
                                                <div class="text-xs text-slate-400 font-semibold">Timer Congelado</div>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="flex items-center bg-[#0b0d11] border border-white/10 rounded-lg p-1">
                                        <div class="px-2 border-r border-white/10">
                                            <span class="text-xs font-semibold text-slate-400">Intervalo</span>
                                            <input type="number" wire:model="tempSeconds"
                                                class="w-10 bg-transparent border-none p-0 focus:ring-0 text-sm font-bold text-white"
                                                min="2" max="60">
                                        </div>
                                        <button wire:click="updateDrawSpeed" wire:loading.attr="disabled"
                                            class="ml-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-xs font-bold transition active:scale-90 disabled:opacity-50">
                                            <span wire:loading.remove wire:target="updateDrawSpeed">OK</span>
                                            <svg wire:loading wire:target="updateDrawSpeed"
                                                class="animate-spin h-3 w-3 text-white" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>

                                    <button wire:click="togglePause"
                                        class="px-5 py-2.5 rounded-lg font-bold text-xs transition active:scale-95 {{ $isPaused ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-orange-500 hover:bg-orange-600 text-white' }}">
                                        {{ $isPaused ? '▶️ Retomar' : '⏸️ Pausar' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Número Sorteado --}}
                    @if ($drawnCount > 0)
                        <div
                            class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl p-10 mb-8 text-center relative overflow-hidden group">
                            <div
                                class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl transition-transform group-hover:scale-125">
                            </div>
                            <div
                                class="absolute -left-10 -bottom-10 w-40 h-40 bg-blue-500/20 rounded-full blur-3xl transition-transform group-hover:scale-125">
                            </div>

                            <div class="relative z-10">
                                <div class="text-xs text-white/60 font-bold uppercase mb-3">Último Número</div>
                                <div
                                    class="text-8xl font-black text-white drop-shadow-2xl transition-transform duration-500 group-hover:scale-110">
                                    {{ $lastDrawnNumber }}
                                </div>
                                <div
                                    class="mt-4 inline-block px-4 py-1 bg-black/30 rounded-lg text-xs font-bold text-white/80 border border-white/10">
                                    Sequência #{{ $drawnCount }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-end mb-4 px-1">
                                <div>
                                    <div class="text-xs font-semibold text-slate-400">Histórico</div>
                                    <div class="text-lg font-bold text-white">Números Sorteados</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-semibold text-slate-400">Faltam</div>
                                    <div class="text-xs font-bold text-blue-400">{{ 75 - $drawnCount }} números</div>
                                </div>
                            </div>

                            <div
                                class="grid grid-cols-10 sm:grid-cols-15 gap-1 p-4 bg-[#161920] rounded-xl border border-white/10 max-h-56 overflow-y-auto">
                                @foreach (range(1, 75) as $num)
                                    <div
                                        class="aspect-square rounded-lg flex items-center justify-center text-xs font-bold transition-all duration-300
                                        {{ in_array($num, $drawnNumbersList) ? 'bg-blue-500 text-white scale-105' : 'bg-[#0b0d11] border border-white/10 text-slate-600' }}">
                                        {{ $num }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-center py-10 bg-[#161920] rounded-xl border border-white/10">
                            <div class="text-4xl text-slate-600 mb-3">🔮</div>
                            <div class="text-xs font-semibold text-slate-500">Aguardando Primeiro Sorteio</div>
                        </div>
                    @endif
                </section>
            @endif

            {{-- Cartelas Vencedoras --}}
            @if ($this->winningCards->count() > 0)
                <section class="mb-10 bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-6">
                    <h2 class="text-2xl font-bold text-yellow-400 mb-6 flex items-center gap-3">
                        <span class="text-3xl animate-pulse">🎉</span> Bingo Detectado!
                    </h2>

                    @php $nextPrize = $this->nextAvailablePrize; @endphp

                    <div class="grid gap-4">
                        @foreach ($this->winningCards as $card)
                            <div
                                class="bg-[#161920] p-5 rounded-xl border border-yellow-500/20 flex flex-col sm:flex-row justify-between items-center gap-4">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="bg-yellow-500/20 text-yellow-400 w-12 h-12 rounded-full flex items-center justify-center font-bold text-xl border border-yellow-500/20">
                                        {{ substr($card->player->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="text-lg font-bold text-white">{{ $card->player->user->name }}
                                        </div>
                                        <div class="text-xs text-yellow-400 font-semibold">Cartela
                                            #{{ substr($card->uuid, 0, 8) }}</div>
                                    </div>
                                </div>

                                <div class="w-full sm:w-auto">
                                    @if ($nextPrize)
                                        <button
                                            wire:click="claimPrize('{{ $card->uuid }}', '{{ $nextPrize->uuid }}')"
                                            wire:loading.attr="disabled"
                                            class="w-full bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-bold text-xs transition active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50">
                                            <span wire:loading.remove
                                                wire:target="claimPrize('{{ $card->uuid }}', '{{ $nextPrize->uuid }}')">
                                                <span>🎁</span> Conceder: {{ $nextPrize->name }}
                                            </span>
                                            <span wire:loading
                                                wire:target="claimPrize('{{ $card->uuid }}', '{{ $nextPrize->uuid }}')">
                                                Processando...
                                            </span>
                                        </button>
                                    @else
                                        <button wire:click="claimPrize('{{ $card->uuid }}', null)"
                                            wire:loading.attr="disabled"
                                            class="w-full bg-slate-600 hover:bg-slate-700 text-white px-8 py-3 rounded-lg font-bold text-xs transition active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50">
                                            <span wire:loading.remove
                                                wire:target="claimPrize('{{ $card->uuid }}', null)">
                                                <span>🏅</span> Registrar Honra
                                            </span>
                                            <span wire:loading wire:target="claimPrize('{{ $card->uuid }}', null)">
                                                Processando...
                                            </span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if (!$nextPrize)
                        <div class="mt-6 p-4 bg-[#0b0d11] border border-yellow-500/20 rounded-xl">
                            <p class="text-yellow-400 text-xs font-semibold">
                                ⚠️ Todos os prêmios foram distribuídos. Novos vencedores receberão registro de honra.
                            </p>
                        </div>
                    @endif
                </section>
            @endif

            {{-- Prêmios --}}
            <section class="mb-10 bg-[#0b0d11] border border-white/10 rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-6">Gerenciar Prêmios</h2>

                @if ($this->nextAvailablePrize)
                    <div class="mb-6 bg-blue-500/10 border border-blue-500/20 rounded-xl p-4">
                        <div class="flex items-center gap-3">
                            <div class="text-3xl">🎯</div>
                            <div>
                                <div class="text-xs text-blue-400 font-semibold">Próximo Prêmio</div>
                                <div class="text-lg font-bold text-blue-400">
                                    {{ $this->nextAvailablePrize->position }}º - {{ $this->nextAvailablePrize->name }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach ($game->prizes->sortBy('position') as $prize)
                        <div wire:key="prize-{{ $prize->id }}"
                            class="border border-white/10 rounded-xl p-5 bg-[#161920] hover:border-blue-500/20 transition
                            {{ $prize->is_claimed ? 'border-green-500/20' : '' }}
                            {{ !$prize->is_claimed && $this->nextAvailablePrize?->id === $prize->id ? 'border-blue-500/50' : '' }}">

                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        @if ($prize->position == 1)
                                            <span class="text-xl">🥇</span>
                                        @endif
                                        <div class="text-sm font-bold text-white">{{ $prize->position }}º -
                                            {{ $prize->name }}</div>
                                    </div>

                                    @if ($prize->is_claimed)
                                        @php $winner = $prize->winner()->where('game_id', $game->id)->first(); @endphp
                                        <div class="mt-2 p-2 bg-[#0b0d11] rounded border border-green-500/20">
                                            <span class="text-xs font-semibold text-green-400">Vencedor:</span>
                                            <div class="text-xs font-bold text-white">
                                                {{ $winner->user->name ?? 'N/A' }}</div>
                                            <div class="text-xs text-slate-500">Rodada {{ $winner->round_number }}
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <span
                                    class="px-2 py-1 rounded-lg text-xs font-semibold border
                                    {{ $prize->is_claimed ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-blue-500/10 text-blue-400 border-blue-500/20' }}">
                                    {{ $prize->is_claimed ? 'Ganho' : 'Disponível' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Jogadores --}}
            <section class="mb-10 bg-[#0b0d11] border border-white/10 rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-6">Jogadores ({{ $game->players->count() }})</h2>

                @if ($game->players->isEmpty())
                    <div class="text-center py-12 text-slate-500">
                        <div class="text-4xl opacity-50 mb-4">👥</div>
                        <div class="text-xs font-semibold">Aguardando Jogadores</div>
                    </div>
                @else
                    <div class="space-y-4 max-h-[60vh] overflow-y-auto">
                        @foreach ($game->players as $player)
                            <div
                                class="flex items-center justify-between p-4 bg-[#161920] rounded-xl border border-white/10 hover:bg-white/5 transition">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="w-12 h-12 bg-blue-500/20 text-blue-400 rounded-full flex items-center justify-center font-bold text-xl border border-blue-500/20">
                                        {{ substr($player->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-white">{{ $player->user->name }}</div>
                                        <div class="text-xs text-slate-400 font-semibold">
                                            {{ $player->cards()->where('round_number', $game->current_round)->count() }}
                                            Cartelas
                                        </div>
                                    </div>
                                </div>
                                @php
                                    $roundWin = $player->user
                                        ->wins()
                                        ->where('game_id', $game->id)
                                        ->where('round_number', $game->current_round)
                                        ->with('prize')
                                        ->first();
                                @endphp

                                @if ($roundWin)
                                    <div class="text-right">
                                        @if ($roundWin->prize)
                                            <span class="block text-xs font-bold text-yellow-400">🏆
                                                {{ $roundWin->prize->position }}º Lugar</span>
                                            <span
                                                class="block text-xs text-slate-400 font-semibold">{{ $roundWin->prize->name }}</span>
                                        @else
                                            <span class="block text-xs font-bold text-slate-400">✨ Honra</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Hall da Fama --}}
            <section
                class="bg-gradient-to-br from-indigo-600/20 to-slate-900/20 rounded-xl p-6 border border-indigo-500/20">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <span class="animate-pulse">🏆</span> Hall da Fama
                        </h3>
                        <span class="text-xs text-indigo-300 font-semibold">Todas as Rodadas</span>
                    </div>
                    <span
                        class="text-xs bg-indigo-500/20 text-indigo-300 px-3 py-1 rounded-lg font-semibold border border-indigo-500/20">
                        {{ $game->winners()->count() }} Vencedores
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($game->winners()->with(['user', 'prize'])->orderBy('won_at', 'asc')->get() as $index => $winner)
                        <div
                            class="flex items-center gap-3 bg-white/5 border border-white/10 p-3 rounded-xl hover:bg-white/10 transition">
                            <div class="relative">
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm
                                    {{ $winner->prize_id ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/20' : 'bg-slate-500/20 text-slate-400 border border-slate-500/20' }}">
                                    {{ $index + 1 }}º
                                </div>
                                <div
                                    class="absolute -top-1 -right-1 bg-indigo-500/50 text-white text-xs px-1 rounded font-bold border border-indigo-900/50">
                                    R{{ $winner->round_number }}
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold text-white truncate">{{ $winner->user->name }}</div>
                                <div
                                    class="text-xs font-semibold {{ $winner->prize_id ? 'text-yellow-400' : 'text-indigo-400' }}">
                                    {{ $winner->prize ? $winner->prize->name : 'Honra ✨' }}
                                </div>
                            </div>

                            <div class="text-xs font-mono text-white/30">
                                {{ $winner->won_at->format('H:i') }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($game->winners()->count() === 0)
                    <div class="text-center py-10">
                        <div class="text-white/20 text-4xl mb-2">⭐</div>
                        <p class="text-white/40 text-xs font-semibold">Aguardando Primeiros Vencedores...</p>
                    </div>
                @endif
            </section>
        </main>
    </div>

    <x-toast />
</div>

<style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fade-in 0.3s ease-out;
    }
</style>