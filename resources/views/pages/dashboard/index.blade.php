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

        // Calcula nível baseado em vitórias (10 vitórias por nível)
        $level = max(1, floor(($rank?->total_wins ?? 0) / 10) + 1);
        $nextLevelWins = $level * 10;
        $currentWins = $rank?->total_wins ?? 0;
        $xpProgress = ($currentWins % 10) * 10;

        $this->stats = [
            'balance' => $wallet?->balance ?? 0,
            'total_wins' => $rank?->total_wins ?? 0,
            'weekly_wins' => $rank?->weekly_wins ?? 0,
            'monthly_wins' => $rank?->monthly_wins ?? 0,
            'total_games' => $rank?->total_games ?? 0,
            'level' => $level,
            'next_level' => $nextLevelWins,
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

<div class="min-h-screen bg-[#05070a] text-slate-300 italic font-sans pb-12">
    <x-loading />
    <x-toast />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10">
        
        {{-- Banner de Boas-vindas (Mantendo seu gradiente original mas com fontes Premium) --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-blue-600/20 via-blue-600/5 to-transparent border border-blue-500/20 rounded-[2rem] p-8 mb-10 shadow-2xl">
            <div class="relative z-10">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-[10px] font-black text-blue-400 uppercase tracking-[0.3em]">Central de Comando</span>
                    <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-500 text-[9px] font-black rounded-full border border-emerald-500/20 uppercase animate-pulse">● Online</span>
                </div>
                <h2 class="text-4xl sm:text-5xl font-black text-white mb-3 uppercase tracking-tighter">
                    {{ explode(' ', $user->name)[0] }}
                </h2>
                <div class="flex flex-wrap items-center gap-6 text-[10px] font-black uppercase tracking-widest text-slate-400">
                    <span class="flex items-center gap-2">Nível {{ $stats['level'] }}</span>
                    <span class="text-slate-700">•</span>
                    <span class="flex items-center gap-2">{{ $stats['total_games'] }} Partidas</span>
                    <span class="text-slate-700">•</span>
                    <span class="flex items-center gap-2">{{ $stats['win_rate'] }}% Vitórias</span>
                </div>
                
                {{-- Barra de XP --}}
                <div class="mt-6 max-w-md">
                    <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-2">
                        <span class="text-slate-500">Progresso para Nível {{ $stats['level'] + 1 }}</span>
                        <span class="text-blue-500 italic">{{ $stats['total_wins'] }} / {{ $stats['next_level'] }}</span>
                    </div>
                    <div class="w-full h-2 bg-white/5 rounded-full overflow-hidden p-[1px] border border-white/5">
                        <div class="h-full bg-blue-600 rounded-full shadow-[0_0_10px_rgba(37,99,235,0.4)]" style="width: {{ $stats['xp_progress'] }}%"></div>
                    </div>
                </div>
            </div>
            <div class="absolute top-0 right-0 w-96 h-full bg-blue-600/10 blur-[100px] -rotate-12 translate-x-20"></div>
        </div>

        {{-- Cards de Estatísticas --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="bg-[#0b0d11] border border-white/5 rounded-3xl p-6 hover:border-blue-500/30 transition-all shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-emerald-500/10 rounded-2xl flex items-center justify-center text-2xl">💰</div>
                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Saldo</span>
                </div>
                <div class="text-3xl font-black text-white italic tracking-tighter mb-1">
                    {{ number_format($stats['balance'], 0, ',', '.') }} <span class="text-xs text-slate-500">C$</span>
                </div>
                <div class="text-[9px] font-black text-slate-600 uppercase tracking-tight">Disponível para jogar</div>
            </div>

            <div class="bg-[#0b0d11] border border-white/5 rounded-3xl p-6 hover:border-blue-500/30 transition-all shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-500/10 rounded-2xl flex items-center justify-center text-2xl">🏆</div>
                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Carreira</span>
                </div>
                <div class="text-3xl font-black text-white italic tracking-tighter mb-1">{{ $stats['total_wins'] }}</div>
                <div class="text-[9px] font-black text-slate-600 uppercase tracking-tight">Vitórias Totais</div>
            </div>

            <div class="bg-[#0b0d11] border border-white/5 rounded-3xl p-6 hover:border-purple-500/30 transition-all shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-500/10 rounded-2xl flex items-center justify-center text-2xl">⚡</div>
                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Semanal</span>
                </div>
                <div class="text-3xl font-black text-white italic tracking-tighter mb-1">{{ $stats['weekly_wins'] }}</div>
                <div class="text-[9px] font-black text-purple-500 uppercase tracking-tight">Ganhos da semana</div>
            </div>

            <div class="bg-[#0b0d11] border border-white/5 rounded-3xl p-6 hover:border-orange-500/30 transition-all shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-orange-500/10 rounded-2xl flex items-center justify-center text-2xl">🔥</div>
                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Mensal</span>
                </div>
                <div class="text-3xl font-black text-white italic tracking-tighter mb-1">{{ $stats['monthly_wins'] }}</div>
                <div class="text-[9px] font-black text-orange-500 uppercase tracking-tight">Ganhos do mês</div>
            </div>
        </div>

        {{-- Ações Rápidas --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <a href="{{ route('games.create') }}" 
                class="group relative overflow-hidden bg-blue-600 rounded-[2rem] p-7 transition-all hover:scale-[1.02] shadow-2xl shadow-blue-600/20 active:scale-95">
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-3xl">🎮</span>
                        <span class="text-xl font-black text-white uppercase italic tracking-tighter">Criar Sala</span>
                    </div>
                    <p class="text-[10px] font-bold text-blue-100 uppercase tracking-widest opacity-80">Abra uma nova rodada agora</p>
                </div>
                <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 rotate-12 group-hover:rotate-0 transition-transform">🎯</div>
            </a>

            <a href="{{ route('wallet.index') }}" 
                class="group relative overflow-hidden bg-[#11141b] border border-white/10 rounded-[2rem] p-7 transition-all hover:border-blue-600/30 active:scale-95">
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-3xl">💳</span>
                        <span class="text-xl font-black text-white uppercase italic tracking-tighter">Colocar Saldo</span>
                    </div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest opacity-80">Recarregue via PIX rápido</p>
                </div>
            </a>

            <a href="{{ route('rankings.index') }}" 
                class="group relative overflow-hidden bg-[#11141b] border border-white/10 rounded-[2rem] p-7 transition-all hover:border-purple-600/30 active:scale-95">
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-3xl">🏆</span>
                        <span class="text-xl font-black text-white uppercase italic tracking-tighter">Melhores</span>
                    </div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest opacity-80">Veja quem está no topo</p>
                </div>
            </a>
        </div>

        {{-- Listas (Mantendo sua estrutura de 2 colunas) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Minhas Salas --}}
            <div class="bg-[#0b0d11] border border-white/5 rounded-[2.5rem] overflow-hidden shadow-2xl">
                <div class="px-8 py-6 border-b border-white/5 bg-white/[0.02] flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-black text-white uppercase italic tracking-tighter leading-none">Minhas Salas</h2>
                        <p class="text-[9px] font-black text-slate-600 uppercase tracking-widest mt-1">Salas que você organizou</p>
                    </div>
                    <a href="{{ route('games.index') }}" class="text-[9px] font-black text-blue-500 uppercase tracking-widest hover:underline">Ver todas</a>
                </div>
                
                <div class="p-6">
                    @forelse($myGames as $game)
                        <div class="flex items-center justify-between p-5 bg-[#05070a] border border-white/5 rounded-3xl mb-4 hover:border-blue-600/30 transition-all group">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-[#0b0d11] rounded-2xl flex items-center justify-center text-xl font-black text-blue-600 border border-white/5 group-hover:scale-110 transition-transform">
                                    {{ substr($game->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-black text-white uppercase italic text-sm">{{ $game->name }}</div>
                                    <div class="flex items-center gap-3 mt-1">
                                        <span class="text-[9px] font-black text-blue-500 bg-blue-500/5 px-2 py-0.5 rounded border border-blue-500/10">{{ $game->invite_code }}</span>
                                        <span class="text-[8px] font-black px-2 py-0.5 rounded-full uppercase
                                            @if($game->status === 'active') bg-emerald-500/10 text-emerald-500
                                            @elseif($game->status === 'waiting') bg-amber-500/10 text-amber-500
                                            @else bg-white/5 text-slate-500
                                            @endif">
                                            {{ $game->status === 'active' ? 'Em andamento' : ($game->status === 'waiting' ? 'Aguardando' : 'Finalizada') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('games.play', $game) }}" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase italic shadow-lg shadow-blue-600/20">
                                Abrir
                            </a>
                        </div>
                    @empty
                        <div class="text-center py-10 italic text-slate-600 text-xs">Nenhuma sala criada.</div>
                    @endforelse
                </div>
            </div>

            {{-- Partidas Recentes --}}
            <div class="bg-[#0b0d11] border border-white/5 rounded-[2.5rem] overflow-hidden shadow-2xl">
                <div class="px-8 py-6 border-b border-white/5 bg-white/[0.02] flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-black text-white uppercase italic tracking-tighter leading-none">Participações</h2>
                        <p class="text-[9px] font-black text-slate-600 uppercase tracking-widest mt-1">Últimas salas que você entrou</p>
                    </div>
                </div>
                
                <div class="p-6">
                    @forelse($playedGames as $game)
                        <div class="flex items-center justify-between p-5 bg-[#05070a] border border-white/5 rounded-3xl mb-4 hover:border-purple-600/30 transition-all group">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-[#0b0d11] rounded-2xl flex items-center justify-center text-xl font-black text-purple-600 border border-white/5 group-hover:scale-110 transition-transform">
                                    {{ substr($game->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-black text-white uppercase italic text-sm">{{ $game->name }}</div>
                                    <p class="text-[9px] font-black text-slate-600 uppercase italic mt-1">Por: {{ $game->creator->name }}</p>
                                </div>
                            </div>
                            @if(in_array($game->status, ['active', 'waiting']))
                                <a href="{{ route('games.join', $game->invite_code) }}" class="text-[10px] font-black text-purple-500 border border-purple-600/20 px-4 py-2 rounded-xl hover:bg-purple-600 hover:text-white transition-all">
                                    Reentrar
                                </a>
                            @else
                                <div class="text-[9px] font-black text-slate-700 uppercase tracking-widest">Finalizada</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-10 italic text-slate-600 text-xs">Você ainda não jogou.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Footer de Resumo --}}
        <div class="mt-12 pt-8 border-t border-white/5 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center md:text-left">
                <div class="text-2xl font-black text-white italic leading-none">{{ $stats['total_games'] }}</div>
                <div class="text-[9px] font-black text-slate-600 uppercase tracking-widest mt-2">Partidas Jogadas</div>
            </div>
            <div class="text-center md:text-left">
                <div class="text-2xl font-black text-white italic leading-none">{{ $stats['win_rate'] }}%</div>
                <div class="text-[9px] font-black text-slate-600 uppercase tracking-widest mt-2">Aproveitamento</div>
            </div>
            <div class="text-center md:text-left">
                <div class="text-2xl font-black text-white italic leading-none">{{ $stats['level'] }}</div>
                <div class="text-[9px] font-black text-slate-600 uppercase tracking-widest mt-2">Nível Hunter</div>
            </div>
            <div class="text-center md:text-left">
                <div class="text-2xl font-black text-white italic leading-none">{{ max(0, $stats['next_level'] - $stats['total_wins']) }}</div>
                <div class="text-[9px] font-black text-slate-600 uppercase tracking-widest mt-2">Próximas Vitórias</div>
            </div>
        </div>
    </div>
</div>