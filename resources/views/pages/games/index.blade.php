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

        return $query->latest()->paginate(9);
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

<div class="relative min-h-screen bg-[#0b0d11] text-slate-200 pb-32 italic overflow-x-hidden">

<x-loading target="setStatus, openRankingModal" message="CARREGANDO..." />
<x-toast />

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

    {{-- HEADER --}}
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-10 mb-20">
        <div>
            <div class="flex items-center gap-4 mb-4">
                <div class="h-[1px] w-14 bg-blue-600"></div>
                <span class="text-blue-500 font-black tracking-[0.4em] uppercase text-[9px]">
                    Host Dashboard
                </span>
            </div>

            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-white uppercase tracking-tight leading-none">
                MINHAS <span class="text-blue-600">PARTIDAS</span>
            </h1>

            <p class="text-slate-500 text-xs font-bold mt-4 uppercase tracking-widest">
                Controle total das suas arenas
            </p>
        </div>

        <a href="{{ route('games.create') }}"
           class="group relative inline-flex items-center justify-center gap-4 bg-blue-600 hover:bg-blue-500 text-white px-10 py-6 rounded-[2rem] transition-all font-black uppercase text-[11px] tracking-[0.3em] shadow-xl overflow-hidden w-full sm:w-auto">
            <span class="relative z-10 flex items-center gap-3">
                <span class="text-xl group-hover:rotate-90 transition-transform duration-500">+</span>
                CRIAR ARENA
            </span>
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
        </a>
    </div>

    {{-- FILTROS --}}
    <div class="flex gap-3 overflow-x-auto pb-4 mb-14 no-scrollbar">
        @foreach(['all'=>'Todas','draft'=>'Rascunhos','waiting'=>'Aguardando','active'=>'Em Jogo','finished'=>'Encerradas'] as $key=>$label)
            <button wire:click="setStatus('{{ $key }}')"
                class="px-6 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest border transition-all whitespace-nowrap
                {{ $statusFilter === $key
                    ? 'bg-blue-600 border-blue-500 text-white'
                    : 'bg-[#161920] text-slate-500 border-white/5 hover:text-white hover:border-white/10' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- GRID RESPONSIVO --}}
    @php $currentGames = $this->games(); @endphp

    <div class="grid gap-8 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($currentGames as $game)
            @php 
                $statusColor = $this->statusColor[$game->status] ?? 'slate';
                $statusLabel = $this->statusLabel[$game->status] ?? strtoupper($game->status);
            @endphp

            <div class="group relative bg-[#161920] border border-white/5 rounded-[2.5rem] p-8 hover:border-{{ $statusColor }}-500/40 transition-all duration-500 shadow-xl">

                <div class="flex justify-between items-start mb-6">
                    <h3 class="text-xl font-black text-white uppercase tracking-tight group-hover:text-blue-500 transition">
                        {{ $game->name }}
                    </h3>

                    <span class="text-[8px] px-3 py-1 rounded-full border border-{{ $statusColor }}-500/20 text-{{ $statusColor }}-500 uppercase tracking-widest">
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="space-y-3 text-[11px] font-black uppercase tracking-widest text-slate-400">
                    <div class="flex justify-between">
                        <span>Pacote</span>
                        <span class="text-white">{{ $game->package->name ?? 'Padrão' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Jogadores</span>
                        <span class="text-white">{{ $game->players->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Código</span>
                        <span class="text-blue-500 font-mono">{{ $game->invite_code }}</span>
                    </div>
                </div>

                <div class="mt-8 flex flex-wrap gap-3">

                    @if($game->winners()->exists())
                        <button wire:click="openRankingModal('{{ $game->uuid }}')"
                            class="flex-1 bg-amber-500/10 hover:bg-amber-500 text-amber-500 hover:text-white px-4 py-3 rounded-xl text-[9px] font-black uppercase tracking-widest transition">
                            RANKING
                        </button>
                    @endif

                    <a href="{{ route('games.play', $game->uuid) }}"
                       class="flex-1 bg-white hover:bg-blue-600 text-black hover:text-white px-4 py-3 rounded-xl text-[9px] font-black uppercase tracking-widest transition text-center">
                        ACESSAR
                    </a>

                </div>

                <div class="absolute -bottom-6 -right-6 text-white/[0.03] text-7xl font-black pointer-events-none">
                    {{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}
                </div>
            </div>

        @empty
            <div class="col-span-full text-center py-32 border border-dashed border-white/5 rounded-[3rem]">
                <div class="text-6xl opacity-10 mb-8">📂</div>
                <p class="text-slate-500 uppercase text-xs tracking-widest font-black">
                    Nenhuma arena encontrada
                </p>
            </div>
        @endforelse
    </div>

    @if($currentGames->hasPages())
        <div class="pt-20 flex justify-center">
            {{ $currentGames->links('vendor.livewire.tailwind') }}
        </div>
    @endif

</div>

{{-- MODAL RESPONSIVO --}}
@if($showRankingModal && $selectedGame)
<div class="fixed inset-0 bg-[#0b0d11]/95 backdrop-blur-xl flex items-center justify-center z-[300] p-4" wire:click="closeRankingModal">
    <div class="bg-[#161920] w-full max-w-2xl rounded-[2.5rem] p-8 overflow-y-auto max-h-[85vh]" wire:click.stop>

        <div class="flex justify-between items-center mb-8">
            <h2 class="text-2xl font-black text-white uppercase">
                Hall da Fama
            </h2>
            <button wire:click="closeRankingModal" class="text-3xl text-slate-500 hover:text-white">&times;</button>
        </div>

        <div class="space-y-4">
            @foreach($gameRanking as $index => $data)
                <div class="bg-[#0b0d11] border border-white/5 rounded-2xl p-5 flex justify-between items-center">
                    <div>
                        <div class="font-black text-white uppercase">
                            {{ $data['user']->name }}
                        </div>
                        <div class="text-xs text-slate-500 uppercase">
                            {{ $data['wins'] }} vitórias
                        </div>
                    </div>
                    <div class="text-xl">
                        @if($index===0) 🥇
                        @elseif($index===1) 🥈
                        @elseif($index===2) 🥉
                        @else #{{ $index+1 }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</div>
@endif

<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { scrollbar-width: none; -ms-overflow-style: none; }
</style>

</div>
