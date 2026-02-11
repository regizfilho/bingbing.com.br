<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Game\Game;

new class extends Component {
    use WithPagination;

    public $user;
    public string $statusFilter = 'all';

    public function mount()
    {
        $this->user = auth()->user();
    }

    public function setStatus(string $status)
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function with()
    {
        $query = $this->user->createdGames()->with('package');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return [
            'games' => $query->latest()->paginate(10),
        ];
    }
};
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Minhas Partidas</h1>
            <p class="text-gray-600">Gerencie todas as suas partidas de bingo</p>
        </div>
        <a
            href="{{ route('games.create') }}"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition font-semibold"
        >
            + Nova Partida
        </a>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex gap-2">
        <button
            wire:click="setStatus('all')"
            class="px-4 py-2 rounded-lg transition {{ $statusFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}"
        >
            Todas
        </button>
        <button
            wire:click="setStatus('draft')"
            class="px-4 py-2 rounded-lg transition {{ $statusFilter === 'draft' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}"
        >
            Rascunho
        </button>
        <button
            wire:click="setStatus('waiting')"
            class="px-4 py-2 rounded-lg transition {{ $statusFilter === 'waiting' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}"
        >
            Aguardando
        </button>
        <button
            wire:click="setStatus('active')"
            class="px-4 py-2 rounded-lg transition {{ $statusFilter === 'active' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}"
        >
            Ativas
        </button>
        <button
            wire:click="setStatus('finished')"
            class="px-4 py-2 rounded-lg transition {{ $statusFilter === 'finished' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}"
        >
            Finalizadas
        </button>
    </div>

    <!-- Games List -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        @if($games->count() > 0)
            <div class="divide-y">
                @foreach($games as $game)
                    <div class="p-6 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $game->name }}</h3>
                                    <span
                                        class="px-3 py-1 text-xs rounded-full
                                            @if($game->status === 'active') bg-green-100 text-green-800
                                            @elseif($game->status === 'waiting') bg-yellow-100 text-yellow-800
                                            @elseif($game->status === 'finished') bg-gray-100 text-gray-800
                                            @else bg-blue-100 text-blue-800
                                            @endif"
                                    >
                                        {{ ucfirst($game->status) }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600 mb-3">
                                    <div>
                                        <span class="text-gray-500">Pacote:</span>
                                        <span class="font-medium">{{ $game->package->name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Código:</span>
                                        <span class="font-mono font-semibold">{{ $game->invite_code }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Jogadores:</span>
                                        <span class="font-medium">{{ $game->players()->count() }}/{{ $game->package->max_players }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Criado em:</span>
                                        <span class="font-medium">{{ $game->created_at->format('d/m/Y') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2 ml-4">
                                @if($game->status === 'draft')
                                    <a
                                        href="{{ route('games.edit', $game) }}"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition text-sm"
                                    >
                                        Editar
                                    </a>
                                @endif

                                <a
                                    href="{{ route('games.play', $game) }}"
                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition text-sm"
                                >
                                    Gerenciar
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($games->hasPages())
                <div class="px-6 py-4 border-t">
                    {{ $games->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-16 text-gray-500">
                <div class="text-lg font-semibold mb-2">Nenhuma partida encontrada</div>
                <p class="text-sm">Crie sua primeira partida para começar!</p>
            </div>
        @endif
    </div>
</div>
