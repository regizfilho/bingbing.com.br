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

    // Garantir que cards_per_player está acessível
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
                
                if (is_array($numbers)) {
                    $numbersArray = $numbers;
                } else {
                    $numbersArray = json_decode($numbers, true) ?? [];
                }
                
                if (is_array($marked)) {
                    $markedArray = $marked;
                } else {
                    $markedArray = json_decode($marked, true) ?? [];
                }
                
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

    // Verificar se o usuário já tem cartelas nesta rodada
    $existingCards = Card::where('player_id', $this->player?->id)
        ->where('round_number', $this->game->current_round)
        ->count();
    
    if ($existingCards > 0) {
        session()->flash('info', 'Você já está na arena com cartelas ativas.');
        return;
    }

    DB::transaction(function () {
        $this->player = Player::firstOrCreate([
            'game_id' => $this->game->id,
            'user_id' => auth()->id(),
        ], [
            'joined_at' => now(),
        ]);

        // Remover cartelas antigas se existirem
        Card::where('player_id', $this->player->id)
            ->where('round_number', $this->game->current_round)
            ->delete();

        $this->game->refresh();
        
        $cardsPerPlayer = (int) ($this->game->cards_per_player ?? 1);
        $cardsPerPlayer = max(1, min(10, $cardsPerPlayer));

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
    session()->flash('success', 'Você entrou na arena!');
}

    public function markNumber(int $cardIndex, int $number)
    {
        if (!$this->player || $this->game->status !== 'active') {
            return;
        }

        $card = $this->cards[$cardIndex] ?? null;
        if (!$card) {
            return;
        }

        if ($card['is_bingo']) {
            $this->dispatch('notify', type: 'info', text: 'Cartela já vencedora');
            return;
        }

        $cardModel = Card::find($card['id']);
        if (!$cardModel || in_array($number, $card['marked'])) {
            return;
        }

        // REGRA ABSOLUTA: Número precisa ter sido sorteado
        if (!in_array($number, $this->drawnNumbers)) {
            $this->dispatch('notify', type: 'error', text: 'Este número ainda não foi sorteado!');
            return;
        }

        $marked = array_merge($card['marked'], [$number]);
        $cardModel->update(['marked' => json_encode($marked)]);

        // Verificar bingo baseado nas marcações do jogador
        $allNumbers = $card['numbers'];
        $markedNumbers = $marked;
        $hasAllNumbers = count($allNumbers) === count($markedNumbers) && empty(array_diff($allNumbers, $markedNumbers));

        if ($hasAllNumbers) {
            $cardModel->update(['is_bingo' => true]);
            
            $alreadyWinner = Winner::where('card_id', $card['id'])
                ->where('round_number', $this->game->current_round)
                ->exists();
            
            if (!$alreadyWinner) {
                if ($this->game->auto_claim_prizes) {
                    $this->claimPrizeAutomatically($cardModel);
                } else {
                    session()->flash('success', 'BINGO! Aguardando validação do organizador.');
                    broadcast(new GameUpdated($this->game))->toOthers();
                }
            }
        }

        $this->syncGameState();
    }

    private function claimPrizeAutomatically(Card $card)
    {
        $nextPrize = $this->game->prizes()
            ->where('is_claimed', false)
            ->orderBy('position', 'asc')
            ->first();

        DB::transaction(function () use ($card, $nextPrize) {
            if ($nextPrize) {
                $nextPrize->update([
                    'is_claimed' => true,
                    'winner_card_id' => $card->id,
                    'claimed_at' => now(),
                ]);
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
    public function roundWinners()
    {
        return Winner::where('game_id', $this->game->id)
            ->where('round_number', $this->game->current_round)
            ->with(['user', 'prize'])
            ->orderBy('won_at', 'asc')
            ->get();
    }

    #[Computed]
    public function allTimeWinners()
    {
        return Winner::where('game_id', $this->game->id)
            ->with(['user', 'prize'])
            ->orderBy('won_at', 'desc')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function myWinningCards(): array
    {
        if (!$this->player || empty($this->cards)) {
            return [];
        }

        return Winner::whereIn('card_id', collect($this->cards)->pluck('id'))
            ->where('round_number', $this->game->current_round)
            ->pluck('card_id')
            ->toArray();
    }

    #[Computed]
    public function canMarkNumbers(): bool
    {
        return !empty($this->drawnNumbers);
    }
};
?>

<div class="min-h-screen bg-[#0b0d11] text-slate-200">
    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        
        <header class="mb-10">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="h-[2px] w-12 bg-blue-600"></span>
                        <span class="text-blue-500 font-black tracking-[0.3em] uppercase text-[10px] italic">
                            Arena {{ $game->status === 'active' ? 'em operação' : 'em espera' }}
                        </span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white tracking-tighter uppercase italic leading-none">
                        {{ $game->name }}
                    </h1>
                </div>

                <div class="flex items-center gap-4">
                    <div class="bg-[#161920] border border-white/5 rounded-xl px-5 py-3">
                        <p class="text-[8px] font-black text-slate-500 uppercase italic">Rodada</p>
                        <p class="text-xl font-black text-white italic leading-none">
                            {{ $game->current_round }}<span class="text-slate-700 text-xs">/{{ $game->max_rounds }}</span>
                        </p>
                    </div>
                    <div class="px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] border 
                        {{ $game->status === 'active' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-500' : 'bg-white/5 border-white/10 text-slate-500' }}">
                        {{ $game->status }}
                    </div>
                </div>
            </div>

            @if(session()->has('success'))
                <div class="mt-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 px-6 py-4 rounded-xl font-black uppercase text-xs">
                    {{ session('success') }}
                </div>
            @endif
        </header>

        @if(!$player)
            <div class="max-w-md mx-auto text-center py-20 bg-[#161920] rounded-[2rem] border border-white/5">
                <h2 class="text-lg font-black text-white uppercase italic mb-6">Acesso à arena</h2>
                <p class="text-sm text-slate-400 mb-8">Utilize seu código de acesso para entrar na partida</p>
                <button wire:click="join" class="bg-blue-600 hover:bg-blue-700 px-12 py-5 rounded-xl font-black italic uppercase text-white text-sm tracking-widest transition-all">
                    ENTRAR NA ARENA
                </button>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2 space-y-8">
                    
                    @if($game->show_drawn_to_players && !empty($drawnNumbers))
                        <div class="bg-[#161920] border border-white/5 rounded-[2rem] p-6">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-[10px] font-black text-slate-500 uppercase italic tracking-widest">
                                    Números sorteados
                                </span>
                                <span class="text-[10px] font-black text-slate-700">
                                    {{ $totalDraws }}/75
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach(array_slice($drawnNumbers, -10) as $number)
                                    <span class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center text-sm font-bold text-slate-300 border border-white/5">
                                        {{ $number }}
                                    </span>
                                @endforeach
                                @if($totalDraws > 10)
                                    <span class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center text-xs font-bold text-slate-500">
                                        +{{ $totalDraws - 10 }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @elseif($game->show_drawn_to_players && empty($drawnNumbers) && $game->status === 'active')
                        <div class="bg-[#161920] border border-white/5 rounded-[2rem] p-6">
                            <div class="text-center">
                                <span class="text-[10px] font-black text-slate-500 uppercase italic tracking-widest">
                                    Aguardando primeiro sorteio...
                                </span>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($cards as $index => $card)
                            @php 
                                $hasWon = in_array($card['id'], $this->myWinningCards);
                                $isBingo = $card['is_bingo'] ?? false;
                                $marked = $card['marked'] ?? [];
                                $numbers = $card['numbers'] ?? [];
                                $totalNumbers = count($numbers);
                                $markedCount = count($marked);
                                $allMarked = $markedCount === $totalNumbers && $totalNumbers > 0;
                                
                                $canMark = $game->status === 'active' && !$hasWon && !$isBingo && !$allMarked;
                                $buttonDisabled = !$canMark;
                            @endphp
                            <div wire:key="card-{{ $card['id'] }}" 
                                class="bg-[#161920] rounded-[2rem] p-6 border transition-all duration-300
                                {{ $isBingo || $hasWon ? 'border-emerald-500/50 bg-emerald-500/5' : ($allMarked ? 'border-amber-500/50 bg-amber-500/5' : 'border-white/5') }}">
                                
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <span class="text-[9px] font-black text-slate-600 uppercase italic tracking-wider">
                                            Cartela #{{ $index + 1 }}
                                        </span>
                                        @if($totalNumbers > 0)
                                            <span class="ml-2 text-[8px] font-black text-slate-700">
                                                {{ $markedCount }}/{{ $totalNumbers }}
                                            </span>
                                        @endif
                                    </div>
                                    
                                    @if($hasWon)
                                        <span class="bg-emerald-500/10 text-emerald-500 text-[8px] font-black px-3 py-1 rounded-full uppercase italic border border-emerald-500/20">
                                            PREMIADA
                                        </span>
                                    @elseif($isBingo)
                                        <span class="bg-emerald-500/10 text-emerald-500 text-[8px] font-black px-3 py-1 rounded-full uppercase italic border border-emerald-500/20">
                                            BINGO
                                        </span>
                                    @elseif($allMarked)
                                        <span class="bg-amber-500/10 text-amber-500 text-[8px] font-black px-3 py-1 rounded-full uppercase italic border border-amber-500/20">
                                            AGUARDANDO
                                        </span>
                                    @endif
                                </div>

                                <div class="grid gap-1.5" style="grid-template-columns: repeat(5, 1fr)">
                                    @foreach($numbers as $num)
                                        @php 
                                            $isMarked = in_array($num, $marked);
                                            $wasDrawn = in_array($num, $drawnNumbers);
                                            $shouldHighlight = $game->show_player_matches && $wasDrawn && !$isMarked && $canMark;
                                        @endphp
                                        <button wire:click="markNumber({{ $index }}, {{ $num }})"
                                            @if($buttonDisabled || $isMarked || !$wasDrawn) disabled @endif
                                            class="aspect-square rounded-lg flex items-center justify-center font-bold text-base transition-all
                                            {{ $isMarked 
                                                ? 'bg-blue-600 text-white' 
                                                : ($shouldHighlight && $wasDrawn
                                                    ? 'bg-amber-500/10 text-amber-500 border border-amber-500/30' 
                                                    : 'bg-white/5 text-slate-600 border border-white/5 hover:bg-white/10 hover:text-slate-400'
                                                )
                                            }}
                                            {{ $hasWon || $isBingo ? 'opacity-50 cursor-not-allowed' : '' }}
                                            title="{{ !$wasDrawn ? 'Aguardando sorteio' : '' }}">
                                            {{ $num }}
                                        </button>
                                    @endforeach
                                </div>
                                
                                @if($allMarked && !$isBingo && !$hasWon)
                                    <div class="mt-4 text-center py-2 px-3 bg-amber-500/5 border border-amber-500/20 rounded-xl">
                                        <span class="text-[9px] font-black text-amber-500 uppercase tracking-widest">
                                            ✓ Cartela completa - Aguardando validação
                                        </span>
                                    </div>
                                @endif
                                
                                @if($isBingo && !$hasWon)
                                    <div class="mt-4 text-center py-2 px-3 bg-emerald-500/5 border border-emerald-500/20 rounded-xl">
                                        <span class="text-[9px] font-black text-emerald-500 uppercase tracking-widest">
                                            ✓ BINGO validado
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <aside class="space-y-6">
                    
                    <div class="bg-[#161920] border border-white/5 rounded-[2rem] p-6">
                        <h3 class="text-[10px] font-black text-white uppercase italic tracking-widest mb-5 pb-3 border-b border-white/5">
                            Prêmios - Rodada {{ $game->current_round }}
                        </h3>
                        
                        <div class="space-y-3">
                            @foreach($game->prizes->sortBy('position') as $prize)
                                @php $winner = $this->roundWinners->firstWhere('prize_id', $prize->id); @endphp
                                <div class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border border-white/5">
                                    <div class="flex items-center gap-3">
                                        <span class="text-[10px] font-black text-slate-600 w-6">
                                            #{{ $prize->position }}
                                        </span>
                                        <div>
                                            <p class="text-xs font-black text-white uppercase italic tracking-tight">
                                                {{ $prize->name }}
                                            </p>
                                            @if($winner)
                                                <p class="text-[8px] font-bold text-slate-500 uppercase mt-0.5">
                                                    {{ $winner->user->name }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    @if($prize->is_claimed)
                                        <span class="text-emerald-500 text-[10px] font-black">✓</span>
                                    @else
                                        <span class="text-slate-700 text-[8px] font-black uppercase">Disponível</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-[#161920] border border-white/5 rounded-[2rem] p-6">
                        <div class="flex items-center justify-between mb-5 pb-3 border-b border-white/5">
                            <h3 class="text-[10px] font-black text-white uppercase italic tracking-widest">
                                Classificação - Rodada {{ $game->current_round }}
                            </h3>
                            <span class="text-[9px] font-black text-slate-600">
                                {{ $this->roundWinners->count() }} vencedor(es)
                            </span>
                        </div>
                        
                        @if($this->roundWinners->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($this->roundWinners as $index => $winner)
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-lg bg-white/5 flex items-center justify-center">
                                            <span class="text-[10px] font-black {{ $index < 3 ? 'text-amber-500' : 'text-slate-600' }}">
                                                {{ $index + 1 }}º
                                            </span>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-xs font-black text-white uppercase italic tracking-tight">
                                                {{ $winner->user->name }}
                                            </p>
                                            <p class="text-[8px] font-bold {{ $winner->prize ? 'text-emerald-500' : 'text-slate-500' }} uppercase">
                                                {{ $winner->prize?->name ?? 'Honra' }}
                                            </p>
                                        </div>
                                        <span class="text-[8px] font-bold text-slate-600">
                                            {{ $winner->won_at->format('H:i') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6">
                                <p class="text-[10px] font-black text-slate-700 uppercase tracking-widest">
                                    Nenhum vencedor
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="bg-[#161920] border border-white/5 rounded-[2rem] p-6">
                        <div class="flex items-center justify-between mb-5 pb-3 border-b border-white/5">
                            <h3 class="text-[10px] font-black text-white uppercase italic tracking-widest">
                                Hall da Fama
                            </h3>
                            <span class="text-[9px] font-black text-slate-600">
                                Todas as rodadas
                            </span>
                        </div>
                        
                        @if($this->allTimeWinners->isNotEmpty())
                            <div class="space-y-2">
                                @foreach($this->allTimeWinners as $winner)
                                    <div class="flex items-center justify-between py-2 border-b border-white/5 last:border-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[9px] font-black text-slate-600 w-5">
                                                R{{ $winner->round_number }}
                                            </span>
                                            <span class="text-[10px] font-black text-white uppercase">
                                                {{ $winner->user->name }}
                                            </span>
                                        </div>
                                        <span class="text-[8px] font-bold {{ $winner->prize ? 'text-emerald-500' : 'text-slate-600' }} uppercase">
                                            {{ $winner->prize?->name ?? 'Honra' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6">
                                <p class="text-[10px] font-black text-slate-700 uppercase tracking-widest">
                                    Aguardando vencedores
                                </p>
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        @endif
    </div>
</div>