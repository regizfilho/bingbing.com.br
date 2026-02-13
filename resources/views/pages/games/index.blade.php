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

    protected $queryString = [
        'statusFilter' => ['except' => 'all'],
    ];

    public function user()
    {
        return auth()->user();
    }

    public function games()
    {
        $query = $this->user()->createdGames()
            ->with(['package', 'players', 'winners.user', 'winners.prize']);

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
                    'prizes' => [],
                    'totalRounds' => $this->selectedGame->max_rounds,
                ];
            }

            $ranking[$userId]['wins']++;
            $ranking[$userId]['rounds'][] = $winner->round_number;
            $ranking[$userId]['prizes'][] = $winner->prize?->name ?? 'Honra';
        }

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

    public function getStatusColorProperty()
    {
        return [
            'draft' => 'blue',
            'waiting' => 'amber',
            'active' => 'emerald',
            'finished' => 'slate',
            'paused' => 'red',
        ];
    }

    public function getStatusLabelProperty()
    {
        return [
            'draft' => 'RASCUNHO',
            'waiting' => 'AGUARDANDO',
            'active' => 'ATIVA',
            'finished' => 'FINALIZADA',
            'paused' => 'PAUSADA',
        ];
    }
};
?>

<div class="relative min-h-screen bg-[#0b0d11] text-slate-200 pb-20 italic">
    {{-- COMPONENTES DE FEEDBACK --}}
    <x-loading target="setStatus, openRankingModal" message="CARREGANDO..." />
    <x-toast />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        
        {{-- CABEÇALHO --}}
        <div class="flex flex-col md:flex-row justify-between items-end gap-6 mb-16">
            <div>
                <div class="flex items-center gap-4 mb-3">
                    <div class="h-[1px] w-12 bg-blue-600"></div>
                    <span class="text-blue-500 font-black tracking-[0.4em] uppercase text-[9px] italic">Host Dashboard</span>
                </div>
                <h1 class="text-5xl sm:text-6xl font-black text-white tracking-tighter uppercase italic leading-none">
                    MINHAS <span class="text-blue-600">PARTIDAS</span>
                </h1>
                <p class="text-slate-500 text-sm font-bold mt-3 uppercase tracking-widest">Gerencie e monitore suas arenas em tempo real</p>
            </div>

            <a href="{{ route('games.create') }}"
                class="group relative inline-flex items-center gap-4 bg-blue-600 hover:bg-blue-500 text-white px-10 py-6 rounded-[2.5rem] transition-all font-black uppercase text-[11px] tracking-[0.3em] italic shadow-2xl shadow-blue-600/20 overflow-hidden w-full md:w-auto text-center justify-center">
                <span class="relative z-10 flex items-center gap-3">
                    <span class="text-xl group-hover:rotate-90 transition-transform duration-500">+</span> 
                    CRIAR NOVA ARENA
                </span>
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
            </a>
        </div>

        {{-- FILTROS --}}
        <div class="bg-[#161920] border border-white/5 rounded-[2rem] p-4 mb-12 flex gap-3 overflow-x-auto no-scrollbar">
            @foreach(['all' => 'Todas', 'draft' => 'Rascunhos', 'waiting' => 'Aguardando', 'active' => 'Em Jogo', 'finished' => 'Encerradas'] as $key => $label)
                <button wire:click="setStatus('{{ $key }}')"
                    class="px-8 py-4 rounded-xl transition-all font-black uppercase text-[10px] tracking-[0.2em] border italic whitespace-nowrap
                    {{ $statusFilter === $key 
                        ? 'bg-blue-600 border-blue-500 text-white shadow-lg' 
                        : 'bg-[#0b0d11] text-slate-500 border-white/5 hover:text-white hover:border-white/10' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- LISTAGEM --}}
        <div class="space-y-10">
            @php $currentGames = $this->games(); @endphp
            
            @forelse ($currentGames as $game)
                @php 
                    $statusColor = $this->getStatusColorProperty()[$game->status] ?? 'slate';
                    $statusLabel = $this->getStatusLabelProperty()[$game->status] ?? strtoupper($game->status);
                @endphp
                <div class="group relative bg-[#161920] border border-white/5 rounded-[3rem] p-10 hover:border-{{ $statusColor }}-500/30 transition-all duration-500 shadow-2xl" 
                    wire:key="game-{{ $game->uuid }}">
                    
                    <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-10">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-5 mb-8">
                                <h3 class="text-3xl font-black text-white italic tracking-tighter uppercase group-hover:text-blue-500 transition-colors">
                                    {{ $game->name }}
                                </h3>
                                <div class="px-5 py-2 rounded-full border bg-[#0b0d11] border-{{ $statusColor }}-500/20 text-{{ $statusColor }}-500 flex items-center gap-3 shadow-inner">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                                    <span class="text-[9px] font-black uppercase tracking-[0.2em] italic">{{ $statusLabel }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                                <div class="space-y-2">
                                    <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest italic">Pacote</span>
                                    <div class="text-[11px] font-black text-white uppercase italic tracking-widest">{{ $game->package->name ?? 'Padrão' }}</div>
                                </div>
                                <div class="space-y-2 border-l border-white/5 pl-8">
                                    <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest italic">Código</span>
                                    <div class="text-[11px] font-black text-blue-500 font-mono tracking-widest">{{ $game->invite_code }}</div>
                                </div>
                                <div class="space-y-2 border-l border-white/5 pl-8">
                                    <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest italic">Jogadores</span>
                                    <div class="text-[11px] font-black text-white uppercase italic">{{ $game->players->count() }} / {{ $game->package->max_players ?? '∞' }}</div>
                                </div>
                                <div class="space-y-2 border-l border-white/5 pl-8">
                                    <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest italic">Criado em</span>
                                    <div class="text-[11px] font-black text-white uppercase italic">{{ $game->created_at->format('d/m/y') }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- AÇÕES --}}
                        <div class="flex flex-wrap items-center gap-4 w-full lg:w-auto pt-8 lg:pt-0 border-t lg:border-t-0 border-white/5">
                            @if ($game->winners()->exists())
                                <button wire:click="openRankingModal('{{ $game->uuid }}')"
                                    class="flex-1 lg:flex-none bg-[#0b0d11] hover:bg-amber-500 border border-amber-500/20 text-amber-500 hover:text-white px-8 py-5 rounded-2xl transition-all text-[10px] font-black uppercase tracking-widest italic flex items-center justify-center gap-3">
                                    🏆 RANKING
                                </button>
                            @endif

                            @if ($game->status === 'draft')
                                <a href="{{ route('games.edit', $game->uuid) }}"
                                    class="flex-1 lg:flex-none bg-blue-600/10 hover:bg-blue-600 border border-blue-600/20 text-blue-400 hover:text-white px-8 py-5 rounded-2xl transition-all text-[10px] font-black uppercase tracking-widest italic text-center">
                                    EDITAR
                                </a>
                            @elseif ($game->status === 'waiting')
                                <a href="{{ route('games.edit', $game->uuid) }}"
                                    class="flex-1 lg:flex-none bg-blue-600/10 hover:bg-blue-600 border border-blue-600/20 text-blue-400 hover:text-white px-8 py-5 rounded-2xl transition-all text-[10px] font-black uppercase tracking-widest italic text-center">
                                    CONFIGURAR
                                </a>
                                <a href="{{ route('games.play', $game->uuid) }}"
                                    class="flex-1 lg:flex-none bg-white hover:bg-blue-500 text-black hover:text-white px-10 py-5 rounded-2xl transition-all text-[10px] font-black uppercase tracking-widest italic text-center shadow-xl">
                                    ACESSAR
                                </a>
                            @elseif (in_array($game->status, ['active', 'paused']))
                                <a href="{{ route('games.play', $game->uuid) }}"
                                    class="flex-1 lg:flex-none bg-emerald-600 hover:bg-emerald-500 text-white px-10 py-5 rounded-2xl transition-all text-[10px] font-black uppercase tracking-widest italic text-center shadow-2xl shadow-emerald-600/20">
                                    JOGAR AGORA
                                </a>
                            @elseif ($game->status === 'finished')
                                <a href="{{ route('games.play', $game->uuid) }}" 
                                    class="flex-1 lg:flex-none bg-[#0b0d11] hover:bg-white border border-white/10 text-slate-500 hover:text-black px-8 py-5 rounded-2xl transition-all text-[10px] font-black uppercase tracking-widest italic text-center">
                                    VER RESULTADO
                                </a>
                            @endif
                        </div>
                    </div>
                    
                    {{-- WATERMARK DE FUNDO --}}
                    <div class="absolute right-10 bottom-4 text-white/[0.02] font-black text-9xl italic pointer-events-none select-none tracking-tighter">
                        {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                    </div>
                </div>
            @empty
                <div class="bg-[#161920] border border-white/5 border-dashed rounded-[4rem] py-32 px-10 text-center">
                    <div class="text-7xl mb-10 opacity-10">📂</div>
                    <h3 class="text-3xl font-black text-white uppercase italic tracking-tighter">Vazio por aqui</h3>
                    <p class="text-slate-600 text-[11px] font-black uppercase tracking-[0.3em] mt-4 italic">
                        {{ $statusFilter === 'all' ? 'Comece sua jornada criando sua primeira arena' : 'Nenhuma partida encontrada com este filtro' }}
                    </p>
                </div>
            @endforelse

            {{-- PAGINAÇÃO --}}
            @if ($currentGames->hasPages())
                <div class="pt-16 flex justify-center">
                    {{ $currentGames->links('vendor.livewire.tailwind') }}
                </div>
            @endif
        </div>

        {{-- MODAL RANKING --}}
        @if ($showRankingModal && $selectedGame)
            <div class="fixed inset-0 bg-[#0b0d11]/95 backdrop-blur-xl flex items-center justify-center z-[200] p-6" wire:click="closeRankingModal">
                <div class="bg-[#161920] border border-white/10 rounded-[3rem] shadow-3xl w-full max-w-3xl max-h-[85vh] flex flex-col relative overflow-hidden" wire:click.stop>
                    
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 via-amber-500 to-blue-600"></div>

                    <div class="p-12 border-b border-white/5 flex justify-between items-center bg-white/[0.01]">
                        <div>
                            <h2 class="text-4xl font-black text-white tracking-tighter italic uppercase leading-none">
                                HALL DA <span class="text-amber-500">FAMA</span>
                            </h2>
                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] italic mt-4">
                                {{ $selectedGame->name }}
                            </p>
                        </div>
                        <button wire:click="closeRankingModal" class="text-slate-500 hover:text-white transition-colors text-4xl p-4">&times;</button>
                    </div>

                    <div class="p-12 overflow-y-auto flex-1 space-y-6 no-scrollbar">
                        @forelse ($gameRanking as $index => $data)
                            <div class="bg-[#0b0d11] border border-white/5 rounded-[2rem] p-8 flex items-center gap-10 hover:border-amber-500/30 transition-all">
                                <div class="w-16 text-center">
                                    @if($index === 0) <span class="text-6xl drop-shadow-lg">🥇</span>
                                    @elseif($index === 1) <span class="text-5xl">🥈</span>
                                    @elseif($index === 2) <span class="text-5xl">🥉</span>
                                    @else <span class="text-2xl font-black text-slate-800 italic">#{{ $index + 1 }}</span>
                                    @endif
                                </div>

                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="font-black text-xl text-white uppercase italic tracking-tighter">{{ $data['user']->name }}</h4>
                                        <span class="text-[11px] font-black text-amber-500 uppercase italic">{{ $data['wins'] }} Vitórias</span>
                                    </div>
                                    
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($data['prizes'] as $prizeName)
                                            <span class="px-4 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border border-white/5 bg-white/5 text-slate-400 italic">
                                                🎁 {{ $prizeName }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-20">
                                <p class="text-slate-600 text-xs font-black uppercase italic tracking-widest">Aguardando vencedores...</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="p-10 border-t border-white/5">
                        <button wire:click="closeRankingModal"
                            class="w-full bg-[#0b0d11] hover:bg-white text-slate-500 hover:text-black py-6 rounded-[2rem] font-black uppercase text-xs tracking-[0.4em] italic transition-all border border-white/10">
                            FECHAR RANKING
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</div>