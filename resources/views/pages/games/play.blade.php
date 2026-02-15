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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\GameAudio;
use Livewire\Attributes\Layout;

new #[Layout('layouts.control')] class extends Component {
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

    public bool $audioEnabled = true;
    public ?int $selected_number_sound_id = null;
    public ?int $selected_winner_sound_id = null;
    public bool $showAudioSettings = false;

    public bool $isDrawing = false;
    public bool $isProcessingAction = false;

    // Mobile UI
    public bool $showMobileSidebar = false;
    public string $activeMobileTab = 'control'; // control, prizes, players, winners

    public function mount(string $uuid): void
    {
        $user = auth()->user();

        $this->game = Game::where('uuid', $uuid)
            ->with(['creator:id,name,wallet_id', 'package:id,name,max_players,is_free,cost_credits', 'prizes:id,game_id,name,description,position,is_claimed,uuid', 'players.user:id,name', 'audioSettings.audio'])
            ->firstOrFail();

        $this->isCreator = $this->game->creator_id === $user->id;

        if (!$this->isCreator && !$this->game->players()->where('user_id', $user->id)->exists()) {
            abort(403, 'Acesso negado.');
        }

        $this->winningCards = collect();
        $this->tempSeconds = $this->game->auto_draw_seconds ?? 3;

        $this->loadAudioSettings();
        $this->loadGameData();
    }

    private function loadAudioSettings(): void
    {
        $numberSetting = $this->game->audioSettings()->where('audio_category', 'number')->where('is_enabled', true)->first();

        $winnerSetting = $this->game->audioSettings()->where('audio_category', 'winner')->where('is_enabled', true)->first();

        if ($numberSetting) {
            $this->selected_number_sound_id = $numberSetting->game_audio_id;
            $this->audioEnabled = true;
        } else {
            $defaultNumber = GameAudio::active()->where('type', 'number')->where('is_default', true)->first();

            $this->selected_number_sound_id = $defaultNumber?->id;
        }

        if ($winnerSetting) {
            $this->selected_winner_sound_id = $winnerSetting->game_audio_id;
        } else {
            $defaultWinner = GameAudio::active()->where('type', 'winner')->where('is_default', true)->first();

            $this->selected_winner_sound_id = $defaultWinner?->id;
        }
    }

    #[Computed]
    public function canUseCustomAudio(): bool
    {
        return !$this->game->package->is_free;
    }

    #[Computed]
    public function selectedNumberSound()
    {
        if (!$this->selected_number_sound_id) {
            return null;
        }
        return GameAudio::find($this->selected_number_sound_id);
    }

    #[Computed]
    public function selectedWinnerSound()
    {
        if (!$this->selected_winner_sound_id) {
            return null;
        }
        return GameAudio::find($this->selected_winner_sound_id);
    }

    public function updatedSelectedNumberSoundId(): void
    {
        if (!$this->canUseCustomAudio) {
            return;
        }

        if ($this->selected_number_sound_id) {
            $this->game->setAudioForCategory('number', $this->selected_number_sound_id, true);
            $this->dispatch('notify', type: 'success', text: 'Som de número atualizado!');

            Log::info('Audio setting updated', [
                'game_id' => $this->game->id,
                'category' => 'number',
                'audio_id' => $this->selected_number_sound_id,
            ]);

            broadcast(new GameUpdated($this->game))->toOthers();
        }
    }

    public function updatedSelectedWinnerSoundId(): void
    {
        if (!$this->canUseCustomAudio) {
            return;
        }

        if ($this->selected_winner_sound_id) {
            $this->game->setAudioForCategory('winner', $this->selected_winner_sound_id, true);
            $this->dispatch('notify', type: 'success', text: 'Som de vencedor atualizado!');

            Log::info('Audio setting updated', [
                'game_id' => $this->game->id,
                'category' => 'winner',
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
        $this->game->audioSettings()->update(['is_enabled' => $this->audioEnabled]);

        Log::info('Audio toggled', [
            'game_id' => $this->game->id,
            'enabled' => $this->audioEnabled,
        ]);

        $msg = $this->audioEnabled ? 'Áudio ativado!' : 'Áudio desativado!';
        $this->dispatch('notify', type: 'info', text: $msg);

        broadcast(new GameUpdated($this->game))->toOthers();
    }

    public function testNumberSound(): void
    {
        if (!$this->canUseCustomAudio) {
            return;
        }

        $audio = $this->selectedNumberSound;
        if ($audio) {
            $this->dispatch('play-sound', type: 'number', audioId: $audio->id);
        }
    }

    public function testWinnerSound(): void
    {
        if (!$this->canUseCustomAudio) {
            return;
        }

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
            ->with(['creator:id,name,wallet_id', 'package:id,name,max_players,is_free,cost_credits', 'prizes:id,game_id,name,description,position,is_claimed,uuid', 'players.user:id,name', 'players.cards', 'draws', 'winners.user', 'winners.prize', 'audioSettings.audio'])
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
        $this->game->load(['prizes', 'package', 'players.cards', 'players.user:id,name', 'winners.user', 'draws' => fn($q) => $q->where('round_number', $this->game->current_round), 'audioSettings.audio']);
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
        if ($this->isDrawing) {
            return;
        }

        if (!$this->isCreator || $this->game->status !== 'active') {
            return;
        }

        if ($this->drawnCount >= 75) {
            return;
        }

        $this->isDrawing = true;

        try {
            $draw = $this->game->drawNumber();

            if (!$draw) {
                Log::warning('Draw number failed', [
                    'game_id' => $this->game->id,
                    'drawn_count' => $this->drawnCount,
                ]);

                $this->dispatch('notify', type: 'error', text: 'Erro ao sortear número.');
                $this->isDrawing = false;
                return;
            }

            Log::info('Number drawn', [
                'game_id' => $this->game->id,
                'number' => $draw->number,
                'round' => $this->game->current_round,
            ]);

            if ($this->drawnCount + 1 >= 75 && $this->game->current_round >= $this->game->max_rounds) {
                $this->finishAction("Número {$draw->number} sorteado!");

                usleep(500000);

                $this->game->update(['status' => 'finished', 'finished_at' => now()]);

                Log::info('Game auto-finished', [
                    'game_id' => $this->game->id,
                    'reason' => 'all_numbers_drawn_last_round',
                ]);

                $this->dispatch('notify', type: 'info', text: 'Partida encerrada automaticamente - todos os números sorteados.');

                broadcast(new GameUpdated($this->game))->toOthers();
                $this->dispatch('game-updated')->self();
            } else {
                $this->finishAction("Número {$draw->number} sorteado!");
                usleep(500000);
            }
        } catch (\Exception $e) {
            Log::error('Draw number exception', [
                'game_id' => $this->game->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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
        $alreadyWinnerIds = DB::table('winners')->where('game_id', $this->game->id)->where('round_number', $this->game->current_round)->pluck('card_id')->toArray();

        $this->winningCards = $allWinningCards->whereNotIn('id', $alreadyWinnerIds)->values();

        if ($this->winningCards->isNotEmpty()) {
            $this->isPaused = true;

            Log::info('Winning cards detected', [
                'game_id' => $this->game->id,
                'winning_cards_count' => $this->winningCards->count(),
                'round' => $this->game->current_round,
            ]);
        }

        $this->showNoPrizesWarning = $this->winningCards->isNotEmpty() && !$this->game->hasAvailablePrizes();
    }

    public function claimPrize(string $cardUuid, ?string $prizeUuid = null): void
    {
        if ($this->isProcessingAction) {
            return;
        }

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

            $existingWinner = Winner::where('game_id', $this->game->id)->where('card_id', $card->id)->where('round_number', $this->game->current_round)->exists();

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

            Log::info('Prize claimed', [
                'game_id' => $this->game->id,
                'card_uuid' => $cardUuid,
                'prize_uuid' => $prizeUuid,
                'user_id' => $card->player->user_id,
                'round' => $this->game->current_round,
            ]);

            $this->finishAction($prize ? 'Prêmio concedido!' : 'Bingo de Honra registrado!');
        } catch (\Exception $e) {
            Log::error('Claim prize failed', [
                'game_id' => $this->game->id,
                'card_uuid' => $cardUuid,
                'prize_uuid' => $prizeUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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
        if ($this->isProcessingAction) {
            return;
        }

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
            $nextRound = $this->game->current_round + 1;

            DB::transaction(function () use ($nextRound) {
                DB::table('games')
                    ->where('id', $this->game->id)
                    ->update([
                        'current_round' => $nextRound,
                        'updated_at' => now(),
                    ]);

                $players = DB::table('players')->where('game_id', $this->game->id)->get();
                $cardsPer = (int) ($this->game->cards_per_player ?? 1);

                foreach ($players as $player) {
                    DB::table('cards')->where('player_id', $player->id)->where('round_number', $nextRound)->delete();
                    for ($i = 0; $i < $cardsPer; $i++) {
                        DB::table('cards')->insert([
                            'uuid' => (string) Str::uuid(),
                            'game_id' => $this->game->id,
                            'player_id' => $player->id,
                            'round_number' => $nextRound,
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
                    'title' => "🔄 Rodada {$nextRound} Iniciada!",
                    'body' => "{$this->game->name} - Novas cartelas disponíveis",
                ];

                foreach ($playerIds as $playerId) {
                    $pushService->notifyUser($playerId, $message['title'], $message['body'], route('games.play', $this->game->uuid));
                }
            }

            Log::info('Round started', [
                'game_id' => $this->game->id,
                'round' => $nextRound,
                'players_count' => count($playerIds),
            ]);

            $this->finishAction("Rodada {$nextRound} iniciada!");
        } catch (\Exception $e) {
            Log::error('Start round failed', [
                'game_id' => $this->game->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('notify', type: 'error', text: 'Erro ao iniciar rodada: ' . $e->getMessage());
        } finally {
            $this->isProcessingAction = false;
        }
    }

    public function startGame(): void
    {
        if ($this->isProcessingAction) {
            return;
        }

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

            Log::info('Game started', [
                'game_id' => $this->game->id,
                'players_count' => count($playerIds),
            ]);

            $this->finishAction('Partida iniciada!');
        } catch (\Exception $e) {
            Log::error('Start game failed', [
                'game_id' => $this->game->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('notify', type: 'error', text: 'Erro ao iniciar partida: ' . $e->getMessage());
        } finally {
            $this->isProcessingAction = false;
        }
    }

    public function finishGame(): void
    {
        if ($this->isProcessingAction) {
            return;
        }

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

            Log::info('Game finished', [
                'game_id' => $this->game->id,
                'players_count' => count($playerIds),
            ]);

            $this->finishAction('Partida finalizada!');
        } catch (\Exception $e) {
            Log::error('Finish game failed', [
                'game_id' => $this->game->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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

        Log::info('Game pause toggled', [
            'game_id' => $this->game->id,
            'paused' => $this->isPaused,
        ]);

        $msg = $this->isPaused ? 'Sorteio pausado!' : 'Sorteio retomado!';
        $this->dispatch('notify', type: 'info', text: $msg);
    }

    public function updateDrawSpeed(): void
    {
        if (!$this->isCreator) {
            $this->dispatch('notify', type: 'error', text: 'Apenas o criador pode alterar a velocidade.');
            return;
        }

        if ($this->tempSeconds < 2) {
            $this->tempSeconds = 2;
        }
        if ($this->tempSeconds > 60) {
            $this->tempSeconds = 60;
        }

        $this->game->update(['auto_draw_seconds' => $this->tempSeconds]);

        Log::info('Draw speed updated', [
            'game_id' => $this->game->id,
            'seconds' => $this->tempSeconds,
        ]);

        $this->dispatch('notify', type: 'success', text: "Intervalo atualizado para {$this->tempSeconds}s");
    }

    public function autoDraw(): void
    {
        if (!$this->isCreator || $this->game->status !== 'active' || $this->game->draw_mode !== 'automatic') {
            return;
        }

        if ($this->isPaused || $this->winningCards->isNotEmpty()) {
            return;
        }
        if ($this->drawnCount >= 75) {
            return;
        }

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
        return GameAudio::active()->where('type', 'number')->orderBy('order')->get();
    }

    #[Computed]
    public function winnerSounds()
    {
        return GameAudio::active()->where('type', 'winner')->orderBy('order')->get();
    }
};
?>

<script>
    window.isControlPanel = true;
    window.isPublicDisplay = false;
</script>

<div class="min-h-screen bg-[#05070a] text-slate-200">


    {{-- MOBILE: Bottom Navigation --}}
    <div
        class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-[#0b0d11]/95 backdrop-blur-xl border-t border-white/10 safe-area-bottom shadow-2xl">
        <div class="grid grid-cols-4 gap-1 p-2">
            <button wire:click="$set('activeMobileTab', 'control')"
                class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-lg transition-colors {{ $activeMobileTab === 'control' ? 'bg-blue-600/20 text-blue-400' : 'text-slate-500' }}">
                <span class="text-xl">🕹️</span>
                <span class="text-[10px] font-bold">Controle</span>
            </button>
            <button wire:click="$set('activeMobileTab', 'prizes')"
                class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-lg transition-colors {{ $activeMobileTab === 'prizes' ? 'bg-blue-600/20 text-blue-400' : 'text-slate-500' }}">
                <span class="text-xl">🎁</span>
                <span class="text-[10px] font-bold">Prêmios</span>
            </button>
            <button wire:click="$set('activeMobileTab', 'players')"
                class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-lg transition-colors {{ $activeMobileTab === 'players' ? 'bg-blue-600/20 text-blue-400' : 'text-slate-500' }}">
                <span class="text-xl">👥</span>
                <span class="text-[10px] font-bold">Jogadores</span>
            </button>
            <button wire:click="$set('activeMobileTab', 'winners')"
                class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-lg transition-colors {{ $activeMobileTab === 'winners' ? 'bg-blue-600/20 text-blue-400' : 'text-slate-500' }}">
                <span class="text-xl">🏆</span>
                <span class="text-[10px] font-bold">Vencedores</span>
            </button>
        </div>
    </div>

    {{-- MOBILE: Floating Menu Button --}}
    <button wire:click="$toggle('showMobileSidebar')"
        class="lg:hidden fixed top-16 left-4 z-40 w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center shadow-xl active:scale-95 transition-transform">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="{{ $showMobileSidebar ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16' }}" />
        </svg>
    </button>

    {{-- MOBILE: Sidebar Overlay --}}
    @if ($showMobileSidebar)
        <div class="lg:hidden fixed inset-0 z-30 bg-black/60 backdrop-blur-sm"
            wire:click="$set('showMobileSidebar', false)"></div>
    @endif

    <div class="flex flex-col lg:flex-row min-h-screen relative mt-12">

        {{-- Sidebar --}}
        <aside
            class="fixed lg:sticky top-0 left-0 h-screen z-30 w-80 bg-[#0b0d11] border-r border-white/10 p-4 overflow-y-auto transition-transform duration-300 lg:translate-x-0 {{ $showMobileSidebar ? 'translate-x-0' : '-translate-x-full' }}">
            <div class="space-y-4">

                {{-- Header --}}
                <div>
                    <h1 class="text-xl font-bold text-white truncate">{{ $game->name }}</h1>
                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        <span
                            class="px-2 py-1 rounded text-[10px] font-bold border
                            @if ($game->status === 'active') bg-green-500/10 text-green-400 border-green-500/20
                            @elseif($game->status === 'waiting') bg-yellow-500/10 text-yellow-400 border-yellow-500/20
                            @elseif($game->status === 'finished') bg-slate-500/10 text-slate-400 border-white/10
                            @else bg-blue-500/10 text-blue-400 border-blue-500/20 @endif">
                            {{ ucfirst($game->status) }}
                        </span>
                        <span class="text-xs text-slate-400 font-semibold">
                            R{{ $game->current_round }}/{{ $game->max_rounds }}
                        </span>
                    </div>
                </div>

                {{-- Saldo --}}
                @if ($isCreator)
                    <div class="bg-[#161920] border border-white/10 rounded-lg p-3">
                        <div class="text-xs text-blue-400 font-semibold mb-1">Saldo</div>
                        <div class="text-xl font-bold text-white">
                            {{ number_format($this->creatorBalance, 0, ',', '.') }} C$</div>
                        @if (!$game->package->is_free && $game->status !== 'finished')
                            <div class="mt-2 pt-2 border-t border-white/10">
                                <div class="text-xs text-slate-400">Custo:
                                    {{ number_format($this->packageCost, 0, ',', '.') }} C$</div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Código --}}
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Código</label>
                    <div
                        class="bg-[#161920] rounded-lg px-3 py-2 text-blue-400 font-bold text-center text-lg border border-white/10">
                        {{ $game->invite_code }}
                    </div>
                </div>

                {{-- Ações --}}
                @if ($isCreator)
                    <div class="space-y-2 mt-10">
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false" type="button"
                                class="w-full bg-blue-600/20 hover:bg-blue-600/30 text-white py-2.5 rounded-lg font-semibold text-xs flex items-center justify-center gap-2 transition border border-blue-500/20">
                                <span>📺</span> Transmitir
                            </button>
                            <div x-show="open" x-transition
                                class="absolute bottom-full left-0 mb-2 w-full bg-[#161920] rounded-lg shadow-2xl border border-white/10 py-1 z-50">
                                <button
                                    @click="window.open('https://wa.me/?text=' + encodeURIComponent('{{ addslashes($game->name) }} - Código: {{ $game->invite_code }}. Veja: {{ route('games.display', $game) }}'), '_blank'); open = false"
                                    class="w-full text-left px-3 py-2 hover:bg-white/5 flex items-center gap-2 text-xs text-slate-300">
                                    <span>📱</span> WhatsApp
                                </button>
                                <button
                                    @click="navigator.clipboard.writeText('{{ route('games.display', $game) }}').then(() => { open = false; })"
                                    class="w-full text-left px-3 py-2 hover:bg-white/5 flex items-center gap-2 text-xs text-slate-300">
                                    <span>📋</span> Copiar Link
                                </button>
                            </div>
                        </div>

                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false" type="button"
                                class="w-full bg-green-500/20 hover:bg-green-500/30 text-white py-2.5 rounded-lg font-semibold text-xs flex items-center justify-center gap-2 transition border border-green-500/20">
                                <span>👥</span> Convocar
                            </button>
                            <div x-show="open" x-transition
                                class="absolute bottom-full left-0 mb-2 w-full bg-[#161920] rounded-lg shadow-2xl border border-white/10 py-1 z-50">
                                <button
                                    @click="window.open('https://wa.me/?text=' + encodeURIComponent('Entre no bingo {{ addslashes($game->name) }} com código {{ $game->invite_code }}: {{ route('games.join', $game->invite_code) }}'), '_blank'); open = false"
                                    class="w-full text-left px-3 py-2 hover:bg-white/5 flex items-center gap-2 text-xs text-slate-300">
                                    <span>📱</span> WhatsApp
                                </button>
                                <button
                                    @click="navigator.clipboard.writeText('{{ route('games.join', $game->invite_code) }}').then(() => { open = false; });"
                                    class="w-full text-left px-3 py-2 hover:bg-white/5 flex items-center gap-2 text-xs text-slate-300">
                                    <span>📋</span> Copiar Link
                                </button>
                            </div>
                        </div>

                        <a href="{{ route('games.display', $game) }}" target="_blank"
                            class="w-full bg-purple-500/20 hover:bg-purple-500/30 text-white py-2.5 rounded-lg font-semibold text-xs flex items-center justify-center gap-2 transition border border-purple-500/20">
                            <span>📺</span> Visor Público
                        </a>

                        @if ($game->status === 'waiting')
                            <button wire:click="startGame" wire:loading.attr="disabled"
                                class="w-full bg-green-600 hover:bg-green-700 text-white py-2.5 rounded-lg font-semibold text-xs transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="startGame">Iniciar Partida</span>
                                <span wire:loading wire:target="startGame">Iniciando...</span>
                            </button>
                        @endif

                        @if ($game->status === 'active')
                            <button wire:click="startNextRound" wire:loading.attr="disabled"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-semibold text-xs transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="startNextRound">Próxima Rodada
                                    ({{ $game->current_round + 1 }}/{{ $game->max_rounds }})</span>
                                <span wire:loading wire:target="startNextRound">Iniciando...</span>
                            </button>
                        @endif

                        @if ($game->status !== 'finished')
                            <button wire:click="finishGame" wire:confirm="Deseja finalizar a partida agora?"
                                wire:loading.attr="disabled"
                                class="w-full bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-lg font-semibold text-xs transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="finishGame">Encerrar Partida</span>
                                <span wire:loading wire:target="finishGame">Encerrando...</span>
                            </button>
                        @endif

                        {{-- Áudio --}}
                        @if ($this->canUseCustomAudio)
                            <div
                                class="bg-gradient-to-br from-indigo-600/10 to-purple-600/10 border-2 border-indigo-500/30 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-xs font-bold text-white uppercase flex items-center gap-1">
                                        <span>🔊</span> Áudio
                                    </h4>
                                    <button wire:click="$toggle('showAudioSettings')" class="text-indigo-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="{{ $showAudioSettings ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" />
                                        </svg>
                                    </button>
                                </div>

                                <div
                                    class="flex items-center justify-between p-2 bg-[#0b0d11] rounded border border-white/5 mb-2">
                                    <span class="text-xs font-semibold text-slate-300">Ativado</span>
                                    <button wire:click="toggleAudioEnabled"
                                        class="w-10 h-5 rounded-full relative transition-all {{ $audioEnabled ? 'bg-indigo-600' : 'bg-slate-600' }}">
                                        <div
                                            class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform {{ $audioEnabled ? 'translate-x-5' : '' }}">
                                        </div>
                                    </button>
                                </div>

                                @if ($showAudioSettings && $audioEnabled)
                                    <div class="space-y-2 animate-fade-in">
                                        <div class="p-2 bg-[#0b0d11] rounded border border-white/5">
                                            <label
                                                class="block text-[10px] font-bold text-blue-400 mb-1 flex items-center justify-between">
                                                <span>🎵 Número</span>
                                                <button wire:click="testNumberSound" type="button"
                                                    class="text-[9px] bg-blue-600 text-white px-2 py-0.5 rounded font-bold">Testar</button>
                                            </label>
                                            <select wire:model.live="selected_number_sound_id"
                                                class="w-full bg-[#161920] border border-white/10 rounded px-2 py-1 text-xs text-white">
                                                @foreach ($this->numberSounds as $sound)
                                                    <option value="{{ $sound->id }}">{{ $sound->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="p-2 bg-[#0b0d11] rounded border border-white/5">
                                            <label
                                                class="block text-[10px] font-bold text-purple-400 mb-1 flex items-center justify-between">
                                                <span>🏆 Vencedor</span>
                                                <button wire:click="testWinnerSound" type="button"
                                                    class="text-[9px] bg-purple-600 text-white px-2 py-0.5 rounded font-bold">Testar</button>
                                            </label>
                                            <select wire:model.live="selected_winner_sound_id"
                                                class="w-full bg-[#161920] border border-white/10 rounded px-2 py-1 text-xs text-white">
                                                @foreach ($this->winnerSounds as $sound)
                                                    <option value="{{ $sound->id }}">{{ $sound->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div
                                class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 border-2 border-orange-500/30 rounded-lg p-3">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-lg">🔊</span>
                                    <h4 class="text-xs font-bold text-white uppercase">Áudio</h4>
                                </div>
                                <div class="mb-2 px-2 py-1 bg-orange-600/20 border border-orange-500/30 rounded">
                                    <p class="text-[9px] text-orange-300 font-bold uppercase text-center">🔒 Exclusivo
                                        Planos Pagos</p>
                                </div>
                                <p class="text-[10px] text-slate-400 mb-2">Personalize sons com vozes e efeitos
                                    exclusivos.</p>
                                <a href="{{ route('wallet.index') }}"
                                    class="block text-center bg-gradient-to-r from-orange-600 to-red-600 text-white py-1.5 rounded font-bold text-[10px] uppercase">⭐
                                    Upgrade</a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 p-4 pb-20 lg:pb-4 overflow-y-auto">

            {{-- MOBILE VIEW --}}
            <div class="lg:hidden">

                {{-- Control Tab --}}
                @if ($activeMobileTab === 'control')
                    <div class="space-y-4">

                        {{-- Alertas --}}
                        @if ($showNoPrizesWarning)
                            <div class="bg-orange-500/10 border border-orange-500/20 rounded-lg p-4">
                                <div class="flex items-start gap-3">
                                    <div class="text-2xl">⚠️</div>
                                    <div class="flex-1">
                                        <h3 class="text-sm font-bold text-orange-400 mb-1">Prêmios Esgotados</h3>
                                        <p class="text-xs text-slate-400 mb-3">Há cartelas vencedoras sem prêmios
                                            disponíveis.</p>
                                        <div class="flex gap-2">
                                            <a href="{{ route('games.edit', $game) }}"
                                                class="bg-orange-600 text-white px-3 py-1.5 rounded text-xs font-bold">Adicionar</a>
                                            @if ($game->canStartNextRound())
                                                <button wire:click="startNextRound"
                                                    class="bg-blue-600 text-white px-3 py-1.5 rounded text-xs font-bold">Próxima
                                                    Rodada</button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Centro de Comando --}}
                        @if ($game->status === 'active')
                            <section class="bg-[#0b0d11] border border-white/10 rounded-xl p-4"
                                @if ($game->draw_mode === 'automatic' && !$isPaused && !$this->winningCards->isNotEmpty()) wire:poll.visible.{{ $game->auto_draw_seconds ?? 3 }}s="autoDraw" @endif>

                                <div class="flex justify-between items-center mb-4">
                                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                                        <span>🕹️</span> Comando
                                    </h2>
                                    <div
                                        class="flex items-center gap-1 bg-white/5 px-2 py-1 rounded border border-white/10">
                                        <div class="text-[10px] font-bold text-slate-400">Progresso</div>
                                        <div
                                            class="w-12 bg-[#161920] rounded-full h-1 overflow-hidden border border-white/10">
                                            <div class="bg-blue-500 h-full transition-all"
                                                style="width: {{ ($drawnCount / 75) * 100 }}%"></div>
                                        </div>
                                        <span
                                            class="text-[10px] font-bold text-blue-400">{{ round(($drawnCount / 75) * 100) }}%</span>
                                    </div>
                                </div>

                                @if ($game->draw_mode === 'manual')
                                    <button wire:click="drawNumber" wire:loading.attr="disabled"
                                        class="relative w-full bg-blue-600 text-white py-3 rounded-lg font-bold text-sm mb-4 transition disabled:opacity-50"
                                        @if ($drawnCount >= 75 || $isDrawing) disabled @endif>
                                        <span wire:loading.remove wire:target="drawNumber">Sortear Número</span>
                                        <span wire:loading wire:target="drawNumber">Sorteando...</span>
                                    </button>
                                @else
                                    <div class="bg-[#161920] border border-blue-500/20 rounded-lg p-3 mb-4">
                                        <div class="flex items-center justify-between gap-3 mb-3">
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="h-3 w-3 rounded-full {{ !$isPaused ? 'bg-green-500 animate-pulse' : 'bg-red-500' }}">
                                                </div>
                                                <div>
                                                    <div
                                                        class="text-xs font-bold {{ !$isPaused ? 'text-green-400' : 'text-red-400' }}">
                                                        {{ !$isPaused ? 'Automático' : 'Pausado' }}</div>
                                                    @if (!$isPaused)
                                                        <div class="text-[10px] text-slate-400 font-semibold">Próximo
                                                            em {{ $game->auto_draw_seconds ?? 3 }}s</div>
                                                    @endif
                                                </div>
                                            </div>

                                            <button wire:click="togglePause"
                                                class="px-3 py-1.5 rounded font-bold text-xs {{ $isPaused ? 'bg-green-600' : 'bg-orange-500' }} text-white">
                                                {{ $isPaused ? '▶️' : '⏸️' }}
                                            </button>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex-1 flex items-center bg-[#0b0d11] border border-white/10 rounded px-2 py-1">
                                                <span
                                                    class="text-[10px] font-bold text-slate-400 mr-1">Intervalo:</span>
                                                <input type="number" wire:model="tempSeconds"
                                                    class="flex-1 bg-transparent border-none p-0 focus:ring-0 text-xs font-bold text-white w-8"
                                                    min="2" max="60">
                                                <span class="text-[10px] text-slate-400">s</span>
                                            </div>
                                            <button wire:click="updateDrawSpeed" wire:loading.attr="disabled"
                                                class="bg-blue-600 text-white px-3 py-1 rounded text-xs font-bold disabled:opacity-50">
                                                OK
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                {{-- Número Sorteado --}}
                                @if ($drawnCount > 0)
                                    <div
                                        class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl p-6 mb-4 text-center">
                                        <div class="text-[10px] text-white/60 font-bold uppercase mb-2">Último Número
                                        </div>
                                        <div class="text-6xl font-black text-white mb-2">{{ $lastDrawnNumber }}</div>
                                        <div
                                            class="inline-block px-3 py-1 bg-black/30 rounded text-[10px] font-bold text-white/80 border border-white/10">
                                            #{{ $drawnCount }}
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between items-end mb-2">
                                            <div>
                                                <div class="text-[10px] font-bold text-slate-400">Histórico</div>
                                                <div class="text-sm font-bold text-white">Sorteados</div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-[10px] font-bold text-slate-400">Faltam</div>
                                                <div class="text-xs font-bold text-blue-400">{{ 75 - $drawnCount }}
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            class="grid grid-cols-10 gap-1 p-3 bg-[#161920] rounded-lg border border-white/10 max-h-48 overflow-y-auto">
                                            @foreach (range(1, 75) as $num)
                                                <div
                                                    class="aspect-square rounded flex items-center justify-center text-[10px] font-bold transition-all
                                    {{ in_array($num, $drawnNumbersList) ? 'bg-blue-500 text-white' : 'bg-[#0b0d11] border border-white/10 text-slate-600' }}">
                                                    {{ $num }}
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center py-8 bg-[#161920] rounded-lg border border-white/10">
                                        <div class="text-3xl text-slate-600 mb-2">🔮</div>
                                        <div class="text-xs font-semibold text-slate-500">Aguardando Primeiro Sorteio
                                        </div>
                                    </div>
                                @endif
                            </section>
                        @endif

                        {{-- Bingos --}}
                        @if ($this->winningCards->count() > 0)
                            <section class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-4">
                                <h2 class="text-base font-bold text-yellow-400 mb-3 flex items-center gap-2">
                                    <span class="animate-pulse">🎉</span> Bingo!
                                </h2>
                                @php $nextPrize = $this->nextAvailablePrize; @endphp
                                <div class="space-y-3">
                                    @foreach ($this->winningCards as $card)
                                        <div class="bg-[#161920] p-3 rounded-lg border border-yellow-500/20">
                                            <div class="flex items-center gap-3 mb-3">
                                                <div
                                                    class="bg-yellow-500/20 text-yellow-400 w-10 h-10 rounded-full flex items-center justify-center font-bold border border-yellow-500/20">
                                                    {{ substr($card->player->user->name, 0, 1) }}
                                                </div>
                                                <div class="flex-1">
                                                    <div class="text-sm font-bold text-white">
                                                        {{ $card->player->user->name }}</div>
                                                    <div class="text-xs text-yellow-400 font-semibold">
                                                        #{{ substr($card->uuid, 0, 8) }}</div>
                                                </div>
                                            </div>
                                            @if ($nextPrize)
                                                <button
                                                    wire:click="claimPrize('{{ $card->uuid }}', '{{ $nextPrize->uuid }}')"
                                                    wire:loading.attr="disabled"
                                                    class="w-full bg-green-600 text-white px-4 py-2 rounded-lg font-bold text-xs disabled:opacity-50">
                                                    <span wire:loading.remove
                                                        wire:target="claimPrize('{{ $card->uuid }}', '{{ $nextPrize->uuid }}')">
                                                        🎁 Conceder: {{ $nextPrize->name }}
                                                    </span>
                                                    <span wire:loading
                                                        wire:target="claimPrize('{{ $card->uuid }}', '{{ $nextPrize->uuid }}')">Processando...</span>
                                                </button>
                                            @else
                                                <button wire:click="claimPrize('{{ $card->uuid }}', null)"
                                                    wire:loading.attr="disabled"
                                                    class="w-full bg-slate-600 text-white px-4 py-2 rounded-lg font-bold text-xs disabled:opacity-50">
                                                    <span wire:loading.remove
                                                        wire:target="claimPrize('{{ $card->uuid }}', null)">🏅
                                                        Registrar Honra</span>
                                                    <span wire:loading
                                                        wire:target="claimPrize('{{ $card->uuid }}', null)">Processando...</span>
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                @endif

                {{-- Prizes Tab --}}
                @if ($activeMobileTab === 'prizes')
                    <div class="space-y-4">
                        <h2 class="text-xl font-bold text-white">Prêmios</h2>

                        @if ($this->nextAvailablePrize)
                            <div class="bg-blue-500/10 border border-blue-500/20 rounded-lg p-3">
                                <div class="flex items-center gap-2">
                                    <div class="text-2xl">🎯</div>
                                    <div>
                                        <div class="text-[10px] text-blue-400 font-semibold">Próximo</div>
                                        <div class="text-sm font-bold text-blue-400">
                                            {{ $this->nextAvailablePrize->position }}º -
                                            {{ $this->nextAvailablePrize->name }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="space-y-3">
                            @foreach ($game->prizes->sortBy('position') as $prize)
                                <div
                                    class="border border-white/10 rounded-lg p-4 bg-[#161920] {{ $prize->is_claimed ? 'border-green-500/20' : '' }}">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                @if ($prize->position == 1)
                                                    <span class="text-lg">🥇</span>
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
                                                    <div class="text-xs text-slate-500">Rodada
                                                        {{ $winner->round_number }}</div>
                                                </div>
                                            @endif
                                        </div>
                                        <span
                                            class="px-2 py-1 rounded text-[10px] font-bold border {{ $prize->is_claimed ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-blue-500/10 text-blue-400 border-blue-500/20' }}">
                                            {{ $prize->is_claimed ? 'Ganho' : 'Disponível' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Players Tab --}}
                @if ($activeMobileTab === 'players')
                    <div class="space-y-4">
                        <h2 class="text-xl font-bold text-white">Jogadores ({{ $game->players->count() }})</h2>
                        @if ($game->players->isEmpty())
                            <div class="text-center py-12 text-slate-500">
                                <div class="text-3xl opacity-50 mb-3">👥</div>
                                <div class="text-xs font-semibold">Aguardando Jogadores</div>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($game->players as $player)
                                    <div
                                        class="flex items-center justify-between p-3 bg-[#161920] rounded-lg border border-white/10">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-blue-500/20 text-blue-400 rounded-full flex items-center justify-center font-bold border border-blue-500/20">
                                                {{ substr($player->user->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-white">{{ $player->user->name }}
                                                </div>
                                                <div class="text-xs text-slate-400 font-semibold">
                                                    {{ $player->cards()->where('round_number', $game->current_round)->count() }}
                                                    Cartelas</div>
                                            </div>
                                        </div>
                                        @php $roundWin = $player->user->wins()->where('game_id', $game->id)->where('round_number', $game->current_round)->with('prize')->first(); @endphp
                                        @if ($roundWin)
                                            <div class="text-right">
                                                @if ($roundWin->prize)
                                                    <span class="block text-xs font-bold text-yellow-400">🏆
                                                        {{ $roundWin->prize->position }}º</span>
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
                    </div>
                @endif

                {{-- Winners Tab --}}
                @if ($activeMobileTab === 'winners')
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                                <span class="animate-pulse">🏆</span> Hall da Fama
                            </h2>
                            <span
                                class="text-xs bg-indigo-500/20 text-indigo-300 px-2 py-1 rounded font-semibold border border-indigo-500/20">
                                {{ $game->winners()->count() }}
                            </span>
                        </div>

                        <div class="space-y-2">
                            @foreach ($game->winners()->with(['user', 'prize'])->orderBy('won_at', 'asc')->get() as $index => $winner)
                                <div class="flex items-center gap-3 bg-white/5 border border-white/10 p-3 rounded-lg">
                                    <div class="relative">
                                        <div
                                            class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs {{ $winner->prize_id ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/20' : 'bg-slate-500/20 text-slate-400 border border-slate-500/20' }}">
                                            {{ $index + 1 }}
                                        </div>
                                        <div
                                            class="absolute -top-1 -right-1 bg-indigo-500/50 text-white text-[9px] px-1 rounded font-bold border border-indigo-900/50">
                                            R{{ $winner->round_number }}
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-bold text-white truncate">{{ $winner->user->name }}
                                        </div>
                                        <div
                                            class="text-xs font-semibold {{ $winner->prize_id ? 'text-yellow-400' : 'text-indigo-400' }}">
                                            {{ $winner->prize ? $winner->prize->name : 'Honra ✨' }}
                                        </div>
                                    </div>
                                    <div class="text-xs font-mono text-white/30">{{ $winner->won_at->format('H:i') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($game->winners()->count() === 0)
                            <div class="text-center py-10">
                                <div class="text-white/20 text-3xl mb-2">⭐</div>
                                <p class="text-white/40 text-xs font-semibold">Aguardando Primeiros Vencedores...</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- DESKTOP VIEW --}}
            <div class="hidden lg:block">
                {{-- Aviso Prêmios --}}
                @if ($showNoPrizesWarning)
                    <div class="mb-6 bg-orange-500/10 border border-orange-500/20 rounded-xl p-6">
                        <div class="flex items-start gap-4">
                            <div class="text-3xl text-orange-400">⚠️</div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-orange-400 mb-2">Alerta: Prêmios Esgotados</h3>
                                <p class="text-slate-400 text-sm mb-4">Todos os prêmios foram distribuídos, mas há
                                    cartelas vencedoras. Adicione mais prêmios ou avance para a próxima rodada.</p>
                                <div class="flex flex-wrap gap-3">
                                    <a href="{{ route('games.edit', $game) }}"
                                        class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-semibold text-xs transition">Adicionar
                                        Prêmios</a>
                                    @if ($game->canStartNextRound())
                                        <button wire:click="startNextRound"
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold text-xs transition">Próxima
                                            Rodada</button>
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

                            <div
                                class="flex items-center gap-2 bg-white/5 px-3 py-1 rounded-lg border border-white/10">
                                <div class="text-xs font-semibold text-slate-400">Progresso</div>
                                <div
                                    class="w-16 bg-[#161920] rounded-full h-1.5 overflow-hidden border border-white/10">
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
                                                        ⚠️ Bingo Detectado</div>
                                                @else
                                                    <div class="text-xs text-slate-400 font-semibold">Timer Congelado
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex items-center bg-[#0b0d11] border border-white/10 rounded-lg p-1">
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
                                        {{ $lastDrawnNumber }}</div>
                                    <div
                                        class="mt-4 inline-block px-4 py-1 bg-black/30 rounded-lg text-xs font-bold text-white/80 border border-white/10">
                                        Sequência #{{ $drawnCount }}</div>
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
                                        <div class="text-xs font-bold text-blue-400">{{ 75 - $drawnCount }} números
                                        </div>
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
                                                    wire:target="claimPrize('{{ $card->uuid }}', '{{ $nextPrize->uuid }}')">Processando...</span>
                                            </button>
                                        @else
                                            <button wire:click="claimPrize('{{ $card->uuid }}', null)"
                                                wire:loading.attr="disabled"
                                                class="w-full bg-slate-600 hover:bg-slate-700 text-white px-8 py-3 rounded-lg font-bold text-xs transition active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50">
                                                <span wire:loading.remove
                                                    wire:target="claimPrize('{{ $card->uuid }}', null)">
                                                    <span>🏅</span> Registrar Honra
                                                </span>
                                                <span wire:loading
                                                    wire:target="claimPrize('{{ $card->uuid }}', null)">Processando...</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if (!$nextPrize)
                            <div class="mt-6 p-4 bg-[#0b0d11] border border-yellow-500/20 rounded-xl">
                                <p class="text-yellow-400 text-xs font-semibold">⚠️ Todos os prêmios foram
                                    distribuídos. Novos vencedores receberão registro de honra.</p>
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
                                        {{ $this->nextAvailablePrize->position }}º -
                                        {{ $this->nextAvailablePrize->name }}</div>
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
                                                <div class="text-xs text-slate-500">Rodada
                                                    {{ $winner->round_number }}</div>
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
                                                Cartelas</div>
                                        </div>
                                    </div>
                                    @php $roundWin = $player->user->wins()->where('game_id', $game->id)->where('round_number', $game->current_round)->with('prize')->first(); @endphp

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
                                    <div class="text-xs font-bold text-white truncate">{{ $winner->user->name }}
                                    </div>
                                    <div
                                        class="text-xs font-semibold {{ $winner->prize_id ? 'text-yellow-400' : 'text-indigo-400' }}">
                                        {{ $winner->prize ? $winner->prize->name : 'Honra ✨' }}
                                    </div>
                                </div>

                                <div class="text-xs font-mono text-white/30">{{ $winner->won_at->format('H:i') }}
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
            </div>
        </main>
    </div>

    <x-toast />
</div>

<style>
    @keyframes fade-in {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in {
        animation: fade-in 0.3s ease-out;
    }

    .safe-area-bottom {
        padding-bottom: env(safe-area-inset-bottom);
    }
</style>
