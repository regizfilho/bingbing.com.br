<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Game\Game;
use App\Models\Game\Winner;
use App\Models\Game\Card;
use App\Models\Game\Prize;
use App\Events\GameUpdated;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function mount(string $uuid): void
    {
        $user = auth()->user();

        $this->game = Game::where('uuid', $uuid)
            ->with([
                'creator:id,name,wallet_id', 
                'package:id,name,max_players,is_free,cost_credits', 
                'prizes:id,game_id,name,description,position,is_claimed,uuid', 
                'players.user:id,name'
            ])
            ->firstOrFail();

        $this->isCreator = $this->game->creator_id === $user->id;

        if (!$this->isCreator && !$this->game->players()->where('user_id', $user->id)->exists()) {
            abort(403, 'Acesso negado.');
        }

        $this->winningCards = collect();
        $this->tempSeconds = $this->game->auto_draw_seconds ?? 3;
        $this->loadGameData();
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
                'winners.prize'
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
            'draws' => fn($q) => $q->where('round_number', $this->game->current_round)
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
        if (!$this->isCreator || $this->game->status !== 'active') {
            return;
        }

        $draw = $this->game->drawNumber();

        if (!$draw) {
            session()->flash('error', 'Todos os números já sorteados.');
            return;
        }

        $this->finishAction("Número {$draw->number} sorteado!");
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
        if (!$this->isCreator || $this->game->status !== 'active') {
            return;
        }

        $card = Card::where('uuid', $cardUuid)->first();
        $prize = $prizeUuid ? Prize::where('uuid', $prizeUuid)->where('is_claimed', false)->first() : null;

        if (!$card) {
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

            // 🏆 ENVIAR NOTIFICAÇÃO PUSH DE VITÓRIA
            if ($prize) {
                $pushService = app(\App\Services\PushNotificationService::class);
                $message = \App\Services\NotificationMessages::bingoWinner(
                    $this->game->name,
                    $prize->name
                );

                $pushService->notifyUser(
                    $card->player->user_id,
                    $message['title'],
                    $message['body'],
                    route('games.play', $this->game->uuid)
                );
            }
        });

        $this->finishAction($prize ? 'Prêmio concedido!' : 'Bingo de Honra registrado!');
    }

    private function finishAction(string $msg): void
    {
        $this->game->refresh();
        $this->loadGameData();
        
        broadcast(new GameUpdated($this->game))->toOthers();
        $this->dispatch('game-updated')->self();
        
        session()->flash('success', $msg);
    }

    public function startNextRound(): void
    {
        if (!$this->isCreator) {
            return;
        }

        if ($this->game->current_round >= $this->game->max_rounds) {
            session()->flash('error', 'Esta é a última rodada.');
            return;
        }

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

        $this->finishAction("Rodada {$proxRodada} iniciada!");
    }

    public function startGame(): void
    {
        if (!$this->isCreator || $this->game->status !== 'waiting') {
            return;
        }
        if ($this->game->players()->count() === 0) {
            session()->flash('error', 'Aguarde jogadores.');
            return;
        }
        $this->game->update(['status' => 'active', 'started_at' => now()]);
        $this->finishAction('Partida iniciada!');
    }

    public function finishGame(): void
    {
        $this->game->update(['status' => 'finished', 'finished_at' => now()]);
        $this->finishAction('Partida finalizada!');
    }

    public function togglePause(): void
    {
        $this->isPaused = !$this->isPaused;

        if (!$this->isPaused) {
            $this->game->refresh();
        }

        $msg = $this->isPaused ? 'Sorteio pausado!' : 'Sorteio retomado!';
        session()->flash('info', $msg);
    }

    public function updateDrawSpeed(): void
    {
        if ($this->tempSeconds < 2) {
            $this->tempSeconds = 2;
        }

        $this->game->update(['auto_draw_seconds' => $this->tempSeconds]);
        session()->flash('success', "Intervalo atualizado para {$this->tempSeconds}s");
    }

    public function autoDraw(): void
    {
        if (!$this->isCreator || $this->game->status !== 'active' || $this->game->draw_mode !== 'automatic') {
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
};
?>

<div class="min-h-screen bg-[#0b0d11] text-slate-200">
    <x-slot name="header">Gerenciar Partida</x-slot>

    <div class="flex flex-col lg:flex-row min-h-screen relative">
        {{-- Sidebar --}}
        <aside class="w-full lg:w-80 xl:w-96 bg-[#161920] border-b lg:border-r border-white/5 p-6 lg:sticky lg:top-0 lg:h-screen overflow-y-auto">
            <div class="space-y-6">
                {{-- Header --}}
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-white uppercase italic tracking-tighter">{{ $game->name }}</h1>
                    <div class="mt-3 flex items-center gap-3 flex-wrap">
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border
                            @if($game->status === 'active') bg-green-500/10 text-green-500 border-green-500/20
                            @elseif($game->status === 'waiting') bg-yellow-500/10 text-yellow-500 border-yellow-500/20
                            @elseif($game->status === 'finished') bg-slate-500/10 text-slate-500 border-white/10
                            @else bg-blue-500/10 text-blue-500 border-blue-500/20 @endif">
                            {{ ucfirst($game->status) }}
                        </span>
                        <span class="text-[10px] text-slate-400 font-bold uppercase">
                            Rodada {{ $game->current_round }}/{{ $game->max_rounds }}
                        </span>
                    </div>
                </div>

                {{-- Saldo --}}
                @if($isCreator)
                    <div class="bg-[#1c2128] border border-white/10 rounded-2xl p-4">
                        <div class="text-[10px] text-blue-500 font-black uppercase tracking-widest mb-1">Saldo Disponível</div>
                        <div class="text-2xl font-black text-white tracking-tighter">
                            {{ number_format($this->creatorBalance, 0, ',', '.') }} C$
                        </div>
                        @if(!$game->package->is_free && $game->status !== 'finished')
                            <div class="mt-2 pt-2 border-t border-white/5">
                                <div class="text-[9px] text-slate-400 font-bold">
                                    Custo: {{ number_format($this->packageCost, 0, ',', '.') }} C$
                                </div>
                                @if($this->willRefund)
                                    <div class="text-[9px] text-green-500 font-bold mt-1 flex items-center gap-1">
                                        <span>✓</span> Reembolso automático
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Código --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Código de Acesso</label>
                    <div class="bg-[#0d0f14] rounded-xl px-4 py-3 text-blue-500 font-black tracking-[0.2em] text-lg border border-white/5">
                        {{ $game->invite_code }}
                    </div>
                </div>

                {{-- Ações --}}
                @if($isCreator)
                    <div class="pt-6 border-t border-white/5 space-y-3">
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false" type="button"
                                class="w-full bg-blue-600/20 hover:bg-blue-600/30 text-white py-3 rounded-2xl font-black uppercase text-[10px] flex items-center justify-center gap-2 transition border border-blue-500/20">
                                <span class="text-xl">📺</span> Transmitir Arena
                            </button>

                            <div x-show="open" x-transition class="absolute bottom-full left-0 mb-2 w-full bg-[#161920] rounded-2xl shadow-2xl border border-white/5 py-2 z-50">
                                <button @click="window.open('https://wa.me/?text=' + encodeURIComponent('Participe do bingo {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}. Veja: {{ route('games.display', $game) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-2xl">📱</span> WhatsApp
                                </button>
                                <button @click="window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('Jogue bingo: {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}') + '&url=' + encodeURIComponent('{{ route('games.display', $game) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-2xl">🐦</span> Twitter/X
                                </button>
                                <button @click="navigator.clipboard.writeText('{{ route('games.display', $game) }}').then(() => { open = false; })"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-2xl">📋</span> Copiar Link
                                </button>
                            </div>
                        </div>

                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false" type="button"
                                class="w-full bg-green-500/20 hover:bg-green-500/30 text-white py-3 rounded-2xl font-black uppercase text-[10px] flex items-center justify-center gap-2 transition border border-green-500/20">
                                <span class="text-xl">👥</span> Convocar Jogadores
                            </button>

                            <div x-show="open" x-transition class="absolute bottom-full left-0 mb-2 w-full bg-[#161920] rounded-2xl shadow-2xl border border-white/5 py-2 z-50">
                                <button @click="window.open('https://wa.me/?text=' + encodeURIComponent('Entre no bingo {{ addslashes($game->name) }} com o código {{ $game->invite_code }}: {{ route('games.join', $game->invite_code) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-2xl">📱</span> WhatsApp
                                </button>
                                <button @click="window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('Entre no bingo {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}') + '&url=' + encodeURIComponent('{{ route('games.join', $game->invite_code) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-2xl">🐦</span> Twitter/X
                                </button>
                                <button @click="navigator.clipboard.writeText('{{ route('games.join', $game->invite_code) }}').then(() => { open = false; });"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-2xl">📋</span> Copiar Link
                                </button>
                            </div>
                        </div>

                        <a href="{{ route('games.display', $game) }}" target="_blank"
                            class="w-full bg-purple-500/20 hover:bg-purple-500/30 text-white py-3 rounded-2xl font-black uppercase text-[10px] flex items-center justify-center gap-2 transition border border-purple-500/20">
                            <span class="text-xl">📺</span> Visor Público
                        </a>

                        @if($game->status === 'waiting')
                            <button wire:click="startGame"
                                class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-2xl font-black uppercase text-[10px] transition active:scale-95">
                                Iniciar Partida
                            </button>
                        @endif

                        @if($game->status === 'active' && $game->current_round < $game->max_rounds)
                            <button wire:click="startNextRound"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-2xl font-black uppercase text-[10px] transition active:scale-95">
                                Próxima Rodada ({{ $game->current_round + 1 }}/{{ $game->max_rounds }})
                            </button>
                        @endif

                        @if($game->status !== 'finished')
                            <button wire:click="finishGame" wire:confirm="Deseja finalizar a partida agora?"
                                class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-2xl font-black uppercase text-[10px] transition active:scale-95">
                                Encerrar Partida
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 p-6 lg:p-8 overflow-y-auto">

            {{-- Aviso Prêmios --}}
            @if($showNoPrizesWarning)
                <div class="mb-6 bg-[#1c2128] border border-orange-500/20 rounded-2xl p-6">
                    <div class="flex items-start gap-4">
                        <div class="text-4xl text-orange-500">⚠️</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-black text-orange-400 uppercase italic mb-2">Alerta: Prêmios Esgotados</h3>
                            <p class="text-slate-400 text-sm mb-4">
                                Todos os prêmios foram distribuídos, mas há cartelas vencedoras. Adicione mais prêmios ou avance para a próxima rodada.
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <a href="{{ route('games.edit', $game) }}"
                                    class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-2xl font-black uppercase text-[10px] transition">
                                    Adicionar Prêmios
                                </a>
                                @if($game->canStartNextRound())
                                    <button wire:click="startNextRound"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-2xl font-black uppercase text-[10px] transition">
                                        Próxima Rodada
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Centro de Comando --}}
            @if($game->status === 'active')
                <section class="mb-10 bg-[#161920] border border-white/10 rounded-[2rem] p-6"
                    wire:key="control-{{ $game->current_round }}-{{ $isPaused ? 'p' : 'a' }}"
                    @if($game->draw_mode === 'automatic' && !$isPaused) wire:poll.visible.{{ $game->auto_draw_seconds ?? 3 }}s="autoDraw" @endif>

                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-black text-white uppercase italic flex items-center gap-2">
                            <span class="text-2xl text-blue-500">🕹️</span> Centro de Comando
                        </h2>

                        <div class="flex items-center gap-2 bg-white/5 px-3 py-1 rounded-full border border-white/5">
                            <div class="text-[10px] font-black uppercase text-slate-500">Progresso</div>
                            <div class="w-16 bg-[#0d0f14] rounded-full h-1.5 overflow-hidden border border-white/5">
                                <div class="bg-blue-500 h-full transition-all duration-500" style="width: {{ ($drawnCount / 75) * 100 }}%"></div>
                            </div>
                            <span class="text-[10px] font-black text-blue-400">{{ round(($drawnCount / 75) * 100) }}%</span>
                        </div>
                    </div>

                    @if($game->draw_mode === 'manual')
                        <button wire:click="drawNumber" wire:loading.attr="disabled"
                            class="relative w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-2xl font-black uppercase text-sm mb-6 transition active:scale-95 disabled:opacity-50"
                            @if($drawnCount >= 75) disabled @endif>
                            <div wire:loading wire:target="drawNumber" class="absolute left-6 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <span wire:loading.remove wire:target="drawNumber">Sortear Número</span>
                            <span wire:loading wire:target="drawNumber">Sorteando...</span>
                        </button>
                    @else
                        <div class="bg-[#1c2128] border border-blue-500/20 rounded-2xl p-5 mb-6">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    @if(!$isPaused)
                                        <div class="flex h-4 w-4 rounded-full bg-green-500 animate-pulse"></div>
                                        <div>
                                            <div class="text-sm font-black text-green-400 uppercase italic">Modo Automático</div>
                                            <div class="text-[10px] text-slate-400 font-bold uppercase">Próximo em {{ $game->auto_draw_seconds ?? 3 }}s</div>
                                        </div>
                                    @else
                                        <div class="flex h-4 w-4 rounded-full bg-red-500"></div>
                                        <div>
                                            <div class="text-sm font-black text-red-400 uppercase italic">Pausado</div>
                                            @if($this->winningCards->isNotEmpty())
                                                <div class="text-[10px] bg-red-500/10 text-red-400 px-2 py-0.5 rounded mt-1 font-black uppercase border border-red-500/20">
                                                    ⚠️ Bingo Detectado
                                                </div>
                                            @else
                                                <div class="text-[10px] text-slate-400 font-bold uppercase">Timer Congelado</div>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="flex items-center bg-[#0d0f14] border border-white/10 rounded-2xl p-1">
                                        <div class="px-2 border-r border-white/5">
                                            <span class="text-[9px] font-black text-slate-500 uppercase">Intervalo</span>
                                            <input type="number" wire:model="tempSeconds"
                                                class="w-10 bg-transparent border-none p-0 focus:ring-0 text-sm font-black text-white"
                                                min="2" max="60">
                                        </div>
                                        <button wire:click="updateDrawSpeed" wire:loading.attr="disabled"
                                            class="ml-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-xl text-[10px] font-black transition active:scale-90">
                                            <span wire:loading.remove wire:target="updateDrawSpeed">OK</span>
                                            <svg wire:loading wire:target="updateDrawSpeed" class="animate-spin h-3 w-3 text-white" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    </div>

                                    <button wire:click="togglePause"
                                        class="px-5 py-2.5 rounded-2xl font-black text-[10px] uppercase transition active:scale-95 {{ $isPaused ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-orange-500 hover:bg-orange-600 text-white' }}">
                                        {{ $isPaused ? '▶️ Retomar' : '⏸️ Pausar' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Número Sorteado --}}
                    @if($drawnCount > 0)
                        <div class="bg-gradient-to-br from-blue-700 to-indigo-900 rounded-[2rem] p-10 mb-8 text-center relative overflow-hidden group">
                            <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl transition-transform group-hover:scale-125"></div>
                            <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-blue-500/20 rounded-full blur-3xl transition-transform group-hover:scale-125"></div>

                            <div class="relative z-10">
                                <div class="text-[10px] text-white/60 font-black uppercase tracking-[0.3em] mb-3">Último Número</div>
                                <div class="text-8xl font-black text-white drop-shadow-2xl transition-transform duration-500 group-hover:scale-110">
                                    {{ $lastDrawnNumber }}
                                </div>
                                <div class="mt-4 inline-block px-4 py-1 bg-black/30 rounded-full text-[9px] font-black text-white/80 uppercase tracking-widest border border-white/10">
                                    Sequência #{{ $drawnCount }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-end mb-4 px-1">
                                <div>
                                    <div class="text-[10px] font-black text-slate-500 uppercase">Histórico</div>
                                    <div class="text-lg font-black text-white uppercase italic">Números Sorteados</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] font-black text-slate-500 uppercase">Faltam</div>
                                    <div class="text-[10px] font-black text-blue-400">{{ 75 - $drawnCount }} números</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-10 sm:grid-cols-15 gap-1 p-4 bg-[#0d0f14] rounded-2xl border border-white/5 max-h-56 overflow-y-auto">
                                @foreach(range(1, 75) as $num)
                                    <div class="aspect-square rounded-lg flex items-center justify-center text-[11px] font-black transition-all duration-300
                                        {{ in_array($num, $drawnNumbersList) ? 'bg-blue-500 text-white scale-105' : 'bg-[#161920] border border-white/5 text-slate-600' }}">
                                        {{ $num }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-center py-10 bg-[#0d0f14] rounded-2xl border border-white/5">
                            <div class="text-4xl text-slate-600 mb-3">🔮</div>
                            <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Aguardando Primeiro Sorteio</div>
                        </div>
                    @endif
                </section>
            @endif

                        {{-- Alertas --}}
            @if(session()->has('success') || session()->has('info') || session()->has('error'))
                <div class="mb-6 space-y-3">
                    @if(session('success'))
                        <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-2xl">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if(session('info'))
                        <div class="bg-blue-500/10 border border-blue-500/20 text-blue-400 px-4 py-3 rounded-2xl">
                            {{ session('info') }}
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-2xl">
                            {{ session('error') }}
                        </div>
                    @endif
                </div>
            @endif


            {{-- Cartelas Vencedoras --}}
            @if($this->winningCards->count() > 0)
                <section class="mb-10 bg-[#1c2128] border border-yellow-500/20 rounded-[2rem] p-6">
                    <h2 class="text-2xl font-black text-yellow-400 uppercase italic mb-6 flex items-center gap-3">
                        <span class="text-3xl animate-pulse">🎉</span> Bingo Detectado!
                    </h2>

                    @php $nextPrize = $this->nextAvailablePrize; @endphp

                    <div class="grid gap-4">
                        @foreach($this->winningCards as $card)
                            <div class="bg-[#161920] p-5 rounded-2xl border border-yellow-500/20 flex flex-col sm:flex-row justify-between items-center gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="bg-yellow-500/20 text-yellow-400 w-12 h-12 rounded-full flex items-center justify-center font-black text-xl border border-yellow-500/20">
                                        {{ substr($card->player->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="text-lg font-black text-white uppercase italic">{{ $card->player->user->name }}</div>
                                        <div class="text-[10px] text-yellow-400 font-black uppercase">Cartela #{{ substr($card->uuid, 0, 8) }}</div>
                                    </div>
                                </div>

                                <div class="w-full sm:w-auto">
                                    @if($nextPrize)
                                        <button wire:click="claimPrize('{{ $card->uuid }}', '{{ $nextPrize->uuid }}')"
                                            class="w-full bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-2xl font-black uppercase text-[10px] transition active:scale-95 flex items-center justify-center gap-2">
                                            <span>🎁</span> Conceder: {{ $nextPrize->name }}
                                        </button>
                                    @else
                                        <button wire:click="claimPrize('{{ $card->uuid }}', null)"
                                            class="w-full bg-slate-600 hover:bg-slate-700 text-white px-8 py-3 rounded-2xl font-black uppercase text-[10px] transition active:scale-95 flex items-center justify-center gap-2">
                                            <span>🏅</span> Registrar Honra
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if(!$nextPrize)
                        <div class="mt-6 p-4 bg-[#0d0f14] border border-yellow-500/20 rounded-2xl">
                            <p class="text-yellow-400 text-[10px] font-black uppercase tracking-widest">
                                ⚠️ Todos os prêmios foram distribuídos. Novos vencedores receberão registro de honra.
                            </p>
                        </div>
                    @endif
                </section>
            @endif

            {{-- Prêmios --}}
            <section class="mb-10 bg-[#161920] border border-white/10 rounded-[2rem] p-6">
                <h2 class="text-xl font-black text-white uppercase italic mb-6">Gerenciar Prêmios</h2>

                @if($this->nextAvailablePrize)
                    <div class="mb-6 bg-blue-500/10 border border-blue-500/20 rounded-2xl p-4">
                        <div class="flex items-center gap-3">
                            <div class="text-3xl text-blue-400">🎯</div>
                            <div>
                                <div class="text-[10px] text-blue-400 font-black uppercase tracking-widest">Próximo Prêmio</div>
                                <div class="text-lg font-black text-blue-400 uppercase italic">
                                    {{ $this->nextAvailablePrize->position }}º - {{ $this->nextAvailablePrize->name }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach($game->prizes->sortBy('position') as $prize)
                        <div wire:key="prize-{{ $prize->id }}"
                            class="border border-white/5 rounded-2xl p-5 bg-[#0d0f14] hover:border-blue-500/20 transition
                            {{ $prize->is_claimed ? 'border-green-500/20' : '' }}
                            {{ !$prize->is_claimed && $this->nextAvailablePrize?->id === $prize->id ? 'border-blue-500/50' : '' }}">

                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        @if($prize->position == 1)<span class="text-xl text-yellow-400">🥇</span>@endif
                                        <div class="text-sm font-black text-white uppercase italic">{{ $prize->position }}º - {{ $prize->name }}</div>
                                    </div>

                                    @if($prize->is_claimed)
                                        @php $winner = $prize->winner()->where('game_id', $game->id)->first(); @endphp
                                        <div class="mt-2 p-2 bg-[#161920] rounded border border-green-500/20">
                                            <span class="text-[9px] font-black text-green-400 uppercase">Vencedor:</span>
                                            <div class="text-[10px] font-black text-white uppercase">{{ $winner->user->name ?? 'N/A' }}</div>
                                            <div class="text-[8px] text-slate-500 uppercase">Rodada {{ $winner->round_number }}</div>
                                        </div>
                                    @endif
                                </div>

                                <span class="px-2 py-1 rounded-full text-[9px] font-black uppercase border
                                    {{ $prize->is_claimed ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-blue-500/10 text-blue-400 border-blue-500/20' }}">
                                    {{ $prize->is_claimed ? 'Ganho' : 'Disponível' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Jogadores --}}
            <section class="mb-10 bg-[#161920] border border-white/10 rounded-[2rem] p-6">
                <h2 class="text-xl font-black text-white uppercase italic mb-6">Jogadores ({{ $game->players->count() }})</h2>

                @if($game->players->isEmpty())
                    <div class="text-center py-12 text-slate-500">
                        <div class="text-4xl opacity-50 mb-4">👥</div>
                        <div class="text-[10px] font-black uppercase tracking-widest">Aguardando Jogadores</div>
                    </div>
                @else
                    <div class="space-y-4 max-h-[60vh] overflow-y-auto">
                        @foreach($game->players as $player)
                            <div class="flex items-center justify-between p-4 bg-[#0d0f14] rounded-2xl border border-white/5 hover:bg-white/5 transition">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-blue-500/20 text-blue-400 rounded-full flex items-center justify-center font-black text-xl border border-blue-500/20">
                                        {{ substr($player->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-black text-white uppercase italic">{{ $player->user->name }}</div>
                                        <div class="text-[10px] text-slate-400 font-bold uppercase">
                                            {{ $player->cards()->where('round_number', $game->current_round)->count() }} Cartelas
                                        </div>
                                    </div>
                                </div>
                                @php
                                    $roundWin = $player->user->wins()->where('game_id', $game->id)->where('round_number', $game->current_round)->with('prize')->first();
                                @endphp

                                @if($roundWin)
                                    <div class="text-right">
                                        @if($roundWin->prize)
                                            <span class="block text-[10px] font-black text-yellow-400 uppercase">🏆 {{ $roundWin->prize->position }}º Lugar</span>
                                            <span class="block text-[9px] text-slate-400 font-bold uppercase">{{ $roundWin->prize->name }}</span>
                                        @else
                                            <span class="block text-[10px] font-black text-slate-400 uppercase">✨ Honra</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Hall da Fama --}}
            <section class="bg-gradient-to-br from-indigo-900 to-slate-950 rounded-[2rem] p-6 border border-indigo-500/20">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-black text-white uppercase italic flex items-center gap-2">
                            <span class="animate-pulse text-yellow-400">🏆</span> Hall da Fama
                        </h3>
                        <span class="text-[9px] text-indigo-300 font-black uppercase">Todas as Rodadas</span>
                    </div>
                    <span class="text-[9px] bg-indigo-500/20 text-indigo-300 px-3 py-1 rounded-full font-black uppercase border border-indigo-500/20">
                        {{ $game->winners()->count() }} Vencedores
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($game->winners()->with(['user', 'prize'])->orderBy('won_at', 'asc')->get() as $index => $winner)
                        <div class="flex items-center gap-3 bg-white/5 border border-white/10 p-3 rounded-2xl hover:bg-white/10 transition">
                            <div class="relative">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-black text-sm
                                    {{ $winner->prize_id ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/20' : 'bg-slate-500/20 text-slate-400 border border-slate-500/20' }}">
                                    {{ $index + 1 }}º
                                </div>
                                <div class="absolute -top-1 -right-1 bg-indigo-500/50 text-white text-[8px] px-1 rounded font-black border border-indigo-900/50">
                                    R{{ $winner->round_number }}
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="text-[10px] font-black text-white truncate uppercase">{{ $winner->user->name }}</div>
                                <div class="text-[9px] font-black uppercase tracking-tighter {{ $winner->prize_id ? 'text-yellow-400' : 'text-indigo-400' }}">
                                    {{ $winner->prize ? $winner->prize->name : 'Honra ✨' }}
                                </div>
                            </div>

                            <div class="text-[8px] font-mono text-white/30">
                                {{ $winner->won_at->format('H:i') }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($game->winners()->count() === 0)
                    <div class="text-center py-10">
                        <div class="text-white/20 text-4xl mb-2">⭐</div>
                        <p class="text-white/40 text-[10px] font-black uppercase tracking-widest">Aguardando Primeiros Vencedores...</p>
                    </div>
                @endif
            </section>
        </main>
    </div>
</div>