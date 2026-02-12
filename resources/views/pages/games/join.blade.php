<?php

use Livewire\Attributes\{On, Computed};
use Livewire\Component;
use App\Models\Game\{Game, Player, Card, Winner};
use App\Events\GameUpdated;
use Illuminate\Support\Facades\{Log, DB};

new class extends Component {
    // Propriedades Tipificadas para melhor performance e clareza
    public Game $game;
    public ?Player $player = null;
    public array $cards = [];
    public ?int $lastDrawnNumber = null;
    public array $recentDraws = [];
    public int $totalDraws = 0;

    /**
     * Inicialização do Componente
     */
    public function mount(string $invite_code)
    {
        $this->game = Game::where('invite_code', $invite_code)
            ->with(['creator:id,name', 'package:id,max_players'])
            ->firstOrFail();

        $this->player = $this->game
            ->players()
            ->where('user_id', auth()->id())
            ->first();

        $this->syncGameState();
    }

    /**
     * Hook de Hidratação: Executado em cada requisição Livewire.
     * Limpa o estado interno para garantir que os dados reflitam o banco.
     */
    public function hydrate(): void
    {
        $this->syncGameState();
    }

    /**
     * Ouvinte do Broadcast e Re-tentativa
     */
    #[On('echo:game.{game.uuid},.GameUpdated')]
    #[On('retry-load-cards')]
    public function handleUpdate(): void
    {
        $this->syncGameState();

        // Fallback para latência do SQLite: se o jogo está ativo mas as cartelas
        // da rodada atual ainda não aparecem, tenta novamente em 500ms.
        if ($this->player && empty($this->cards) && $this->game->status === 'active') {
            $this->dispatch('retry-load-cards');
        }
    }

    /**
     * MÉTODO CENTRAL DE SINCRONIZAÇÃO
     * Unifica a carga de dados para evitar vazamento de memória e inconsistência.
     */
    private function syncGameState(): void
    {
        // 1. Atualiza o Model principal e limpa relações em cache
        $this->game->refresh();
        $this->game->unsetRelations();

        // 2. Sincroniza o Player (caso tenha acabado de entrar)
        if (!$this->player) {
            $this->player = Player::where('game_id', $this->game->id)
                ->where('user_id', auth()->id())
                ->first();
        }

        // 3. Carrega Sorteios da Rodada Atual
        $draws = $this->game->draws()->where('round_number', $this->game->current_round)->orderByDesc('created_at')->get();

        $this->lastDrawnNumber = $draws->first()?->number;
        $this->recentDraws = $draws->take(10)->pluck('number')->toArray();
        $this->totalDraws = $draws->count();

        // 4. Carrega Cartelas (Direto do Banco para ignorar cache do Eloquent)
        if ($this->player) {
            $this->cards = Card::where('player_id', $this->player->id)->where('round_number', $this->game->current_round)->get()->all();
        }
    }

    /**
     * ENTRAR NA PARTIDA
     */
    public function join()
    {
        if ($this->player || !$this->game->canJoin()) {
            return;
        }

        // O erro de "5 cartelas" nasce aqui se houver um Observer ou Boot no model Player
        $this->player = Player::create([
            'game_id' => $this->game->id,
            'user_id' => auth()->id(),
            'joined_at' => now(),
        ]);

        broadcast(new GameUpdated($this->game))->toOthers();
        $this->syncGameState();
    }

    /**
     * MARCAR NÚMERO NA CARTELA
     */
    public function markNumber(int $cardIndex, int $number)
    {
        if (!$this->player || $this->game->status !== 'active') {
            return;
        }

        $card = $this->cards[$cardIndex] ?? null;
        if (!$card || in_array($number, $card->marked ?? [])) {
            return;
        }

        // Validação de Segurança: O número existe na cartela e foi sorteado?
        $drawnNumbers = $this->game->getCurrentRoundDrawnNumbers();

        if (!in_array($number, $card->numbers)) {
            $this->addError('game', 'Número não pertence a esta cartela.');
            return;
        }

        if (!in_array($number, $drawnNumbers)) {
            $this->addError('game', 'Este número ainda não foi sorteado.');
            return;
        }

        // Persiste a marcação
        $card->markNumber($number);

        // Verifica Bingo
        if ($card->checkBingo($drawnNumbers)) {
            $card->update(['is_bingo' => true]);
            broadcast(new GameUpdated($this->game))->toOthers();
            session()->flash('success', '🎉 BINGO! Aguarde a validação.');
        }

        $this->syncGameState();
    }

    /**
     * Vencedores da Rodada (Calculado apenas quando necessário)
     */
    #[Computed]
    public function cardWinners(): array
    {
        if (!$this->player || empty($this->cards)) {
            return [];
        }

        return Winner::whereIn('card_id', collect($this->cards)->pluck('id'))
            ->where('round_number', $this->game->current_round)
            ->pluck('card_id')
            ->toArray();
    }
};
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $game->name }}</h1>
                    <div class="flex items-center gap-4 text-sm text-gray-600 mt-2">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            {{ $game->creator->name }}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Rodada {{ $game->current_round }}/{{ $game->max_rounds }}
                        </span>
                    </div>
                </div>

                <div class="text-right">
                    <div
                        class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium
                        @if ($game->status === 'active') bg-green-100 text-green-800
                        @elseif($game->status === 'waiting') bg-yellow-100 text-yellow-800
                        @elseif($game->status === 'finished') bg-gray-100 text-gray-800
                        @else bg-blue-100 text-blue-800 @endif">
                        @if ($game->status === 'active')
                            <span class="w-2 h-2 bg-green-600 rounded-full mr-2 animate-pulse"></span>
                        @endif
                        {{ ucfirst($game->status) }}
                    </div>
                </div>
            </div>

            @if (session()->has('success'))
                <div
                    class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif
        </div>

        @if (!$player)
            <div class="max-w-lg mx-auto">
                <div class="bg-white rounded-2xl shadow-xl p-8 text-center border border-gray-100">
                    <div class="text-7xl mb-6">🎲</div>
                    <h2 class="text-3xl font-bold mb-4 text-gray-900">Entrar na Partida</h2>

                    <div class="space-y-4 mb-8">
                        <div class="flex items-center justify-center gap-3 text-gray-700">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-lg font-medium">{{ $game->package->cards_per_player ?? 1 }} cartela(s) de
                                {{ $game->card_size }} números</span>
                        </div>

                        <div class="flex items-center justify-center gap-3 text-gray-700">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span
                                class="text-lg font-medium">{{ $game->players->count() }}/{{ $game->package->max_players }}
                                jogadores</span>
                        </div>

                        @if ($game->prizes->count() > 0)
                            <div class="flex items-center justify-center gap-3 text-gray-700">
                                <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                <span class="text-lg font-medium">{{ $game->prizes->count() }} prêmio(s)</span>
                            </div>
                        @endif
                    </div>

                    @php
                        $maxPlayers = $game->package->max_players;
                        $currentPlayers = $game->players->count();
                        $isFull = $currentPlayers >= $maxPlayers;
                        $spotsLeft = $maxPlayers - $currentPlayers;
                    @endphp

                    @if ($isFull)
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center justify-center gap-2 text-red-700">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span class="font-semibold">Sala cheia! Limite de {{ $maxPlayers }} jogadores
                                    atingido.</span>
                            </div>
                        </div>
                        <button disabled
                            class="w-full bg-gray-300 text-gray-500 px-6 py-4 rounded-xl font-bold text-lg cursor-not-allowed">
                            Partida Completa
                        </button>
                    @elseif($spotsLeft <= 3 && $spotsLeft > 0)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center justify-center gap-2 text-yellow-700">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span class="font-semibold">Apenas {{ $spotsLeft }} vaga(s) restante(s)!</span>
                            </div>
                        </div>
                        <button wire:click="join"
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-6 py-4 rounded-xl transition font-bold text-lg shadow-lg">
                            Entrar Agora
                        </button>
                    @else
                        <button wire:click="join"
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-6 py-4 rounded-xl transition font-bold text-lg shadow-lg">
                            Entrar Agora
                        </button>
                    @endif
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold text-gray-900">Minhas Cartelas</h2>
                        <span class="text-sm text-gray-600">Rodada {{ $game->current_round }}</span>
                    </div>

                    {{-- Container Principal das Cartelas --}}
                    @if (empty($cards))
                        <div wire:key="waiting-round-{{ $game->current_round }}"
                            class="bg-yellow-50 border border-yellow-200 rounded-xl p-8 text-center">
                            <svg class="w-16 h-16 text-yellow-500 mx-auto mb-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="text-yellow-800 font-semibold">Aguardando cartelas da rodada
                                {{ $game->current_round }}</p>
                            <p class="text-xs text-yellow-600 mt-2">ID do Jogador: {{ $player->id }}</p>
                        </div>
                    @else
                        <div wire:key="cards-round-{{ $game->current_round }}"
                            class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach ($cards as $index => $card)
                                @php
                                    // Verificação de vencedor usando a propriedade computada
                                    $hasWon = in_array($card->id, $this->cardWinners);
                                @endphp
                                <div wire:key="card-{{ $card->id }}"
                                    class="bg-white rounded-2xl shadow-lg p-6 border-2 transition-all
                                    {{ $hasWon ? 'border-green-500 ring-4 ring-green-200' : 'border-gray-100' }}">

                                    @if ($hasWon)
                                        @php
                                            $myWin = \App\Models\Game\Winner::where('card_id', $card->id)->first();
                                        @endphp
                                        <div
                                            class="bg-gradient-to-r from-green-500 to-emerald-600 text-white text-center py-3 rounded-xl mb-4 shadow-lg">
                                            <div class="font-bold text-xl animate-bounce">🎉 BINGO! 🎉</div>
                                            @if ($myWin)
                                                <div class="text-xs font-medium opacity-90">Você ganhou o
                                                    {{ $myWin->prize->position }}º Lugar!</div>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="text-center mb-5">
                                        <span class="font-bold text-gray-700 text-lg">Cartela
                                            #{{ $index + 1 }}</span>
                                    </div>

                                    <div class="grid gap-2.5 mb-5"
                                        style="grid-template-columns: repeat({{ $game->card_size === 9 ? 3 : 5 }}, minmax(0, 1fr))">
                                        @foreach ($card->numbers as $number)
                                            @php
                                                $isMarked = in_array($number, $card->marked ?? []);
                                            @endphp
                                            <button wire:click="markNumber({{ $index }}, {{ $number }})"
                                                @if ($game->status !== 'active') disabled @endif
                                                class="aspect-square rounded-xl flex items-center justify-center font-bold text-xl transition-all duration-200 shadow-sm
                                                    {{ $isMarked ? 'bg-gradient-to-br from-blue-600 to-blue-700 text-white shadow-md scale-95' : 'bg-gray-100 text-gray-900' }}
                                                    {{ $game->status === 'active' && !$isMarked ? 'hover:bg-gray-200 active:bg-gray-300 cursor-pointer' : '' }}
                                                    {{ $game->status !== 'active' ? 'opacity-50 cursor-not-allowed' : '' }}">
                                                {{ $number }}
                                            </button>
                                        @endforeach
                                    </div>

                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">
                                            {{ count($card->marked ?? []) }} / {{ count($card->numbers) }} marcados
                                        </span>
                                        <div class="flex gap-1">
                                            @php
                                                $percentage =
                                                    count($card->numbers) > 0
                                                        ? count($card->marked ?? []) / count($card->numbers)
                                                        : 0;
                                            @endphp
                                            @for ($i = 0; $i < 5; $i++)
                                                <div
                                                    class="w-2 h-2 rounded-full {{ $i < $percentage * 5 ? 'bg-blue-600' : 'bg-gray-200' }}">
                                                </div>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="space-y-6">
                    <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                        <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Estatísticas
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-600 text-sm">Rodada atual</span>
                                <span
                                    class="font-bold text-gray-900">{{ $game->current_round }}/{{ $game->max_rounds }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-600 text-sm">Jogadores</span>
                                <span class="font-bold text-gray-900">{{ $game->players->count() }}</span>
                            </div>
                            @if ($game->status === 'active' && $game->show_drawn_to_players)
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                    <span class="text-gray-600 text-sm">Sorteados</span>
                                    <span class="font-bold text-gray-900">{{ $totalDraws }}/75</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                        <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            Prêmios
                        </h3>
                        <div class="space-y-3">
                            @forelse($game->prizes->sortBy('position') as $prize)
                                <div
                                    class="p-4 rounded-xl transition-all border-2
        {{ $prize->is_claimed ? 'bg-gradient-to-r from-green-50 to-emerald-50 border-green-200' : 'bg-gray-50 border-gray-100' }}
        {{ !$prize->is_claimed && $game->getNextAvailablePrize()?->id === $prize->id ? 'ring-2 ring-blue-500 shadow-md' : '' }}">

                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div
                                                class="font-bold {{ $prize->position == 1 ? 'text-indigo-900' : 'text-gray-900' }}">
                                                {{ $prize->position }}º - {{ $prize->name }}
                                            </div>

                                            @if ($prize->is_claimed)
                                                {{-- Busca o ganhador independente da rodada para o histórico --}}
                                                @php $winner = $prize->winner()->first(); @endphp
                                                <div
                                                    class="text-sm text-green-700 mt-2 font-semibold flex items-center gap-1">
                                                    <span>🏆</span>
                                                    <span>{{ $winner->user->name ?? 'N/A' }}</span>
                                                    <span class="text-[10px] text-green-600/70 font-normal">(Rd
                                                        {{ $winner->round_number }})</span>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($prize->is_claimed)
                                            <div class="text-green-600 text-xl font-bold">✓</div>
                                        @else
                                            <div
                                                class="px-2 py-1 {{ $game->getNextAvailablePrize()?->id === $prize->id ? 'bg-blue-600 text-white' : 'bg-yellow-100 text-yellow-700' }} text-[10px] rounded-full font-bold uppercase tracking-wider">
                                                {{ $game->getNextAvailablePrize()?->id === $prize->id ? 'Próximo' : 'Aguardando' }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
