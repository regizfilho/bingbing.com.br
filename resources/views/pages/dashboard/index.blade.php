<?php

use Livewire\Component;
use App\Models\Game\Game;
use Livewire\Attributes\Layout;

new  class extends Component {
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
        Painel de Controle
    </x-slot>

    <div class="max-w-7xl mx-auto py-2">
        
        <div class="relative overflow-hidden bg-blue-600/10 border border-blue-500/20 rounded-[2rem] p-8 mb-10">
            <div class="relative z-10">
                <h2 class="font-game text-3xl font-black text-white uppercase italic tracking-tighter">
                    Bem-vindo de volta, <span class="text-blue-500">{{ explode(' ', $user->name)[0] }}</span>
                </h2>
                <p class="text-slate-400 text-sm mt-1 font-medium">Status do Sistema: <span class="text-green-500 animate-pulse">Online</span> | Operativo Nível {{ max(1, floor($stats['total_wins'] / 10)) }}</p>
            </div>
            <div class="absolute top-0 right-0 w-64 h-full bg-blue-600/10 blur-[50px] -rotate-12 translate-x-10"></div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="bg-[#161920] border border-white/5 rounded-3xl p-6 hover:border-blue-500/30 transition group">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 bg-green-500/10 rounded-xl flex items-center justify-center text-green-500">💰</div>
                    <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest">Créditos</span>
                </div>
                <div class="font-game text-2xl font-black text-white tracking-tighter">
                    {{ number_format($stats['balance'], 2, ',', '.') }}
                </div>
            </div>

            <div class="bg-[#161920] border border-white/5 rounded-3xl p-6 hover:border-blue-500/30 transition group">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center text-blue-500">🏆</div>
                    <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest">Total Wins</span>
                </div>
                <div class="font-game text-2xl font-black text-white tracking-tighter">{{ $stats['total_wins'] }}</div>
                <div class="text-[10px] text-slate-500 font-bold uppercase mt-1">Carreira</div>
            </div>

            <div class="bg-[#161920] border border-white/5 rounded-3xl p-6 hover:border-purple-500/30 transition group">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 bg-purple-500/10 rounded-xl flex items-center justify-center text-purple-500">⚡</div>
                    <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest">Weekly</span>
                </div>
                <div class="font-game text-2xl font-black text-white tracking-tighter">{{ $stats['weekly_wins'] }}</div>
                <div class="text-[10px] text-purple-500 font-bold uppercase mt-1">Desta semana</div>
            </div>

            <div class="bg-[#161920] border border-white/5 rounded-3xl p-6 hover:border-orange-500/30 transition group">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 bg-orange-500/10 rounded-xl flex items-center justify-center text-orange-500">🔥</div>
                    <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest">Monthly</span>
                </div>
                <div class="font-game text-2xl font-black text-white tracking-tighter">{{ $stats['monthly_wins'] }}</div>
                <div class="text-[10px] text-orange-500 font-bold uppercase mt-1">Meta do mês</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <a href="{{ route('games.create') }}" class="group relative overflow-hidden bg-blue-600 rounded-3xl p-8 transition-all hover:scale-[1.02] active:scale-95 shadow-[0_10px_30px_rgba(37,99,235,0.3)]">
                <div class="relative z-10">
                    <div class="text-2xl mb-2">➕</div>
                    <div class="font-game text-lg font-black text-white uppercase italic">Criar Partida</div>
                    <div class="text-blue-100 text-xs font-medium opacity-80 mt-1">Inicie um novo lobby agora</div>
                </div>
                <div class="absolute -right-4 -bottom-4 text-8xl opacity-10 rotate-12 group-hover:rotate-0 transition-transform">🎮</div>
            </a>

            <a href="{{ route('wallet.index') }}" class="group relative overflow-hidden bg-[#1c2128] border border-white/10 rounded-3xl p-8 transition-all hover:border-blue-500/50 hover:scale-[1.02] active:scale-95">
                <div class="relative z-10">
                    <div class="text-2xl mb-2">💳</div>
                    <div class="font-game text-lg font-black text-white uppercase italic">Adicionar Saldo</div>
                    <div class="text-slate-400 text-xs font-medium mt-1">Recarregue seus créditos</div>
                </div>
                <div class="absolute -right-4 -bottom-4 text-8xl opacity-5 rotate-12 group-hover:rotate-0 transition-transform">💎</div>
            </a>

            <a href="{{ route('rankings.index') }}" class="group relative overflow-hidden bg-[#1c2128] border border-white/10 rounded-3xl p-8 transition-all hover:border-blue-500/50 hover:scale-[1.02] active:scale-95">
                <div class="relative z-10">
                    <div class="text-2xl mb-2">🏅</div>
                    <div class="font-game text-lg font-black text-white uppercase italic">Hall da Fama</div>
                    <div class="text-slate-400 text-xs font-medium mt-1">Veja os melhores da liga</div>
                </div>
                <div class="absolute -right-4 -bottom-4 text-8xl opacity-5 rotate-12 group-hover:rotate-0 transition-transform">👑</div>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="bg-[#161920] border border-white/10 rounded-[2.5rem] overflow-hidden shadow-2xl">
                <div class="px-8 py-6 border-b border-white/5 bg-white/[0.02] flex justify-between items-center">
                    <h2 class="font-game text-sm font-black text-white uppercase tracking-widest italic">Host: Minhas Salas</h2>
                    <span class="text-[10px] font-bold text-blue-500 uppercase tracking-tighter">Últimas 5</span>
                </div>
                <div class="p-8">
                    @if($myGames->count())
                        <div class="space-y-4">
                            @foreach($myGames as $game)
                                <div class="group bg-white/[0.02] border border-white/5 rounded-2xl p-5 hover:bg-white/[0.04] transition-colors">
                                    <div class="flex justify-between items-center mb-4">
                                        <div>
                                            <div class="font-black text-white uppercase text-sm tracking-tight">{{ $game->name }}</div>
                                            <div class="text-[10px] text-slate-500 font-bold uppercase">{{ $game->package->name }}</div>
                                        </div>
                                        <span class="px-3 py-1 text-[9px] font-black uppercase rounded-lg tracking-widest
                                            @if($game->status === 'active') bg-green-500/10 text-green-500 border border-green-500/20
                                            @elseif($game->status === 'waiting') bg-yellow-500/10 text-yellow-500 border border-yellow-500/20
                                            @else bg-slate-500/10 text-slate-500 border border-white/10
                                            @endif">
                                            {{ $game->status }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-end">
                                        <div class="bg-[#0d0f14] px-4 py-2 rounded-xl border border-white/5">
                                            <div class="text-[8px] text-slate-600 font-black uppercase mb-1">Passcode</div>
                                            <div class="font-game text-sm text-blue-500 tracking-widest">{{ $game->invite_code }}</div>
                                        </div>
                                        <a href="{{ route('games.play', $game) }}" class="text-[10px] font-black text-white uppercase tracking-widest bg-blue-600 px-4 py-2 rounded-lg hover:bg-blue-500 transition shadow-lg shadow-blue-600/20">
                                            Gerenciar
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="text-4xl mb-4 opacity-20">📡</div>
                            <div class="text-slate-500 text-xs font-black uppercase tracking-widest">Nenhuma transmissão ativa</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-[#161920] border border-white/10 rounded-[2.5rem] overflow-hidden shadow-2xl">
                <div class="px-8 py-6 border-b border-white/5 bg-white/[0.02] flex justify-between items-center">
                    <h2 class="font-game text-sm font-black text-white uppercase tracking-widest italic">Histórico de Combate</h2>
                    <span class="text-[10px] font-bold text-purple-500 uppercase tracking-tighter">Jogos Recentes</span>
                </div>
                <div class="p-8">
                    @if($playedGames->count())
                        <div class="space-y-4">
                            @foreach($playedGames as $game)
                                <div class="flex items-center gap-5 p-5 bg-white/[0.02] border border-white/5 rounded-2xl hover:bg-white/[0.04] transition group">
                                    <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center font-game font-bold text-slate-500 group-hover:text-blue-500 transition">
                                        {{ substr($game->name, 0, 1) }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-black text-white uppercase text-sm tracking-tight">{{ $game->name }}</div>
                                        <div class="text-[10px] text-slate-500 font-bold uppercase italic">Host: {{ $game->creator->name }}</div>
                                    </div>
                                    @if(in_array($game->status, ['active', 'waiting']))
                                        <a href="{{ route('games.join', $game->invite_code) }}" class="p-3 bg-blue-600/10 text-blue-500 rounded-xl hover:bg-blue-600 hover:text-white transition">
                                            ▶️
                                        </a>
                                    @else
                                        <span class="text-[9px] font-black text-slate-600 uppercase italic">Encerrado</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="text-4xl mb-4 opacity-20">🕹️</div>
                            <div class="text-slate-500 text-xs font-black uppercase tracking-widest">Aguardando novo desafio</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>