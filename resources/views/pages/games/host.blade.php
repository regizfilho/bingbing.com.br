<?php

use Livewire\Component;
use App\Models\Game\Game;
use App\Models\Game\Winner;
use Illuminate\Support\Collection;

new class extends Component {
    public $game;
    public $user;
    public bool $isCreator = false;
    public Collection $winningCards;

    protected $listeners = ['refreshGame' => '$refresh'];

    public function mount($game)
    {
        $this->user = auth()->user();

        $this->game = Game::where('uuid', $game)
            ->with(['creator', 'package', 'prizes', 'players.user', 'players.cards', 'draws', 'winners.user'])
            ->firstOrFail();

        $this->isCreator = $this->game->creator_id === $this->user->id;

        if (!$this->isCreator && !$this->game->players()->where('user_id', $this->user->id)->exists()) {
            abort(403, 'Acesso negado.');
        }

        $this->winningCards = collect();
        $this->checkWinningCards();
    }

    public function startGame()
    {
        if (!$this->isCreator || $this->game->status !== 'waiting') {
            return;
        }

        if ($this->game->players()->count() === 0) {
            session()->flash('error', 'Aguarde pelo menos um jogador entrar na partida.');
            return;
        }

        $this->game->update([
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->game->refresh();
        session()->flash('success', 'Partida iniciada com sucesso!');
    }

    public function drawNumber()
    {
        if (!$this->isCreator || $this->game->status !== 'active' || $this->game->draw_mode !== 'manual') {
            return;
        }

        $draw = $this->game->drawNumber();

        if (!$draw) {
            session()->flash('error', 'Todos os números já foram sorteados.');
            return;
        }

        $this->game->refresh();
        $this->checkWinningCards();
        $this->dispatch('numberDrawn', number: $draw->number);
    }

    public function checkWinningCards()
    {
        if ($this->game->status !== 'active') {
            return;
        }

        $this->winningCards = $this->game->checkWinningCards() ?? collect();
    }

    public function claimPrize($cardUuid, $prizeUuid)
    {
        if (!$this->isCreator) {
            return;
        }

        $card = $this->game->players
            ->flatMap->cards
            ->firstWhere('uuid', $cardUuid);

        $prize = $this->game->prizes()->where('uuid', $prizeUuid)->first();

        if (!$card || !$prize) {
            session()->flash('error', 'Prêmio ou cartela inválidos.');
            return;
        }

        // Verificação reforçada para evitar duplicatas (causa do erro UNIQUE)
        if ($prize->is_claimed || Winner::where('prize_id', $prize->id)->exists()) {
            session()->flash('error', 'Este prêmio já foi concedido anteriormente.');
            // Corrige inconsistência se o campo is_claimed estiver desatualizado
            $prize->update(['is_claimed' => true]);
            $this->game->refresh();
            $this->checkWinningCards();
            return;
        }

        // Cria o registro de vencedor
        Winner::create([
            'game_id' => $this->game->id,
            'prize_id' => $prize->id,
            'card_id' => $card->id,
            'user_id' => $card->player->user_id,
            'won_at' => now(),
        ]);

        // Marca o prêmio como concedido (depois de criar, evita race condition)
        $prize->update(['is_claimed' => true]);

        $this->game->refresh();
        $this->checkWinningCards();

        session()->flash('success', "Prêmio '{$prize->name}' atribuído a {$card->player->user->name}!");

        if ($this->game->prizes()->where('is_claimed', false)->count() === 0) {
            $this->finishGame();
        }
    }

    public function finishGame()
    {
        if (!$this->isCreator || $this->game->status !== 'active') {
            return;
        }

        $this->game->update([
            'status' => 'finished',
            'finished_at' => now(),
        ]);

        $this->game->refresh();
        session()->flash('success', 'Partida finalizada com sucesso!');
    }


};

?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12" wire:poll.3s="checkWinningCards">
    <div class="mb-8">
        <div class="flex justify-between items-start mb-4 flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $game->name }}</h1>
                <p class="text-gray-600">Código de convite: <span class="font-mono font-semibold">{{ $game->invite_code }}</span></p>
            </div>
            <div class="flex items-center gap-3">
                <span class="px-4 py-2 text-sm rounded-full font-medium
                    @if($game->status === 'active') bg-green-100 text-green-800
                    @elseif($game->status === 'waiting') bg-yellow-100 text-yellow-800
                    @elseif($game->status === 'finished') bg-gray-100 text-gray-800
                    @else bg-blue-100 text-blue-800
                    @endif">
                    {{ ucfirst($game->status) }}
                </span>

                @if($isCreator && $game->status === 'waiting')
                    <button wire:click="startGame" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition font-semibold shadow-sm">
                        Iniciar Partida
                    </button>
                @endif

                @if($isCreator && $game->status === 'active')
                    <button wire:click="finishGame" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg transition font-semibold shadow-sm">
                        Finalizar Partida
                    </button>
                @endif
            </div>
        </div>

        @if (session()->has('success'))
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                {{ session('error') }}
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Área principal: Sorteio e Prêmios -->
        <div class="lg:col-span-2 space-y-6">
            @if($game->status === 'active')
                <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                    <h2 class="text-xl font-semibold mb-4">Sorteio de Números</h2>

                    @if($isCreator && $game->draw_mode === 'manual')
                        <button wire:click="drawNumber" wire:loading.attr="disabled" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-5 rounded-xl transition font-semibold text-lg mb-6 shadow-md disabled:opacity-50" @if($game->draws->count() >= 75) disabled @endif>
                            Sortear Próximo Número
                        </button>
                    @endif

                    @if($game->draws->count() > 0)
                        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl p-10 mb-6 text-center shadow-lg">
                            <div class="text-sm opacity-90 mb-3">Último número sorteado</div>
                            <div class="text-7xl font-extrabold">{{ $game->draws->last()->number }}</div>
                        </div>

                        <div class="mb-6">
                            <div class="text-sm font-medium text-gray-700 mb-3">Últimos sorteados</div>
                            <div class="flex gap-3 flex-wrap">
                                @foreach($game->draws->reverse()->take(10) as $draw)
                                    <div class="w-14 h-14 bg-blue-100 text-blue-900 rounded-full flex items-center justify-center font-bold text-lg shadow-sm">
                                        {{ $draw->number }}
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <div class="text-sm font-medium text-gray-700 mb-3">Números sorteados ({{ $game->draws->count() }}/75)</div>
                            <div class="grid grid-cols-10 sm:grid-cols-15 gap-1.5 max-h-48 overflow-y-auto p-2 bg-gray-50 rounded-lg">
                                @foreach(range(1, 75) as $num)
                                    <div class="w-10 h-10 rounded flex items-center justify-center text-xs font-medium
                                        {{ $game->draws->pluck('number')->contains($num) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400' }}">
                                        {{ $num }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-center py-16 text-gray-500">
                            <div class="text-xl font-medium mb-2">Nenhum número sorteado ainda</div>
                            <p class="text-sm">Clique em "Sortear Próximo Número" para começar</p>
                        </div>
                    @endif
                </div>
            @endif

            @if($isCreator && $winningCards->count() > 0)
                <div class="bg-yellow-50 border-2 border-yellow-400 rounded-xl p-6 shadow-md">
                    <h2 class="text-xl font-semibold text-yellow-900 mb-4">⚠️ Cartelas com BINGO detectadas!</h2>
                    <div class="space-y-4">
                        @foreach($winningCards as $card)
                            <div class="bg-white rounded-lg p-5 border border-yellow-300">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="font-semibold text-lg">Cartela #{{ substr($card->uuid, 0, 8) }}...</div>
                                        <div class="text-sm text-gray-600">Jogador: {{ $card->player->user->name }}</div>
                                    </div>
                                    <div class="text-3xl">🎉</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                <h2 class="text-xl font-semibold mb-4">Prêmios da Partida</h2>
                <div class="space-y-4">
                    @foreach($game->prizes as $prize)
                        <div class="border rounded-lg p-5 {{ $prize->is_claimed ? 'bg-green-50 border-green-200' : 'bg-gray-50' }}">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1">
                                    <div class="font-semibold text-lg">{{ $prize->position }}º - {{ $prize->name }}</div>
                                    @if($prize->description)
                                        <div class="text-sm text-gray-600 mt-1">{{ $prize->description }}</div>
                                    @endif
                                </div>
                                @if($prize->is_claimed)
                                    <span class="px-4 py-1 bg-green-600 text-white text-sm rounded-full font-medium">Concedido</span>
                                @else
                                    <span class="px-4 py-1 bg-gray-300 text-gray-700 text-sm rounded-full font-medium">Disponível</span>
                                @endif
                            </div>

                            @if($prize->winner)
                                <div class="mt-4 pt-4 border-t">
                                    <div class="flex items-center gap-3">
                                        <span class="text-3xl">🏆</span>
                                        <div>
                                            <div class="font-semibold text-green-700">{{ $prize->winner->user->name }}</div>
                                            <div class="text-xs text-gray-600">{{ $prize->winner->won_at->format('d/m/Y H:i') }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($isCreator && !$prize->is_claimed && $winningCards->count() > 0 && $game->status === 'active')
                                <div class="mt-4 pt-4 border-t">
                                    <div class="text-sm font-medium text-gray-700 mb-3">Atribuir este prêmio a:</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @foreach($winningCards as $card)
                                            <button
                                                wire:click="claimPrize('{{ $card->uuid }}', '{{ $prize->uuid }}')"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="opacity-50 cursor-wait"
                                                class="bg-green-600 hover:bg-green-700 text-white px-5 py-3 rounded-lg transition font-medium text-sm shadow-sm">
                                                Cartela #{{ substr($card->uuid, 0, 8) }} ({{ $card->player->user->name }})
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Sidebar: Jogadores e Vencedores -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                <h2 class="text-xl font-semibold mb-4">Jogadores ({{ $game->players->count() }})</h2>

                @if($game->status === 'waiting')
                    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-5">
                        <div class="text-sm font-medium text-blue-900 mb-3">Compartilhe este link:</div>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                value="{{ route('games.join', $game->invite_code) }}"
                                readonly
                                class="flex-1 px-4 py-3 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                onclick="this.select()">
                            <button onclick="navigator.clipboard.writeText(this.previousElementSibling.value)" class="bg-gray-200 hover:bg-gray-300 px-4 py-3 rounded-lg transition">
                                Copiar
                            </button>
                        </div>
                    </div>
                @endif

                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @forelse($game->players as $player)
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center text-lg font-semibold">
                                    {{ substr($player->user->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-medium">{{ $player->user->name }}</div>
                                    <div class="text-sm text-gray-600">{{ $player->cards->count() }} cartela(s)</div>
                                </div>
                            </div>
                            @if($player->user->wins()->where('game_id', $game->id)->count() > 0)
                                <span class="text-2xl">🏆</span>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-10 text-gray-500">
                            Nenhum jogador entrou ainda
                        </div>
                    @endforelse
                </div>
            </div>

            @if($game->winners->count() > 0)
                <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                    <h2 class="text-xl font-semibold mb-4">🏆 Vencedores</h2>
                    <div class="space-y-3">
                        @foreach($game->winners->groupBy('user_id') as $wins)
                            <div class="flex items-center justify-between p-4 bg-yellow-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-yellow-600 text-white rounded-full flex items-center justify-center text-lg font-semibold">
                                        {{ substr($wins->first()->user->name, 0, 1) }}
                                    </div>
                                    <div class="font-medium">{{ $wins->first()->user->name }}</div>
                                </div>
                                <div class="text-lg font-semibold text-yellow-700">{{ $wins->count() }}x</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>