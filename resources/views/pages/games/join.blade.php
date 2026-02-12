<?php

use Livewire\Attributes\{On, Computed};
use Livewire\Component;
use App\Models\Game\{Game, Player, Card, Winner};
use App\Events\GameUpdated;
use Illuminate\Support\Facades\{Log, DB};

new class extends Component {
    // Propriedades Tipificadas para melhor performance e clareza
    public Game $game;
    public ?Player $player = null;
    public array $cards = [];
    public ?int $lastDrawnNumber = null;
    public array $recentDraws = [];
    public int $totalDraws = 0;

    /**
     * Inicialização do Componente
     */
    public function mount(string $invite_code)
    {
        $this->game = Game::where('invite_code', $invite_code)
            ->with(['creator:id,name', 'package:id,max_players'])
            ->firstOrFail();

        $this->player = $this->game
            ->players()
            ->where('user_id', auth()->id())
            ->first();

        $this->syncGameState();
    }

    /**
     * Hook de Hidratação: Executado em cada requisição Livewire.
     * Limpa o estado interno para garantir que os dados reflitam o banco.
     */
    public function hydrate(): void
    {
        $this->syncGameState();
    }

    /**
     * Ouvinte do Broadcast e Re-tentativa
     */
    #[On('echo:game.{game.uuid},.GameUpdated')]
    #[On('retry-load-cards')]
    public function handleUpdate(): void
    {
        $this->syncGameState();

        // Fallback para latência do SQLite: se o jogo está ativo mas as cartelas
        // da rodada atual ainda não aparecem, tenta novamente em 500ms.
        if ($this->player && empty($this->cards) && $this->game->status === 'active') {
            $this->dispatch('retry-load-cards');
        }
    }

    /**
     * MÉTODO CENTRAL DE SINCRONIZAÇÃO
     * Unifica a carga de dados para evitar vazamento de memória e inconsistência.
     */
    private function syncGameState(): void
    {
        // 1. Atualiza o Model principal e limpa relações em cache
        $this->game->refresh();
        $this->game->unsetRelations();

        // 2. Sincroniza o Player (caso tenha acabado de entrar)
        if (!$this->player) {
            $this->player = Player::where('game_id', $this->game->id)
                ->where('user_id', auth()->id())
                ->first();
        }

        // 3. Carrega Sorteios da Rodada Atual
        $draws = $this->game->draws()->where('round_number', $this->game->current_round)->orderByDesc('created_at')->get();

        $this->lastDrawnNumber = $draws->first()?->number;
        $this->recentDraws = $draws->take(10)->pluck('number')->toArray();
        $this->totalDraws = $draws->count();

        // 4. Carrega Cartelas (Direto do Banco para ignorar cache do Eloquent)
        if ($this->player) {
            $this->cards = Card::where('player_id', $this->player->id)->where('round_number', $this->game->current_round)->get()->all();
        }
    }

    /**
     * ENTRAR NA PARTIDA
     */
    public function join()
    {
        if ($this->player || !$this->game->canJoin()) {
            return;
        }

        // O erro de "5 cartelas" nasce aqui se houver um Observer ou Boot no model Player
        $this->player = Player::create([
            'game_id' => $this->game->id,
            'user_id' => auth()->id(),
            'joined_at' => now(),
        ]);

        broadcast(new GameUpdated($this->game))->toOthers();
        $this->syncGameState();
    }

    /**
     * MARCAR NÚMERO NA CARTELA
     */
    public function markNumber(int $cardIndex, int $number)
    {
        if (!$this->player || $this->game->status !== 'active') {
            return;
        }

        $card = $this->cards[$cardIndex] ?? null;
        if (!$card || in_array($number, $card->marked ?? [])) {
            return;
        }

        // Validação de Segurança: O número existe na cartela e foi sorteado?
        $drawnNumbers = $this->game->getCurrentRoundDrawnNumbers();

        if (!in_array($number, $card->numbers)) {
            $this->addError('game', 'Número não pertence a esta cartela.');
            return;
        }

        if (!in_array($number, $drawnNumbers)) {
            $this->addError('game', 'Este número ainda não foi sorteado.');
            return;
        }

        // Persiste a marcação
        $card->markNumber($number);

        // Verifica Bingo
        if ($card->checkBingo($drawnNumbers)) {
            $card->update(['is_bingo' => true]);
            broadcast(new GameUpdated($this->game))->toOthers();
            session()->flash('success', '🎉 BINGO! Aguarde a validação.');
        }

        $this->syncGameState();
    }

    /**
     * Vencedores da Rodada (Calculado apenas quando necessário)
     */
    #[Computed]
    public function cardWinners(): array
    {
        if (!$this->player || empty($this->cards)) {
            return [];
        }

        return Winner::whereIn('card_id', collect($this->cards)->pluck('id'))
            ->where('round_number', $this->game->current_round)
            ->pluck('card_id')
            ->toArray();
    }
};
?>

<div class="min-h-screen bg-[#05070a] py-8 text-slate-300 font-sans selection:bg-blue-500/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- Header de Missão (Operativo) --}}
        <header class="mb-10">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="h-[2px] w-12 bg-blue-600 shadow-[0_0_10px_#3b82f6]"></span>
                        <span class="text-blue-500 font-black tracking-[0.3em] uppercase text-[10px] italic">Operation Dashboard</span>
                    </div>
                    <h1 class="text-4xl lg:text-5xl font-black text-white tracking-tighter uppercase italic leading-none drop-shadow-2xl">
                        {{ $game->name }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-4 lg:gap-8 text-[10px] font-black uppercase tracking-widest text-slate-500 mt-5 italic">
                        <span class="flex items-center gap-2">
                            <span class="text-blue-500/50">ID:</span> {{ $game->invite_code }}
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="text-blue-500/50">Ciclo:</span> {{ $game->current_round }}/{{ $game->max_rounds }}
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="text-blue-500/50">Operativos:</span> {{ $game->players->count() }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col items-end gap-3">
                    <div class="inline-flex items-center px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] border transition-all shadow-2xl
                        @if ($game->status === 'active') bg-emerald-500/10 border-emerald-500/20 text-emerald-500
                        @elseif($game->status === 'waiting') bg-yellow-500/10 border-yellow-500/20 text-yellow-500
                        @else bg-white/5 border-white/10 text-slate-500 @endif">
                        @if ($game->status === 'active')
                            <span class="relative flex h-2 w-2 mr-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500 shadow-[0_0_8px_#10b981]"></span>
                            </span>
                        @endif
                        {{ $game->status }}
                    </div>
                </div>
            </div>

            {{-- Terminal de Mensagens --}}
            @if (session()->has('success') || session()->has('error'))
                <div class="mt-8 animate-in slide-in-from-top-4 duration-500">
                    <div class="px-6 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest italic border 
                        {{ session('success') ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border-red-500/20 text-red-400' }}">
                        {{ session('success') ?? session('error') }}
                    </div>
                </div>
            @endif
        </header>

        @if (!$player)
            {{-- Landing de Entrada (Join Arena) --}}
            <div class="max-w-2xl mx-auto py-12">
                <div class="bg-[#0b0d11] rounded-[3rem] shadow-2xl p-10 lg:p-16 text-center border border-white/5 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-blue-600 to-indigo-500 shadow-[0_0_15px_rgba(37,99,235,0.5)]"></div>
                    
                    <div class="text-6xl mb-8 filter drop-shadow-[0_0_20px_rgba(37,99,235,0.3)]">🛡️</div>
                    <h2 class="text-3xl font-black text-white uppercase italic tracking-tighter mb-10">Inicializar Operação</h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-12">
                        <div class="bg-white/[0.02] border border-white/5 p-5 rounded-2xl">
                            <div class="text-[8px] font-black text-slate-500 uppercase mb-2 italic">Dotação</div>
                            <div class="text-base font-black text-white italic tracking-tighter">{{ $game->package->cards_per_player ?? 1 }} UNIT</div>
                        </div>
                        <div class="bg-white/[0.02] border border-white/5 p-5 rounded-2xl">
                            <div class="text-[8px] font-black text-slate-500 uppercase mb-2 italic">Arena</div>
                            <div class="text-base font-black text-white italic tracking-tighter">{{ $game->players->count() }}/{{ $game->package->max_players }}</div>
                        </div>
                        <div class="bg-white/[0.02] border border-white/5 p-5 rounded-2xl">
                            <div class="text-[8px] font-black text-slate-500 uppercase mb-2 italic">Targets</div>
                            <div class="text-base font-black text-white italic tracking-tighter">{{ $game->prizes->count() }} ITENS</div>
                        </div>
                    </div>

                    @php
                        $isFull = $game->players->count() >= $game->package->max_players;
                    @endphp

                    @if ($isFull)
                        <div class="bg-red-500/10 border border-red-500/20 rounded-2xl p-6 mb-6">
                            <span class="text-[10px] font-black text-red-500 uppercase tracking-widest italic">Capacidade Máxima Atingida</span>
                        </div>
                    @else
                        <button wire:click="join" class="w-full bg-blue-600 hover:bg-blue-500 text-white px-8 py-5 rounded-2xl font-black uppercase text-xs tracking-[0.3em] italic transition-all shadow-xl shadow-blue-900/40 active:scale-95 group">
                            Solicitar Acesso à Arena <span class="inline-block group-hover:translate-x-2 transition-transform ml-2">→</span>
                        </button>
                    @endif
                </div>
            </div>
        @else
            {{-- Dashboard de Jogo --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                {{-- Área de Cartelas --}}
                <div class="lg:col-span-2 space-y-8">
                    <div class="flex items-center justify-between border-b border-white/5 pb-4">
                        <h2 class="text-[11px] font-black text-white uppercase tracking-[0.4em] italic flex items-center gap-3">
                            <span class="w-2 h-2 bg-blue-600 rounded-full animate-pulse"></span>
                            Dispositivos de Jogo
                        </h2>
                    </div>

                    @if (empty($cards))
                        <div wire:key="waiting-round-{{ $game->current_round }}" class="bg-[#0b0d11] border-2 border-dashed border-white/5 rounded-[2.5rem] p-16 text-center">
                            <div class="text-4xl mb-6 opacity-20">🛰️</div>
                            <p class="text-slate-500 font-black uppercase italic tracking-tighter text-lg">Aguardando Sincronização de Ciclo</p>
                            <p class="text-[9px] text-slate-700 font-black uppercase tracking-widest mt-2">O Host está gerando suas unidades para a Rodada {{ $game->current_round }}</p>
                        </div>
                    @else
                        <div wire:key="cards-round-{{ $game->current_round }}" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            @foreach ($cards as $index => $card)
                                @php $hasWon = in_array($card->id, $this->cardWinners); @endphp
                                <div wire:key="card-{{ $card->id }}" 
                                    class="bg-[#0b0d11] rounded-[2.5rem] p-8 border border-white/5 transition-all duration-500 relative overflow-hidden
                                    {{ $hasWon ? 'border-emerald-500/50 shadow-[0_0_40px_rgba(16,185,129,0.15)]' : '' }}">
                                    
                                    @if ($hasWon)
                                        <div class="absolute inset-0 bg-emerald-500/[0.03] animate-pulse"></div>
                                        <div class="relative z-10 bg-emerald-600 text-white text-center py-4 rounded-2xl mb-8 shadow-xl shadow-emerald-900/40">
                                            <div class="font-black text-2xl italic tracking-tighter uppercase">BINGO!</div>
                                            <div class="text-[8px] font-black uppercase tracking-widest mt-1 opacity-80">Objetivo Concluído</div>
                                        </div>
                                    @endif

                                    <div class="flex justify-between items-center mb-8 relative z-10">
                                        <div>
                                            <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest italic">Unit #0{{ $index + 1 }}</span>
                                            <div class="text-[8px] font-mono text-slate-800 mt-1">{{ substr($card->uuid, 0, 8) }}</div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            @php $pct = count($card->numbers) > 0 ? count($card->marked ?? []) / count($card->numbers) : 0; @endphp
                                            <span class="text-[10px] font-black text-blue-500 italic">{{ round($pct * 100) }}%</span>
                                            <div class="h-1.5 w-16 bg-white/5 rounded-full overflow-hidden">
                                                <div class="h-full bg-blue-600 shadow-[0_0_10px_#3b82f6] transition-all duration-1000" style="width: {{ $pct * 100 }}%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 mb-8 relative z-10" style="grid-template-columns: repeat({{ $game->card_size === 9 ? 3 : 5 }}, minmax(0, 1fr))">
                                        @foreach ($card->numbers as $number)
                                            @php $isMarked = in_array($number, $card->marked ?? []); @endphp
                                            <button wire:click="markNumber({{ $index }}, {{ $number }})"
                                                @if ($game->status !== 'active' || $hasWon) disabled @endif
                                                class="aspect-square rounded-xl flex items-center justify-center font-black text-xl lg:text-2xl transition-all duration-300 relative
                                                    {{ $isMarked ? 'bg-blue-600 text-white shadow-[0_0_20px_rgba(37,99,235,0.4)] scale-90' : 'bg-white/[0.02] text-slate-600 border border-white/5 hover:border-blue-500/30 hover:text-blue-400' }}
                                                    {{ $game->status !== 'active' ? 'opacity-20 cursor-not-allowed' : '' }}">
                                                {{ $number }}
                                                @if($isMarked)
                                                    <span class="absolute -top-1 -right-1 flex h-3 w-3">
                                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                                        <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-300"></span>
                                                    </span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>

                                    <div class="flex items-center justify-between text-[9px] font-black uppercase tracking-widest italic text-slate-700">
                                        <span>Capturas: {{ count($card->marked ?? []) }} / {{ count($card->numbers) }}</span>
                                        <span class="text-blue-500/50">Status: Sincronizado</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Sidebar Tática --}}
                <aside class="space-y-8">
                    {{-- Telemetria --}}
                    <div class="bg-[#0b0d11] border border-white/5 rounded-[2.5rem] p-8 shadow-2xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/[0.02] blur-3xl"></div>
                        <h3 class="text-[10px] font-black text-white uppercase tracking-[0.4em] mb-8 italic flex items-center gap-3">
                            <span class="w-2 h-2 bg-blue-600 rounded-full shadow-[0_0_5px_#3b82f6]"></span> 
                            Telemetria
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-4 bg-white/[0.01] border border-white/5 rounded-2xl">
                                <span class="text-[9px] font-black text-slate-600 uppercase italic">Operativos</span>
                                <span class="font-black text-white italic text-sm">{{ $game->players->count() }}</span>
                            </div>
                            @if ($game->status === 'active' && $game->show_drawn_to_players)
                                <div class="flex justify-between items-center p-4 bg-blue-600/5 border border-blue-600/10 rounded-2xl">
                                    <span class="text-[9px] font-black text-blue-500 uppercase italic">Globo Manifest</span>
                                    <span class="font-black text-white italic text-sm">{{ $totalDraws }}<span class="text-slate-800 mx-1">/</span>75</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Lista de Objetivos (Prêmios) --}}
                    <div class="bg-[#0b0d11] border border-white/5 rounded-[2.5rem] p-8 shadow-2xl">
                        <h3 class="text-[10px] font-black text-white uppercase tracking-[0.4em] mb-8 italic flex items-center gap-3">
                            <span class="w-2 h-2 bg-amber-500 rounded-full shadow-[0_0_5px_#f59e0b]"></span>
                            Objetivos
                        </h3>
                        <div class="space-y-4">
                            @foreach($game->prizes->sortBy('position') as $prize)
                                @php 
                                    $isNext = !$prize->is_claimed && $game->getNextAvailablePrize()?->id === $prize->id;
                                    $isClaimed = $prize->is_claimed;
                                @endphp
                                <div class="p-5 rounded-[1.5rem] border transition-all duration-500 relative
                                    {{ $isClaimed ? 'bg-emerald-500/[0.02] border-emerald-500/20 opacity-50' : 'bg-white/[0.01] border-white/5' }}
                                    {{ $isNext ? 'border-blue-500/40 bg-blue-500/5 shadow-[0_0_20px_rgba(59,130,246,0.1)]' : '' }}">
                                    
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <div class="text-[9px] font-black uppercase italic tracking-tighter mb-1 {{ $isClaimed ? 'text-emerald-500' : ($isNext ? 'text-blue-500' : 'text-slate-700') }}">
                                                {{ $prize->position }}º Target
                                            </div>
                                            <div class="text-sm font-black text-white uppercase italic tracking-tighter">
                                                {{ $prize->name }}
                                            </div>

                                            @if ($isClaimed)
                                                @php $winner = $prize->winner()->first(); @endphp
                                                <div class="mt-3 flex items-center gap-2">
                                                    <div class="text-[8px] font-black uppercase text-emerald-500 tracking-widest italic bg-emerald-500/10 px-2 py-0.5 rounded">
                                                        Capturado: {{ explode(' ', $winner->user->name)[0] }}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($isClaimed)
                                            <span class="text-emerald-500 text-lg">✓</span>
                                        @elseif($isNext)
                                            <div class="bg-blue-600 h-2 w-2 rounded-full animate-ping"></div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </aside>
            </div>
        @endif
    </div>
</div>

<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>