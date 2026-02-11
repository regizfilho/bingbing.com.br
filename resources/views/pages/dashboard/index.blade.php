<?php

use Livewire\Component;
use App\Models\Game\Game;

new class extends Component {
    public $user;
    public array $stats = [];
    public $myGames;
    public $playedGames;

    public function mount()
    {
        $this->user = auth()->user();

        $wallet = $this->user->wallet;
        $rank = $this->user->rank;

        $this->stats = [
            'balance' => $wallet?->balance ?? 0,
            'total_wins' => $rank?->total_wins ?? 0,
            'weekly_wins' => $rank?->weekly_wins ?? 0,
            'monthly_wins' => $rank?->monthly_wins ?? 0,
            'total_games' => $rank?->total_games ?? 0,
        ];

        $this->myGames = $this->user->createdGames()
            ->with('package')
            ->latest()
            ->take(5)
            ->get();

        $this->playedGames = $this->user->playedGames()
            ->with(['creator', 'package'])
            ->latest()
            ->take(5)
            ->get();
    }
};
?>
<div>
    <x-slot name="header">
        Dashboard
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-gray-600">Olá, {{ $user->name }}</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Saldo</div>
                <div class="text-2xl font-bold text-green-600">
                    {{ number_format($stats['balance'], 2, ',', '.') }} créditos
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Vitórias Totais</div>
                <div class="text-2xl font-bold text-blue-600">{{ $stats['total_wins'] }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Vitórias Semanais</div>
                <div class="text-2xl font-bold text-purple-600">{{ $stats['weekly_wins'] }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Vitórias Mensais</div>
                <div class="text-2xl font-bold text-orange-600">{{ $stats['monthly_wins'] }}</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <a href="{{ route('games.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow p-6 text-center transition">
                <div class="text-lg font-semibold">Criar Partida</div>
                <div class="text-sm opacity-90">Configure e inicie um novo jogo</div>
            </a>

            <a href="{{ route('wallet.index') }}" class="bg-green-600 hover:bg-green-700 text-white rounded-lg shadow p-6 text-center transition">
                <div class="text-lg font-semibold">Comprar Créditos</div>
                <div class="text-sm opacity-90">Adicione saldo à sua carteira</div>
            </a>

            <a href="{{ route('rankings.index') }}" class="bg-purple-600 hover:bg-purple-700 text-white rounded-lg shadow p-6 text-center transition">
                <div class="text-lg font-semibold">Ver Rankings</div>
                <div class="text-sm opacity-90">Confira os melhores jogadores</div>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- My Games -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h2 class="text-xl font-semibold">Minhas Partidas</h2>
                </div>
                <div class="p-6">
                    @if($myGames->count())
                        <div class="space-y-4">
                            @foreach($myGames as $game)
                                <div class="border rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <div class="font-semibold">{{ $game->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $game->package->name }}</div>
                                        </div>

                                        <span class="px-2 py-1 text-xs rounded-full
                                            @if($game->status === 'active') bg-green-100 text-green-800
                                            @elseif($game->status === 'waiting') bg-yellow-100 text-yellow-800
                                            @elseif($game->status === 'finished') bg-gray-100 text-gray-800
                                            @else bg-blue-100 text-blue-800
                                            @endif">
                                            {{ ucfirst($game->status) }}
                                        </span>
                                    </div>

                                    <div class="text-sm text-gray-600 mb-3">
                                        Código:
                                        <span class="font-mono font-semibold">{{ $game->invite_code }}</span>
                                    </div>

                                    <a href="{{ route('games.play', $game) }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                                        Gerenciar →
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">
                            Nenhuma partida criada ainda
                        </div>
                    @endif
                </div>
            </div>

            <!-- Played Games -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h2 class="text-xl font-semibold">Partidas Jogadas</h2>
                </div>
                <div class="p-6">
                    @if($playedGames->count())
                        <div class="space-y-4">
                            @foreach($playedGames as $game)
                                <div class="border rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <div class="font-semibold">{{ $game->name }}</div>
                                            <div class="text-sm text-gray-500">Por: {{ $game->creator->name }}</div>
                                        </div>

                                        <span class="px-2 py-1 text-xs rounded-full
                                            @if($game->status === 'active') bg-green-100 text-green-800
                                            @elseif($game->status === 'waiting') bg-yellow-100 text-yellow-800
                                            @elseif($game->status === 'finished') bg-gray-100 text-gray-800
                                            @else bg-blue-100 text-blue-800
                                            @endif">
                                            {{ ucfirst($game->status) }}
                                        </span>
                                    </div>

                                    @if(in_array($game->status, ['active', 'waiting']))
                                        <a href="{{ route('games.join', $game->invite_code) }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                                            Entrar na partida →
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">
                            Você ainda não jogou nenhuma partida
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>