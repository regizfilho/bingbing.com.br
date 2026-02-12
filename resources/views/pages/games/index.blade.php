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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    
    {{-- Header de Comando --}}
    <div class="mb-10 sm:mb-12 flex flex-col sm:flex-row sm:items-end justify-between gap-6 sm:gap-8">
        <div>
            <div class="flex items-center gap-3 sm:gap-4 mb-3 sm:mb-4">
                <div class="h-[1px] w-8 sm:w-12 bg-gradient-to-r from-blue-600 to-transparent"></div>
                <span class="text-blue-500/80 font-black tracking-[0.3em] sm:tracking-[0.4em] uppercase text-[8px] sm:text-[9px] italic">Host Dashboard</span>
            </div>
            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white tracking-tighter uppercase italic leading-none">
                MINHAS <span class="text-blue-500">PARTIDAS</span>
            </h1>
            <p class="text-slate-500 text-xs sm:text-sm font-bold mt-2 sm:mt-3">Gerencie todas as arenas que você criou</p>
        </div>
        
        <a href="{{ route('games.create') }}"
            class="group relative inline-flex items-center justify-center gap-3 sm:gap-4 bg-blue-600 hover:bg-blue-500 text-white px-8 sm:px-10 py-4 sm:py-5 rounded-2xl sm:rounded-[2rem] transition-all font-black uppercase text-[10px] sm:text-[11px] tracking-[0.2em] sm:tracking-[0.3em] italic shadow-2xl shadow-blue-600/20 overflow-hidden w-full sm:w-auto">
            <span class="relative z-10 flex items-center gap-2 sm:gap-3">
                <span class="text-base sm:text-lg group-hover:rotate-90 transition-transform duration-500">+</span> 
                NOVA ARENA
            </span>
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
        </a>
    </div>

    {{-- Filtros --}}
    <div class="mb-8 sm:mb-10 flex gap-2 sm:gap-3 overflow-x-auto pb-4 sm:pb-6 no-scrollbar">
        @foreach(['all' => 'Todas', 'draft' => 'Rascunhos', 'waiting' => 'Aguardando', 'active' => 'Ativas', 'finished' => 'Finalizadas'] as $key => $label)
            <button wire:click="setStatus('{{ $key }}')"
                class="px-4 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl transition-all font-black uppercase text-[8px] sm:text-[9px] tracking-[0.15em] sm:tracking-[0.2em] border italic whitespace-nowrap flex-shrink-0
                {{ $statusFilter === $key 
                    ? 'bg-blue-600/10 border-blue-500 text-white shadow-lg shadow-blue-500/10 ring-1 ring-blue-500/50' 
                    : 'bg-[#0b0d11] text-slate-600 border-white/5 hover:text-white hover:border-white/20' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Listagem --}}
    <div class="space-y-6 sm:space-y-8">
        @php $currentGames = $this->games(); @endphp
        
        @forelse ($currentGames as $game)
            @php 
                $statusColor = $this->getStatusColorProperty()[$game->status] ?? 'slate';
                $statusLabel = $this->getStatusLabelProperty()[$game->status] ?? strtoupper($game->status);
            @endphp
            <div class="group relative bg-[#0b0d11] border border-white/10 rounded-2xl sm:rounded-[2.5rem] p-6 sm:p-8 lg:p-10 hover:border-{{ $statusColor }}-500/40 transition-all duration-500 shadow-2xl overflow-hidden" 
                wire:key="game-{{ $game->uuid }}">
                
                {{-- LED Status --}}
                <div class="absolute left-0 top-0 w-1 h-full bg-{{ $statusColor }}-500 shadow-[2px_0_15px_rgba(var(--status-rgb),0.4)]"></div>

                <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-6 lg:gap-10 relative z-10">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 mb-4 sm:mb-6">
                            <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white italic tracking-tighter uppercase group-hover:text-{{ $statusColor }}-400 transition-colors break-words">
                                {{ $game->name }}
                            </h3>
                            <div class="flex items-center gap-2 px-3 sm:px-4 py-1 sm:py-1.5 rounded-full border bg-black/40 border-{{ $statusColor }}-500/30 text-{{ $statusColor }}-500 w-fit">
                                <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                                <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] italic">{{ $statusLabel }}</span>
                            </div>
                        </div>

                        {{-- Grid Info --}}
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-8">
                            <div class="col-span-2 sm:col-span-1 space-y-1">
                                <span class="text-[7px] sm:text-[8px] font-black text-slate-600 uppercase tracking-[0.2em] sm:tracking-[0.3em] italic">Pacote</span>
                                <div class="text-[10px] sm:text-[11px] font-black text-white uppercase italic tracking-widest truncate">{{ $game->package->name ?? 'Padrão' }}</div>
                            </div>
                            <div class="space-y-1 sm:border-l border-white/5 sm:pl-4 lg:pl-8">
                                <span class="text-[7px] sm:text-[8px] font-black text-slate-600 uppercase tracking-[0.2em] sm:tracking-[0.3em] italic">Código</span>
                                <div class="text-[10px] sm:text-[11px] font-black text-blue-500 font-mono tracking-[0.2em] sm:tracking-[0.3em] truncate">{{ $game->invite_code }}</div>
                            </div>
                            <div class="space-y-1 sm:border-l border-white/5 sm:pl-4 lg:pl-8">
                                <span class="text-[7px] sm:text-[8px] font-black text-slate-600 uppercase tracking-[0.2em] sm:tracking-[0.3em] italic">Jogadores</span>
                                <div class="text-[10px] sm:text-[11px] font-black text-white uppercase italic">{{ $game->players->count() }} / {{ $game->package->max_players ?? '∞' }}</div>
                            </div>
                            <div class="col-span-2 sm:col-span-1 space-y-1 sm:border-l border-white/5 sm:pl-4 lg:pl-8">
                                <span class="text-[7px] sm:text-[8px] font-black text-slate-600 uppercase tracking-[0.2em] sm:tracking-[0.3em] italic">Criação</span>
                                <div class="text-[10px] sm:text-[11px] font-black text-white uppercase italic">{{ $game->created_at->format('d/m/y') }}</div>
                            </div>
                        </div>

                        {{-- Rodada Atual --}}
                        @if($game->status === 'active')
                            <div class="mt-4 sm:mt-6 flex items-center gap-3">
                                <span class="text-[9px] sm:text-[10px] font-black text-{{ $statusColor }}-500 uppercase italic">Rodada {{ $game->current_round }}/{{ $game->max_rounds }}</span>
                                <div class="w-20 sm:w-24 bg-white/5 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-{{ $statusColor }}-500 h-full transition-all" 
                                        style="width: {{ ($game->current_round / max($game->max_rounds, 1)) * 100 }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Ações --}}
                    {{-- Ações --}}
<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4 w-full lg:w-auto">
    @if ($game->winners()->exists())
        <button wire:click="openRankingModal('{{ $game->uuid }}')"
            class="flex-1 lg:flex-none bg-amber-500/5 hover:bg-amber-500 border border-amber-500/20 text-amber-500 hover:text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl sm:rounded-2xl transition-all text-[9px] sm:text-[10px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] italic flex items-center justify-center gap-2">
            <span class="text-base">🏆</span> Ranking
        </button>
    @endif

    @if ($game->status === 'draft')
        <a href="{{ route('games.edit', $game->uuid) }}"
            class="flex-1 lg:flex-none bg-blue-600/5 hover:bg-blue-600 border border-blue-600/20 text-blue-400 hover:text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl sm:rounded-2xl transition-all text-[9px] sm:text-[10px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] italic text-center">
            Editar
        </a>
    @endif

    @if ($game->status === 'waiting')
        <a href="{{ route('games.edit', $game->uuid) }}"
            class="flex-1 lg:flex-none bg-blue-600/5 hover:bg-blue-600 border border-blue-600/20 text-blue-400 hover:text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl sm:rounded-2xl transition-all text-[9px] sm:text-[10px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] italic text-center">
            Configurar
        </a>
    @endif

    @if (in_array($game->status, ['waiting', 'active', 'paused']))
        <a href="{{ route('games.play', $game->uuid) }}"
            class="flex-1 lg:flex-none bg-white hover:bg-white/90 text-black px-8 sm:px-10 py-3 sm:py-4 rounded-xl sm:rounded-2xl transition-all text-[9px] sm:text-[10px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] italic text-center shadow-xl shadow-white/5">
            {{ $game->status === 'waiting' ? 'Acessar' : 'Jogar' }}
        </a>
    @endif

    @if ($game->status === 'finished')
        <a href="{{ route('games.play', $game->uuid) }}" 
            class="flex-1 lg:flex-none bg-slate-600/5 hover:bg-slate-600 border border-slate-600/20 text-slate-400 hover:text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl sm:rounded-2xl transition-all text-[9px] sm:text-[10px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] italic text-center">
            Visualizar
        </a>
    @endif
</div>
                </div>
                
                {{-- Watermark --}}
                <div class="absolute -right-4 sm:-right-6 -bottom-4 sm:-bottom-8 text-white/[0.02] font-black text-7xl sm:text-8xl lg:text-[12rem] italic pointer-events-none select-none tracking-tighter">
                    {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                </div>
            </div>
        @empty
            <div class="bg-[#0b0d11] border border-white/5 border-dashed rounded-2xl sm:rounded-[3rem] py-16 sm:py-32 px-4 text-center">
                <div class="text-5xl sm:text-6xl mb-6 sm:mb-8 opacity-20 grayscale">📂</div>
                <h3 class="text-xl sm:text-2xl font-black text-white uppercase italic tracking-tighter">Nenhuma partida encontrada</h3>
                <p class="text-slate-600 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] mt-2 sm:mt-3 italic">
                    {{ $statusFilter === 'all' ? 'Comece criando sua primeira arena' : 'Nenhuma partida com este status' }}
                </p>
                @if($statusFilter !== 'all')
                    <button wire:click="setStatus('all')" 
                        class="mt-6 sm:mt-8 text-blue-500 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] hover:text-white transition-colors italic">
                        Ver todas as partidas →
                    </button>
                @else
                    <div class="mt-8 sm:mt-12">
                        <a href="{{ route('games.create') }}" 
                            class="inline-flex items-center gap-2 sm:gap-3 text-blue-500 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] hover:text-white transition-colors italic">
                            Criar primeira arena <span class="text-base sm:text-lg">→</span>
                        </a>
                    </div>
                @endif
            </div>
        @endforelse

        {{-- Paginação --}}
        @if ($currentGames->hasPages())
            <div class="pt-8 sm:pt-10 flex justify-center">
                {{ $currentGames->links('vendor.livewire.tailwind') }}
            </div>
        @endif
    </div>

    {{-- Modal Ranking --}}
    @if ($showRankingModal && $selectedGame)
        <div class="fixed inset-0 bg-[#05070a]/95 backdrop-blur-2xl flex items-center justify-center z-[200] p-3 sm:p-6"
            wire:click="closeRankingModal">
            <div class="bg-[#0b0d11] border border-white/10 rounded-2xl sm:rounded-3xl lg:rounded-[3rem] shadow-3xl w-full max-w-3xl max-h-[90vh] sm:max-h-[85vh] flex flex-col relative overflow-hidden"
                wire:click.stop>
                
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 via-amber-500 to-blue-600"></div>

                <div class="px-6 sm:px-10 lg:px-12 py-6 sm:py-8 lg:py-10 border-b border-white/5 flex justify-between items-center bg-white/[0.01]">
                    <div>
                        <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-white tracking-tighter italic uppercase leading-none">
                            HALL DA <span class="text-amber-500">VITÓRIA</span>
                        </h2>
                        <div class="flex items-center gap-2 sm:gap-3 mt-2 sm:mt-3">
                            <span class="w-1 h-1 sm:w-1.5 sm:h-1.5 rounded-full bg-amber-500"></span>
                            <p class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] sm:tracking-[0.3em] italic truncate max-w-[200px] sm:max-w-xs">
                                {{ $selectedGame->name }}
                            </p>
                        </div>
                    </div>
                    <button wire:click="closeRankingModal" 
                        class="text-slate-500 hover:text-white transition-colors text-3xl sm:text-4xl font-light leading-none p-2">
                        &times;
                    </button>
                </div>

                <div class="p-6 sm:p-10 lg:p-12 overflow-y-auto flex-1 space-y-4 sm:space-y-6 lg:space-y-8 no-scrollbar">
                    @forelse ($gameRanking as $index => $data)
                        <div class="bg-white/[0.02] border border-white/5 rounded-xl sm:rounded-2xl lg:rounded-[2rem] p-4 sm:p-6 lg:p-8 flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-6 lg:gap-10 transition-all hover:bg-white/[0.04]">
                            
                            <div class="w-12 sm:w-16 flex-shrink-0 text-center mx-auto sm:mx-0">
                                @if($index === 0) 
                                    <span class="text-4xl sm:text-5xl lg:text-6xl drop-shadow-[0_0_15px_rgba(245,158,11,0.5)]">🥇</span>
                                @elseif($index === 1) 
                                    <span class="text-3xl sm:text-4xl lg:text-5xl">🥈</span>
                                @elseif($index === 2) 
                                    <span class="text-3xl sm:text-4xl lg:text-5xl">🥉</span>
                                @else 
                                    <span class="text-lg sm:text-xl lg:text-2xl font-black text-slate-800 italic">#{{ $index + 1 }}</span>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0 w-full">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-4 mb-3 sm:mb-4">
                                    <h4 class="font-black text-base sm:text-lg lg:text-xl text-white uppercase italic tracking-tighter truncate">
                                        {{ $data['user']->name }}
                                    </h4>
                                    <span class="text-[10px] sm:text-[11px] lg:text-xs font-black text-amber-500/80 uppercase italic whitespace-nowrap">
                                        {{ $data['wins'] }} {{ $data['wins'] === 1 ? 'vitória' : 'vitórias' }}
                                    </span>
                                </div>
                                
                                @if(!empty($data['prizes']))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach (array_slice($data['prizes'], 0, 3) as $prizeName)
                                            <span class="px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 rounded-lg sm:rounded-xl text-[8px] sm:text-[9px] lg:text-[10px] font-black uppercase tracking-widest border border-amber-500/20 bg-amber-500/10 text-amber-500 italic whitespace-nowrap">
                                                🎁 {{ $prizeName }}
                                            </span>
                                        @endforeach
                                        @if(count($data['prizes']) > 3)
                                            <span class="px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 rounded-lg sm:rounded-xl text-[8px] sm:text-[9px] lg:text-[10px] font-black uppercase tracking-widest border border-white/5 bg-white/5 text-slate-500 italic">
                                                +{{ count($data['prizes']) - 3 }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 sm:py-16">
                            <div class="text-4xl sm:text-5xl mb-4 opacity-20">🏆</div>
                            <p class="text-slate-600 text-[10px] sm:text-xs font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] italic">
                                Nenhum vencedor registrado
                            </p>
                        </div>
                    @endforelse
                </div>

                <div class="p-6 sm:p-8 lg:p-10 border-t border-white/5">
                    <button wire:click="closeRankingModal"
                        class="w-full bg-[#0b0d11] hover:bg-white text-slate-600 hover:text-black py-4 sm:py-5 lg:py-6 rounded-xl sm:rounded-2xl lg:rounded-[2rem] font-black uppercase text-[10px] sm:text-[11px] lg:text-xs tracking-[0.2em] sm:tracking-[0.3em] lg:tracking-[0.4em] italic transition-all border border-white/10">
                        FECHAR
                    </button>
                </div>
            </div>
        </div>
    @endif

    <style>
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        @keyframes bounce-x {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(4px); }
        }
        .animate-bounce-x {
            animation: bounce-x 1s infinite;
        }
    </style>
</div>