<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\Game\Game;
use App\Models\Game\Winner;
use App\Models\Game\Prize;

new #[Layout('layouts.display')] class extends Component {
    public string $gameUuid;
    public Game $game;

    public array $currentDraws = [];
    public array $drawnNumbers = [];
    public array $roundWinners = [];
    public array $prizes = [];
    public $lastDrawId = null;

    public function mount(string $uuid): void
    {
        $this->gameUuid = $uuid;
        $this->reloadBaseGame();
        $this->loadGameData();
    }

    public function hydrate(): void
    {
        $this->game->refresh();
        $this->loadGameData();
    }

    #[On('echo:game.{game.uuid},.GameUpdated')]
    public function handleGameUpdate(): void
    {
        $this->reloadBaseGame();
        $this->loadGameData();
    }

    private function reloadBaseGame(): void
    {
        $this->game = Game::where('uuid', $this->gameUuid)
            ->with(['draws', 'players', 'creator', 'package'])
            ->firstOrFail();
    }

    private function loadGameData(): void
    {
        $round = $this->game->current_round;

        $this->drawnNumbers = $this->game->draws()
            ->where('round_number', $round)
            ->orderBy('number', 'asc')
            ->pluck('number')
            ->toArray();

        $drawsDesc = $this->game->draws()
            ->where('round_number', $round)
            ->orderBy('created_at', 'desc')
            ->get();

        $latest = $drawsDesc->first();
        $this->lastDrawId = $latest ? $latest->id : null;

        $this->currentDraws = $drawsDesc
            ->take(10)
            ->map(fn($draw) => [
                'id' => $draw->id,
                'number' => $draw->number,
                'created_at' => $draw->created_at->format('H:i:s'),
                'unique_key' => "draw-{$draw->id}-" . $draw->created_at->timestamp,
            ])
            ->values()
            ->toArray();

        $this->roundWinners = Winner::where('game_id', $this->game->id)
            ->where('round_number', $round)
            ->with('user')
            ->latest()
            ->get()
            ->map(fn($w) => ['name' => $w->user->name])
            ->toArray();

        $this->prizes = Prize::where('game_id', $this->game->id)
            ->orderBy('position', 'asc')
            ->get()
            ->map(function ($p) {
                $winnerEntry = Winner::where('prize_id', $p->id)->with('user')->first();
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'position' => $p->position,
                    'winner' => $winnerEntry ? $winnerEntry->user->name : null,
                ];
            })
            ->toArray();
    }

    public function getStatusColorProperty(): string
    {
        return match ($this->game->status) {
            'active' => 'blue',
            'waiting' => 'amber',
            'finished' => 'slate',
            'paused' => 'red',
            default => 'blue',
        };
    }

    public function getStatusLabelProperty(): string
    {
        return match ($this->game->status) {
            'active' => 'AO VIVO',
            'waiting' => 'AGUARDANDO',
            'finished' => 'FINALIZADA',
            'paused' => 'PAUSADA',
            default => 'RASCUNHO',
        };
    }
};
?>

<div class="min-h-screen w-full bg-[#05070a] text-slate-200 p-6 lg:p-10 overflow-hidden font-sans italic">
    {{-- LOADING OVERLAY SUTIL --}}
    <div wire:loading class="fixed top-10 right-10 z-[500]">
        <div class="flex items-center gap-3 bg-blue-600/10 border border-blue-500/20 px-6 py-3 rounded-full backdrop-blur-md">
            <div class="w-2 h-2 bg-blue-500 rounded-full animate-ping"></div>
            <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Sincronizando...</span>
        </div>
    </div>

    {{-- HEADER DE TRANSMISSÃO --}}
    <header class="max-w-[1800px] mx-auto mb-12 flex flex-col md:flex-row justify-between items-start md:items-end gap-8">
        <div class="relative">
            <div class="flex items-center gap-4 mb-4">
                <div class="h-[2px] w-12 bg-blue-600"></div>
                <span class="text-blue-500 font-black tracking-[0.4em] uppercase text-[10px]">Arena Display System</span>
                @if($game->status === 'active')
                    <div class="flex items-center gap-2 px-3 py-1 bg-red-600/10 border border-red-500/20 rounded-md">
                        <div class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></div>
                        <span class="text-[8px] font-black text-red-500 uppercase tracking-widest">LIVE</span>
                    </div>
                @endif
            </div>
            <h1 class="text-5xl lg:text-7xl font-black text-white tracking-tighter uppercase leading-none">
                {{ $game->name }}
            </h1>
        </div>

        <div class="flex items-center gap-6 bg-[#0b0d11] border border-white/5 p-6 rounded-[2rem] shadow-2xl">
            <div class="text-right border-r border-white/10 pr-6">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Rodada Atual</p>
                <p class="text-3xl font-black text-white leading-none">
                    {{ str_pad($game->current_round, 2, '0', STR_PAD_LEFT) }}<span class="text-slate-700 text-sm italic">/{{ $game->max_rounds }}</span>
                </p>
            </div>
            <div class="text-center">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Status da Arena</p>
                <div class="px-6 py-2 rounded-full text-[10px] font-black uppercase tracking-widest border bg-{{ $this->statusColor }}-600 border-{{ $this->statusColor }}-500 text-white shadow-lg shadow-{{ $this->statusColor }}-600/20">
                    {{ $this->statusLabel }}
                </div>
            </div>
        </div>
    </header>

    @if ($game->status === 'active')
        <div class="max-w-[1800px] mx-auto grid grid-cols-12 gap-10">
            
            {{-- COLUNA ESQUERDA: HISTÓRICO VERTICAL --}}
            <div class="col-span-12 lg:col-span-3 space-y-8">
                <div class="bg-[#0b0d11] border border-white/5 rounded-[2.5rem] p-8 shadow-2xl">
                    <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                        <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                        Últimos Números
                    </h3>

                    <div class="grid grid-cols-2 gap-4">
                        @foreach (collect($currentDraws)->take(8) as $index => $draw)
                            <div wire:key="{{ $draw['unique_key'] }}" 
                                 class="relative aspect-square rounded-[1.5rem] flex items-center justify-center transition-all duration-500
                                 {{ $index === 0 ? 'bg-blue-600 shadow-2xl shadow-blue-600/40 scale-105 z-10' : 'bg-[#161920] border border-white/5 opacity-40' }}">
                                <span class="text-4xl font-black {{ $index === 0 ? 'text-white' : 'text-slate-400' }}">
                                    {{ str_pad($draw['number'], 2, '0', STR_PAD_LEFT) }}
                                </span>
                                @if($index === 0)
                                    <div class="absolute -top-2 -right-2 bg-white text-blue-600 text-[8px] font-black px-2 py-1 rounded-md uppercase tracking-tighter">Novo</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- INFO DA SALA --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-[#0b0d11] border border-white/5 rounded-2xl p-6">
                        <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest block mb-2">Jogadores</span>
                        <span class="text-xl font-black text-white italic">{{ $game->players->count() }}</span>
                    </div>
                    <div class="bg-[#0b0d11] border border-white/5 rounded-2xl p-6">
                        <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest block mb-2">Sorteados</span>
                        <span class="text-xl font-black text-white italic">{{ count($drawnNumbers) }}</span>
                    </div>
                </div>
            </div>

            {{-- CENTRO: O GRANDE NÚMERO --}}
            <div class="col-span-12 lg:col-span-6 flex flex-col items-center justify-between min-h-[60vh]">
                @if ($lastDrawId && !empty($currentDraws))
                    <div wire:key="display-main-{{ $lastDrawId }}" class="relative group mt-10">
                        {{-- Efeito de Brilho --}}
                        <div class="absolute inset-0 bg-blue-600/20 blur-[120px] rounded-full animate-pulse"></div>
                        
                        <div class="relative bg-gradient-to-b from-[#161920] to-[#0b0d11] border border-white/10 w-80 h-80 lg:w-[28rem] lg:h-[28rem] rounded-full flex flex-col items-center justify-center shadow-[0_0_100px_rgba(0,0,0,0.5)]">
                            <span class="text-[12px] font-black text-blue-500 uppercase tracking-[0.5em] mb-4">Sorteado</span>
                            <div class="text-[10rem] lg:text-[15rem] font-black text-white leading-none tracking-tighter drop-shadow-2xl">
                                {{ str_pad($currentDraws[0]['number'], 2, '0', STR_PAD_LEFT) }}
                            </div>
                            <div class="absolute bottom-12 px-6 py-2 bg-white/5 rounded-full border border-white/10 backdrop-blur-md">
                                <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">#{{ count($drawnNumbers) }} de 75</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- GRID 75 NÚMEROS --}}
                <div class="w-full bg-[#0b0d11] border border-white/5 rounded-[3rem] p-8 mt-12">
                    <div class="grid grid-cols-15 gap-2">
                        @foreach (range(1, 75) as $num)
                            <div wire:key="grid-{{ $num }}-{{ in_array($num, $drawnNumbers) ? 'y' : 'n' }}" 
                                 class="aspect-square rounded-lg flex items-center justify-center text-sm font-black transition-all duration-700
                                 {{ in_array($num, $drawnNumbers) 
                                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30' 
                                    : 'bg-[#161920] text-slate-800 border border-white/[0.02]' }}">
                                {{ str_pad($num, 2, '0', STR_PAD_LEFT) }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- COLUNA DIREITA: PRÊMIOS E GANHADORES --}}
            <div class="col-span-12 lg:col-span-3 space-y-8">
                <div class="bg-[#0b0d11] border border-white/5 rounded-[2.5rem] p-8 shadow-2xl flex flex-col h-full">
                    <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                        <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                        Premiação Ativa
                    </h3>

                    <div class="space-y-4 overflow-y-auto no-scrollbar max-h-[70vh]">
                        @forelse ($prizes as $prize)
                            <div class="p-5 rounded-2xl transition-all border {{ $prize['winner'] ? 'bg-emerald-600 border-emerald-500 scale-[0.98] opacity-50' : 'bg-[#161920] border-white/5 shadow-xl' }}">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-[9px] font-black {{ $prize['winner'] ? 'text-white/60' : 'text-blue-500' }} uppercase">Slot #{{ $prize['position'] }}</span>
                                    @if($prize['winner'])
                                        <span class="bg-white/20 text-white text-[8px] px-2 py-0.5 rounded-md font-black italic">GANHO</span>
                                    @endif
                                </div>
                                <h4 class="text-lg font-black text-white uppercase italic truncate">{{ $prize['name'] }}</h4>
                                @if($prize['winner'])
                                    <div class="mt-3 pt-3 border-t border-white/10 flex items-center gap-2">
                                        <span class="text-xl">🏆</span>
                                        <span class="text-[11px] font-black text-white uppercase truncate">{{ $prize['winner'] }}</span>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-20">
                                <span class="text-[10px] font-black text-slate-700 uppercase tracking-widest italic">Nenhum prêmio na rodada</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- TELA DE ESPERA / STANDBY --}}
        <div class="max-w-4xl mx-auto mt-32">
            <div class="bg-[#0b0d11] border border-white/5 rounded-[4rem] p-24 text-center relative overflow-hidden">
                <div class="absolute inset-0 bg-blue-600/5 blur-[100px]"></div>
                <div class="relative z-10">
                    <div class="text-8xl mb-10">📺</div>
                    <h2 class="text-4xl font-black text-white uppercase italic tracking-tighter mb-6">Aguardando Início</h2>
                    <div class="flex items-center justify-center gap-4">
                        <div class="px-6 py-2 bg-white/5 border border-white/10 rounded-full">
                            <span class="text-blue-500 text-[10px] font-black uppercase tracking-widest italic">{{ $this->statusLabel }}</span>
                        </div>
                    </div>
                    <p class="mt-12 text-slate-500 text-[11px] font-black uppercase tracking-[0.4em] italic">O sorteio aparecerá automaticamente nesta tela</p>
                </div>
            </div>
        </div>
    @endif

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        @media (min-width: 1024px) {
            .grid-cols-15 { grid-template-columns: repeat(15, minmax(0, 1fr)); }
        }
    </style>
</div>