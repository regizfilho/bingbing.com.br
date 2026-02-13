<?php

use Livewire\Attributes\{On, Computed};
use Livewire\Component;
use App\Models\Game\{Game, Player, Card, Winner};
use App\Events\GameUpdated;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public Game $game;
    public ?Player $player = null;
    public array $cards = [];
    public array $drawnNumbers = [];
    public int $totalDraws = 0;

    public function mount(string $invite_code)
    {
        $this->game = Game::where('invite_code', $invite_code)
            ->with(['creator', 'package', 'prizes.winner.user', 'winners.user', 'winners.prize'])
            ->firstOrFail();

        if (!isset($this->game->cards_per_player) || $this->game->cards_per_player === null) {
            $this->game->cards_per_player = 1;
        }

        $this->player = Player::where('game_id', $this->game->id)
            ->where('user_id', auth()->id())
            ->first();

        $this->syncGameState();
    }

    #[On('echo:game.{game.uuid},.GameUpdated')]
    public function handleUpdate(): void
    {
        $this->game->refresh();
        $this->game->load(['prizes.winner.user', 'draws', 'winners.user', 'winners.prize']);
        
        if (!$this->player) {
            $this->player = Player::where('game_id', $this->game->id)
                ->where('user_id', auth()->id())
                ->first();
        }

        $this->syncGameState();
    }

    private function syncGameState(): void
    {
        $this->drawnNumbers = $this->game->draws()
            ->where('round_number', $this->game->current_round)
            ->orderBy('number', 'asc')
            ->pluck('number')
            ->toArray();
        
        $this->totalDraws = count($this->drawnNumbers);

        if ($this->player) {
            $cards = Card::where('player_id', $this->player->id)
                ->where('round_number', $this->game->current_round)
                ->get();
            
            $this->cards = $cards->map(function ($card) {
                $numbers = $card->numbers;
                $marked = $card->marked ?? '[]';
                $numbersArray = is_array($numbers) ? $numbers : (json_decode($numbers, true) ?? []);
                $markedArray = is_array($marked) ? $marked : (json_decode($marked, true) ?? []);
                
                return [
                    'id' => $card->id,
                    'uuid' => $card->uuid,
                    'numbers' => $numbersArray,
                    'marked' => $markedArray,
                    'is_bingo' => $card->is_bingo,
                ];
            })->toArray();
        }
    }

    public function join()
    {
        if ($this->player || !$this->game->canJoin()) {
            return;
        }

        DB::transaction(function () {
            $this->player = Player::firstOrCreate([
                'game_id' => $this->game->id,
                'user_id' => auth()->id(),
            ], [
                'joined_at' => now(),
            ]);

            Card::where('player_id', $this->player->id)
                ->where('round_number', $this->game->current_round)
                ->delete();

            $this->game->refresh();
            $cardsPerPlayer = max(1, min(10, (int) ($this->game->cards_per_player ?? 1)));

            for ($i = 0; $i < $cardsPerPlayer; $i++) {
                Card::create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'game_id' => $this->game->id,
                    'player_id' => $this->player->id,
                    'round_number' => $this->game->current_round,
                    'numbers' => json_encode($this->game->generateCardNumbers()),
                    'marked' => json_encode([]),
                    'is_bingo' => false,
                ]);
            }
        });

        broadcast(new GameUpdated($this->game))->toOthers();
        $this->syncGameState();
        $this->dispatch('notify', type: 'success', text: 'Você entrou na arena!');
    }

    public function markNumber(int $cardIndex, int $number)
    {
        if (!$this->player || $this->game->status !== 'active') return;

        $card = $this->cards[$cardIndex] ?? null;
        if (!$card || $card['is_bingo']) return;

        $cardModel = Card::find($card['id']);
        if (!$cardModel || in_array($number, $card['marked'])) return;

        if (!in_array($number, $this->drawnNumbers)) {
            $this->dispatch('notify', type: 'error', text: 'Este número ainda não foi sorteado!');
            return;
        }

        $marked = array_merge($card['marked'], [$number]);
        $cardModel->update(['marked' => json_encode($marked)]);

        $allNumbers = $card['numbers'];
        $hasAllNumbers = count($allNumbers) === count($marked) && empty(array_diff($allNumbers, $marked));

        if ($hasAllNumbers) {
            $cardModel->update(['is_bingo' => true]);
            if ($this->game->auto_claim_prizes) {
                $this->claimPrizeAutomatically($cardModel);
            } else {
                $this->dispatch('notify', type: 'success', text: 'BINGO! Aguardando validação.');
                broadcast(new GameUpdated($this->game))->toOthers();
            }
        }

        $this->syncGameState();
    }

    private function claimPrizeAutomatically(Card $card)
    {
        $nextPrize = $this->game->prizes()->where('is_claimed', false)->orderBy('position', 'asc')->first();
        DB::transaction(function () use ($card, $nextPrize) {
            if ($nextPrize) {
                $nextPrize->update(['is_claimed' => true, 'winner_card_id' => $card->id, 'claimed_at' => now()]);
            }
            Winner::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'game_id' => $this->game->id,
                'card_id' => $card->id,
                'user_id' => $this->player->user_id,
                'prize_id' => $nextPrize?->id,
                'round_number' => $this->game->current_round,
                'won_at' => now(),
            ]);
        });
    }

    #[Computed]
    public function roundWinners() {
        return Winner::where('game_id', $this->game->id)->where('round_number', $this->game->current_round)->with(['user', 'prize'])->orderBy('won_at', 'asc')->get();
    }

    #[Computed]
    public function allTimeWinners() {
        return Winner::where('game_id', $this->game->id)->with(['user', 'prize'])->orderBy('won_at', 'desc')->limit(10)->get();
    }

    #[Computed]
    public function myWinningCards(): array {
        if (!$this->player || empty($this->cards)) return [];
        return Winner::whereIn('card_id', collect($this->cards)->pluck('id'))->where('round_number', $this->game->current_round)->pluck('card_id')->toArray();
    }
};
?>

<div class="min-h-screen bg-[#05070a] text-slate-200 italic font-sans pb-20">
    <x-loading />
    <x-toast />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        
        {{-- HEADER PREMIUM --}}
        <header class="mb-12">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
                <div>
                    <div class="flex items-center gap-4 mb-3">
                        <div class="h-[1px] w-12 bg-blue-600"></div>
                        <span class="text-blue-500 font-black tracking-[0.4em] uppercase text-[10px]">
                            {{ $game->status === 'active' ? 'ARENA EM OPERAÇÃO' : 'ARENA EM ESPERA' }}
                        </span>
                    </div>
                    <h1 class="text-4xl md:text-6xl font-black text-white tracking-tighter uppercase leading-none">
                        {{ $game->name }}
                    </h1>
                </div>

                <div class="flex items-center gap-4 bg-[#0b0d11] p-4 rounded-3xl border border-white/5 shadow-2xl">
                    <div class="text-right border-r border-white/10 pr-4">
                        <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Rodada</p>
                        <p class="text-2xl font-black text-white italic leading-none">
                            {{ $game->current_round }}<span class="text-slate-700 text-xs">/{{ $game->max_rounds }}</span>
                        </p>
                    </div>
                    <div class="px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest border 
                        {{ $game->status === 'active' ? 'bg-blue-600 border-blue-500 text-white shadow-lg shadow-blue-600/20' : 'bg-white/5 border-white/10 text-slate-500' }}">
                        {{ $game->status === 'active' ? 'AO VIVO' : 'STANDBY' }}
                    </div>
                </div>
            </div>
        </header>

        @if(!$player)
            <div class="max-w-xl mx-auto text-center py-24 bg-[#0b0d11] rounded-[4rem] border border-white/5 shadow-3xl relative overflow-hidden">
                <div class="absolute inset-0 bg-blue-600/5 blur-[100px]"></div>
                <div class="relative z-10 px-10">
                    <div class="w-20 h-20 bg-blue-600/10 rounded-full flex items-center justify-center mx-auto mb-8 border border-blue-600/20">
                        <span class="text-3xl">🎮</span>
                    </div>
                    <h2 class="text-3xl font-black text-white uppercase tracking-tighter mb-4">Pronto para o Jogo?</h2>
                    <p class="text-slate-500 font-bold text-sm mb-10 uppercase tracking-widest leading-relaxed">
                        Você foi convidado para participar desta arena. Clique abaixo para gerar suas cartelas.
                    </p>
                    <button wire:click="join" class="group relative w-full bg-blue-600 hover:bg-blue-500 text-white py-6 rounded-3xl font-black uppercase text-xs tracking-[0.3em] transition-all shadow-2xl shadow-blue-600/20 overflow-hidden">
                        <span class="relative z-10">ENTRAR NA PARTIDA</span>
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                    </button>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                
                {{-- ÁREA DE JOGO --}}
                <div class="lg:col-span-8 space-y-10">
                    
                    {{-- SORTEIO EM TEMPO REAL --}}
                    @if($game->show_drawn_to_players)
                        <div class="bg-[#0b0d11] border border-white/5 rounded-[3rem] p-8 shadow-2xl">
                            <div class="flex items-center justify-between mb-8">
                                <span class="text-[11px] font-black text-slate-500 uppercase tracking-[0.3em] flex items-center gap-3">
                                    <span class="w-2 h-2 bg-blue-600 rounded-full animate-ping"></span>
                                    Painel de Sorteio
                                </span>
                                <span class="text-[10px] font-black text-white bg-white/5 px-4 py-1 rounded-full border border-white/5 uppercase">
                                    {{ $totalDraws }} de 75 sorteados
                                </span>
                            </div>
                            
                            <div class="flex flex-wrap gap-3">
                                @forelse(collect($drawnNumbers)->take(-12)->reverse() as $index => $number)
                                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-xl font-black transition-all duration-500
                                        {{ $loop->first ? 'bg-blue-600 text-white shadow-2xl shadow-blue-600/40 scale-110 z-10' : 'bg-[#161920] text-slate-500 border border-white/5 opacity-50' }}">
                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                @empty
                                    <div class="w-full py-4 text-center border border-white/5 border-dashed rounded-2xl">
                                        <span class="text-[10px] font-black text-slate-700 uppercase tracking-widest italic">Aguardando o organizador iniciar os sorteios...</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    {{-- MINHAS CARTELAS --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        @foreach($cards as $index => $card)
                            @php 
                                $hasWon = in_array($card['id'], $this->myWinningCards);
                                $isBingo = $card['is_bingo'] ?? false;
                                $marked = $card['marked'] ?? [];
                                $numbers = $card['numbers'] ?? [];
                                $markedCount = count($marked);
                                $totalNums = count($numbers);
                                $allMarked = ($markedCount === $totalNums && $totalNums > 0);
                                $active = $game->status === 'active' && !$isBingo && !$hasWon;
                            @endphp
                            
                            <div wire:key="player-card-{{ $card['id'] }}" 
                                class="relative bg-[#0b0d11] rounded-[3rem] p-8 border transition-all duration-500 group
                                {{ $isBingo || $hasWon ? 'border-emerald-500/40 bg-emerald-500/5 shadow-emerald-500/10' : ($allMarked ? 'border-amber-500/40 bg-amber-500/5' : 'border-white/5 hover:border-white/10 shadow-2xl') }}">
                                
                                <div class="flex justify-between items-center mb-8">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center font-black text-xs text-slate-400">
                                            #{{ $index + 1 }}
                                        </div>
                                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest italic">Minha Cartela</span>
                                    </div>
                                    
                                    @if($hasWon || $isBingo)
                                        <div class="px-4 py-1.5 bg-emerald-600 text-white text-[9px] font-black rounded-full shadow-lg shadow-emerald-600/30 uppercase italic">VENCEDORA</div>
                                    @elseif($allMarked)
                                        <div class="px-4 py-1.5 bg-amber-600 text-white text-[9px] font-black rounded-full uppercase italic">VALIDANDO</div>
                                    @else
                                        <span class="text-[10px] font-black text-slate-700 italic">{{ $markedCount }}/{{ $totalNums }}</span>
                                    @endif
                                </div>

                                <div class="grid grid-cols-5 gap-2.5">
                                    @foreach($numbers as $num)
                                        @php 
                                            $isMarked = in_array($num, $marked);
                                            $wasDrawn = in_array($num, $drawnNumbers);
                                            $highlight = $game->show_player_matches && $wasDrawn && !$isMarked && $active;
                                        @endphp
                                        <button 
                                            wire:click="markNumber({{ $index }}, {{ $num }})"
                                            @if(!$active || $isMarked || !$wasDrawn) disabled @endif
                                            class="aspect-square rounded-xl flex items-center justify-center font-black text-lg transition-all relative overflow-hidden
                                            {{ $isMarked 
                                                ? 'bg-blue-600 text-white shadow-xl shadow-blue-600/30 ring-2 ring-white/10' 
                                                : ($highlight 
                                                    ? 'bg-amber-500/20 text-amber-500 border-2 border-amber-500/40 animate-pulse' 
                                                    : 'bg-[#161920] text-slate-700 border border-white/5 hover:bg-white/5 hover:text-slate-400 disabled:cursor-not-allowed'
                                                )
                                            }}">
                                            {{ $num }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- SIDEBAR DE STATUS --}}
                <aside class="lg:col-span-4 space-y-8">
                    
                    {{-- PRÊMIOS --}}
                    <div class="bg-[#0b0d11] border border-white/5 rounded-[2.5rem] p-8 shadow-2xl">
                        <h3 class="text-[11px] font-black text-white uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                            <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                            Tabela de Prêmios
                        </h3>
                        
                        <div class="space-y-4">
                            @foreach($game->prizes->sortBy('position') as $prize)
                                @php $pWinner = $this->roundWinners->firstWhere('prize_id', $prize->id); @endphp
                                <div class="flex items-center justify-between p-5 rounded-2xl border transition-all
                                    {{ $prize->is_claimed ? 'bg-emerald-600/5 border-emerald-500/20 opacity-50' : 'bg-[#161920] border-white/5' }}">
                                    <div class="flex items-center gap-4">
                                        <span class="text-xs font-black text-slate-600">#{{ $prize->position }}</span>
                                        <div>
                                            <p class="text-sm font-black text-white uppercase italic">{{ $prize->name }}</p>
                                            @if($pWinner)
                                                <p class="text-[9px] font-bold text-emerald-500 uppercase mt-1">Ganhador: {{ $pWinner->user->name }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    @if($prize->is_claimed)
                                        <span class="text-emerald-500">🏆</span>
                                    @else
                                        <span class="text-[8px] font-black text-slate-700 uppercase italic">Ativo</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- RANKING RODADA --}}
                    <div class="bg-[#0b0d11] border border-white/5 rounded-[2.5rem] p-8 shadow-2xl">
                        <div class="flex items-center justify-between mb-8 border-b border-white/5 pb-4">
                            <h3 class="text-[11px] font-black text-white uppercase tracking-[0.3em]">Vencedores R{{ $game->current_round }}</h3>
                        </div>
                        
                        @if($this->roundWinners->isNotEmpty())
                            <div class="space-y-5">
                                @foreach($this->roundWinners as $index => $winner)
                                    <div class="flex items-center gap-4 group">
                                        <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center border border-white/5">
                                            <span class="text-[10px] font-black {{ $index < 3 ? 'text-amber-500' : 'text-slate-600' }}">{{ $index + 1 }}º</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-black text-white uppercase italic truncate">{{ $winner->user->name }}</p>
                                            <p class="text-[9px] font-bold text-slate-500 uppercase">{{ $winner->prize?->name ?? 'Honra' }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-10 border border-white/5 border-dashed rounded-2xl">
                                <p class="text-[10px] font-black text-slate-700 uppercase tracking-widest italic">Aguardando Bingo...</p>
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        @endif
    </div>
</div>