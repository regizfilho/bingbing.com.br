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
use Illuminate\Support\Facades\Log;
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
    public int $tempSeconds;

    public function mount(string $uuid): void
    {
        $user = auth()->user();

        $this->game = Game::where('uuid', $uuid)
            ->with(['creator:id,name,wallet_id', 'package:id,name,max_players,is_free,cost_credits', 'prizes:id,game_id,name,description,position,is_claimed,uuid', 'players.user:id,name'])
            ->firstOrFail();

        $this->isCreator = $this->game->creator_id === $user->id;

        if (!$this->isCreator && !$this->game->players()->where('user_id', $user->id)->exists()) {
            abort(403, 'Acesso negado.');
        }

        $this->winningCards = collect();
        $this->loadGameData();
        $this->tempSeconds = $this->game->auto_draw_seconds;
    }

    #[On('echo:game.{game.uuid},.GameUpdated')]
    public function handleGameUpdate(): void
    {
        // RECARGA COMPLETA - Resolve o F5 e mantém a consistência
        $this->game->unsetRelations();
        $this->game = Game::where('uuid', $this->game->uuid)
            ->with(['draws', 'winners.user', 'prizes.winner.user', 'players', 'creator', 'package'])
            ->firstOrFail();

        $this->loadGameData();
        $this->dispatch('$refresh');
    }

    public function hydrate(): void
    {
        $this->game->unsetRelations();
        $this->game->load(['prizes', 'package', 'players.cards', 'players.user:id,name', 'winners.user', 'draws' => fn($q) => $q->where('round_number', $this->game->current_round)]);
        $this->loadGameData();
    }

    private function loadGameData(): void
    {
        $this->loadDrawData();
        $this->checkWinningCards();
    }

    // --- MÉTODOS COMPUTED (RESTAURADOS E PROTEGIDOS) ---

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
        // Pega o prêmio de menor número de posição (ex: 1) que ainda não foi ganho
        return $this->game->prizes->where('is_claimed', false)->sortBy('position')->first();
    }

    // --- LÓGICA DE SORTEIO ---

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

    // --- LÓGICA DE VENCEDORES ---

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

        // --- NOVA LÓGICA DE AUTO-PAUSA ---
        // Se houver qualquer cartela vencedora pendente, pausamos o sorteio automático
        if ($this->winningCards->isNotEmpty()) {
            $this->isPaused = true;
        }
        // ---------------------------------

        $this->showNoPrizesWarning = $this->winningCards->isNotEmpty() && !$this->game->hasAvailablePrizes();
    }
    public function claimPrize(string $cardUuid, ?string $prizeUuid = null): void
    {
        if (!$this->isCreator || $this->game->status !== 'active') {
            return;
        }

        $card = Card::where('uuid', $cardUuid)->first();

        // Se enviou um prêmio, buscamos ele. Se não, é um "Prêmio de Honra"
        $prize = $prizeUuid ? Prize::where('uuid', $prizeUuid)->where('is_claimed', false)->first() : null;

        if (!$card) {
            return;
        }

        DB::transaction(function () use ($card, $prize) {
            // Se houver prêmio real, marca como ganho
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
        });

        $this->finishAction($prize ? 'Prêmio concedido!' : 'Bingo de Honra registrado!');
    }

    // --- CICLO DE RODADAS ---

    public function startNextRound(): void
    {
        // Remova ou ajuste a verificação de segurança
        if (!$this->isCreator) {
            return;
        }

        // Se quiser manter uma trava mínima, verifique apenas se não é a última rodada
        if ($this->game->current_round >= $this->game->max_rounds) {
            session()->flash('error', 'Esta é a última rodada.');
            return;
        }

        $proxRodada = (int) ($this->game->current_round + 1);

        DB::transaction(function () use ($proxRodada) {
            // 1. Atualiza a rodada no jogo
            DB::table('games')
                ->where('id', $this->game->id)
                ->update([
                    'current_round' => $proxRodada,
                    'updated_at' => now(),
                ]);

            // ATENÇÃO: Removi o reset de 'is_claimed' dos prêmios.
            // Os prêmios já ganhos devem permanecer ganhos.

            $players = DB::table('players')->where('game_id', $this->game->id)->get();
            $cardsPer = (int) ($this->game->cards_per_player ?? 1);

            foreach ($players as $player) {
                // Gera novas cartelas apenas para a nova rodada
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

    // --- HELPERS ---

    private function finishAction(string $msg): void
    {
        $this->game->refresh();
        $this->loadGameData();
        broadcast(new GameUpdated($this->game))->toOthers();
        session()->flash('success', $msg);
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

    // Novo método para alternar pausa
    public function togglePause(): void
    {
        $this->isPaused = !$this->isPaused;

        // Opcional: Se quiser dar um reset visual no timer ao retomar
        if (!$this->isPaused) {
            $this->game->refresh();
        }

        $msg = $this->isPaused ? 'Sorteio pausado!' : 'Sorteio retomado!';
        session()->flash('info', $msg);
    }

    // Novo método para atualizar o tempo sem recarregar a página
    public function updateDrawSpeed(): void
    {
        if ($this->tempSeconds < 2) {
            $this->tempSeconds = 2;
        } // Segurança

        $this->game->update(['auto_draw_seconds' => $this->tempSeconds]);
        session()->flash('success', "Intervalo atualizado para {$this->tempSeconds}s");
    }

    // Adicione este método ao seu arquivo PHP (dentro da classe)
    public function autoDraw(): void
    {
        // Só sorteia se for o criador, o jogo estiver ativo e o modo for automático
        if (!$this->isCreator || $this->game->status !== 'active' || $this->game->draw_mode !== 'automatic') {
            return;
        }

        // Se já sorteou 75 números, não faz nada
        if ($this->drawnCount >= 75) {
            return;
        }

        $this->drawNumber();
    }
};
?>
<div>
    <x-slot name="header">
        Gerenciar Partida
    </x-slot>

    <div class="flex flex-col lg:flex-row min-h-screen relative overflow-hidden bg-[#05070a] text-slate-300">

        {{-- Fundo Atmosférico --}}
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_0%,rgba(37,99,235,0.15)_0%,transparent_50%)]"></div>

        <aside class="w-full lg:w-80 bg-[#161920] border-b lg:border-r border-white/5 p-4 sm:p-6 lg:sticky lg:top-0 lg:h-screen overflow-y-auto flex-shrink-0 relative z-10">
            <div class="space-y-4 sm:space-y-6">
                <div>
                    <h1 class="font-game text-2xl sm:text-3xl font-black text-white uppercase italic tracking-tighter">{{ $game->name }}</h1>
                    <div class="mt-2 sm:mt-3 flex items-center gap-2 sm:gap-3 flex-wrap">
                        <span
                            class="px-2 sm:px-3 py-1 rounded-full text-[9px] sm:text-[10px] font-black uppercase tracking-widest
                            @if ($game->status === 'active') bg-green-500/10 text-green-500 border border-green-500/20
                            @elseif($game->status === 'waiting') bg-yellow-500/10 text-yellow-500 border border-yellow-500/20
                            @elseif($game->status === 'finished') bg-slate-500/10 text-slate-500 border border-white/10
                            @else bg-blue-500/10 text-blue-500 border border-blue-500/20 @endif">
                            {{ ucfirst($game->status) }}
                        </span>
                        <span class="text-[9px] sm:text-[10px] text-slate-400 font-bold uppercase tracking-tighter">
                            Rodada {{ $game->current_round }}/{{ $game->max_rounds }}
                        </span>
                    </div>
                </div>

                @if ($isCreator)
                    <div class="bg-[#1c2128] border border-white/10 rounded-2xl p-3 sm:p-4 shadow-[0_0_15px_rgba(37,99,235,0.1)]">
                        <div class="text-[9px] sm:text-[10px] text-blue-500 font-black uppercase tracking-widest mb-1">Seu Arsenal</div>
                        <div class="font-game text-xl sm:text-2xl font-black text-white tracking-tighter">
                            {{ number_format($this->creatorBalance, 0, ',', '.') }} Créditos
                        </div>
                        @if (!$game->package->is_free && $game->status !== 'finished')
                            <div class="mt-2 pt-2 border-t border-white/5">
                                <div class="text-[8px] sm:text-[9px] text-slate-400 font-bold uppercase">
                                    Custo Operacional: {{ number_format($this->packageCost, 0, ',', '.') }} Créditos
                                </div>
                                @if ($this->willRefund)
                                    <div class="text-[8px] sm:text-[9px] text-green-500 font-bold uppercase mt-1 flex items-center gap-1">
                                        <span class="text-green-400">✓</span> Reembolso Automático (Sem Atividade)
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                <div>
                    <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Código de Acesso</label>
                    <div class="bg-[#0d0f14] rounded-xl px-3 sm:px-4 py-2 sm:py-3 font-game text-blue-500 font-black tracking-[0.2em] text-base sm:text-lg border border-white/5">
                        {{ $game->invite_code }}
                    </div>
                </div>

                @if ($isCreator)
                    <div class="pt-4 sm:pt-6 border-t border-white/5 space-y-2 sm:space-y-3">
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false" type="button"
                                class="w-full bg-blue-600/20 hover:bg-blue-600/30 text-white py-2 sm:py-3 rounded-2xl font-black uppercase tracking-widest text-[9px] sm:text-[10px] flex items-center justify-center gap-2 transition shadow-[0_0_15px_rgba(37,99,235,0.2)] border border-blue-500/20">
                                <span class="text-lg sm:text-xl">📺</span> Transmitir Arena Pública
                            </button>

                            <div x-show="open" x-transition
                                class="absolute bottom-full left-0 mb-2 w-full bg-[#161920] rounded-2xl shadow-2xl border border-white/5 py-2 z-50 backdrop-blur-md">
                                <button
                                    @click="window.open('https://wa.me/?text=' + encodeURIComponent('Participe do bingo {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}. Veja ao vivo: {{ route('games.display', $game) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-xl sm:text-2xl">📱</span> WhatsApp
                                </button>
                                <button
                                    @click="window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('Jogue bingo ao vivo em {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}') + '&url=' + encodeURIComponent('{{ route('games.display', $game) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-xl sm:text-2xl">🐦</span> Twitter/X
                                </button>
                                <button
                                    @click="navigator.clipboard.writeText('{{ route('games.display', $game) }}').then(() => { open = false; })"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-xl sm:text-2xl">📋</span> Copiar Link
                                </button>
                            </div>
                        </div>

                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false" type="button"
                                class="w-full bg-green-500/20 hover:bg-green-500/30 text-white py-2 sm:py-3 rounded-2xl font-black uppercase tracking-widest text-[9px] sm:text-[10px] flex items-center justify-center gap-2 transition shadow-[0_0_15px_rgba(34,197,94,0.2)] border border-green-500/20">
                                <span class="text-lg sm:text-xl">👥</span> Convocar Aliados
                            </button>

                            <div x-show="open" x-transition
                                class="absolute bottom-full left-0 mb-2 w-full bg-[#161920] rounded-2xl shadow-2xl border border-white/5 py-2 z-50 backdrop-blur-md">
                                <button
                                    @click="window.open('https://wa.me/?text=' + encodeURIComponent('Entre no bingo {{ addslashes($game->name) }} usando o código {{ $game->invite_code }} ou clique aqui: {{ route('games.join', $game->invite_code) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-xl sm:text-2xl">📱</span> WhatsApp
                                </button>
                                <button
                                    @click="window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('Entre no bingo {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}') + '&url=' + encodeURIComponent('{{ route('games.join', $game->invite_code) }}'), '_blank'); open = false"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-xl sm:text-2xl">🐦</span> Twitter/X
                                </button>
                                <button
                                    @click="navigator.clipboard.writeText('{{ route('games.join', $game->invite_code) }}').then(() => { open = false; });"
                                    class="w-full text-left px-4 py-3 hover:bg-white/5 flex items-center gap-3 text-sm text-slate-300">
                                    <span class="text-xl sm:text-2xl">📋</span> Copiar Link
                                </button>
                            </div>
                        </div>

                        <a href="{{ route('games.display', $game) }}" target="_blank"
                            class="w-full bg-purple-500/20 hover:bg-purple-500/30 text-white py-2 sm:py-3 rounded-2xl font-black uppercase tracking-widest text-[9px] sm:text-[10px] flex items-center justify-center gap-2 transition shadow-[0_0_15px_rgba(168,85,247,0.2)] border border-purple-500/20">
                            <span class="text-lg sm:text-xl">📺</span> Ativar Visor Público
                        </a>

                        @if ($game->status === 'waiting')
                            <button wire:click="startGame"
                                class="w-full bg-green-600 hover:bg-green-700 text-white py-2 sm:py-3 rounded-2xl font-black uppercase tracking-widest text-[9px] sm:text-[10px] shadow-[0_0_20px_rgba(34,197,94,0.3)] transition active:scale-95">
                                Iniciar Operação
                            </button>
                        @endif

                        @if ($game->status === 'active' && $game->current_round < $game->max_rounds)
                            <button wire:click="startNextRound"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 sm:py-3 rounded-2xl font-black uppercase tracking-widest text-[9px] sm:text-[10px] shadow-[0_0_20px_rgba(37,99,235,0.3)] transition active:scale-95">
                                Próxima Fase ({{ $game->current_round + 1 }}/{{ $game->max_rounds }})
                            </button>
                        @endif

                        @if ($game->status !== 'finished')
                            <button wire:click="finishGame"
                                wire:confirm="Esta é a última rodada. Deseja finalizar a partida agora?"
                                class="w-full bg-red-600 hover:bg-red-700 text-white py-2 sm:py-3 rounded-2xl font-black uppercase tracking-widest text-[9px] sm:text-[10px] shadow-[0_0_20px_rgba(239,68,68,0.3)] transition active:scale-95">
                                Encerrar Missão
                            </button>
                        @endif

                    </div>
                @endif
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 relative z-10 overflow-y-auto">
            @if (session()->has('success') || session()->has('info') || session()->has('error'))
                <div class="mb-4 sm:mb-6 space-y-2 sm:space-y-3">
                    @if (session('success'))
                        <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-3 sm:px-4 py-2 sm:py-3 rounded-2xl shadow-[0_0_15px_rgba(34,197,94,0.2)]">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('info'))
                        <div class="bg-blue-500/10 border border-blue-500/20 text-blue-400 px-3 sm:px-4 py-2 sm:py-3 rounded-2xl shadow-[0_0_15px_rgba(37,99,235,0.2)]">
                            {{ session('info') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-3 sm:px-4 py-2 sm:py-3 rounded-2xl shadow-[0_0_15px_rgba(239,68,68,0.2)]">
                            {{ session('error') }}
                        </div>
                    @endif
                </div>
            @endif

            @if ($showNoPrizesWarning)
                <div class="mb-4 sm:mb-6 bg-[#1c2128] border border-orange-500/20 rounded-2xl p-4 sm:p-6 shadow-[0_0_20px_rgba(249,115,22,0.2)]">
                    <div class="flex items-start gap-3 sm:gap-4">
                        <div class="text-3xl sm:text-4xl text-orange-500">⚠️</div>
                        <div class="flex-1">
                            <h3 class="font-game text-base sm:text-lg font-black text-orange-400 uppercase italic mb-2">Alerta: Arsenal Vazio</h3>
                            <p class="text-slate-400 text-xs sm:text-sm mb-3 sm:mb-4">
                                Todos os prêmios desta fase foram alocados, mas há cartelas em alerta máximo.
                                Reabasteça o arsenal ou avance para a próxima fase.
                            </p>
                            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                                <a href="{{ route('games.edit', $game) }}"
                                    class="bg-orange-600 hover:bg-orange-700 text-white px-3 sm:px-4 py-2 rounded-2xl font-black uppercase text-[9px] sm:text-[10px] transition shadow-[0_0_10px_rgba(249,115,22,0.3)]">
                                    Reabastecer Arsenal
                                </a>
                                @if ($game->canStartNextRound())
                                    <button wire:click="startNextRound"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 sm:px-4 py-2 rounded-2xl font-black uppercase text-[9px] sm:text-[10px] transition shadow-[0_0_10px_rgba(37,99,235,0.3)]">
                                        Avançar Fase
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($game->status === 'active')
                <section class="mb-4 sm:mb-10 bg-[#161920] border border-white/10 rounded-2xl sm:rounded-[2rem] p-4 sm:p-6 shadow-2xl overflow-hidden"
                    wire:key="control-panel-{{ $game->current_round }}-{{ $game->auto_draw_seconds }}-{{ $isPaused ? 'paused' : 'active' }}"
                    @if ($game->draw_mode === 'automatic' && !$isPaused) wire:poll.visible.{{ $game->auto_draw_seconds }}s="autoDraw" @endif>

                    <div class="flex justify-between items-center mb-4 sm:mb-6">
                        <h2 class="font-game text-base sm:text-xl font-black text-white uppercase italic tracking-tighter flex items-center gap-2">
                            <span class="text-xl sm:text-2xl text-blue-500">🕹️</span> Centro de Comando
                        </h2>

                        {{-- Badge de Progresso --}}
                        <div class="flex items-center gap-1 sm:gap-2 bg-white/5 px-2 sm:px-3 py-1 rounded-full border border-white/5">
                            <div class="text-[9px] sm:text-[10px] font-black uppercase text-slate-500">Progresso</div>
                            <div class="w-12 sm:w-16 bg-[#0d0f14] rounded-full h-1.5 overflow-hidden border border-white/5">
                                <div class="bg-blue-500 h-full transition-all duration-500"
                                    style="width: {{ ($drawnCount / 75) * 100 }}%"></div>
                            </div>
                            <span class="text-[9px] sm:text-[10px] font-black text-blue-400">{{ round(($drawnCount / 75) * 100) }}%</span>
                        </div>
                    </div>

                    @if ($game->draw_mode === 'manual')
                        <button wire:click="drawNumber" wire:loading.attr="disabled"
                            class="relative w-full bg-blue-600 hover:bg-blue-700 text-white py-3 sm:py-4 rounded-2xl font-black uppercase tracking-widest text-xs sm:text-sm mb-4 sm:mb-6 shadow-[0_0_20px_rgba(37,99,235,0.3)] transition active:scale-95 disabled:opacity-50"
                            @if ($drawnCount >= 75) disabled @endif>

                            {{-- Spinner de Carregamento --}}
                            <div wire:loading wire:target="drawNumber" class="absolute left-4 sm:left-6 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-4 sm:h-5 w-4 sm:w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </div>

                            <span wire:loading.remove wire:target="drawNumber">Ativar Sorteio</span>
                            <span wire:loading wire:target="drawNumber">Processando...</span>
                        </button>
                    @else
                        {{-- MODO AUTOMÁTICO --}}
                        <div class="bg-[#1c2128] border border-blue-500/20 rounded-2xl p-4 sm:p-5 mb-4 sm:mb-6 shadow-[0_0_15px_rgba(37,99,235,0.1)]">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 sm:gap-6">

                                {{-- Status e Timer --}}
                                <div class="flex items-center gap-3 sm:gap-4">
                                    @if (!$isPaused)
                                        <div class="flex h-3 sm:h-4 w-3 sm:w-4 rounded-full bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.5)] animate-pulse"></div>
                                        <div>
                                            <div class="font-game text-xs sm:text-sm font-black text-green-400 uppercase italic">Modo Automático</div>
                                            <div class="text-[9px] sm:text-[10px] text-slate-400 font-bold uppercase mt-1">Próxima em {{ $game->auto_draw_seconds }}s</div>
                                        </div>
                                    @else
                                        <div class="flex h-3 sm:h-4 w-3 sm:w-4 rounded-full bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]"></div>
                                        <div>
                                            <div class="font-game text-xs sm:text-sm font-black text-red-400 uppercase italic">Pausa Ativada</div>
                                            @if ($this->winningCards->isNotEmpty())
                                                <div class="text-[9px] sm:text-[10px] bg-red-500/10 text-red-400 px-2 py-0.5 rounded mt-1 font-black uppercase border border-red-500/20">
                                                    ⚠️ Bingo Detectado
                                                </div>
                                            @else
                                                <div class="text-[9px] sm:text-[10px] text-slate-400 font-bold uppercase mt-1">Timer Congelado</div>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                {{-- Controles Rápidos --}}
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <div class="flex items-center bg-[#0d0f14] border border-white/10 rounded-2xl p-1 shadow-[0_0_10px_rgba(255,255,255,0.05)] focus-within:border-blue-500/50 transition-all">
                                        <div class="px-1 sm:px-2 border-r border-white/5 flex flex-col">
                                            <span class="text-[8px] sm:text-[9px] font-black text-slate-500 uppercase leading-none mb-1">Ciclo</span>
                                            <input type="number" wire:model="tempSeconds"
                                                class="w-8 sm:w-10 bg-transparent border-none p-0 focus:ring-0 text-xs sm:text-sm font-black text-white"
                                                min="2" max="60">
                                        </div>
                                        <button wire:click="updateDrawSpeed" wire:loading.attr="disabled"
                                            class="ml-1 bg-blue-600 hover:bg-blue-700 text-white px-2 sm:px-3 py-1 sm:py-2 rounded-xl text-[9px] sm:text-[10px] font-black transition active:scale-90">
                                            <span wire:loading.remove wire:target="updateDrawSpeed">OK</span>
                                            <svg wire:loading wire:target="updateDrawSpeed"
                                                class="animate-spin h-2 sm:h-3 w-2 sm:w-3 text-white" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>

                                    <button wire:click="togglePause"
                                        class="px-3 sm:px-5 py-1.5 sm:py-2.5 rounded-2xl font-black text-[9px] sm:text-[10px] uppercase tracking-widest transition shadow-[0_0_15px_rgba(255,255,255,0.1)] active:scale-95 {{ $isPaused ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-orange-500 hover:bg-orange-600 text-white' }}">
                                        {{ $isPaused ? '▶️ Retomar' : '⏸️ Pausar' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Área do Número Sorteado --}}
                    @if ($drawnCount > 0)
                        <div class="bg-gradient-to-br from-blue-700 to-indigo-900 rounded-2xl sm:rounded-[2rem] p-6 sm:p-10 mb-6 sm:mb-8 text-center shadow-[0_0_30px_rgba(79,70,229,0.3)] relative overflow-hidden group">
                            {{-- Efeitos de Fundo --}}
                            <div class="absolute -right-10 -top-10 w-32 sm:w-40 h-32 sm:h-40 bg-white/10 rounded-full blur-3xl transition-transform group-hover:scale-125"></div>
                            <div class="absolute -left-10 -bottom-10 w-32 sm:w-40 h-32 sm:h-40 bg-blue-500/20 rounded-full blur-3xl transition-transform group-hover:scale-125"></div>

                            <div class="relative z-10">
                                <div class="text-[9px] sm:text-[10px] text-white/60 font-black uppercase tracking-[0.3em] mb-2 sm:mb-3">Último Sinal</div>
                                <div class="font-game text-6xl sm:text-8xl font-black text-white drop-shadow-2xl transition-transform duration-500 group-hover:scale-110">
                                    {{ $lastDrawnNumber }}
                                </div>
                                <div class="mt-3 sm:mt-4 inline-block px-3 sm:px-4 py-1 bg-black/30 rounded-full text-[8px] sm:text-[9px] font-black text-white/80 uppercase tracking-widest border border-white/10">
                                    Sequência #{{ $drawnCount }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-end mb-3 sm:mb-4 px-1">
                                <div>
                                    <div class="text-[9px] sm:text-[10px] font-black text-slate-500 uppercase tracking-tighter">Registro</div>
                                    <div class="font-game text-base sm:text-lg font-black text-white uppercase italic">Matriz de Sorteio</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[9px] sm:text-[10px] font-black text-slate-500 uppercase">Pendentes</div>
                                    <div class="text-[9px] sm:text-[10px] font-black text-blue-400 uppercase">{{ 75 - $drawnCount }} Sinais</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-5 xs:grid-cols-10 sm:grid-cols-15 gap-1 p-3 sm:p-4 bg-[#0d0f14] rounded-2xl border border-white/5 shadow-inner max-h-48 sm:max-h-56 overflow-y-auto custom-scrollbar">
                                @foreach (range(1, 75) as $num)
                                    <div class="aspect-square rounded-lg flex items-center justify-center text-[10px] sm:text-[11px] font-black transition-all duration-300
                                        {{ in_array($num, $drawnNumbersList) ? 'bg-blue-500 text-white shadow-[0_4px_10px_rgba(37,99,235,0.4)] scale-105 border-transparent' : 'bg-[#161920] border border-white/5 text-slate-600' }}">
                                        {{ $num }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        {{-- Estado Vazio --}}
                        <div class="text-center py-8 sm:py-10 bg-[#0d0f14] rounded-2xl border border-white/5 shadow-[0_0_10px_rgba(255,255,255,0.05)]">
                            <div class="text-3xl sm:text-4xl text-slate-600 mb-2 sm:mb-3">🔮</div>
                            <div class="text-[9px] sm:text-[10px] font-black text-slate-500 uppercase tracking-widest">Aguardando Primeiro Sinal</div>
                        </div>
                    @endif
                </section>

            @endif

            <style>
                .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #3b82f6; }
            </style>

            @if ($this->winningCards->count() > 0)
                <section class="mb-4 sm:mb-10 bg-[#1c2128] border border-yellow-500/20 rounded-2xl sm:rounded-[2rem] p-4 sm:p-6 shadow-[0_0_30px_rgba(234,179,8,0.2)]">
                    <h2 class="font-game text-xl sm:text-2xl font-black text-yellow-400 uppercase italic mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <span class="text-2xl sm:text-3xl animate-pulse">🎉</span> Alerta de Vitória!
                    </h2>

                    @php
                        $nextPrize = $this->nextAvailablePrize;
                    @endphp

                    <div class="grid gap-3 sm:gap-4">
                        @foreach ($this->winningCards as $card)
                            <div class="bg-[#161920] p-4 sm:p-5 rounded-2xl border border-yellow-500/20 shadow-[0_0_15px_rgba(234,179,8,0.1)] flex flex-col sm:flex-row justify-between items-center gap-3 sm:gap-4">
                                <div class="flex items-center gap-3 sm:gap-4">
                                    <div class="bg-yellow-500/20 text-yellow-400 w-10 sm:w-12 h-10 sm:h-12 rounded-full flex items-center justify-center font-black text-lg sm:text-xl shadow-inner border border-yellow-500/20">
                                        {{ substr($card->player->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-game text-base sm:text-lg font-black text-white uppercase italic leading-none">
                                            {{ $card->player->user->name }}
                                        </div>
                                        <div class="text-[9px] sm:text-[10px] text-yellow-400 font-black uppercase tracking-tighter mt-1">
                                            Cartela #{{ substr($card->uuid, 0, 8) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="w-full sm:w-auto mt-3 sm:mt-0">
                                    @if ($nextPrize)
                                        <button wire:click="claimPrize('{{ $card->uuid }}', '{{ $nextPrize->uuid }}')"
                                            class="w-full bg-green-600 hover:bg-green-700 text-white px-6 sm:px-8 py-2 sm:py-3 rounded-2xl font-black uppercase text-[9px] sm:text-[10px] transition shadow-[0_0_20px_rgba(34,197,94,0.3)] active:scale-95 flex items-center justify-center gap-2">
                                            <span>🎁</span> Conceder: {{ $nextPrize->name }}
                                        </button>
                                    @else
                                        <button wire:click="claimPrize('{{ $card->uuid }}', null)"
                                            class="w-full bg-slate-600 hover:bg-slate-700 text-white px-6 sm:px-8 py-2 sm:py-3 rounded-2xl font-black uppercase text-[9px] sm:text-[10px] transition shadow-[0_0_20px_rgba(148,163,184,0.3)] active:scale-95 flex items-center justify-center gap-2">
                                            <span>🏅</span> Registrar Honra
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if (!$nextPrize)
                        <div class="mt-4 sm:mt-6 p-3 sm:p-4 bg-[#0d0f14] border border-yellow-500/20 rounded-2xl shadow-inner">
                            <p class="text-yellow-400 text-[9px] sm:text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                                Aviso: Arsenal Esgotado. Novos Campeões no Ranking de Honra.
                            </p>
                        </div>
                    @endif
                </section>
            @endif

            <section class="mb-4 sm:mb-10 bg-[#161920] border border-white/10 rounded-2xl sm:rounded-[2rem] p-4 sm:p-6 shadow-2xl">
                <h2 class="font-game text-base sm:text-xl font-black text-white uppercase italic mb-4 sm:mb-6">Gerenciar Arsenal</h2>

                @if ($this->nextAvailablePrize)
                    <div class="mb-4 sm:mb-6 bg-blue-500/10 border border-blue-500/20 rounded-2xl p-3 sm:p-4 shadow-[0_0_15px_rgba(37,99,235,0.2)]">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="text-2xl sm:text-3xl text-blue-400">🎯</div>
                            <div>
                                <div class="text-[9px] sm:text-[10px] text-blue-400 font-black uppercase tracking-widest">Próximo Alvo</div>
                                <div class="font-game text-base sm:text-lg font-black text-blue-400 uppercase italic">
                                    {{ $this->nextAvailablePrize->position }}º - {{ $this->nextAvailablePrize->name }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">

                    @foreach ($game->prizes->sortBy('position') as $prize)
                        <div wire:key="prize-{{ $prize->id }}"
                            class="border border-white/5 rounded-2xl p-4 sm:p-5 bg-[#0d0f14] shadow-inner hover:shadow-[0_0_15px_rgba(37,99,235,0.2)] transition 
                            {{ $prize->is_claimed ? 'border-green-500/20' : '' }} 
                            {{ !$prize->is_claimed && $this->nextAvailablePrize?->id === $prize->id ? 'border-blue-500/50 shadow-[0_0_20px_rgba(37,99,235,0.3)]' : '' }}">

                            <div class="flex justify-between items-start mb-2 sm:mb-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-1 sm:gap-2">
                                        @if ($prize->position == 1)
                                            <span class="text-lg sm:text-xl text-yellow-400">🥇</span>
                                        @endif
                                        <div class="font-game text-xs sm:text-sm font-black text-white uppercase italic">{{ $prize->position }}º - {{ $prize->name }}</div>
                                    </div>

                                    @if ($prize->is_claimed)
                                        @php $winner = $prize->winner()->where('game_id', $game->id)->first(); @endphp
                                        <div class="mt-1 sm:mt-2 p-1 sm:p-2 bg-[#161920] rounded border border-green-500/20">
                                            <span class="text-[8px] sm:text-[9px] font-black text-green-400 uppercase tracking-wider">Campeão:</span>
                                            <div class="text-[9px] sm:text-[10px] font-black text-white uppercase">
                                                {{ $winner->user->name ?? 'N/A' }}</div>
                                            <div class="text-[7px] sm:text-[8px] text-slate-500 uppercase">Fase {{ $winner->round_number }}</div>
                                        </div>
                                    @endif
                                </div>

                                <span class="px-1 sm:px-2 py-0.5 sm:py-1 rounded-full text-[8px] sm:text-[9px] font-black uppercase tracking-widest border 
                                    {{ $prize->is_claimed ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-blue-500/10 text-blue-400 border-blue-500/20' }}">
                                    {{ $prize->is_claimed ? 'Alocado' : 'Disponível' }}
                                </span>
                            </div>

                            @php
                                $roundWinner = $prize->winner()->where('round_number', $game->current_round)->first();
                            @endphp

                            @if ($roundWinner)
                                <div class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-white/5 bg-green-500/5 -m-4 sm:-m-5 p-4 sm:p-5 rounded-b-2xl">
                                    <div class="flex items-center justify-between gap-2 sm:gap-3">
                                        <div>
                                            <div class="font-game text-[9px] sm:text-[10px] font-black text-green-400 uppercase italic">
                                                {{ $roundWinner->user->name }}
                                            </div>
                                            <div class="text-[8px] sm:text-[9px] text-slate-400 font-bold uppercase">
                                                {{ $roundWinner->won_at->format('d/m/Y H:i') }}
                                            </div>
                                        </div>
                                        @if ($game->status === 'active')
                                            <button wire:click="removePrize({{ $roundWinner->id }})"
                                                wire:confirm="Tem certeza que deseja remover este prêmio de {{ $roundWinner->user->name }}?"
                                                class="bg-red-600 hover:bg-red-700 text-white px-2 sm:px-3 py-0.5 sm:py-1 rounded-2xl text-[8px] sm:text-[9px] font-black uppercase transition shadow-[0_0_10px_rgba(239,68,68,0.2)]">
                                                Revogar
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if (!$prize->is_claimed && $this->winningCards->count() > 0 && $game->status === 'active')
                                <div class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-white/5">
                                    <div class="text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 sm:mb-3">Alocar Para:</div>
                                    <div class="grid gap-2 sm:gap-3">
                                        @foreach ($this->winningCards as $card)
                                            <button wire:key="claim-{{ $prize->uuid }}-{{ $card->uuid }}"
                                                wire:click="claimPrize('{{ $card->uuid }}', '{{ $prize->uuid }}')"
                                                class="bg-green-600 hover:bg-green-700 text-white py-2 sm:py-3 rounded-2xl font-black uppercase text-[9px] sm:text-[10px] disabled:opacity-50 transition shadow-[0_0_15px_rgba(34,197,94,0.2)]"
                                                wire:loading.attr="disabled">
                                                {{ $card->player->user->name }} - Cartela #{{ substr($card->uuid, 0, 8) }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="mb-4 sm:mb-10 bg-[#161920] border border-white/10 rounded-2xl sm:rounded-[2rem] p-4 sm:p-6 shadow-2xl">
                <h2 class="font-game text-base sm:text-xl font-black text-white uppercase italic mb-4 sm:mb-6">Esquadrão ({{ $game->players->count() }})</h2>

                @if ($game->players->isEmpty())
                    <div class="text-center py-8 sm:py-12 text-slate-500">
                        <div class="text-3xl sm:text-4xl opacity-50 mb-3 sm:mb-4">👥</div>
                        <div class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest">Aguardando Recrutas</div>
                    </div>
                @else
                    <div class="space-y-3 sm:space-y-4 max-h-[50vh] sm:max-h-[60vh] overflow-y-auto custom-scrollbar">
                        @foreach ($game->players as $player)
                            <div class="flex items-center justify-between p-3 sm:p-4 bg-[#0d0f14] rounded-2xl border border-white/5 hover:bg-white/5 transition">
                                <div class="flex items-center gap-3 sm:gap-4">
                                    <div class="w-10 sm:w-12 h-10 sm:h-12 bg-blue-500/20 text-blue-400 rounded-full flex items-center justify-center font-black text-lg sm:text-xl border border-blue-500/20">
                                        {{ substr($player->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-game text-xs sm:text-sm font-black text-white uppercase italic">{{ $player->user->name }}</div>
                                        <div class="text-[9px] sm:text-[10px] text-slate-400 font-bold uppercase">
                                            {{ $player->cards()->where('round_number', $game->current_round)->count() }} Cartelas
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
                                            <span class="block text-[9px] sm:text-[10px] font-black text-yellow-400 uppercase">
                                                🏆 {{ $roundWin->prize->position }}º Setor
                                            </span>
                                            <span class="block text-[8px] sm:text-[9px] text-slate-400 font-bold uppercase">
                                                {{ $roundWin->prize->name }}
                                            </span>
                                        @else
                                            <span class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase">
                                                ✨ Honra de Combate
                                            </span>
                                            <span class="block text-[7px] sm:text-[8px] text-slate-500 uppercase font-black">
                                                Mérito
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- HALL DA FAMA - RANKING GERAL DA PARTIDA --}}
            <section class="bg-gradient-to-br from-indigo-900 to-slate-950 rounded-2xl sm:rounded-[2rem] p-4 sm:p-6 shadow-[0_0_30px_rgba(79,70,229,0.3)] border border-indigo-500/20">
                <div class="flex items-center justify-between mb-4 sm:mb-6">
                    <div class="flex flex-col">
                        <h3 class="font-game text-base sm:text-lg font-black text-white uppercase italic tracking-widest flex items-center gap-2">
                            <span class="animate-pulse text-yellow-400">🏆</span> Hall da Fama
                        </h3>
                        <span class="text-[8px] sm:text-[9px] text-indigo-300 font-black uppercase tracking-tighter">Acumulado de Todas as Fases</span>
                    </div>
                    <span class="text-[8px] sm:text-[9px] bg-indigo-500/20 text-indigo-300 px-2 sm:px-3 py-0.5 sm:py-1 rounded-full font-black uppercase tracking-widest border border-indigo-500/20 shadow-[0_0_10px_rgba(79,70,229,0.2)]">
                        {{ $game->winners()->count() }} Campeões
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    @foreach ($game->winners()->with(['user', 'prize'])->orderBy('won_at', 'asc')->get() as $index => $winner)
                        <div class="flex items-center gap-2 sm:gap-3 bg-white/5 border border-white/10 p-2 sm:p-3 rounded-2xl hover:bg-white/10 hover:border-yellow-500/20 transition-all group backdrop-blur-md">

                            {{-- Posição Geral --}}
                            <div class="relative">
                                <div class="w-8 sm:w-10 h-8 sm:h-10 rounded-full flex items-center justify-center font-black text-xs sm:text-sm 
                                    {{ $winner->prize_id ? 'bg-yellow-500/20 text-yellow-400 shadow-[0_0_15px_rgba(234,179,8,0.3)] border border-yellow-500/20' : 'bg-slate-500/20 text-slate-400 border border-slate-500/20' }}">
                                    {{ $index + 1 }}º
                                </div>
                                {{-- Badge da Rodada --}}
                                <div class="absolute -top-1 -right-1 bg-indigo-500/50 text-white text-[7px] sm:text-[8px] px-1 rounded font-black border border-indigo-900/50">
                                    F{{ $winner->round_number }}
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="text-[9px] sm:text-[10px] font-black text-white truncate group-hover:text-yellow-400 transition-colors uppercase">
                                    {{ $winner->user->name }}
                                </div>
                                <div class="text-[8px] sm:text-[9px] font-black uppercase tracking-tighter 
                                    {{ $winner->prize_id ? 'text-yellow-400' : 'text-indigo-400' }}">
                                    {{ $winner->prize ? $winner->prize->name : 'Honra ✨' }}
                                </div>
                            </div>

                            {{-- Horário --}}
                            <div class="text-[7px] sm:text-[8px] font-mono text-white/30 group-hover:text-white/60">
                                {{ $winner->won_at->format('H:i') }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($game->winners()->count() === 0)
                    <div class="text-center py-8 sm:py-10">
                        <div class="text-white/20 text-3xl sm:text-4xl mb-2">⭐</div>
                        <p class="text-white/40 text-[9px] sm:text-[10px] font-black uppercase tracking-widest">Aguardando Primeiros Campeões...</p>
                    </div>
                @endif
            </section>
        </main>
    </div>
</div>