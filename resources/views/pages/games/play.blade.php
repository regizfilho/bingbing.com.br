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

<div class="flex min-h-screen bg-gray-50">
    <aside class="w-80 bg-white border-r p-6 lg:sticky lg:top-0 lg:h-screen overflow-y-auto flex-shrink-0">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $game->name }}</h1>
                <div class="mt-3 flex items-center gap-3 flex-wrap">
                    <span
                        class="px-3 py-1 rounded-full text-xs font-medium
                        @if ($game->status === 'active') bg-green-100 text-green-800
                        @elseif($game->status === 'waiting') bg-yellow-100 text-yellow-800
                        @elseif($game->status === 'finished') bg-gray-100 text-gray-800
                        @else bg-blue-100 text-blue-800 @endif">
                        {{ ucfirst($game->status) }}
                    </span>
                    <span class="text-sm text-gray-600">
                        Rodada {{ $game->current_round }}/{{ $game->max_rounds }}
                    </span>
                </div>
            </div>

            @if ($isCreator)
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
                    <div class="text-xs text-blue-700 mb-1">Seu Saldo</div>
                    <div class="text-2xl font-bold text-blue-900">
                        {{ number_format($this->creatorBalance, 0) }} créditos
                    </div>
                    @if (!$game->package->is_free && $game->status !== 'finished')
                        <div class="mt-2 pt-2 border-t border-blue-200">
                            <div class="text-xs text-blue-600">
                                Custo desta partida: {{ number_format($this->packageCost, 0) }} créditos
                            </div>
                            @if ($this->willRefund)
                                <div class="text-xs text-green-600 mt-1">
                                    ✓ Será reembolsado ao finalizar (sem jogadores/vencedores)
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código de Convite</label>
                <div class="bg-gray-100 rounded-md px-4 py-3 font-mono font-semibold text-gray-800">
                    {{ $game->invite_code }}
                </div>
            </div>

            @if ($isCreator)
                <div class="pt-6 border-t space-y-4">
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false" type="button"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-lg font-medium flex items-center justify-center gap-2 transition shadow">
                            <x-icons.monitor />
                            Compartilhar Tela Pública
                        </button>

                        <div x-show="open" x-transition
                            class="absolute bottom-full left-0 mb-2 w-full bg-white rounded-lg shadow-xl border py-2 z-50">
                            <button
                                @click="window.open('https://wa.me/?text=' + encodeURIComponent('Participe do bingo {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}. Veja ao vivo: {{ route('games.display', $game) }}'), '_blank'); open = false"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 text-sm">
                                <x-icons.whatsapp color="#16a34a" />
                                WhatsApp
                            </button>
                            <button
                                @click="window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('Jogue bingo ao vivo em {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}') + '&url=' + encodeURIComponent('{{ route('games.display', $game) }}'), '_blank'); open = false"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 text-sm">
                                <x-icons.twitter color="#000000" />
                                Twitter/X
                            </button>
                            <button
                                @click="navigator.clipboard.writeText('{{ route('games.display', $game) }}').then(() => { open = false; })"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 text-sm">
                                <x-icons.copy color="#4b5563" />
                                Copiar Link
                            </button>
                        </div>
                    </div>

                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false" type="button"
                            class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-medium flex items-center justify-center gap-2 transition shadow">
                            <x-icons.user-add />
                            Compartilhar Convite
                        </button>

                        <div x-show="open" x-transition
                            class="absolute bottom-full left-0 mb-2 w-full bg-white rounded-lg shadow-xl border py-2 z-50">
                            <button
                                @click="window.open('https://wa.me/?text=' + encodeURIComponent('Entre no bingo {{ addslashes($game->name) }} usando o código {{ $game->invite_code }} ou clique aqui: {{ route('games.join', $game->invite_code) }}'), '_blank'); open = false"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 text-sm">
                                <x-icons.whatsapp color="#16a34a" />
                                WhatsApp
                            </button>
                            <button
                                @click="window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('Entre no bingo {{ addslashes($game->name) }}! Código: {{ $game->invite_code }}') + '&url=' + encodeURIComponent('{{ route('games.join', $game->invite_code) }}'), '_blank'); open = false"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 text-sm">
                                <x-icons.twitter color="#000000" />
                                Twitter/X
                            </button>
                            <button
                                @click="navigator.clipboard.writeText('{{ route('games.join', $game->invite_code) }}').then(() => { open = false; });"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 text-sm">
                                <x-icons.copy color="#4b5563" />
                                Copiar Link
                            </button>
                        </div>
                    </div>

                    <a href="{{ route('games.display', $game) }}" target="_blank"
                        class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-lg font-medium flex items-center justify-center gap-2 transition">
                        <x-icons.monitor />
                        Abrir Tela Pública
                    </a>

                    @if ($game->status === 'waiting')
                        <button wire:click="startGame"
                            class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-medium shadow">
                            Iniciar Partida
                        </button>
                    @endif

                    @if ($game->status === 'active' && $game->current_round < $game->max_rounds)
                        <button wire:click="startNextRound"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-medium shadow transition">
                            Próxima Rodada ({{ $game->current_round + 1 }}/{{ $game->max_rounds }})
                        </button>
                    @endif

                    @if ($game->status !== 'finish')
                        <button wire:click="finishGame"
                            wire:confirm="Esta é a última rodada. Deseja finalizar a partida agora?"
                            class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg font-medium shadow transition">
                            Finalizar Partida
                        </button>
                    @endif

                </div>
            @endif
        </div>
    </aside>

    <main class="flex-1 p-6 lg:p-8">
        @if (session()->has('success') || session()->has('info') || session()->has('error'))
            <div class="mb-6 space-y-3">
                @if (session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('info'))
                    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">
                        {{ session('info') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                        {{ session('error') }}
                    </div>
                @endif
            </div>
        @endif

        @if ($showNoPrizesWarning)
            <div class="mb-6 bg-orange-50 border-2 border-orange-300 rounded-xl p-6">
                <div class="flex items-start gap-4">
                    <div class="text-4xl">⚠️</div>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-orange-900 mb-2">Atenção: Sem Prêmios Disponíveis</h3>
                        <p class="text-orange-800 mb-4">
                            Todos os prêmios desta rodada já foram distribuídos, mas ainda há cartelas vencedoras.
                            Você pode cadastrar novos prêmios ou iniciar a próxima rodada.
                        </p>
                        <div class="flex gap-3">
                            <a href="{{ route('games.edit', $game) }}"
                                class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-medium text-sm">
                                Cadastrar Novos Prêmios
                            </a>
                            @if ($game->canStartNextRound())
                                <button wire:click="startNextRound"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium text-sm">
                                    Iniciar Próxima Rodada
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($game->status === 'active')
            {{-- A wire:key dinâmica é o segredo: se o tempo ou pausa mudar, o Livewire reseta o timer do zero --}}
            <section class="mb-10 bg-white rounded-xl shadow p-6 border"
                wire:key="control-panel-{{ $game->current_round }}-{{ $game->auto_draw_seconds }}-{{ $isPaused ? 'paused' : 'active' }}"
                @if ($game->draw_mode === 'automatic' && !$isPaused) wire:poll.visible.{{ $game->auto_draw_seconds }}s="autoDraw" @endif>

                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <x-icons.monitor class="w-5 h-5 text-blue-600" />
                        Painel de Controle
                    </h2>

                    {{-- Badge de Progresso --}}
                    <div class="flex items-center gap-2 bg-gray-100 px-3 py-1 rounded-full">
                        <div class="text-[10px] font-black uppercase text-gray-500">Progresso</div>
                        <div class="w-16 bg-gray-300 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-blue-600 h-full transition-all duration-500"
                                style="width: {{ ($drawnCount / 75) * 100 }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-blue-700">{{ round(($drawnCount / 75) * 100) }}%</span>
                    </div>
                </div>

                @if ($game->draw_mode === 'manual')
                    <button wire:click="drawNumber" wire:loading.attr="disabled"
                        class="relative w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-lg font-bold mb-6 shadow-lg transition-all active:scale-[0.98] disabled:opacity-70"
                        @if ($drawnCount >= 75) disabled @endif>

                        {{-- Spinner de Carregamento --}}
                        <div wire:loading wire:target="drawNumber" class="absolute left-6">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </div>

                        <span wire:loading.remove wire:target="drawNumber">Sortear Próximo Número</span>
                        <span wire:loading wire:target="drawNumber">Sorteando...</span>
                    </button>
                @else
                    {{-- MODO AUTOMÁTICO --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 mb-6">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-6">

                            {{-- Status e Timer --}}
                            <div class="flex items-center gap-4">
                                @if (!$isPaused)
                                    {{-- ... código do sinalizador verde ... --}}
                                    <div class="text-blue-900 font-black leading-none">MODO AUTOMÁTICO</div>
                                    <div class="text-xs text-blue-700 mt-1">Próxima bola em
                                        {{ $game->auto_draw_seconds }}s</div>
                                @else
                                    <span
                                        class="flex h-4 w-4 rounded-full bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]"></span>
                                    <div>
                                        <div class="text-red-900 font-black leading-none uppercase">Sorteio Pausado
                                        </div>
                                        @if ($this->winningCards->isNotEmpty())
                                            <div
                                                class="text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded mt-1 font-bold">
                                                ⚠️ BINGO DETECTADO: Verifique as cartelas
                                            </div>
                                        @else
                                            <div class="text-xs text-red-700 mt-1">O timer foi interrompido</div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Controles Rápidos --}}
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex items-center bg-white border border-blue-200 rounded-lg p-1.5 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 transition-all">
                                    <div class="px-2 border-r border-gray-100 flex flex-col">
                                        <span
                                            class="text-[9px] font-black text-gray-400 uppercase leading-none mb-1">Intervalo</span>
                                        <input type="number" wire:model="tempSeconds"
                                            class="w-10 border-none p-0 focus:ring-0 text-sm font-bold text-gray-700"
                                            min="2" max="60">
                                    </div>
                                    <button wire:click="updateDrawSpeed" wire:loading.attr="disabled"
                                        class="ml-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-md text-[10px] font-black transition active:scale-90">
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
                                    class="px-5 py-2.5 rounded-lg font-black text-xs uppercase tracking-wider transition shadow-md active:scale-95 {{ $isPaused ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-orange-500 hover:bg-orange-600 text-white' }}">
                                    {{ $isPaused ? '▶️ Retomar' : '⏸️ Pausar' }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Área do Número Sorteado --}}
                @if ($drawnCount > 0)
                    <div
                        class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 text-white rounded-2xl p-10 mb-8 text-center shadow-xl relative overflow-hidden group">
                        {{-- Círculos decorativos de fundo --}}
                        <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
                        <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-indigo-500/20 rounded-full blur-3xl">
                        </div>

                        <div class="relative z-10">
                            <div class="text-xs opacity-70 mb-3 font-bold uppercase tracking-[0.3em]">Última Bola</div>
                            <div
                                class="text-8xl font-black drop-shadow-2xl transition-transform duration-500 group-hover:scale-110">
                                {{ $lastDrawnNumber }}
                            </div>
                            <div
                                class="mt-4 inline-block px-4 py-1 bg-black/20 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/10">
                                Bola de número #{{ $drawnCount }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-end mb-4 px-1">
                            <div>
                                <div class="text-xs font-black text-gray-400 uppercase tracking-tighter">Histórico
                                </div>
                                <div class="text-lg font-bold text-gray-700">Globo Virtual</div>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] font-black text-gray-400 uppercase">Restantes</div>
                                <div class="text-sm font-bold text-blue-600">{{ 75 - $drawnCount }} bolas</div>
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-10 sm:grid-cols-15 gap-1.5 p-4 bg-gray-50 rounded-2xl border border-gray-100 shadow-inner max-h-56 overflow-y-auto custom-scrollbar">
                            @foreach (range(1, 75) as $num)
                                <div
                                    class="aspect-square rounded-lg flex items-center justify-center text-[11px] font-black transition-all duration-300
                            {{ in_array($num, $drawnNumbersList)
                                ? 'bg-blue-600 text-white shadow-[0_4px_10px_rgba(37,99,235,0.4)] scale-105 border-transparent'
                                : 'bg-white border border-gray-200 text-gray-300' }}">
                                    {{ $num }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    {{-- Estado Vazio --}}
                    <div class="text-center py-10 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                        <div class="text-4xl mb-3">🔮</div>
                        <div class="text-sm font-bold text-gray-400 uppercase">Aguardando o primeiro sorteio</div>
                    </div>
                @endif
            </section>

        @endif

        <style>
            /* Scrollbar personalizada para o histórico */
            .custom-scrollbar::-webkit-scrollbar {
                width: 4px;
            }

            .custom-scrollbar::-webkit-scrollbar-track {
                background: transparent;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: #e2e8f0;
                border-radius: 10px;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #cbd5e1;
            }
        </style>

        @if ($this->winningCards->count() > 0)
            <section class="mb-10 bg-yellow-50 border-2 border-yellow-400 rounded-2xl p-6 shadow-xl animate-bounce-in">
                <h2 class="text-2xl font-black text-yellow-900 mb-6 flex items-center gap-3">
                    <span class="text-3xl">🎉</span> BINGO DETECTADO!
                </h2>

                @php
                    // Pega o próximo prêmio disponível da lista (usando o método do componente ou do model)
                    $nextPrize = $this->nextAvailablePrize;
                @endphp

                <div class="grid gap-4">
                    @foreach ($this->winningCards as $card)
                        <div
                            class="bg-white p-5 rounded-xl border border-yellow-300 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
                            <div class="flex items-center gap-4">
                                <div
                                    class="bg-yellow-400 text-yellow-900 w-12 h-12 rounded-full flex items-center justify-center font-black text-xl shadow-inner">
                                    {{ substr($card->player->user->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-black text-lg text-gray-900 leading-none">
                                        {{ $card->player->user->name }}
                                    </div>
                                    <div class="text-xs text-yellow-700 font-bold mt-1 uppercase tracking-tighter">
                                        Cartela #{{ substr($card->uuid, 0, 8) }}
                                    </div>
                                </div>
                            </div>

                            <div class="w-full md:w-auto">
                                @if ($nextPrize)
                                    {{-- BOTÃO PARA PRÊMIO REAL --}}
                                    <button wire:click="claimPrize('{{ $card->uuid }}', '{{ $nextPrize->uuid }}')"
                                        class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-black transition shadow-lg active:scale-95 flex items-center justify-center gap-2">
                                        <span>🎁</span> Conceder: {{ $nextPrize->name }}
                                    </button>
                                @else
                                    {{-- BOTÃO PARA RANKING DE HONRA (Quando não há prêmios) --}}
                                    <button wire:click="claimPrize('{{ $card->uuid }}', null)"
                                        class="w-full md:w-auto bg-slate-700 hover:bg-slate-800 text-white px-8 py-3 rounded-lg font-black transition shadow-lg active:scale-95 flex items-center justify-center gap-2">
                                        <span>🏅</span> Registrar Mérito (Ranking)
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (!$nextPrize)
                    <div class="mt-6 p-4 bg-white/50 border border-yellow-200 rounded-lg">
                        <p class="text-yellow-800 text-sm font-medium flex items-center gap-2">
                            <strong>Aviso:</strong> Todos os prêmios físicos desta rodada foram entregues.
                            Novos vencedores entrarão para o <b>Ranking de Honra</b> da rodada.
                        </p>
                    </div>
                @endif
            </section>
        @endif

        <section class="mb-10 bg-white rounded-xl shadow p-6 border">
            <h2 class="text-xl font-semibold mb-6">Gerenciar Prêmios</h2>

            @if ($this->nextAvailablePrize)
                <div class="mb-6 bg-blue-50 border-2 border-blue-300 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <div class="text-3xl">🎯</div>
                        <div>
                            <div class="font-semibold text-blue-900">Próximo Prêmio a Distribuir:</div>
                            <div class="text-lg font-bold text-blue-700">
                                {{ $this->nextAvailablePrize->position }}º - {{ $this->nextAvailablePrize->name }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                @foreach ($game->prizes->sortBy('position') as $prize)
                    <div wire:key="prize-{{ $prize->id }}"
                        class="border rounded-lg p-5 {{ $prize->is_claimed ? 'bg-green-50 border-green-200' : 'bg-gray-50' }} 
        {{ !$prize->is_claimed && $this->nextAvailablePrize?->id === $prize->id ? 'ring-2 ring-blue-500 shadow-md' : '' }}">

                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    @if ($prize->position == 1)
                                        <span class="text-xl">🥇</span>
                                    @endif
                                    <div class="font-bold text-gray-900">{{ $prize->position }}º -
                                        {{ $prize->name }}</div>
                                </div>

                                @if ($prize->is_claimed)
                                    @php $winner = $prize->winner()->where('game_id', $game->id)->first(); @endphp
                                    <div class="mt-2 p-2 bg-white rounded border border-green-100">
                                        <span
                                            class="text-xs font-bold text-green-700 uppercase tracking-wider">Ganhador:</span>
                                        <div class="text-sm font-semibold text-gray-800">
                                            {{ $winner->user->name ?? 'N/A' }}</div>
                                        <div class="text-[10px] text-gray-500">Rodada {{ $winner->round_number }}
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <span
                                class="px-2 py-1 rounded-full text-[10px] font-bold uppercase {{ $prize->is_claimed ? 'bg-green-600 text-white' : 'bg-blue-100 text-blue-700' }}">
                                {{ $prize->is_claimed ? 'Concedido' : 'Disponível' }}
                            </span>
                        </div>

                        @php
                            $roundWinner = $prize->winner()->where('round_number', $game->current_round)->first();
                        @endphp

                        @if ($roundWinner)
                            <div class="mt-4 pt-4 border-t bg-green-100 -m-5 p-5 rounded-b-lg">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-green-700">
                                            {{ $roundWinner->user->name }}
                                        </div>
                                        <div class="text-xs text-gray-600">
                                            {{ $roundWinner->won_at->format('d/m/Y H:i') }}
                                        </div>
                                    </div>
                                    @if ($game->status === 'active')
                                        <button wire:click="removePrize({{ $roundWinner->id }})"
                                            wire:confirm="Tem certeza que deseja remover este prêmio de {{ $roundWinner->user->name }}?"
                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs font-medium transition">
                                            Remover
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if (!$prize->is_claimed && $this->winningCards->count() > 0 && $game->status === 'active')
                            <div class="mt-4 pt-4 border-t">
                                <div class="text-sm font-medium text-gray-700 mb-3">Conceder a:</div>
                                <div class="grid gap-3">
                                    @foreach ($this->winningCards as $card)
                                        <button {{-- O wire:key impede que o Livewire trave o botão após o primeiro clique --}}
                                            wire:key="claim-{{ $prize->uuid }}-{{ $card->uuid }}"
                                            wire:click="claimPrize('{{ $card->uuid }}', '{{ $prize->uuid }}')"
                                            class="bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-medium text-sm disabled:opacity-50"
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

        <section class="bg-white rounded-xl shadow p-6 border">
            <h2 class="text-xl font-semibold mb-6">Jogadores ({{ $game->players->count() }})</h2>

            @if ($game->players->isEmpty())
                <div class="text-center py-12 text-gray-500">
                    Nenhum jogador entrou ainda.
                </div>
            @else
                <div class="space-y-4 max-h-[60vh] overflow-y-auto">
                    @foreach ($game->players as $player)
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center text-xl font-bold">
                                    {{ substr($player->user->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-medium">{{ $player->user->name }}</div>
                                    <div class="text-sm text-gray-600">
                                        {{ $player->cards()->where('round_number', $game->current_round)->count() }}
                                        cartela(s)
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
                                        {{-- Vencedor com Prêmio Real --}}
                                        <span class="block text-sm font-bold text-yellow-600">
                                            🏆 {{ $roundWin->prize->position }}º Lugar
                                        </span>
                                        <span class="block text-xs text-gray-500">
                                            {{ $roundWin->prize->name }}
                                        </span>
                                    @else
                                        {{-- Vencedor de Honra (Sem prêmio) --}}
                                        <span class="block text-sm font-bold text-slate-500">
                                            ✨ Bingo de Honra
                                        </span>
                                        <span class="block text-[10px] text-gray-400 uppercase font-black">
                                            Mérito Acadêmico
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
        <section
            class="mt-8 bg-gradient-to-br from-slate-900 to-indigo-950 rounded-2xl p-6 shadow-2xl border border-indigo-500/30">
            <div class="flex items-center justify-between mb-6">
                <div class="flex flex-col">
                    <h3 class="text-white font-black uppercase tracking-widest flex items-center gap-2">
                        <span class="animate-pulse text-yellow-400">🏆</span> Hall da Fama Geral
                    </h3>
                    <span class="text-[9px] text-indigo-300 font-bold uppercase tracking-tighter">Ranking acumulado de
                        todas as rodadas</span>
                </div>
                {{-- Contador total de vencedores do jogo --}}
                <span
                    class="text-[10px] bg-indigo-500 text-white px-3 py-1 rounded-full font-black uppercase shadow-lg">
                    {{ $game->winners()->count() }} Ganhadores Totais
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Removido o filtro de round_number para pegar TODOS os ganhadores do game --}}
                @foreach ($game->winners()->with(['user', 'prize'])->orderBy('won_at', 'asc')->get() as $index => $winner)
                    <div
                        class="flex items-center gap-3 bg-white/5 backdrop-blur-sm border border-white/10 p-3 rounded-xl hover:bg-white/10 transition-all group">

                        {{-- Posição Geral --}}
                        <div class="relative">
                            <div
                                class="w-10 h-10 rounded-full flex items-center justify-center font-black text-sm {{ $winner->prize_id ? 'bg-yellow-500 text-yellow-950 shadow-[0_0_15px_rgba(234,179,8,0.3)]' : 'bg-slate-700 text-slate-300' }}">
                                {{ $index + 1 }}º
                            </div>
                            {{-- Badge da Rodada --}}
                            <div
                                class="absolute -top-1 -right-1 bg-indigo-600 text-white text-[8px] px-1 rounded font-bold border border-slate-900">
                                R{{ $winner->round_number }}
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div
                                class="text-sm font-bold text-white truncate group-hover:text-yellow-400 transition-colors">
                                {{ $winner->user->name }}
                            </div>
                            <div
                                class="text-[9px] font-black uppercase tracking-tighter {{ $winner->prize_id ? 'text-yellow-500' : 'text-indigo-400' }}">
                                {{ $winner->prize ? $winner->prize->name : 'Bingo de Honra ✨' }}
                            </div>
                        </div>

                        {{-- Horário --}}
                        <div class="text-[8px] font-mono text-white/20 group-hover:text-white/50">
                            {{ $winner->won_at->format('H:i') }}
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($game->winners()->count() === 0)
                <div class="text-center py-10">
                    <div class="text-white/20 text-4xl mb-2">⭐</div>
                    <p class="text-white/40 text-xs font-bold uppercase tracking-widest">Aguardando o primeiro herói
                        desta partida...</p>
                </div>
            @endif
        </section>
    </main>
</div>

<style>
    .animate-fade {
        animation: fadeInOut 2.5s ease-in-out forwards;
    }

    @keyframes fadeInOut {
        0% {
            opacity: 0;
            transform: translateY(20px)
        }

        10% {
            opacity: 1;
            transform: translateY(0)
        }

        90% {
            opacity: 1;
            transform: translateY(0)
        }

        100% {
            opacity: 0;
            transform: translateY(-20px)
        }
    }
</style>
