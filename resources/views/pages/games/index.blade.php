<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Game\Game;

new class extends Component {
    use WithPagination;

    public string $statusFilter = 'all';
    public ?Game $selectedGame = null;
    public bool $showRankingModal = false;
    public array $gameRanking = [];

    public function user()
    {
        return auth()->user();
    }

    /**
     * Retorna as partidas paginadas com os filtros aplicados.
     */
    public function games()
    {
        $query = $this->user()->createdGames()->with(['package', 'players', 'winners.user', 'winners.prize']);

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->latest()->paginate(10);
    }

    public function setStatus(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    /**
     * Abre o modal e processa o ranking consolidado da partida.
     */
    public function openRankingModal(string $gameUuid): void
    {
        $this->selectedGame = Game::where('uuid', $gameUuid)
            ->with(['winners.user', 'winners.prize', 'players.user'])
            ->firstOrFail();

        $ranking = [];
        foreach ($this->selectedGame->winners as $winner) {
            $userId = $winner->user_id;

            if (!isset($ranking[$userId])) {
                $ranking[$userId] = [
                    'user' => $winner->user,
                    'wins' => 0,
                    'rounds' => [],
                    'prizes' => []
                ];
            }

            $ranking[$userId]['wins']++;
            $ranking[$userId]['rounds'][] = $winner->round_number;
            
            // TRATAMENTO AQUI: Se o prêmio for nulo, registra como Mérito
            $ranking[$userId]['prizes'][] = $winner->prize->name ?? 'Mérito Honorário ✨';
        }

        // Ordena por quem tem mais vitórias
        usort($ranking, fn($a, $b) => $b['wins'] <=> $a['wins']);

        $this->gameRanking = $ranking;
        $this->showRankingModal = true;
    }

    public function closeRankingModal(): void
    {
        $this->showRankingModal = false;
        $this->selectedGame = null;
        $this->gameRanking = [];
    }
};
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    {{-- Header --}}
    <div class="mb-8 flex justify-between items-center flex-wrap gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Minhas Partidas</h1>
            <p class="text-gray-600">Gerencie e acompanhe os resultados dos seus bingos</p>
        </div>
        <a href="{{ route('games.create') }}"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition font-semibold shadow-md">
            + Nova Partida
        </a>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 flex gap-2 flex-wrap">
        @foreach(['all' => 'Todas', 'draft' => 'Rascunho', 'waiting' => 'Aguardando', 'active' => 'Ativas', 'finished' => 'Finalizadas'] as $key => $label)
            <button wire:click="setStatus('{{ $key }}')"
                class="px-4 py-2 rounded-lg transition font-medium border
                {{ $statusFilter === $key ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Listagem --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border">
        @php $currentGames = $this->games(); @endphp
        
        @if ($currentGames->count() > 0)
            <div class="divide-y">
                @foreach ($currentGames as $game)
                    <div class="p-6 hover:bg-gray-50/50 transition" wire:key="game-{{ $game->uuid }}">
                        <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 mb-2 flex-wrap">
                                    <h3 class="text-lg font-bold text-gray-900 truncate">{{ $game->name }}</h3>
                                    <span class="px-3 py-1 text-xs rounded-full font-bold uppercase tracking-wider
                                        @if ($game->status === 'active') bg-green-100 text-green-800
                                        @elseif($game->status === 'waiting') bg-yellow-100 text-yellow-800
                                        @elseif($game->status === 'finished') bg-gray-100 text-gray-800
                                        @else bg-blue-100 text-blue-800 @endif">
                                        {{ $game->status }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-8 gap-y-2 text-sm text-gray-600">
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-400">Pacote:</span> {{ $game->package->name }}
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-400">Código:</span> <span class="font-mono font-bold text-blue-600">{{ $game->invite_code }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-400">Jogadores:</span> {{ $game->players->count() }}
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-400">Data:</span> {{ $game->created_at->format('d/m/Y') }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2 flex-wrap">
                                {{-- Botão Ranking corrigido para checarWinners --}}
                                @if ($game->winners()->exists())
                                    <button wire:click="openRankingModal('{{ $game->uuid }}')"
                                        class="flex-1 lg:flex-none bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg transition text-sm font-bold shadow-sm">
                                        🏆 Ranking
                                    </button>
                                @endif

                                @if ($game->status === 'draft')
                                    <a href="{{ route('games.edit', $game) }}"
                                        class="flex-1 lg:flex-none bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition text-sm font-bold text-center">
                                        Editar
                                    </a>
                                @endif

                                @if ($game->status !== 'draft')
                                    <a href="{{ route('games.play', $game) }}"
                                        class="flex-1 lg:flex-none bg-gray-800 hover:bg-black text-white px-4 py-2 rounded-lg transition text-sm font-bold text-center">
                                        Gerenciar
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($currentGames->hasPages())
                <div class="px-6 py-4 border-t bg-gray-50">
                    {{ $currentGames->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-20">
                <div class="text-4xl mb-4">📭</div>
                <h3 class="text-lg font-bold text-gray-900">Nenhuma partida por aqui</h3>
                <p class="text-gray-500 max-w-xs mx-auto mt-2">
                    {{ $statusFilter === 'all' ? 'Você ainda não criou nenhuma partida.' : 'Não encontramos partidas com o status selecionado.' }}
                </p>
            </div>
        @endif
    </div>

    {{-- Modal de Ranking Consolidado --}}
    @if ($showRankingModal && $selectedGame)
        <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm flex items-center justify-center z-50 p-4"
            wire:click="closeRankingModal">
            <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[85vh] flex flex-col"
                wire:click.stop>
                
                <div class="px-8 py-6 border-b flex justify-between items-center bg-gray-50 rounded-t-2xl">
                    <div>
                        <h2 class="text-2xl font-black text-gray-900 tracking-tight">RANKING FINAL</h2>
                        <p class="text-sm font-medium text-blue-600">{{ $selectedGame->name }} • Total de Ganhadores</p>
                    </div>
                    <button wire:click="closeRankingModal" class="text-gray-400 hover:text-gray-600 text-2xl">
                        &times;
                    </button>
                </div>

                <div class="p-8 overflow-y-auto flex-1">
                    <div class="space-y-4">
                        @foreach ($gameRanking as $index => $data)
                            <div class="relative overflow-hidden border rounded-xl p-5 flex items-center gap-6 
                                {{ $index === 0 ? 'bg-amber-50 border-amber-200 shadow-sm' : 'bg-white border-gray-100' }}">
                                
                                <div class="w-12 text-center flex-shrink-0">
                                    @if($index === 0) <span class="text-4xl">🥇</span>
                                    @elseif($index === 1) <span class="text-4xl">🥈</span>
                                    @elseif($index === 2) <span class="text-4xl">🥉</span>
                                    @else <span class="text-xl font-bold text-gray-400">#{{ $index + 1 }}</span>
                                    @endif
                                </div>

                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-bold text-lg text-gray-900">{{ $data['user']->name }}</h4>
                                            <p class="text-[10px] text-gray-500 uppercase font-black tracking-widest mt-1">
                                                {{ $data['wins'] }} Vitórias {{ $data['wins'] > 1 ? 'acumuladas' : '' }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-[10px] text-gray-400 font-bold uppercase">Nas Rodadas</div>
                                            <div class="text-xs font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full inline-block">
                                                {{ implode(', ', array_unique($data['rounds'])) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($data['prizes'] as $prizeName)
                                            @php $isHonra = str_contains($prizeName, 'Mérito') || str_contains($prizeName, '✨'); @endphp
                                            <span class="px-3 py-1 border rounded-lg text-[10px] font-black uppercase shadow-sm flex items-center gap-1
                                                {{ $isHonra ? 'bg-slate-50 border-slate-200 text-slate-500' : 'bg-white border-amber-200 text-amber-700' }}">
                                                {{ $isHonra ? '🏅' : '🎁' }} {{ $prizeName }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="p-6 border-t bg-gray-50 rounded-b-2xl">
                    <button wire:click="closeRankingModal"
                        class="w-full bg-gray-900 hover:bg-black text-white py-4 rounded-xl font-bold transition shadow-lg">
                        FECHAR RESULTADOS
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>