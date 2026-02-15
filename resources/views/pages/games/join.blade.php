<?php

use Livewire\Attributes\{On, Computed};
use Livewire\Component;
use App\Models\Game\{Game, Player, Card, Winner};
use App\Events\GameUpdated;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public Game $game;
    public ?Player $player = null;
    public array $cards = [];
    public array $drawnNumbers = [];
    public int $totalDraws = 0;
    public bool $isJoining = false;

    public function mount(string $invite_code)
    {
        $this->game = Game::where('invite_code', $invite_code)
            ->with(['creator', 'package', 'prizes.winner.user', 'winners.user', 'winners.prize', 'players.user'])
            ->firstOrFail();

        if (!isset($this->game->cards_per_player) || $this->game->cards_per_player === null) {
            $this->game->cards_per_player = 1;
        }

        $this->player = Player::where('game_id', $this->game->id)
            ->where('user_id', auth()->id())
            ->first();

        $this->syncGameState();
    }

    #[On('echo:game.{game.uuid},.GameUpdated')]
    public function handleUpdate(): void
    {
        $this->game->refresh();
        $this->game->load(['prizes.winner.user', 'draws', 'winners.user', 'winners.prize', 'players.user']);
        
        if (!$this->player) {
            $this->player = Player::where('game_id', $this->game->id)
                ->where('user_id', auth()->id())
                ->first();
        }

        $this->syncGameState();
    }

    private function syncGameState(): void
    {
        $this->drawnNumbers = $this->game->draws()
            ->where('round_number', $this->game->current_round)
            ->orderBy('number', 'asc')
            ->pluck('number')
            ->toArray();
        
        $this->totalDraws = count($this->drawnNumbers);

        if ($this->player) {
            $cards = Card::where('player_id', $this->player->id)
                ->where('round_number', $this->game->current_round)
                ->get();
            
            $this->cards = $cards->map(function ($card) {
                $numbers = $card->numbers;
                $marked = $card->marked ?? '[]';
                $numbersArray = is_array($numbers) ? $numbers : (json_decode($numbers, true) ?? []);
                $markedArray = is_array($marked) ? $marked : (json_decode($marked, true) ?? []);
                
                return [
                    'id' => $card->id,
                    'uuid' => $card->uuid,
                    'numbers' => $numbersArray,
                    'marked' => $markedArray,
                    'is_bingo' => $card->is_bingo,
                ];
            })->toArray();
        }
    }

    public function join(): void
    {
        if ($this->isJoining) {
            return;
        }

        if ($this->player) {
            $this->dispatch('notify', type: 'info', text: 'Você já está na partida.');
            return;
        }

        if (!$this->game->canJoin()) {
            $this->dispatch('notify', type: 'error', text: 'Não é possível entrar nesta partida.');
            return;
        }

        $this->isJoining = true;

        try {
            DB::transaction(function () {
                $this->player = Player::firstOrCreate([
                    'game_id' => $this->game->id,
                    'user_id' => auth()->id(),
                ], [
                    'joined_at' => now(),
                ]);

                Card::where('player_id', $this->player->id)
                    ->where('round_number', $this->game->current_round)
                    ->delete();

                $this->game->refresh();
                $cardsPerPlayer = max(1, min(10, (int) ($this->game->cards_per_player ?? 1)));

                for ($i = 0; $i < $cardsPerPlayer; $i++) {
                    Card::create([
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'game_id' => $this->game->id,
                        'player_id' => $this->player->id,
                        'round_number' => $this->game->current_round,
                        'numbers' => json_encode($this->game->generateCardNumbers()),
                        'marked' => json_encode([]),
                        'is_bingo' => false,
                    ]);
                }

                $pushService = app(\App\Services\PushNotificationService::class);
                $message = \App\Services\NotificationMessages::playerJoinedRoom(
                    $this->game->name,
                    $cardsPerPlayer
                );

                $pushService->notifyUser(
                    auth()->id(),
                    $message['title'],
                    $message['body'],
                    route('games.display', $this->game->invite_code)
                );
            });

            broadcast(new GameUpdated($this->game))->toOthers();
            $this->syncGameState();
            $this->dispatch('notify', type: 'success', text: 'Você entrou na arena!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao entrar na partida: ' . $e->getMessage());
        } finally {
            $this->isJoining = false;
        }
    }

    public function markNumber(int $cardIndex, int $number): void
    {
        if (!$this->player || $this->game->status !== 'active') {
            return;
        }

        $card = $this->cards[$cardIndex] ?? null;
        if (!$card || $card['is_bingo']) {
            return;
        }

        $cardModel = Card::find($card['id']);
        if (!$cardModel || in_array($number, $card['marked'])) {
            return;
        }

        if (!in_array($number, $this->drawnNumbers)) {
            $this->dispatch('notify', type: 'error', text: 'Este número ainda não foi sorteado!');
            return;
        }

        try {
            $marked = array_merge($card['marked'], [$number]);
            $cardModel->update(['marked' => json_encode($marked)]);

            $allNumbers = $card['numbers'];
            $hasAllNumbers = count($allNumbers) === count($marked) && empty(array_diff($allNumbers, $marked));

            if ($hasAllNumbers) {
                $cardModel->update(['is_bingo' => true]);
                
                if ($this->game->auto_claim_prizes) {
                    $this->claimPrizeAutomatically($cardModel);
                } else {
                    $this->dispatch('notify', type: 'success', text: 'BINGO! Aguardando validação do organizador.');
                    broadcast(new GameUpdated($this->game))->toOthers();
                }
            }

            $this->syncGameState();
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao marcar número.');
        }
    }

    private function claimPrizeAutomatically(Card $card): void
    {
        try {
            $nextPrize = $this->game->prizes()
                ->where('is_claimed', false)
                ->orderBy('position', 'asc')
                ->first();
            
            DB::transaction(function () use ($card, $nextPrize) {
                $existingWinner = Winner::where('game_id', $this->game->id)
                    ->where('card_id', $card->id)
                    ->where('round_number', $this->game->current_round)
                    ->exists();

                if ($existingWinner) {
                    return;
                }

                if ($nextPrize) {
                    $nextPrize->update([
                        'is_claimed' => true,
                        'winner_card_id' => $card->id,
                        'claimed_at' => now()
                    ]);
                }
                
                Winner::create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'game_id' => $this->game->id,
                    'card_id' => $card->id,
                    'user_id' => $this->player->user_id,
                    'prize_id' => $nextPrize?->id,
                    'round_number' => $this->game->current_round,
                    'won_at' => now(),
                ]);

                if ($nextPrize) {
                    $pushService = app(\App\Services\PushNotificationService::class);
                    $message = \App\Services\NotificationMessages::bingoWinner(
                        $this->game->name,
                        $nextPrize->name
                    );

                    $pushService->notifyUser(
                        $this->player->user_id,
                        $message['title'],
                        $message['body'],
                        route('games.display', $this->game->invite_code)
                    );
                }
            });

            broadcast(new GameUpdated($this->game))->toOthers();
            $this->dispatch('notify', type: 'success', text: $nextPrize ? 'Parabéns! Você ganhou: ' . $nextPrize->name : 'Bingo registrado!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao processar vitória.');
        }
    }

    #[Computed]
    public function roundWinners()
    {
        return Winner::where('game_id', $this->game->id)
            ->where('round_number', $this->game->current_round)
            ->with(['user', 'prize'])
            ->orderBy('won_at', 'asc')
            ->get();
    }

    #[Computed]
    public function allWinners()
    {
        return Winner::where('game_id', $this->game->id)
            ->with(['user', 'prize'])
            ->orderBy('round_number', 'asc')
            ->orderBy('won_at', 'asc')
            ->get();
    }

    #[Computed]
    public function honorWinners()
    {
        return Winner::where('game_id', $this->game->id)
            ->whereNull('prize_id')
            ->with('user')
            ->get();
    }

    #[Computed]
    public function prizeWinners()
    {
        return Winner::where('game_id', $this->game->id)
            ->whereNotNull('prize_id')
            ->with(['user', 'prize'])
            ->orderBy('won_at', 'asc')
            ->get();
    }

    #[Computed]
    public function myWinningCards(): array
    {
        if (!$this->player || empty($this->cards)) {
            return [];
        }
        
        return Winner::whereIn('card_id', collect($this->cards)->pluck('id'))
            ->where('round_number', $this->game->current_round)
            ->pluck('card_id')
            ->toArray();
    }

    #[Computed]
    public function activePlayers()
    {
        return $this->game->players()
            ->with('user')
            ->orderBy('joined_at', 'asc')
            ->get();
    }
};
?>

<div class="min-h-screen bg-[#05070a] text-slate-200 pb-20">
    <x-loading target="join, markNumber" message="Processando..." />
    <x-toast />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        
        {{-- HEADER --}}
        <header class="mb-12">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-2 h-2 bg-blue-600 rounded-full animate-pulse"></div>
                        <span class="text-blue-500 font-bold text-xs uppercase tracking-widest">
                            {{ $game->status === 'active' ? 'Arena em Operação' : ($game->status === 'finished' ? 'Arena Finalizada' : 'Arena em Espera') }}
                        </span>
                    </div>
                    <h1 class="text-4xl md:text-5xl font-bold text-white">
                        {{ $game->name }}
                    </h1>
                </div>

                <div class="flex items-center gap-4 bg-[#0b0d11] p-4 rounded-xl border border-white/10 shadow-xl">
                    <div class="text-right border-r border-white/10 pr-4">
                        <p class="text-xs font-semibold text-slate-400 mb-1">Rodada</p>
                        <p class="text-2xl font-bold text-white">
                            {{ $game->current_round }}<span class="text-slate-600 text-sm">/{{ $game->max_rounds }}</span>
                        </p>
                    </div>
                    <div class="px-4 py-2 rounded-lg text-xs font-bold uppercase border 
                        {{ $game->status === 'active' ? 'bg-blue-600 border-blue-500 text-white' : ($game->status === 'finished' ? 'bg-red-600 border-red-500 text-white' : 'bg-white/5 border-white/10 text-slate-500') }}">
                        {{ $game->status === 'active' ? 'Ao Vivo' : ($game->status === 'finished' ? 'Encerrada' : 'Standby') }}
                    </div>
                </div>
            </div>
        </header>

        {{-- TELA DE FINALIZAÇÃO --}}
        @if($game->status === 'finished')
            <div class="max-w-4xl mx-auto space-y-8">
                
                {{-- Cabeçalho de Finalização --}}
                <div class="text-center py-16 bg-gradient-to-br from-blue-600/20 to-purple-600/20 rounded-3xl border border-blue-500/20 shadow-2xl relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#05070a] via-transparent to-transparent"></div>
                    <div class="relative z-10">
                        <div class="w-24 h-24 bg-blue-600/20 rounded-full flex items-center justify-center mx-auto mb-6 border-2 border-blue-500/30">
                            <span class="text-5xl">🏁</span>
                        </div>
                        <h2 class="text-4xl font-bold text-white mb-3">Partida Finalizada!</h2>
                        <p class="text-slate-400 text-lg mb-6">{{ $game->name }}</p>
                        <div class="flex items-center justify-center gap-6 text-sm">
                            <div class="px-4 py-2 bg-white/5 rounded-lg border border-white/10">
                                <span class="text-slate-400">Rodadas:</span>
                                <span class="text-white font-bold ml-2">{{ $game->current_round }}/{{ $game->max_rounds }}</span>
                            </div>
                            <div class="px-4 py-2 bg-white/5 rounded-lg border border-white/10">
                                <span class="text-slate-400">Participantes:</span>
                                <span class="text-white font-bold ml-2">{{ $this->activePlayers->count() }}</span>
                            </div>
                            <div class="px-4 py-2 bg-white/5 rounded-lg border border-white/10">
                                <span class="text-slate-400">Vencedores:</span>
                                <span class="text-white font-bold ml-2">{{ $this->allWinners->count() }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pódio de Vencedores com Prêmios --}}
                @if($this->prizeWinners->isNotEmpty())
                    <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-8 shadow-xl">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-2xl font-bold text-white flex items-center gap-3">
                                <span class="text-3xl">🏆</span> Pódio de Campeões
                            </h3>
                            <span class="px-4 py-2 bg-yellow-500/10 border border-yellow-500/20 rounded-lg text-sm font-bold text-yellow-400">
                                {{ $this->prizeWinners->count() }} prêmio{{ $this->prizeWinners->count() !== 1 ? 's' : '' }} distribuído{{ $this->prizeWinners->count() !== 1 ? 's' : '' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($this->prizeWinners->take(3) as $index => $winner)
                                <div class="relative group">
                                    <div class="absolute -inset-0.5 bg-gradient-to-br 
                                        {{ $index === 0 ? 'from-yellow-400 to-yellow-600' : ($index === 1 ? 'from-slate-300 to-slate-500' : 'from-amber-600 to-amber-800') }} 
                                        rounded-xl blur opacity-30 group-hover:opacity-50 transition"></div>
                                    
                                    <div class="relative bg-[#161920] rounded-xl p-6 border 
                                        {{ $index === 0 ? 'border-yellow-500/30' : ($index === 1 ? 'border-slate-500/30' : 'border-amber-600/30') }}">
                                        
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="w-16 h-16 rounded-full flex items-center justify-center font-black text-2xl
                                                {{ $index === 0 ? 'bg-yellow-500/20 text-yellow-400 border-2 border-yellow-500/30' : 
                                                   ($index === 1 ? 'bg-slate-500/20 text-slate-300 border-2 border-slate-500/30' : 
                                                    'bg-amber-600/20 text-amber-600 border-2 border-amber-600/30') }}">
                                                {{ $index === 0 ? '🥇' : ($index === 1 ? '🥈' : '🥉') }}
                                            </div>
                                            <span class="text-4xl font-bold {{ $index === 0 ? 'text-yellow-400' : ($index === 1 ? 'text-slate-300' : 'text-amber-600') }}">
                                                {{ $index + 1 }}º
                                            </span>
                                        </div>

                                        <div class="mb-3">
                                            <p class="text-xl font-bold text-white mb-1">{{ $winner->user->name }}</p>
                                            <p class="text-sm text-slate-400">Rodada {{ $winner->round_number }}</p>
                                        </div>

                                        <div class="pt-3 border-t border-white/10">
                                            <p class="text-xs text-slate-500 uppercase font-semibold mb-1">Prêmio</p>
                                            <p class="text-lg font-bold text-blue-400">{{ $winner->prize->name }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($this->prizeWinners->count() > 3)
                            <div class="mt-6 pt-6 border-t border-white/10">
                                <h4 class="text-sm font-bold text-slate-400 uppercase mb-4">Outros Vencedores</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach($this->prizeWinners->skip(3) as $index => $winner)
                                        <div class="flex items-center justify-between p-4 bg-[#161920] rounded-lg border border-white/10 hover:bg-white/5 transition">
                                            <div class="flex items-center gap-3">
                                                <span class="w-8 h-8 bg-blue-500/20 text-blue-400 rounded-lg flex items-center justify-center font-bold text-sm">
                                                    {{ $index + 4 }}º
                                                </span>
                                                <div>
                                                    <p class="text-sm font-bold text-white">{{ $winner->user->name }}</p>
                                                    <p class="text-xs text-slate-400">{{ $winner->prize->name }}</p>
                                                </div>
                                            </div>
                                            <span class="text-xs text-slate-500">R{{ $winner->round_number }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Bingos de Honra --}}
                @if($this->honorWinners->isNotEmpty())
                    <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-8 shadow-xl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-white flex items-center gap-3">
                                <span class="text-2xl">✨</span> Bingos de Honra
                            </h3>
                            <span class="px-3 py-1 bg-purple-500/10 border border-purple-500/20 rounded-lg text-sm font-bold text-purple-400">
                                {{ $this->honorWinners->count() }} jogador{{ $this->honorWinners->count() !== 1 ? 'es' : '' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            @foreach($this->honorWinners as $winner)
                                <div class="flex items-center gap-2 p-3 bg-purple-500/10 rounded-lg border border-purple-500/20">
                                    <div class="w-10 h-10 bg-purple-500/20 rounded-full flex items-center justify-center font-bold text-sm text-purple-400">
                                        {{ substr($winner->user->name, 0, 2) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-white truncate">{{ $winner->user->name }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Todos os Participantes --}}
                <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-8 shadow-xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-white flex items-center gap-3">
                            <span class="text-2xl">👥</span> Todos os Participantes
                        </h3>
                        <span class="px-3 py-1 bg-blue-500/10 border border-blue-500/20 rounded-lg text-sm font-bold text-blue-400">
                            {{ $this->activePlayers->count() }} total
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($this->activePlayers as $activePlayer)
                            @php
                                $playerWins = $this->allWinners->where('user_id', $activePlayer->user_id);
                                $hasPrize = $playerWins->whereNotNull('prize_id')->isNotEmpty();
                                $hasHonor = $playerWins->whereNull('prize_id')->isNotEmpty();
                            @endphp
                            <div class="flex items-center gap-3 p-4 
                                {{ $hasPrize ? 'bg-emerald-500/10 border-emerald-500/20' : ($hasHonor ? 'bg-purple-500/10 border-purple-500/20' : 'bg-[#161920] border-white/10') }} 
                                rounded-lg border hover:bg-white/5 transition">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-sm
                                    {{ $hasPrize ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 
                                       ($hasHonor ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 
                                        'bg-blue-500/20 text-blue-400 border border-blue-500/20') }}">
                                    {{ substr($activePlayer->user->name, 0, 2) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-white truncate">{{ $activePlayer->user->name }}</p>
                                    @if($playerWins->isNotEmpty())
                                        <div class="flex items-center gap-1 mt-1">
                                            @if($hasPrize)
                                                <span class="text-xs font-semibold text-emerald-400">🏆 Vencedor</span>
                                            @elseif($hasHonor)
                                                <span class="text-xs font-semibold text-purple-400">✨ Honra</span>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-xs text-slate-500">Participou</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Botão Voltar --}}
                <div class="text-center">
                    <a href="{{ route('dashboard') }}" 
                        class="inline-flex items-center gap-2 px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition shadow-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Voltar ao Dashboard
                    </a>
                </div>
            </div>

        {{-- TELA DE ENTRADA --}}
        @elseif(!$player)
            <div class="max-w-2xl mx-auto">
                <div class="text-center py-16 bg-[#0b0d11] rounded-3xl border border-white/10 shadow-2xl mb-8">
                    <div class="w-20 h-20 bg-blue-600/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-blue-600/20">
                        <span class="text-3xl">🎮</span>
                    </div>
                    <h2 class="text-3xl font-bold text-white mb-4">Pronto para o Jogo?</h2>
                    <p class="text-slate-400 text-sm mb-8 max-w-md mx-auto">
                        Você foi convidado para participar desta arena. Clique abaixo para gerar suas cartelas e começar a jogar.
                    </p>
                    <button wire:click="join" wire:loading.attr="disabled"
                        class="group relative bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-xl font-bold uppercase text-sm transition-all shadow-xl disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="join">Entrar na Partida</span>
                        <span wire:loading wire:target="join">Entrando...</span>
                    </button>
                </div>

                {{-- JOGADORES NA SALA --}}
                <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-bold text-white">Jogadores na Sala</h3>
                        <span class="px-3 py-1 bg-blue-500/10 border border-blue-500/20 rounded-lg text-xs font-bold text-blue-400">
                            {{ $this->activePlayers->count() }} jogador{{ $this->activePlayers->count() !== 1 ? 'es' : '' }}
                        </span>
                    </div>

                    @if($this->activePlayers->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($this->activePlayers as $activePlayer)
                                <div class="flex items-center gap-3 p-4 bg-[#161920] border border-white/10 rounded-xl hover:bg-white/5 transition">
                                    <div class="w-10 h-10 bg-blue-500/20 text-blue-400 rounded-full flex items-center justify-center font-bold text-sm border border-blue-500/20">
                                        {{ substr($activePlayer->user->name, 0, 2) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-white truncate">{{ $activePlayer->user->name }}</p>
                                        <p class="text-xs text-slate-400">
                                            Entrou {{ $activePlayer->joined_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 border border-white/10 border-dashed rounded-xl">
                            <div class="text-3xl text-slate-600 mb-2">👥</div>
                            <p class="text-xs font-semibold text-slate-500">Seja o primeiro a entrar!</p>
                        </div>
                    @endif
                </div>
            </div>

        {{-- TELA DE JOGO ATIVO --}}
        @else
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                {{-- ÁREA DE JOGO --}}
                <div class="lg:col-span-8 space-y-8">
                    
                    {{-- SORTEIO EM TEMPO REAL --}}
                    @if($game->show_drawn_to_players)
                        <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-6 shadow-xl">
                            <div class="flex items-center justify-between mb-6">
                                <span class="text-xs font-bold text-slate-400 uppercase flex items-center gap-2">
                                    <span class="w-2 h-2 bg-blue-600 rounded-full animate-ping"></span>
                                    Painel de Sorteio
                                </span>
                                <span class="text-xs font-bold text-white bg-white/5 px-3 py-1 rounded-lg border border-white/10">
                                    {{ $totalDraws }}/75 sorteados
                                </span>
                            </div>
                            
                            <div class="flex flex-wrap gap-2">
                                @forelse(collect($drawnNumbers)->take(-12)->reverse() as $index => $number)
                                    <div class="w-12 h-12 rounded-lg flex items-center justify-center text-lg font-bold transition-all duration-500
                                        {{ $loop->first ? 'bg-blue-600 text-white shadow-xl scale-110' : 'bg-[#161920] text-slate-500 border border-white/10 opacity-50' }}">
                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                @empty
                                    <div class="w-full py-4 text-center border border-white/10 border-dashed rounded-xl">
                                        <span class="text-xs font-semibold text-slate-500">Aguardando sorteios...</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    {{-- MINHAS CARTELAS --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($cards as $index => $card)
                            @php 
                                $hasWon = in_array($card['id'], $this->myWinningCards);
                                $isBingo = $card['is_bingo'] ?? false;
                                $marked = $card['marked'] ?? [];
                                $numbers = $card['numbers'] ?? [];
                                $markedCount = count($marked);
                                $totalNums = count($numbers);
                                $allMarked = ($markedCount === $totalNums && $totalNums > 0);
                                $active = $game->status === 'active' && !$isBingo && !$hasWon;
                            @endphp
                            
                            <div wire:key="player-card-{{ $card['id'] }}" 
                                class="relative bg-[#0b0d11] rounded-xl p-6 border transition-all duration-500
                                {{ $isBingo || $hasWon ? 'border-emerald-500/40 bg-emerald-500/5' : ($allMarked ? 'border-amber-500/40 bg-amber-500/5' : 'border-white/10 hover:border-white/20 shadow-xl') }}">
                                
                                <div class="flex justify-between items-center mb-6">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center font-bold text-xs text-slate-400">
                                            #{{ $index + 1 }}
                                        </div>
                                        <span class="text-xs font-semibold text-slate-400">Minha Cartela</span>
                                    </div>
                                    
                                    @if($hasWon || $isBingo)
                                        <div class="px-3 py-1 bg-emerald-600 text-white text-xs font-bold rounded-lg shadow-lg">VENCEDORA</div>
                                    @elseif($allMarked)
                                        <div class="px-3 py-1 bg-amber-600 text-white text-xs font-bold rounded-lg">VALIDANDO</div>
                                    @else
                                        <span class="text-xs font-bold text-slate-600">{{ $markedCount }}/{{ $totalNums }}</span>
                                    @endif
                                </div>

                                <div class="grid grid-cols-5 gap-2">
                                    @foreach($numbers as $num)
                                        @php 
                                            $isMarked = in_array($num, $marked);
                                            $wasDrawn = in_array($num, $drawnNumbers);
                                            $highlight = $game->show_player_matches && $wasDrawn && !$isMarked && $active;
                                        @endphp
                                        <button 
                                            wire:click="markNumber({{ $index }}, {{ $num }})"
                                            wire:loading.attr="disabled"
                                            @if(!$active || $isMarked || !$wasDrawn) disabled @endif
                                            class="aspect-square rounded-lg flex items-center justify-center font-bold text-base transition-all
                                            {{ $isMarked 
                                                ? 'bg-blue-600 text-white shadow-lg ring-2 ring-blue-400/20' 
                                                : ($highlight 
                                                    ? 'bg-amber-500/20 text-amber-400 border-2 border-amber-500/40 animate-pulse' 
                                                    : 'bg-[#161920] text-slate-600 border border-white/10 hover:bg-white/5 hover:text-slate-400 disabled:cursor-not-allowed'
                                                )
                                            }}">
                                            {{ $num }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- SIDEBAR DE STATUS --}}
                <aside class="lg:col-span-4 space-y-6">
                    
                    {{-- PRÊMIOS --}}
                    <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-6 shadow-xl">
                        <h3 class="text-sm font-bold text-white uppercase mb-6 flex items-center gap-2">
                            <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                            Tabela de Prêmios
                        </h3>
                        
                        <div class="space-y-3">
                            @foreach($game->prizes->sortBy('position') as $prize)
                                @php $pWinner = $this->roundWinners->firstWhere('prize_id', $prize->id); @endphp
                                <div class="flex items-center justify-between p-4 rounded-xl border transition-all
                                    {{ $prize->is_claimed ? 'bg-emerald-600/5 border-emerald-500/20 opacity-50' : 'bg-[#161920] border-white/10' }}">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-bold text-slate-600">#{{ $prize->position }}</span>
                                        <div>
                                            <p class="text-sm font-bold text-white">{{ $prize->name }}</p>
                                            @if($pWinner)
                                                <p class="text-xs font-semibold text-emerald-400 mt-1">{{ $pWinner->user->name }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    @if($prize->is_claimed)
                                        <span class="text-lg">🏆</span>
                                    @else
                                        <span class="text-xs font-bold text-slate-600">Ativo</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- JOGADORES ATIVOS --}}
                    <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-6 shadow-xl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-sm font-bold text-white uppercase">Participantes</h3>
                            <span class="px-2 py-1 bg-blue-500/10 border border-blue-500/20 rounded text-xs font-bold text-blue-400">
                                {{ $this->activePlayers->count() }}
                            </span>
                        </div>
                        
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            @foreach($this->activePlayers as $activePlayer)
                                <div class="flex items-center gap-3 p-3 bg-[#161920] border border-white/10 rounded-lg hover:bg-white/5 transition">
                                    <div class="w-8 h-8 bg-blue-500/20 text-blue-400 rounded-full flex items-center justify-center font-bold text-xs border border-blue-500/20">
                                        {{ substr($activePlayer->user->name, 0, 2) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-white truncate">{{ $activePlayer->user->name }}</p>
                                        @php
                                            $playerWins = $this->roundWinners->where('user_id', $activePlayer->user_id)->count();
                                        @endphp
                                        @if($playerWins > 0)
                                            <p class="text-xs text-emerald-400 font-semibold">{{ $playerWins }} vitória{{ $playerWins > 1 ? 's' : '' }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- RANKING RODADA --}}
                    <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-6 shadow-xl">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-white/10">
                            <h3 class="text-sm font-bold text-white uppercase">Vencedores R{{ $game->current_round }}</h3>
                        </div>
                        
                        @if($this->roundWinners->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($this->roundWinners as $index => $winner)
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center border border-white/10">
                                            <span class="text-xs font-bold {{ $index < 3 ? 'text-amber-400' : 'text-slate-600' }}">{{ $index + 1 }}º</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-bold text-white truncate">{{ $winner->user->name }}</p>
                                            <p class="text-xs font-semibold text-slate-500">{{ $winner->prize?->name ?? 'Honra' }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 border border-white/10 border-dashed rounded-xl">
                                <p class="text-xs font-semibold text-slate-500">Aguardando vencedores...</p>
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        @endif
    </div>
</div>
