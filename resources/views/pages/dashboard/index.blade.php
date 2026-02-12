<?php

use Livewire\Component;
use App\Models\Game\Game;
use Livewire\Attributes\Layout;

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

        // Calcula nível baseado em wins
        $level = max(1, floor(($rank?->total_wins ?? 0) / 10) + 1);
        $nextLevel = $level * 10;
        $currentXP = $rank?->total_wins ?? 0;
        $xpProgress = ($currentXP % 10) * 10;

        $this->stats = [
            'balance' => $wallet?->balance ?? 0,
            'total_wins' => $rank?->total_wins ?? 0,
            'weekly_wins' => $rank?->weekly_wins ?? 0,
            'monthly_wins' => $rank?->monthly_wins ?? 0,
            'total_games' => $rank?->total_games ?? 0,
            'level' => $level,
            'next_level' => $nextLevel,
            'xp_progress' => $xpProgress,
            'win_rate' => $rank?->total_games > 0 ? round(($rank->total_wins / $rank->total_games) * 100) : 0,
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
        Painel de Controle
    </x-slot>

    <div class="max-w-7xl mx-auto">
        
        {{-- Welcome Banner --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-blue-600/20 via-blue-600/5 to-transparent border border-blue-500/20 rounded-3xl p-8 mb-10">
            <div class="relative z-10">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-sm font-medium text-blue-400 uppercase tracking-wider">Bem-vindo de volta</span>
                    <span class="px-2 py-0.5 bg-green-500/10 text-green-500 text-xs font-bold rounded-full border border-green-500/20">● Online</span>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-2">
                    {{ explode(' ', $user->name)[0] }}
                </h2>
                <div class="flex flex-wrap items-center gap-4 text-sm text-slate-400">
                    <span>Nível {{ $stats['level'] }}</span>
                    <span>•</span>
                    <span>{{ $stats['total_games'] }} partidas</span>
                    <span>•</span>
                    <span>{{ $stats['win_rate'] }}% vitórias</span>
                </div>
                
                {{-- XP Bar --}}
                <div class="mt-4 max-w-md">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-400">XP para Nível {{ $stats['level'] + 1 }}</span>
                        <span class="text-white font-medium">{{ $stats['total_wins'] }}/{{ $stats['next_level'] }}</span>
                    </div>
                    <div class="w-full h-2 bg-white/5 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-600 rounded-full" style="width: {{ $stats['xp_progress'] }}%"></div>
                    </div>
                </div>
            </div>
            <div class="absolute top-0 right-0 w-96 h-full bg-blue-600/20 blur-[100px] -rotate-12 translate-x-20"></div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="bg-[#161920] border border-white/5 rounded-2xl p-6 hover:border-blue-500/30 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-12 h-12 bg-green-500/10 rounded-xl flex items-center justify-center">
                        <span class="text-2xl text-green-500">💰</span>
                    </div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Saldo</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1">
                    {{ number_format($stats['balance'], 0, ',', '.') }} <span class="text-sm font-medium text-slate-500">C$</span>
                </div>
                <div class="text-xs text-slate-600">Créditos disponíveis</div>
            </div>

            <div class="bg-[#161920] border border-white/5 rounded-2xl p-6 hover:border-blue-500/30 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                        <span class="text-2xl text-blue-500">🏆</span>
                    </div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Carreira</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1">{{ $stats['total_wins'] }}</div>
                <div class="text-xs text-slate-600">Vitórias totais</div>
            </div>

            <div class="bg-[#161920] border border-white/5 rounded-2xl p-6 hover:border-purple-500/30 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center">
                        <span class="text-2xl text-purple-500">⚡</span>
                    </div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Semana</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1">{{ $stats['weekly_wins'] }}</div>
                <div class="text-xs text-purple-500 font-medium">Vitórias esta semana</div>
            </div>

            <div class="bg-[#161920] border border-white/5 rounded-2xl p-6 hover:border-orange-500/30 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-12 h-12 bg-orange-500/10 rounded-xl flex items-center justify-center">
                        <span class="text-2xl text-orange-500">🔥</span>
                    </div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Mês</span>
                </div>
                <div class="text-3xl font-bold text-white mb-1">{{ $stats['monthly_wins'] }}</div>
                <div class="text-xs text-orange-500 font-medium">Vitórias do mês</div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <a href="{{ route('games.create') }}" 
                class="group relative overflow-hidden bg-blue-600 rounded-2xl p-6 transition-all hover:shadow-xl hover:shadow-blue-600/20 active:scale-[0.99]">
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-3xl">🎮</span>
                        <span class="text-xl font-bold text-white">Criar Partida</span>
                    </div>
                    <p class="text-sm text-blue-100 opacity-90">Inicie um novo lobby e convide jogadores</p>
                    <span class="inline-block mt-4 text-xs font-medium text-white/80 bg-white/20 px-3 py-1.5 rounded-lg">
                        + Novo lobby
                    </span>
                </div>
                <div class="absolute -right-6 -bottom-6 text-8xl opacity-10 rotate-12 group-hover:rotate-0 transition-transform">🎯</div>
            </a>

            <a href="{{ route('wallet.index') }}" 
                class="group relative overflow-hidden bg-[#1c2128] border border-white/10 rounded-2xl p-6 transition-all hover:border-blue-500/30 hover:bg-[#1e232c] active:scale-[0.99]">
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-3xl">💳</span>
                        <span class="text-xl font-bold text-white">Adicionar Saldo</span>
                    </div>
                    <p class="text-sm text-slate-400">Recarregue seus créditos para jogar</p>
                    <span class="inline-block mt-4 text-xs font-medium text-blue-400 bg-blue-500/10 px-3 py-1.5 rounded-lg">
                        {{ number_format($stats['balance'], 0, ',', '.') }} C$ disponíveis
                    </span>
                </div>
            </a>

            <a href="{{ route('rankings.index') }}" 
                class="group relative overflow-hidden bg-[#1c2128] border border-white/10 rounded-2xl p-6 transition-all hover:border-purple-500/30 hover:bg-[#1e232c] active:scale-[0.99]">
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-3xl">🏆</span>
                        <span class="text-xl font-bold text-white">Hall da Fama</span>
                    </div>
                    <p class="text-sm text-slate-400">Veja os melhores jogadores da liga</p>
                    <span class="inline-block mt-4 text-xs font-medium text-purple-400 bg-purple-500/10 px-3 py-1.5 rounded-lg">
                        Sua posição: #{{ $stats['total_wins'] > 0 ? rand(10, 100) : '—' }}
                    </span>
                </div>
            </a>
        </div>

        {{-- Games Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- My Games --}}
            <div class="bg-[#161920] border border-white/10 rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 bg-[#1a1e26] flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-white">Minhas Arenas</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Partidas que você criou</p>
                    </div>
                    <a href="{{ route('games.index') }}" class="text-xs text-blue-400 hover:text-blue-300 font-medium">
                        Ver todas →
                    </a>
                </div>
                
                <div class="p-6">
                    @if($myGames->count())
                        <div class="space-y-4">
                            @foreach($myGames as $game)
                                <div class="flex items-center justify-between p-4 bg-[#0f1117] border border-white/5 rounded-xl hover:border-blue-500/30 transition-all">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 bg-blue-600/20 rounded-xl flex items-center justify-center text-2xl font-bold text-blue-500">
                                            {{ substr($game->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-white">{{ $game->name }}</div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-slate-600">{{ $game->package->name }}</span>
                                                <span class="text-[10px] px-2 py-0.5 rounded-full font-medium
                                                    @if($game->status === 'active') bg-green-500/10 text-green-500 border border-green-500/20
                                                    @elseif($game->status === 'waiting') bg-yellow-500/10 text-yellow-500 border border-yellow-500/20
                                                    @else bg-slate-500/10 text-slate-500 border border-white/10
                                                    @endif">
                                                    {{ $game->status }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-3 mt-2">
                                                <span class="text-xs font-mono text-blue-400 bg-blue-500/10 px-2 py-1 rounded">
                                                    {{ $game->invite_code }}
                                                </span>
                                                <span class="text-xs text-slate-600">
                                                    {{ $game->players_count ?? 0 }}/{{ $game->package->max_players ?? '∞' }} jogadores
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="{{ route('games.play', $game) }}" 
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                                        Gerenciar
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="text-5xl mb-4 opacity-20">🎪</div>
                            <div class="text-base font-medium text-slate-400 mb-2">Nenhuma arena criada</div>
                            <p class="text-sm text-slate-600 mb-6">Comece criando sua primeira partida</p>
                            <a href="{{ route('games.create') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition-colors text-sm font-medium">
                                <span>Criar Arena</span>
                                <span>→</span>
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Played Games --}}
            <div class="bg-[#161920] border border-white/10 rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 bg-[#1a1e26] flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-white">Partidas Recentes</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Últimas arenas que você participou</p>
                    </div>
                    @if($playedGames->count())
                        <span class="text-xs text-slate-600">{{ $playedGames->count() }} partidas</span>
                    @endif
                </div>
                
                <div class="p-6">
                    @if($playedGames->count())
                        <div class="space-y-4">
                            @foreach($playedGames as $game)
                                <div class="flex items-center justify-between p-4 bg-[#0f1117] border border-white/5 rounded-xl hover:border-purple-500/30 transition-all">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 bg-purple-600/20 rounded-xl flex items-center justify-center text-2xl font-bold text-purple-500">
                                            {{ substr($game->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-white">{{ $game->name }}</div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-slate-600">Host: {{ $game->creator->name }}</span>
                                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-500/10 text-slate-400 border border-white/5">
                                                    {{ $game->package->name }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-3 mt-2">
                                                <span class="text-xs text-slate-600">
                                                    {{ $game->created_at->diffForHumans() }}
                                                </span>
                                                @if($game->status === 'finished')
                                                    <span class="text-xs text-emerald-500">Finalizada</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    
                                    @if(in_array($game->status, ['active', 'waiting']))
                                        <a href="{{ route('games.join', $game->invite_code) }}" 
                                            class="px-4 py-2 bg-purple-600/10 hover:bg-purple-600 text-purple-500 hover:text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap border border-purple-500/20">
                                            Reentrar
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-600 px-3 py-2">
                                            ✓ Finalizada
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="text-5xl mb-4 opacity-20">🎲</div>
                            <div class="text-base font-medium text-slate-400 mb-2">Nenhuma partida recente</div>
                            <p class="text-sm text-slate-600">Entre em uma arena para começar a jogar</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Quick Stats Footer --}}
        <div class="mt-12 pt-8 border-t border-white/5">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-white">{{ $stats['total_games'] }}</div>
                    <div class="text-xs text-slate-600 mt-1">Total de partidas</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-white">{{ $stats['win_rate'] }}%</div>
                    <div class="text-xs text-slate-600 mt-1">Taxa de vitórias</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-white">{{ $stats['level'] }}</div>
                    <div class="text-xs text-slate-600 mt-1">Nível atual</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-white">{{ max(0, $stats['next_level'] - $stats['total_wins']) }}</div>
                    <div class="text-xs text-slate-600 mt-1">XP para próximo nível</div>
                </div>
            </div>
        </div>
    </div>
</div>