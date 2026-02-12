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
        $this->currentDraws = [];
        $this->drawnNumbers = [];
        $this->reloadBaseGame();
        $this->loadGameData();
    }

    private function reloadBaseGame(): void
    {
        $this->game = Game::where('uuid', $this->gameUuid)
            ->with(['draws', 'players', 'creator'])
            ->firstOrFail();
    }

    private function loadGameData(): void
    {
        $round = $this->game->current_round;

        // 1. Buscamos do banco (Já pedimos DESC)
        $drawsCollection = $this->game->draws()
            ->where('round_number', $round)
            ->orderBy('id', 'desc')
            ->get();

        // Lista completa para o grid 1-75 (mantemos a ordem do banco)
        $this->drawnNumbers = $drawsCollection->pluck('number')->toArray();

        // Definimos o ID do sorteio mais recente
        $latest = $drawsCollection->first();
        $this->lastDrawId = $latest ? $latest->id : null;

        // 2. Histórico: Forçamos a ordenação DESC na coleção e pegamos 10
        // Usamos sortByDesc('id') para garantir que o índice 0 seja o ID mais alto
        $this->currentDraws = $drawsCollection
            ->sortByDesc('id') 
            ->take(10)
            ->map(function ($draw) {
                return [
                    'id' => $draw->id,
                    'number' => $draw->number,
                    'unique_key' => "draw-{$draw->id}-v" . time(),
                ];
            })
            ->values() // Importante: reseta as chaves para 0, 1, 2...
            ->toArray();

        // 3. Vencedores
        $this->roundWinners = Winner::where('game_id', $this->game->id)
            ->where('round_number', $round)
            ->with('user')->latest()->get()->map(fn($w) => ['name' => $w->user->name])->toArray();

        // 4. Prêmios
        $this->prizes = Prize::where('game_id', $this->game->id)
            ->orderBy('position', 'asc')->get()->map(function ($p) {
                $winnerEntry = Winner::where('prize_id', $p->id)->with('user')->first();
                return [
                    'id' => $p->id, 'name' => $p->name, 'position' => $p->position,
                    'winner' => $winnerEntry ? $winnerEntry->user->name : null,
                ];
            })->toArray();
    }
};
?>

<div class="min-h-screen w-full bg-slate-950 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-indigo-900 via-slate-900 to-slate-950 p-4 lg:p-8 overflow-x-hidden text-white font-sans">

    {{-- Header --}}
    <header class="max-w-7xl mx-auto text-center mb-6 lg:mb-10">
        <h1 class="text-4xl lg:text-7xl font-black tracking-tighter mb-4 drop-shadow-2xl">
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500 italic">
                {{ strtoupper($game->name) }}
            </span>
        </h1>
        <div class="flex flex-wrap justify-center gap-3 lg:gap-6 text-sm lg:text-xl font-bold uppercase tracking-widest">
            <div class="bg-white/10 backdrop-blur-md border border-white/20 px-6 py-2 rounded-2xl shadow-xl">
                <span class="text-yellow-500">Rodada:</span> {{ $game->current_round }}/{{ $game->max_rounds }}
            </div>
            <div class="bg-white/10 backdrop-blur-md border border-white/20 px-6 py-2 rounded-2xl shadow-xl">
                <span class="text-blue-400">Jogadores:</span> {{ $game->players->count() }}
            </div>
        </div>
    </header>

    @if ($game->status === 'active')
        <div class="max-w-[1600px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10">

            {{-- Coluna 1: Histórico (Garantido DESC) --}}
            <aside class="lg:col-span-3 order-3 lg:order-1">
                <div class="bg-slate-900/50 backdrop-blur-xl border border-white/10 rounded-[2rem] p-6 sticky top-8 shadow-2xl">
                    <h3 class="text-lg font-black text-slate-400 uppercase tracking-widest mb-6 border-b border-white/10 pb-4 flex justify-between items-center">
                        <span>Histórico</span>
                        <span class="bg-yellow-500 text-slate-950 px-3 py-1 rounded-full text-xs font-bold">#{{ count($drawnNumbers) }}</span>
                    </h3>
                    <div class="grid grid-cols-3 lg:grid-cols-2 gap-4">
                        @foreach ($currentDraws as $index => $draw)
                            <div wire:key="hist-{{ $draw['unique_key'] }}"
                                class="bg-gradient-to-br from-white/10 to-white/5 border border-white/10 rounded-2xl p-4 flex items-center justify-center transition-all duration-500 
                                {{ $index === 0 ? 'scale-110 ring-2 ring-yellow-500 shadow-lg shadow-yellow-500/20 z-10' : 'opacity-40' }}">
                                <span class="text-3xl lg:text-5xl font-black">
                                    {{ str_pad($draw['number'], 2, '0', STR_PAD_LEFT) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>

            {{-- Coluna 2: Destaque Central --}}
            <main class="lg:col-span-6 order-1 lg:order-2 flex flex-col items-center">
                @if ($lastDrawId && isset($currentDraws[0]))
                    <div wire:key="main-card-{{ $lastDrawId }}-{{ count($drawnNumbers) }}" class="w-full flex justify-center">
                        <div class="relative group bounce-in">
                            <div class="absolute inset-0 bg-purple-600 blur-[100px] opacity-30"></div>
                            <div class="relative bg-gradient-to-b from-white to-slate-200 rounded-[5rem] lg:rounded-[8rem] p-10 lg:p-24 shadow-[0_0_80px_rgba(0,0,0,0.5)]">
                                <div class="text-center">
                                    <span class="text-xl lg:text-3xl font-black text-slate-400 uppercase tracking-[0.5em] mb-4 block italic">Sorteado</span>
                                    <div class="text-[12rem] lg:text-[22rem] font-black text-slate-950 leading-none tracking-tighter">
                                        {{-- Agora o index 0 é garantido como o mais novo --}}
                                        {{ str_pad($currentDraws[0]['number'], 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="h-[400px] lg:h-[600px] flex items-center justify-center text-3xl lg:text-5xl text-white/40 font-black animate-pulse uppercase">
                        Aguardando Sorteio...
                    </div>
                @endif

                {{-- Grid 75 --}}
                <div class="mt-12 w-full hidden lg:block">
                    <div class="bg-white/5 border border-white/10 rounded-[2.5rem] p-8">
                        <div class="grid grid-cols-15 gap-2">
                            @foreach (range(1, 75) as $num)
                                <div wire:key="ball-{{ $num }}-{{ in_array($num, $drawnNumbers) ? 'on' : 'off' }}"
                                    class="aspect-square rounded-lg flex items-center justify-center font-black text-lg transition-all duration-500 {{ in_array($num, $drawnNumbers) ? 'bg-yellow-500 text-slate-950 scale-110 shadow-lg shadow-yellow-500/40' : 'bg-white/5 text-white/20' }}">
                                    {{ $num }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </main>

            {{-- Coluna 3: Prêmios --}}
            <aside class="lg:col-span-3 order-2 lg:order-3 flex flex-col gap-6 max-h-[85vh]">
                <div class="bg-slate-900/50 backdrop-blur-xl border border-white/10 rounded-[2rem] p-6 shadow-2xl flex flex-col min-h-0">
                    <h3 class="text-lg font-black text-slate-400 uppercase tracking-widest mb-6 border-b border-white/10 pb-4">Prêmios</h3>
                    <div class="space-y-4 overflow-y-auto pr-2 custom-scrollbar" style="max-height: 40vh;">
                        @foreach ($prizes as $prize)
                            <div wire:key="prz-{{ $prize['id'] }}-{{ $prize['winner'] ? 'won' : 'open' }}"
                                class="relative overflow-hidden transition-all duration-500 {{ $prize['winner'] ? 'bg-green-500/20 border-green-500/50' : 'bg-white/5 border-white/10' }} border-2 rounded-2xl p-4">
                                <div class="font-black text-lg {{ $prize['winner'] ? 'text-green-400' : 'text-slate-200' }}">
                                    {{ $prize['position'] }}º {{ $prize['name'] }}
                                </div>
                                <div class="text-sm font-bold uppercase tracking-widest mt-1">
                                    {!! $prize['winner'] ? "<span class='text-white animate-pulse'>🏆 {$prize['winner']}</span>" : "<span class='text-slate-500 italic'>Disponível</span>" !!}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>
    @endif
</div>

<style>
    @media (min-width: 1024px) { .grid-cols-15 { grid-template-columns: repeat(15, minmax(0, 1fr)); } }
    @keyframes number-pop {
        0% { transform: scale(0.4); opacity: 0; filter: blur(10px); }
        60% { transform: scale(1.05); }
        100% { transform: scale(1); opacity: 1; filter: blur(0); }
    }
    .bounce-in { animation: number-pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
</style>