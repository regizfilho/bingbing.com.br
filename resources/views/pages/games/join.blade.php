<?php

use Livewire\Component;
use App\Models\Game\Game;
use App\Models\Game\Player;

new class extends Component {
    public $game;
    public $user;
    public $player;
    public $cards = [];

    public function mount(string $invite_code)
    {
        $this->user = auth()->user();

        $this->game = Game::where('invite_code', $invite_code)
            ->with(['creator', 'package', 'prizes.winner.user', 'draws', 'players'])
            ->firstOrFail();

        $this->player = $this->game->players()
            ->where('user_id', $this->user->id)
            ->first();

        if ($this->player) {
            $this->cards = $this->player->cards()->get();
        }
    }

    public function join()
    {
        if ($this->player) {
            session()->flash('error', 'Você já está participando desta partida');
            return;
        }

        if (!$this->game->canJoin()) {
            session()->flash('error', 'Esta partida não está aceitando novos jogadores');
            return;
        }

        $this->player = Player::create([
            'game_id'   => $this->game->id,
            'user_id'   => $this->user->id,
            'joined_at' => now(),
        ]);

        $this->cards = $this->player->cards()->get();

        session()->flash('success', 'Você entrou na partida!');
    }

    public function markNumber(int $cardIndex, int $number)
    {
        if (!$this->player || $this->game->status !== 'active') {
            return;
        }

        $card = $this->cards[$cardIndex];
        $card->markNumber($number);
        $card->refresh();

        $this->cards = $this->player->cards()->get();

        if ($card->checkBingo($this->game->draws->pluck('number')->toArray())) {
            session()->flash('success', '🎉 BINGO! Avise ao organizador!');
        }
    }
};
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12" wire:poll.3s>
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $game->name }}</h1>
        <p class="text-gray-600">Por: {{ $game->creator->name }}</p>

        @if (session()->has('success'))
            <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                {{ session('error') }}
            </div>
        @endif
    </div>

    @if(!$player)
        <!-- Join Screen -->
        <div class="bg-white rounded-lg shadow p-8 text-center max-w-md mx-auto">
            <div class="text-6xl mb-4">🎲</div>
            <h2 class="text-2xl font-bold mb-4">Entrar na Partida</h2>
            <p class="text-gray-600 mb-6">
                Você receberá {{ $game->package->max_cards_per_player }} cartela(s) aleatória(s) ao entrar
            </p>
            <button
                wire:click="join"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition font-semibold"
            >
                Entrar Agora
            </button>
        </div>
    @else
        <!-- Game Screen -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cartelas -->
            <div class="lg:col-span-2">
                <h2 class="text-2xl font-semibold mb-4">Minhas Cartelas</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($cards as $index => $card)
                        <div class="bg-white rounded-lg shadow p-6 {{ $card->is_bingo ? 'ring-4 ring-green-500' : '' }}">
                            @if($card->is_bingo)
                                <div class="bg-green-600 text-white text-center py-2 rounded-lg mb-4 font-bold text-lg">
                                    🎉 BINGO! 🎉
                                </div>
                            @endif

                            <div class="text-center mb-4 font-bold text-gray-700">
                                Cartela #{{ $index + 1 }}
                            </div>

                            <div class="grid grid-cols-5 gap-2">
                                @foreach($card->numbers as $number)
                                    <button
                                        wire:click="markNumber({{ $index }}, {{ $number }})"
                                        @if($game->status !== 'active') disabled @endif
                                        class="aspect-square rounded-lg flex items-center justify-center font-bold text-lg transition
                                            {{ in_array($number, $card->marked ?? []) ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' }}
                                            {{ $game->draws->pluck('number')->contains($number) && !in_array($number, $card->marked ?? []) ? 'ring-2 ring-yellow-400' : '' }}"
                                    >
                                        {{ $number }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="mt-4 text-sm text-gray-600 text-center">
                                {{ count($card->marked ?? []) }}/{{ count($card->numbers) }} marcados
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Status -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold mb-4">Status da Partida</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Status:</span>
                            <span
                                class="px-3 py-1 text-sm rounded-full
                                    @if($game->status === 'active') bg-green-100 text-green-800
                                    @elseif($game->status === 'waiting') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif"
                            >
                                {{ ucfirst($game->status) }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Jogadores:</span>
                            <span class="font-semibold">{{ $game->players->count() }}</span>
                        </div>

                        @if($game->status === 'active')
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Números:</span>
                                <span class="font-semibold">{{ $game->draws->count() }}/75</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Último Número -->
                @if($game->status === 'active' && $game->draws->count() > 0)
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg shadow p-6">
                        <div class="text-sm opacity-90 mb-2">Último sorteado</div>
                        <div class="text-5xl font-bold text-center">
                            {{ $game->draws->last()->number }}
                        </div>
                    </div>

                    <!-- Últimos 10 -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="font-semibold mb-3">Últimos sorteados</h3>
                        <div class="grid grid-cols-5 gap-2">
                            @foreach($game->draws->reverse()->take(10) as $draw)
                                <div class="aspect-square bg-blue-100 text-blue-900 rounded-lg flex items-center justify-center font-bold text-sm">
                                    {{ $draw->number }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Prêmios -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold mb-4">Prêmios</h3>
                    <div class="space-y-2">
                        @foreach($game->prizes as $prize)
                            <div class="p-3 rounded-lg {{ $prize->is_claimed ? 'bg-green-50' : 'bg-gray-50' }}">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="font-medium text-sm">
                                            {{ $prize->position }}º - {{ $prize->name }}
                                        </div>
                                        @if($prize->winner)
                                            <div class="text-xs text-green-700 mt-1">
                                                🏆 {{ $prize->winner->user->name }}
                                            </div>
                                        @endif
                                    </div>
                                    @if($prize->is_claimed)
                                        <span class="text-green-600">✓</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
