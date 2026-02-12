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

    // 1. PARA O GRID DE 75 NÚMEROS - ordem ASC
    $this->drawnNumbers = $this->game->draws()
        ->where('round_number', $round)
        ->orderBy('number', 'asc')
        ->pluck('number')
        ->toArray();

    // 2. PARA O HISTÓRICO E ÚLTIMO NÚMERO - USAR CREATED_AT DESC
    $drawsDesc = $this->game->draws()
        ->where('round_number', $round)
        ->orderBy('created_at', 'desc')  // MUDAR DE 'id' PARA 'created_at'
        ->get();

    // DEBUG - Verificar ordem
    \Log::info('Draws em ordem DESC:', $drawsDesc->pluck('number', 'created_at')->toArray());

    // Último sorteado = primeiro da coleção DESC
    $latest = $drawsDesc->first();
    $this->lastDrawId = $latest ? $latest->id : null;

    // Pega os 10 primeiros (mais recentes)
    $this->currentDraws = $drawsDesc
        ->take(10)
        ->map(fn($draw) => [
            'id' => $draw->id,
            'number' => $draw->number,
            'created_at' => $draw->created_at->format('H:i:s'), // DEBUG
            'unique_key' => "draw-{$draw->id}-" . $draw->created_at->timestamp,
        ])
        ->values()
        ->toArray();

    // DEBUG - Verificar currentDraws
    \Log::info('currentDraws[0]:', [$this->currentDraws[0] ?? null]);

    // 3. Vencedores e prêmios
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
            'active' => 'emerald',
            'waiting' => 'amber',
            'finished' => 'slate',
            'paused' => 'red',
            default => 'blue',
        };
    }

    public function getStatusLabelProperty(): string
    {
        return match ($this->game->status) {
            'active' => 'EM OPERAÇÃO',
            'waiting' => 'AGUARDANDO',
            'finished' => 'FINALIZADA',
            'paused' => 'PAUSADA',
            default => 'RASCUNHO',
        };
    }
};
?>

<div class="min-h-screen w-full bg-[#0b0d11] text-slate-200 p-4 sm:p-6 lg:p-8 overflow-x-hidden">

    {{-- Header --}}
    <header class="max-w-7xl mx-auto mb-8 sm:mb-10 lg:mb-12">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 sm:gap-6 mb-4 sm:mb-6">
            <div>
                <div class="flex items-center gap-3 mb-2 sm:mb-3">
                    <div class="h-[2px] w-8 sm:w-10 lg:w-12 bg-blue-600"></div>
                    <span
                        class="text-blue-500 font-black tracking-[0.2em] sm:tracking-[0.3em] uppercase text-[8px] sm:text-[9px] lg:text-[10px] italic">
                        ARENA {{ $game->status === 'active' ? 'AO VIVO' : 'EM ESPERA' }}
                    </span>
                </div>
                <h1
                    class="text-3xl sm:text-4xl lg:text-5xl xl:text-6xl font-black text-white tracking-tighter uppercase italic leading-none">
                    {{ $game->name }}
                </h1>
            </div>

            <div class="flex items-center gap-3 sm:gap-4">
                <div class="bg-[#161920] border border-white/5 rounded-xl px-4 sm:px-5 py-2 sm:py-3">
                    <p class="text-[7px] sm:text-[8px] font-black text-slate-500 uppercase italic">Rodada</p>
                    <p class="text-lg sm:text-xl font-black text-white italic leading-none">
                        {{ $game->current_round }}<span class="text-slate-700 text-xs">/{{ $game->max_rounds }}</span>
                    </p>
                </div>
                <div
                    class="px-4 sm:px-5 py-2 sm:py-3 rounded-xl text-[8px] sm:text-[9px] lg:text-[10px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] border 
                    bg-{{ $this->statusColor }}-500/10 border-{{ $this->statusColor }}-500/20 text-{{ $this->statusColor }}-500">
                    {{ $this->statusLabel }}
                </div>
            </div>
        </div>

        {{-- Stats Bar --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mt-4 sm:mt-6">
            <div class="bg-[#161920] border border-white/5 rounded-xl sm:rounded-2xl p-3 sm:p-4">
                <span class="text-[8px] sm:text-[9px] font-black text-slate-600 uppercase italic">Pacote</span>
                <p class="text-[10px] sm:text-xs font-black text-white uppercase italic truncate">
                    {{ $game->package->name ?? 'Padrão' }}</p>
            </div>
            <div class="bg-[#161920] border border-white/5 rounded-xl sm:rounded-2xl p-3 sm:p-4">
                <span class="text-[8px] sm:text-[9px] font-black text-slate-600 uppercase italic">Código</span>
                <p class="text-[10px] sm:text-xs font-black text-blue-500 font-mono uppercase tracking-widest">
                    {{ $game->invite_code }}</p>
            </div>
            <div class="bg-[#161920] border border-white/5 rounded-xl sm:rounded-2xl p-3 sm:p-4">
                <span class="text-[8px] sm:text-[9px] font-black text-slate-600 uppercase italic">Jogadores</span>
                <p class="text-[10px] sm:text-xs font-black text-white uppercase italic">{{ $game->players->count() }}
                    Ativos</p>
            </div>
            <div class="bg-[#161920] border border-white/5 rounded-xl sm:rounded-2xl p-3 sm:p-4">
                <span class="text-[8px] sm:text-[9px] font-black text-slate-600 uppercase italic">Sorteados</span>
                <p class="text-[10px] sm:text-xs font-black text-white uppercase italic">{{ count($drawnNumbers) }}/75
                </p>
            </div>
        </div>
    </header>

    @if ($game->status === 'active')
        <div class="max-w-[1600px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">

            {{-- Coluna 1: Histórico --}}
            <aside class="lg:col-span-3 order-3 lg:order-1">
                <div
                    class="bg-[#161920] border border-white/5 rounded-2xl sm:rounded-[2rem] p-5 sm:p-6 lg:sticky lg:top-8 shadow-xl">
                    <div class="flex items-center justify-between mb-5 pb-3 border-b border-white/5">
                        <h3 class="text-[10px] sm:text-[11px] font-black text-white uppercase italic tracking-widest">
                            ÚLTIMOS SORTEADOS
                        </h3>
                        <span class="text-[9px] sm:text-[10px] font-black text-slate-600">
                            {{ count($drawnNumbers) }}/75
                        </span>
                    </div>

                    @if(!empty($currentDraws))
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach ($currentDraws as $index => $draw)
                                <div wire:key="{{ $draw['unique_key'] }}"
                                    class="bg-[#0b0d11] border border-white/5 rounded-xl p-3 sm:p-4 flex items-center justify-center transition-all duration-300
                                                {{ $index === 0 ? 'ring-2 ring-blue-500 border-blue-500/50 bg-blue-600/10' : 'opacity-60' }}">
                                    <span
                                        class="text-xl sm:text-2xl lg:text-3xl font-black {{ $index === 0 ? 'text-blue-500' : 'text-slate-500' }}">
                                        {{ str_pad($draw['number'], 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        @if(count($drawnNumbers) > 10)
                            <div class="mt-4 text-center">
                                <span class="text-[8px] font-black text-slate-700 uppercase italic">
                                    + {{ count($drawnNumbers) - 10 }} números
                                </span>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-8">
                            <span class="text-[10px] font-black text-slate-700 uppercase italic tracking-widest">
                                Aguardando sorteio...
                            </span>
                        </div>
                    @endif
                </div>
            </aside>

            {{-- Coluna 2: Destaque Central --}}
            <main class="lg:col-span-6 order-1 lg:order-2 flex flex-col items-center">
                {{-- Destaque Central --}}
@if ($lastDrawId && !empty($currentDraws))
    @php
        // FORÇAR O PRIMEIRO COMO MAIS RECENTE
        $sortedDraws = collect($currentDraws)->sortByDesc('created_at')->values();
        $latestNumber = $sortedDraws[0]['number'] ?? $currentDraws[0]['number'];
    @endphp
    <div wire:key="main-number-{{ $lastDrawId }}-{{ now()->timestamp }}" class="w-full flex justify-center">
        <div class="relative group">
            <div class="absolute inset-0 bg-blue-600/20 blur-[100px] rounded-full"></div>
            <div class="relative bg-gradient-to-b from-[#1c2128] to-[#161920] border border-white/10 rounded-[3rem] sm:rounded-[4rem] lg:rounded-[6rem] p-8 sm:p-12 lg:p-16 shadow-2xl">
                <div class="text-center">
                    <span class="text-[10px] sm:text-xs font-black text-slate-500 uppercase tracking-[0.3em] sm:tracking-[0.4em] mb-2 sm:mb-3 block italic">
                        ÚLTIMO NÚMERO
                    </span>
                    <div class="text-8xl sm:text-9xl lg:text-[10rem] xl:text-[12rem] font-black text-white leading-none tracking-tighter">
                        {{ str_pad($latestNumber, 2, '0', STR_PAD_LEFT) }}
                    </div>
                    <div class="mt-3 sm:mt-4 inline-block px-4 py-1.5 bg-white/5 rounded-full">
                        <span class="text-[8px] sm:text-[9px] font-black text-slate-500 uppercase italic">
                            Sequência #{{ count($drawnNumbers) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

                {{-- Grid 75 --}}
                <div class="mt-8 sm:mt-10 lg:mt-12 w-full">
                    <div class="bg-[#161920] border border-white/5 rounded-2xl sm:rounded-[2rem] p-4 sm:p-6">
                        <div
                            class="grid grid-cols-5 xs:grid-cols-6 sm:grid-cols-8 md:grid-cols-10 lg:grid-cols-12 xl:grid-cols-15 gap-1.5 sm:gap-2">
                            @foreach (range(1, 75) as $num)
                                            <div wire:key="ball-{{ $num }}-{{ in_array($num, $drawnNumbers) ? 'drawn' : 'pending' }}" class="aspect-square rounded-lg flex items-center justify-center font-black text-xs sm:text-sm lg:text-base transition-all duration-300
                                                        {{ in_array($num, $drawnNumbers)
                                ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20'
                                : 'bg-white/5 text-slate-700 border border-white/5' }}">
                                                {{ $num }}
                                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </main>

            {{-- Coluna 3: Prêmios e Vencedores --}}
            <aside class="lg:col-span-3 order-2 lg:order-3 flex flex-col gap-6">

                {{-- Prêmios --}}
                <div class="bg-[#161920] border border-white/5 rounded-2xl sm:rounded-[2rem] p-5 sm:p-6 shadow-xl">
                    <h3
                        class="text-[10px] sm:text-[11px] font-black text-white uppercase italic tracking-widest mb-5 pb-3 border-b border-white/5">
                        PRÊMIOS - RODADA {{ $game->current_round }}
                    </h3>

                    <div class="space-y-3 max-h-[40vh] overflow-y-auto pr-1 custom-scrollbar">
                        @forelse ($prizes as $prize)
                            <div wire:key="prize-{{ $prize['id'] }}-{{ $prize['winner'] ? 'claimed' : 'available' }}" class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border transition-all
                                        {{ $prize['winner'] ? 'border-emerald-500/20 bg-emerald-500/5' : 'border-white/5' }}">
                                <div class="flex items-center gap-3">
                                    <span class="text-[9px] sm:text-[10px] font-black text-slate-600 w-5">
                                        #{{ $prize['position'] }}
                                    </span>
                                    <div>
                                        <p class="text-xs sm:text-sm font-black text-white uppercase italic tracking-tight">
                                            {{ $prize['name'] }}
                                        </p>
                                        @if($prize['winner'])
                                            <p class="text-[8px] sm:text-[9px] font-bold text-emerald-500 uppercase mt-0.5">
                                                {{ $prize['winner'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                @if($prize['winner'])
                                    <span class="text-emerald-500 text-[10px] font-black">✓</span>
                                @else
                                    <span class="text-slate-700 text-[8px] font-black uppercase">Disponível</span>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-6">
                                <p class="text-[10px] font-black text-slate-700 uppercase tracking-widest italic">
                                    Nenhum prêmio cadastrado
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Vencedores da Rodada --}}
                @if(!empty($roundWinners))
                    <div class="bg-[#161920] border border-white/5 rounded-2xl sm:rounded-[2rem] p-5 sm:p-6 shadow-xl">
                        <div class="flex items-center justify-between mb-5 pb-3 border-b border-white/5">
                            <h3 class="text-[10px] sm:text-[11px] font-black text-white uppercase italic tracking-widest">
                                VENCEDORES
                            </h3>
                            <span class="text-[9px] font-black text-amber-500">
                                {{ count($roundWinners) }}
                            </span>
                        </div>

                        <div class="space-y-2">
                            @foreach($roundWinners as $winner)
                                <div class="flex items-center gap-2 py-2 border-b border-white/5 last:border-0">
                                    <span class="w-6 h-6 rounded-lg bg-amber-500/10 flex items-center justify-center">
                                        <span class="text-[9px] font-black text-amber-500">🏆</span>
                                    </span>
                                    <span class="text-[10px] sm:text-[11px] font-black text-white uppercase italic tracking-tight">
                                        {{ $winner['name'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    @else
        {{-- Status Não Ativo --}}
        <div class="max-w-7xl mx-auto mt-20 text-center">
            <div class="bg-[#161920] border border-white/5 rounded-[3rem] p-16 sm:p-20">
                <div class="text-7xl sm:text-8xl mb-6 opacity-20">📺</div>
                <h2 class="text-3xl sm:text-4xl font-black text-white uppercase italic tracking-tighter mb-4">
                    ARENA {{ $this->statusLabel }}
                </h2>
                <p class="text-sm sm:text-base text-slate-600 font-black uppercase tracking-[0.3em] italic">
                    O display será ativado quando a partida iniciar
                </p>
            </div>
        </div>
    @endif

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .animate-spin {
            animation: spin 1s linear infinite;
        }

        @media (min-width: 1280px) {
            .xl\:grid-cols-15 {
                grid-template-columns: repeat(15, minmax(0, 1fr));
            }
        }
    </style>
</div>