<?php

use Livewire\Component;
use App\Models\Game\Game;
use App\Models\Game\Player;

new class extends Component {
    public $game;
    public $user;
    public $player;
    public array $cards = [];

    public function mount($game) // Recebe o UUID do jogo
    {
        $this->user = auth()->user();

        $this->game = Game::where('uuid', $game)
            ->with(['creator', 'package', 'prizes', 'draws', 'players'])
            ->firstOrFail();

        $this->player = $this->game->players()
            ->where('user_id', $this->user->id)
            ->first();

        if (!$this->player) {
            session()->flash('error', 'Você precisa entrar na partida primeiro.');
            $this->redirectRoute('games.join', $this->game->invite_code, navigate: true);
            return;
        }

        $this->cards = $this->player->cards()->get()->all();
    }

    public function markNumber(int $cardIndex, int $number)
    {
        if (!$this->player || $this->game->status !== 'active') {
            return;
        }

        $card = $this->cards[$cardIndex] ?? null;
        if (!$card || $card->player_id !== $this->player->id) {
            return;
        }

        $card->markNumber($number);
        $card->refresh();

        $this->cards = $this->player->cards()->get()->all();

        if ($card->checkBingo($this->game->draws->pluck('number')->toArray())) {
            session()->flash('success', '🎉 BINGO! Avise o organizador da partida!');
        }
    }

    
};

?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12" wire:poll.3s>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $game->name }}</h1>
        <p class="text-gray-600">Organizador: {{ $game->creator->name ?? '—' }}</p>

        @if (session()->has('success'))
            <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                {{ session('error') }}
            </div>
        @endif
    </div>

    <!-- Área principal: Cartelas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <h2 class="text-2xl font-semibold mb-6">Minhas Cartelas</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @forelse($cards as $index => $card)
                    <div class="bg-white rounded-xl shadow-md p-6 {{ $card->is_bingo ? 'ring-4 ring-green-500 ring-offset-2' : '' }}">
                        @if($card->is_bingo)
                            <div class="bg-green-600 text-white text-center py-3 rounded-lg mb-4 font-bold text-xl">
                                🎉 BINGO! 🎉
                            </div>
                        @endif

                        <div class="text-center mb-5 font-bold text-gray-700 text-lg">
                            Cartela #{{ $index + 1 }}
                        </div>

                        <div class="grid grid-cols-5 gap-2.5">
                            @foreach($card->numbers as $number)
                                <button
                                    wire:click="markNumber({{ $index }}, {{ $number }})"
                                    @if($game->status !== 'active') disabled @endif
                                    class="aspect-square rounded-xl flex items-center justify-center font-bold text-xl transition-all duration-200
                                        {{ in_array($number, $card->marked ?? []) 
                                            ? 'bg-blue-600 text-white shadow-inner' 
                                            : 'bg-gray-100 hover:bg-gray-200 active:bg-gray-300' }}
                                        {{ $game->draws->pluck('number')->contains($number) && !in_array($number, $card->marked ?? []) 
                                            ? 'ring-4 ring-yellow-400 ring-offset-2 animate-pulse' 
                                            : '' }}">
                                    {{ $number }}
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-5 text-center text-sm text-gray-600">
                            {{ count($card->marked ?? []) }} / {{ count($card->numbers) }} números marcados
                        </div>
                    </div>
                @empty
                    <div class="col-span-2 text-center py-12 text-gray-500">
                        Nenhuma cartela carregada. Tente atualizar a página.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Sidebar: Informações da partida -->
        <div class="space-y-6">
            <!-- Status da Partida -->
            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                <h3 class="font-semibold text-lg mb-4">Status da Partida</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Status:</span>
                        <span class="px-4 py-1 text-sm font-medium rounded-full
                            @if($game->status === 'active') bg-green-100 text-green-800
                            @elseif($game->status === 'waiting') bg-yellow-100 text-yellow-800
                            @elseif($game->status === 'finished') bg-gray-100 text-gray-800
                            @else bg-blue-100 text-blue-800
                            @endif">
                            {{ ucfirst($game->status) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Jogadores:</span>
                        <span class="font-bold text-gray-900">{{ $game->players->count() }}</span>
                    </div>
                    @if($game->status === 'active')
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Números sorteados:</span>
                            <span class="font-bold text-gray-900">{{ $game->draws->count() }}/75</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Último número sorteado -->
            @if($game->status === 'active' && $game->draws->isNotEmpty())
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl shadow-lg p-8 text-center">
                    <div class="text-sm opacity-90 mb-3">Último número sorteado</div>
                    <div class="text-7xl font-extrabold">{{ $game->draws->last()->number }}</div>
                </div>

                <!-- Últimos 10 números -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="font-semibold text-lg mb-4">Últimos sorteados</h3>
                    <div class="grid grid-cols-5 gap-2">
                        @foreach($game->draws->reverse()->take(10) as $draw)
                            <div class="aspect-square bg-blue-50 text-blue-900 rounded-lg flex items-center justify-center font-bold text-base shadow-sm">
                                {{ $draw->number }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Prêmios -->
            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                <h3 class="font-semibold text-lg mb-4">Prêmios</h3>
                <div class="space-y-3">
                    @forelse($game->prizes as $prize)
                        <div class="p-4 rounded-lg {{ $prize->is_claimed ? 'bg-green-50 border border-green-200' : 'bg-gray-50' }}">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-medium">{{ $prize->position }}º - {{ $prize->name }}</div>
                                    @if($prize->winner)
                                        <div class="text-xs text-green-700 mt-1 font-medium">
                                            🏆 {{ $prize->winner->user->name ?? 'Vencedor' }}
                                        </div>
                                    @endif
                                </div>
                                @if($prize->is_claimed)
                                    <span class="text-green-600 text-xl">✓</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-500 py-4">
                            Nenhum prêmio configurado
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>