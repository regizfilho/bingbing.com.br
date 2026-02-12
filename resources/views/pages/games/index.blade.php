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
<div class="max-w-7xl mx-auto px-4 py-12">
    
    {{-- Header de Comando --}}
    <div class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-8">
        <div>
            <div class="flex items-center gap-4 mb-4">
                <div class="h-[1px] w-12 bg-gradient-to-r from-blue-600 to-transparent"></div>
                <span class="text-blue-500/80 font-black tracking-[0.4em] uppercase text-[9px] italic">Host Dashboard</span>
            </div>
            <h1 class="text-5xl font-black text-white tracking-tighter uppercase italic leading-none">
                MINHAS <span class="text-blue-500 text-glow-blue">PARTIDAS</span>
            </h1>
        </div>
        
        <a href="{{ route('games.create') }}"
            class="group relative inline-flex items-center gap-4 bg-blue-600 hover:bg-blue-500 text-white px-10 py-5 rounded-[2rem] transition-all font-black uppercase text-[11px] tracking-[0.3em] italic shadow-2xl shadow-blue-600/20 overflow-hidden">
            <span class="relative z-10 flex items-center gap-3">
                <span class="text-lg group-hover:rotate-90 transition-transform duration-500">+</span> 
                Nova Partida
            </span>
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
        </a>
    </div>

    {{-- Filtros HUD (Fita tática) --}}
    <div class="mb-10 flex gap-3 overflow-x-auto pb-6 no-scrollbar">
        @foreach(['all' => 'Todas', 'draft' => 'Rascunhos', 'waiting' => 'Aguardando', 'active' => 'Ativas', 'finished' => 'Finalizadas'] as $key => $label)
            <button wire:click="setStatus('{{ $key }}')"
                class="px-6 py-2.5 rounded-2xl transition-all font-black uppercase text-[9px] tracking-[0.2em] border italic whitespace-nowrap
                {{ $statusFilter === $key 
                    ? 'bg-blue-600/10 border-blue-500 text-white shadow-[0_0_20px_rgba(59,130,246,0.15)] ring-1 ring-blue-500/50' 
                    : 'bg-[#0b0d11] text-slate-600 border-white/5 hover:text-white hover:border-white/20' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Listagem de Dossiês --}}
    <div class="space-y-8">
        @php $currentGames = $this->games(); @endphp
        
        @forelse ($currentGames as $game)
            <div class="group relative bg-[#0b0d11] border border-white/10 rounded-[2.5rem] p-8 sm:p-10 hover:border-blue-500/40 transition-all duration-500 shadow-2xl overflow-hidden" wire:key="game-{{ $game->uuid }}">
                
                {{-- LED Lateral de Status --}}
                <div class="absolute left-0 top-0 w-1 h-full 
                    @if ($game->status === 'active') bg-emerald-500 shadow-[2px_0_15px_rgba(16,185,129,0.4)]
                    @elseif($game->status === 'waiting') bg-amber-500 shadow-[2px_0_15px_rgba(245,158,11,0.4)]
                    @elseif($game->status === 'finished') bg-slate-800
                    @else bg-blue-600 shadow-[2px_0_15px_rgba(37,99,235,0.4)] @endif">
                </div>

                <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-10 relative z-10">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-4 mb-6 flex-wrap">
                            <h3 class="text-2xl sm:text-3xl font-black text-white italic tracking-tighter uppercase group-hover:text-blue-400 transition-colors">
                                {{ $game->name }}
                            </h3>
                            <div class="flex items-center gap-2 px-4 py-1.5 rounded-full border bg-black/40
                                @if ($game->status === 'active') border-emerald-500/30 text-emerald-500
                                @elseif($game->status === 'waiting') border-amber-500/30 text-amber-500
                                @elseif($game->status === 'finished') border-white/10 text-slate-500
                                @else border-blue-500/30 text-blue-500 @endif">
                                <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] italic">{{ $game->status }}</span>
                            </div>
                        </div>

                        {{-- Grade Técnica --}}
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                            <div class="space-y-1">
                                <span class="text-[8px] font-black text-slate-600 uppercase tracking-[0.3em] italic">Pacote Ativo</span>
                                <div class="text-[11px] font-black text-white uppercase italic tracking-widest">{{ $game->package->name }}</div>
                            </div>
                            <div class="space-y-1 border-l border-white/5 pl-8">
                                <span class="text-[8px] font-black text-slate-600 uppercase tracking-[0.3em] italic">Código Convite</span>
                                <div class="text-[11px] font-black text-blue-500 font-mono tracking-[0.3em]">{{ $game->invite_code }}</div>
                            </div>
                            <div class="space-y-1 border-l border-white/5 pl-8">
                                <span class="text-[8px] font-black text-slate-600 uppercase tracking-[0.3em] italic">Operativos</span>
                                <div class="text-[11px] font-black text-white uppercase italic tracking-widest">{{ $game->players->count() }} Ativos</div>
                            </div>
                            <div class="space-y-1 border-l border-white/5 pl-8">
                                <span class="text-[8px] font-black text-slate-600 uppercase tracking-[0.3em] italic">Data Inicial</span>
                                <div class="text-[11px] font-black text-white uppercase italic tracking-widest">{{ $game->created_at->format('d/m/Y') }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Botões de Comando --}}
                    <div class="flex items-center gap-4 flex-wrap">
                        @if ($game->winners()->exists())
                            <button wire:click="openRankingModal('{{ $game->uuid }}')"
                                class="flex-1 lg:flex-none bg-amber-500/5 hover:bg-amber-500 border border-amber-500/20 text-amber-500 hover:text-white px-8 py-4 rounded-2xl transition-all text-[10px] font-black uppercase tracking-[0.2em] italic">
                                🏆 Ranking
                            </button>
                        @endif

                        @if ($game->status === 'draft')
                            <a href="{{ route('games.edit', $game) }}"
                                class="flex-1 lg:flex-none bg-blue-600/5 hover:bg-blue-600 border border-blue-600/20 text-blue-400 hover:text-white px-8 py-4 rounded-2xl transition-all text-[10px] font-black uppercase tracking-[0.2em] italic text-center">
                                Reconfigurar
                            </a>
                        @endif

                        @if ($game->status !== 'draft')
                            <a href="{{ route('games.play', $game) }}"
                                class="flex-1 lg:flex-none bg-white hover:bg-white/90 text-black px-10 py-4 rounded-2xl transition-all text-[10px] font-black uppercase tracking-[0.2em] italic text-center shadow-xl shadow-white/5">
                                Gerenciar Arena
                            </a>
                        @endif
                    </div>
                </div>
                
                {{-- Background Watermark --}}
                <div class="absolute -right-6 -bottom-8 text-white/[0.02] font-black text-[12rem] italic pointer-events-none select-none tracking-tighter">
                    {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                </div>
            </div>
        @empty
            <div class="bg-[#0b0d11] border border-white/5 border-dashed rounded-[3rem] py-32 text-center">
                <div class="text-6xl mb-8 opacity-20 grayscale">📂</div>
                <h3 class="text-2xl font-black text-white uppercase italic tracking-tighter">Arquivo de Missões Vazio</h3>
                <p class="text-slate-600 text-[10px] font-black uppercase tracking-[0.3em] mt-3 italic">
                    Nenhum protocolo detectado no banco de dados central.
                </p>
                <div class="mt-12">
                    <a href="{{ route('games.create') }}" class="text-blue-500 text-[10px] font-black uppercase tracking-[0.3em] hover:text-white transition-colors flex items-center justify-center gap-3 italic">
                        Inicializar Primeira Missão <span class="animate-bounce-x">→</span>
                    </a>
                </div>
            </div>
        @endforelse

        @if ($currentGames->hasPages())
            <div class="pt-10 flex justify-center">
                {{ $currentGames->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Hall da Vitória (Refinado) --}}
    @if ($showRankingModal && $selectedGame)
        <div class="fixed inset-0 bg-[#05070a]/95 backdrop-blur-2xl flex items-center justify-center z-[200] p-6"
            wire:click="closeRankingModal">
            <div class="bg-[#0b0d11] border border-white/10 rounded-[3rem] shadow-3xl max-w-3xl w-full max-h-[85vh] flex flex-col relative overflow-hidden"
                wire:click.stop>
                
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 via-amber-500 to-blue-600"></div>

                <div class="px-12 py-10 border-b border-white/5 flex justify-between items-center bg-white/[0.01]">
                    <div>
                        <h2 class="text-4xl font-black text-white tracking-tighter italic uppercase leading-none">HALL DA <span class="text-amber-500">VITÓRIA</span></h2>
                        <div class="flex items-center gap-3 mt-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] italic">{{ $selectedGame->name }} / Dossiê Final</p>
                        </div>
                    </div>
                    <button wire:click="closeRankingModal" class="text-slate-500 hover:text-white transition-colors text-4xl font-light leading-none">
                        &times;
                    </button>
                </div>

                <div class="p-12 overflow-y-auto flex-1 space-y-8 no-scrollbar">
                    @foreach ($gameRanking as $index => $data)
                        <div class="bg-white/[0.02] border border-white/5 rounded-[2rem] p-8 flex items-center gap-10 transition-all hover:bg-white/[0.04]">
                            
                            <div class="w-16 text-center flex-shrink-0">
                                @if($index === 0) <span class="text-6xl drop-shadow-[0_0_15px_rgba(245,158,11,0.5)]">🥇</span>
                                @elseif($index === 1) <span class="text-5xl">🥈</span>
                                @elseif($index === 2) <span class="text-5xl">🥉</span>
                                @else <span class="text-2xl font-black text-slate-800 italic">#{{ $index + 1 }}</span>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <h4 class="font-black text-xl text-white uppercase italic tracking-tighter mb-4">{{ $data['user']->name }}</h4>
                                <div class="flex flex-wrap gap-3">
                                    @foreach ($data['prizes'] as $prizeName)
                                        <span class="px-4 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border border-amber-500/20 bg-amber-500/10 text-amber-500 italic">
                                            🎁 {{ $prizeName }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="p-10 border-t border-white/5">
                    <button wire:click="closeRankingModal"
                        class="w-full bg-[#0b0d11] hover:bg-white text-slate-600 hover:text-black py-6 rounded-[2rem] font-black uppercase text-xs tracking-[0.4em] italic transition-all border border-white/10">
                        ENCERRAR RELATÓRIO
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>