<?php

use Livewire\Component;
use App\Models\Ranking\Rank;
use Illuminate\Support\Collection;

new class extends Component {
    public $user;
    public string $rankingType = 'total'; // total, weekly, monthly
    public Collection $topPlayers;
    public ?int $userPosition = null;

    public function mount()
    {
        $this->user = auth()->user();
        $this->topPlayers = collect();
        $this->loadRankings();
    }

    public function setRankingType(string $type)
    {
        $this->rankingType = $type;
        $this->loadRankings();
    }

    public function loadRankings()
    {
        $column = match ($this->rankingType) {
            'weekly' => 'weekly_wins',
            'monthly' => 'monthly_wins',
            default => 'total_wins',
        };

        // Top 50 jogadores
        $ranks = Rank::with(['user.titles'])
            ->where($column, '>', 0)
            ->orderByDesc($column)
            ->take(50)
            ->get();

        $this->topPlayers = $ranks->map(fn ($rank, $index) => [
            'position' => $index + 1,
            'user' => $rank->user,
            'wins' => $rank->$column,
            'games' => $rank->total_games,
            'titles' => $rank->user->titles->pluck('type')->toArray(),
        ]);

        // Posição do usuário atual (com tratamento para null/0)
        $userWins = Rank::where('user_id', $this->user->id)->value($column);

        if (is_null($userWins) || $userWins <= 0) {
            $this->userPosition = null;
        } else {
            $this->userPosition = Rank::where($column, '>', $userWins)
                ->count() + 1;
        }
    }

    public function getTitleBadge(array $titles): ?array
    {
        $badges = [
            'legend' => ['name' => 'Lenda', 'color' => 'bg-purple-600 text-white'],
            'master' => ['name' => 'Mestre', 'color' => 'bg-yellow-600 text-white'],
            'experienced' => ['name' => 'Experiente', 'color' => 'bg-blue-600 text-white'],
            'beginner' => ['name' => 'Iniciante', 'color' => 'bg-green-600 text-white'],
        ];

        foreach (['legend', 'master', 'experienced', 'beginner'] as $level) {
            if (in_array($level, $titles, true)) {
                return $badges[$level];
            }
        }

        return null;
    }
};

?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Rankings</h1>
        <p class="text-gray-600">Veja os melhores jogadores de bingo</p>
    </div>

    <!-- Card do usuário logado -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-lg p-8 mb-8 text-white">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <div class="text-sm opacity-90 mb-1">Sua Posição</div>
                <div class="text-3xl font-bold">
                    @if($userPosition)
                        #{{ $userPosition }}
                    @else
                        Não ranqueado
                    @endif
                </div>
            </div>
            <div>
                <div class="text-sm opacity-90 mb-1">Vitórias Totais</div>
                <div class="text-3xl font-bold">{{ $user->rank->total_wins ?? 0 }}</div>
            </div>
            <div>
                <div class="text-sm opacity-90 mb-1">Vitórias Semanais</div>
                <div class="text-3xl font-bold">{{ $user->rank->weekly_wins ?? 0 }}</div>
            </div>
            <div>
                <div class="text-sm opacity-90 mb-1">Vitórias Mensais</div>
                <div class="text-3xl font-bold">{{ $user->rank->monthly_wins ?? 0 }}</div>
            </div>
        </div>

        @if($user->titles?->count() > 0)
            <div class="mt-6 pt-6 border-t border-white/20">
                <div class="text-sm opacity-90 mb-3">Seus Títulos</div>
                <div class="flex gap-2 flex-wrap">
                    @foreach($user->titles as $title)
                        @php($badge = $this->getTitleBadge([$title->type]))
                        @if($badge)
                            <span class="px-3 py-1 {{ $badge['color'] }} rounded-full text-sm font-semibold">
                                {{ $badge['name'] }}
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Filtros de ranking -->
    <div class="mb-6 flex gap-2 flex-wrap">
        <button
            wire:click="setRankingType('total')"
            class="px-6 py-3 rounded-lg transition font-semibold {{ $rankingType === 'total' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border' }}">
            🏆 Geral
        </button>
        <button
            wire:click="setRankingType('monthly')"
            class="px-6 py-3 rounded-lg transition font-semibold {{ $rankingType === 'monthly' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border' }}">
            📅 Mensal
        </button>
        <button
            wire:click="setRankingType('weekly')"
            class="px-6 py-3 rounded-lg transition font-semibold {{ $rankingType === 'weekly' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border' }}">
            📆 Semanal
        </button>
    </div>

    <!-- Tabela de rankings -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase w-20">Pos.</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Jogador</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Vitórias</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Partidas</th>
                        <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Taxa</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($topPlayers as $player)
                        <tr class="{{ $player['user']->id === $user->id ? 'bg-blue-50' : 'hover:bg-gray-50' }} transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($player['position'] === 1)
                                        <span class="text-3xl">🥇</span>
                                    @elseif($player['position'] === 2)
                                        <span class="text-3xl">🥈</span>
                                    @elseif($player['position'] === 3)
                                        <span class="text-3xl">🥉</span>
                                    @else
                                        <span class="text-lg font-bold text-gray-600">#{{ $player['position'] }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                                        {{ substr($player['user']->name ?? '', 0, 1) ?: '?' }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900">
                                            {{ $player['user']->name ?? 'Usuário' }}
                                            @if($player['user']->id === $user->id)
                                                <span class="text-blue-600 text-sm">(Você)</span>
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">{{ $player['user']->email ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-lg font-bold text-blue-600">{{ $player['wins'] }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-gray-900">{{ $player['games'] }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-gray-900 font-semibold">
                                    @if($player['games'] > 0)
                                        {{ number_format(($player['wins'] / $player['games']) * 100, 1) }}%
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php($badge = $this->getTitleBadge($player['titles']))
                                @if($badge)
                                    <span class="px-3 py-1 {{ $badge['color'] }} rounded-full text-xs font-semibold inline-block">
                                        {{ $badge['name'] }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-sm">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center text-gray-500">
                                <div class="text-lg font-semibold mb-2">Nenhum ranking disponível ainda</div>
                                <p class="text-sm">Jogue e vença para aparecer aqui!</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Como ganhar títulos -->
    <div class="mt-8 bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Como Ganhar Títulos</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl mb-2">🌱</div>
                <div class="font-semibold mb-1">Iniciante</div>
                <div class="text-sm text-gray-600">1 vitória</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl mb-2">⭐</div>
                <div class="font-semibold mb-1">Experiente</div>
                <div class="text-sm text-gray-600">10 vitórias</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl mb-2">👑</div>
                <div class="font-semibold mb-1">Mestre</div>
                <div class="text-sm text-gray-600">50 vitórias</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl mb-2">🏆</div>
                <div class="font-semibold mb-1">Lenda</div>
                <div class="text-sm text-gray-600">100 vitórias</div>
            </div>
        </div>
    </div>
</div>