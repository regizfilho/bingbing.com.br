<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\Game\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component {
    public Game $game;

    #[Validate('required|min:3|max:255')]
    public string $name = '';

    #[Validate('required|in:manual,automatic')]
    public string $draw_mode = 'manual';

    public ?int $auto_draw_seconds = 3;
    public int $prizes_per_round = 1;
    
    #[Validate('required|integer|min:1|max:10')]
    public int $cards_per_player = 1;
    
    public bool $show_drawn_to_players = false;
    public bool $show_player_matches = false;
    public bool $auto_claim_prizes = false;
    
    public array $prizes = [];

    public function rules(): array
    {
        return [
            'name' => 'required|min:3|max:255',
            'draw_mode' => 'required|in:manual,automatic',
            'prizes_per_round' => 'required|integer|min:1|max:' . count($this->prizes),
            'cards_per_player' => 'required|integer|min:1|max:10',
            'prizes.*.name' => 'required|min:2|max:255',
            'prizes.*.description' => 'nullable|string|max:500',
        ];
    }

    protected function messages(): array
    {
        return [
            'prizes_per_round.max' => 'Quantidade de prêmios por rodada excede o total cadastrado.',
            'prizes.*.name.required' => 'O título do prêmio é obrigatório.',
            'name.required' => 'O nome da arena é obrigatório.',
            'cards_per_player.required' => 'Informe quantas cartelas por jogador.',
            'cards_per_player.min' => 'Mínimo de 1 cartela por jogador.',
            'cards_per_player.max' => 'Máximo de 10 cartelas por jogador.',
        ];
    }

    #[Computed]
    public function user() { return auth()->user(); }

    public function mount(string $uuid): void
    {
        $this->game = Game::where('uuid', $uuid)
            ->with(['prizes', 'package', 'players'])
            ->firstOrFail();

        if ($this->game->creator_id !== $this->user->id) {
            abort(403, 'Você não é o criador desta partida.');
        }

        if (!in_array($this->game->status, ['draft', 'waiting'])) {
            $this->dispatch('notify', type: 'error', text: 'Apenas partidas em rascunho ou aguardando podem ser editadas.');
            $this->redirect(route('games.index'));
            return;
        }

        $this->loadGameData();
    }

    private function loadGameData(): void
    {
        $this->name = $this->game->name;
        $this->draw_mode = $this->game->draw_mode;
        $this->auto_draw_seconds = $this->game->auto_draw_seconds ?? 3;
        $this->prizes_per_round = $this->game->prizes_per_round ?? 1;
        $this->cards_per_player = $this->game->cards_per_player ?? 1;
        $this->show_drawn_to_players = (bool) ($this->game->show_drawn_to_players ?? false);
        $this->show_player_matches = (bool) ($this->game->show_player_matches ?? false);
        $this->auto_claim_prizes = (bool) ($this->game->auto_claim_prizes ?? false);

        $this->prizes = $this->game->prizes
            ->sortBy('position')
            ->map(fn($prize) => [
                'id' => $prize->id,
                'temp_id' => Str::random(8),
                'name' => $prize->name ?? '',
                'description' => $prize->description ?? '',
            ])
            ->toArray();
            
        if (empty($this->prizes)) {
            $this->prizes = [
                ['temp_id' => Str::random(8), 'name' => '1º Prêmio', 'description' => ''],
            ];
        }
    }

    public function addPrize(): void
    {
        $this->prizes[] = ['temp_id' => Str::random(8), 'name' => '', 'description' => ''];
        $this->dispatch('notify', type: 'info', text: 'Novo slot de prêmio adicionado.');
    }

    public function removePrize(int $index): void
    {
        if (count($this->prizes) > 1) {
            array_splice($this->prizes, $index, 1);

            if ($this->prizes_per_round > count($this->prizes)) {
                $this->prizes_per_round = count($this->prizes);
            }

            $this->resetValidation();
            $this->dispatch('notify', type: 'info', text: 'Slot removido.');
        }
    }

    public function update(): void
    {
        if (!$this->validateAndFilterPrizes()) {
            return;
        }

        try {
            DB::transaction(function () {
                $this->game->update([
                    'name' => $this->name,
                    'draw_mode' => $this->draw_mode,
                    'auto_draw_seconds' => $this->draw_mode === 'automatic' ? ($this->auto_draw_seconds ?? 3) : null,
                    'cards_per_player' => $this->cards_per_player,
                    'prizes_per_round' => $this->prizes_per_round,
                    'show_drawn_to_players' => $this->show_drawn_to_players,
                    'show_player_matches' => $this->show_player_matches,
                    'auto_claim_prizes' => $this->auto_claim_prizes,
                ]);

                $this->syncPrizes();
            });

            $this->game->refresh();
            $this->loadGameData();
            $this->dispatch('notify', type: 'success', text: 'Alterações salvas com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao salvar alterações.');
        }
    }

    public function publish(): void
    {
        if (!$this->validateAndFilterPrizes()) {
            return;
        }

        try {
            DB::transaction(function () {
                $this->game->update([
                    'name' => $this->name,
                    'draw_mode' => $this->draw_mode,
                    'auto_draw_seconds' => $this->draw_mode === 'automatic' ? ($this->auto_draw_seconds ?? 3) : null,
                    'cards_per_player' => $this->cards_per_player,
                    'prizes_per_round' => $this->prizes_per_round,
                    'show_drawn_to_players' => $this->show_drawn_to_players,
                    'show_player_matches' => $this->show_player_matches,
                    'auto_claim_prizes' => $this->auto_claim_prizes,
                    'status' => 'waiting',
                ]);

                $this->syncPrizes();

                if ($this->game->players()->count() > 0 && $this->game->status === 'waiting') {
                    $this->game->generateCardsForCurrentRound();
                }
            });

            $this->dispatch('notify', type: 'success', text: 'Partida publicada com sucesso!');
            $this->redirect(route('games.play', $this->game->uuid));
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao publicar partida.');
        }
    }

    private function validateAndFilterPrizes(): bool
    {
        $this->prizes = array_filter($this->prizes, fn($prize) => !empty(trim($prize['name'] ?? '')));
        $this->prizes = array_values($this->prizes);

        if (empty($this->prizes)) {
            $this->dispatch('notify', type: 'error', text: 'Adicione pelo menos um prêmio válido.');
            return false;
        }

        try {
            $this->validate();
            return true;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('notify', type: 'error', text: $firstError);
            return false;
        }
    }

    private function syncPrizes(): void
    {
        $existingIds = $this->game->prizes()->pluck('id')->toArray();
        $keepIds = [];

        foreach ($this->prizes as $index => $prizeData) {
            if (isset($prizeData['id']) && in_array($prizeData['id'], $existingIds)) {
                $this->game->prizes()
                    ->where('id', $prizeData['id'])
                    ->update([
                        'name' => $prizeData['name'],
                        'description' => $prizeData['description'] ?? '',
                        'position' => $index + 1,
                    ]);
                $keepIds[] = $prizeData['id'];
            } else {
                $newPrize = $this->game->prizes()->create([
                    'uuid' => (string) Str::uuid(),
                    'name' => $prizeData['name'],
                    'description' => $prizeData['description'] ?? '',
                    'position' => $index + 1,
                ]);
                $keepIds[] = $newPrize->id;
            }
        }

        $deleteIds = array_diff($existingIds, $keepIds);
        if (!empty($deleteIds)) {
            $this->game->prizes()->whereIn('id', $deleteIds)->delete();
        }
    }

    #[Computed]
    public function canPublish(): bool
    {
        return $this->game->status === 'draft' || $this->game->status === 'waiting';
    }

    #[Computed]
    public function maxCardsPerPlayer(): int
    {
        return $this->game->package->max_cards_per_player ?? 10;
    }
};
?>

<div class="relative min-h-screen bg-[#0b0d11] text-slate-200 pb-20">
    <x-slot name="header">Editar Arena</x-slot>

    <div x-data="{ show: false, text: '', type: 'success', timeout: null }"
        x-on:notify.window="
            show = true; 
            text = $event.detail.text; 
            type = $event.detail.type;
            clearTimeout(timeout);
            timeout = setTimeout(() => show = false, 5000)
        "
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-2 scale-90"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        class="fixed top-8 right-8 z-[9999] min-w-[350px]"
        style="display: none;">
        
        <div :class="{
            'bg-emerald-600 border-emerald-400/30 shadow-emerald-950/40': type === 'success',
            'bg-red-600 border-red-400/30 shadow-red-950/40': type === 'error',
            'bg-blue-600 border-blue-400/30 shadow-blue-950/40': type === 'info'
        }" class="px-6 py-5 rounded-[2rem] border shadow-2xl flex items-center justify-between backdrop-blur-xl">
            <div class="flex items-center gap-4">
                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-sm font-bold italic">
                    <template x-if="type === 'success'"><span>✓</span></template>
                    <template x-if="type === 'error'"><span>✕</span></template>
                    <template x-if="type === 'info'"><span>!</span></template>
                </div>
                <div class="flex flex-col">
                    <span class="text-white font-black uppercase italic text-[11px] tracking-[0.1em]" x-text="text"></span>
                    <span class="text-white/60 text-[8px] font-bold uppercase tracking-widest italic">Sistema Central</span>
                </div>
            </div>
            <button @click="show = false" class="text-white/40 hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="flex flex-col md:flex-row justify-between items-end gap-6 mb-12">
            <div>
                <div class="flex items-center gap-4 mb-3">
                    <div class="h-[1px] w-12 bg-blue-600"></div>
                    <span class="text-blue-500 font-black tracking-[0.4em] uppercase text-[9px] italic">Centro de Controle</span>
                </div>
                <h1 class="text-6xl font-black text-white tracking-tighter uppercase italic leading-none">
                    EDITAR <span class="text-blue-500">ARENA</span>
                </h1>
                <p class="text-slate-500 text-sm font-bold mt-3">Ajuste configurações e prêmios da partida</p>
            </div>

            <div class="flex items-center gap-4">
                <div class="bg-[#1c2128] px-6 py-4 rounded-xl border border-white/5">
                    <span class="text-[10px] font-black text-slate-500 uppercase italic tracking-widest block">Status</span>
                    <span class="text-sm font-black uppercase italic {{ $game->status === 'draft' ? 'text-slate-500' : 'text-amber-500' }}">
                        {{ $game->status === 'draft' ? 'RASCUNHO' : 'AGUARDANDO' }}
                    </span>
                </div>
                <a href="{{ route('games.index', $game->uuid) }}" class="p-4 bg-white/5 hover:bg-blue-600 border border-white/10 rounded-2xl transition-all group">
                    <span class="text-lg group-hover:scale-110 block">←</span>
                </a>
            </div>
        </div>

        <div class="space-y-10">
            <div class="bg-[#161920] border border-white/5 rounded-[2.5rem] p-10">
                <div class="flex items-center gap-3 mb-6">
                    <span class="text-blue-500/50 text-xs">📁</span>
                    <span class="text-slate-500 text-[10px] font-black uppercase tracking-[0.3em] italic">Protocolo de Operação</span>
                </div>

                <div class="flex flex-col md:flex-row md:items-end justify-between gap-8">
                    <div>
                        <h3 class="text-3xl font-black text-white uppercase italic tracking-tighter">{{ $game->package->name ?? 'Padrão' }}</h3>
                        <p class="text-[9px] font-bold text-slate-600 uppercase tracking-widest mt-1 italic">Pacote definido na criação (não pode ser alterado)</p>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-8 border-l border-white/5 pl-8">
                        <div class="flex flex-col">
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2 italic">Jogadores</span>
                            <span class="text-sm font-black text-white uppercase italic tracking-tighter">{{ $game->package->max_players ?? '?' }} Máx</span>
                        </div>
                        <div class="flex flex-col border-l border-white/5 pl-8">
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2 italic">Cartelas</span>
                            <span class="text-sm font-black text-white uppercase italic tracking-tighter">{{ $game->cards_per_player ?? '?' }} por Jogador</span>
                        </div>
                        <div class="flex flex-col border-l border-white/5 pl-8">
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2 italic">Rodadas</span>
                            <span class="text-sm font-black text-white uppercase italic tracking-tighter">{{ $game->max_rounds ?? '?' }} Máximo</span>
                        </div>
                    </div>
                </div>
            </div>

            <form wire:submit.prevent="update" class="space-y-10">
                <div class="bg-[#161920] border border-white/5 rounded-[2.5rem] p-10">
                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 block italic">1. Identificação da Partida</label>
                    <p class="text-[9px] text-slate-600 font-bold mb-4">Nome único e identificador da arena</p>
                    <input type="text" wire:model.blur="name"
                        class="w-full bg-[#0b0d11] border border-white/10 rounded-2xl px-8 py-6 text-white font-black uppercase italic tracking-widest focus:border-blue-500 transition-all text-2xl"
                        placeholder="EX: ARENA PRO #99">
                    @error('name') <span class="text-red-500 text-[10px] font-black uppercase mt-2 block italic">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 bg-[#161920] border border-white/5 rounded-[3rem] p-10 space-y-10">
                        <div class="flex items-center gap-4 border-b border-white/5 pb-6">
                            <span class="text-2xl">⚙️</span>
                            <div>
                                <h3 class="text-xs font-black text-white uppercase tracking-widest italic">Configurações de Campo</h3>
                                <p class="text-[8px] text-slate-600 font-bold mt-1">Defina as regras e mecânicas da partida</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            {{-- Prêmios por Rodada --}}
                            <div class="space-y-4">
                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic">Prêmios por Rodada</label>
                                    <p class="text-[8px] text-slate-700 font-bold mt-1">Distribua entre {{ count($prizes) }} prêmios cadastrados</p>
                                </div>
                                <input type="number" wire:model.live="prizes_per_round" min="1" max="{{ count($prizes) }}"
                                    class="w-full bg-[#0b0d11] border border-white/10 rounded-2xl py-4 text-center font-black text-white text-xl focus:border-blue-500">
                            </div>

                            {{-- Cartelas por Jogador --}}
                            <div class="space-y-4">
                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic">Cartelas por Jogador</label>
                                    <p class="text-[8px] text-slate-700 font-bold mt-1">Quantidade por participante</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" wire:model.live="cards_per_player" 
                                        min="1" max="{{ $this->maxCardsPerPlayer }}"
                                        class="w-full bg-[#0b0d11] border border-white/10 rounded-2xl py-4 text-center font-black text-white text-xl focus:border-blue-500">
                                    <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest whitespace-nowrap">
                                        Máx: {{ $this->maxCardsPerPlayer }}
                                    </span>
                                </div>
                                @error('cards_per_player') 
                                    <span class="text-red-500 text-[8px] font-black uppercase italic">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <button type="button" wire:click="$toggle('show_drawn_to_players')" 
                                class="flex items-center justify-between p-5 rounded-2xl border transition-all 
                                {{ $show_drawn_to_players ? 'bg-blue-600/10 border-blue-500 text-white' : 'bg-[#0b0d11] border-white/5 text-slate-500' }}">
                                <div class="text-left">
                                    <span class="text-[10px] font-black uppercase italic block">Exibir Sorteados</span>
                                    <span class="text-[7px] text-slate-600 font-bold">Mostrar números aos jogadores</span>
                                </div>
                                <div class="w-4 h-4 rounded {{ $show_drawn_to_players ? 'bg-blue-500' : 'bg-slate-800' }}"></div>
                            </button>
                            <button type="button" wire:click="$toggle('show_player_matches')" 
                                class="flex items-center justify-between p-5 rounded-2xl border transition-all 
                                {{ $show_player_matches ? 'bg-blue-600/10 border-blue-500 text-white' : 'bg-[#0b0d11] border-white/5 text-slate-500' }}">
                                <div class="text-left">
                                    <span class="text-[10px] font-black uppercase italic block">Marcar Acertos</span>
                                    <span class="text-[7px] text-slate-600 font-bold">Destacar números na cartela</span>
                                </div>
                                <div class="w-4 h-4 rounded {{ $show_player_matches ? 'bg-blue-500' : 'bg-slate-800' }}"></div>
                            </button>
                            <button type="button" wire:click="$toggle('auto_claim_prizes')" 
                                class="flex items-center justify-between p-5 rounded-2xl border transition-all 
                                {{ $auto_claim_prizes ? 'bg-blue-600/10 border-blue-500 text-white' : 'bg-[#0b0d11] border-white/5 text-slate-500' }}">
                                <div class="text-left">
                                    <span class="text-[10px] font-black uppercase italic block">Ganho Automático</span>
                                    <span class="text-[7px] text-slate-600 font-bold">Sistema reivindica prêmios</span>
                                </div>
                                <div class="w-4 h-4 rounded {{ $auto_claim_prizes ? 'bg-blue-500' : 'bg-slate-800' }}"></div>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-8">
                        <div class="bg-[#161920] border border-white/5 rounded-[3rem] p-10">
                            <div>
                                <label class="text-[10px] font-black text-white uppercase tracking-widest mb-2 block italic">⚡ Sistema de Sorteio</label>
                                <p class="text-[8px] text-slate-600 font-bold mb-6">Controle manual ou automático</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3 mb-6">
                                <button type="button" wire:click="$set('draw_mode', 'manual')" 
                                    class="py-4 rounded-xl border font-black uppercase italic text-[10px] 
                                    {{ $draw_mode === 'manual' ? 'bg-blue-600 border-blue-500 text-white' : 'bg-[#0b0d11] border-white/5 text-slate-600' }}">
                                    Manual
                                </button>
                                <button type="button" wire:click="$set('draw_mode', 'automatic')" 
                                    class="py-4 rounded-xl border font-black uppercase italic text-[10px] 
                                    {{ $draw_mode === 'automatic' ? 'bg-blue-600 border-blue-500 text-white' : 'bg-[#0b0d11] border-white/5 text-slate-600' }}">
                                    Auto
                                </button>
                            </div>

                            @if($draw_mode === 'automatic')
                                <div class="pt-6 border-t border-white/5">
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic mb-4 block">Intervalo (segundos)</label>
                                    <input type="number" wire:model.live="auto_draw_seconds" min="2" max="10"
                                        class="w-full bg-[#0b0d11] border border-white/10 rounded-xl py-3 text-center font-black text-white text-lg focus:border-blue-500">
                                </div>
                            @endif
                        </div>

                        <div class="bg-[#161920] border border-white/5 rounded-[3rem] p-10">
                            <div>
                                <label class="text-[10px] font-black text-white uppercase tracking-widest mb-2 block italic">📏 Tamanho da Cartela</label>
                                <p class="text-[8px] text-slate-600 font-bold mb-6">Definido no pacote (não editável)</p>
                            </div>
                            <div class="py-8 rounded-2xl border border-white/10 bg-[#0b0d11] flex flex-col items-center opacity-50">
                                <span class="text-3xl font-black italic text-slate-700">{{ $game->card_size }}</span>
                                <span class="text-[7px] font-black uppercase text-slate-900">CASAS</span>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-3 bg-[#161920] border border-white/5 rounded-[3rem] p-10">
                        <div class="flex justify-between items-start mb-8">
                            <div class="flex items-start gap-4">
                                <span class="text-2xl">🏆</span>
                                <div>
                                    <h3 class="text-xs font-black text-white uppercase tracking-widest italic">Inventário de Premiação</h3>
                                    <p class="text-[8px] text-slate-600 font-bold mt-1">Configure os prêmios que serão distribuídos durante a partida</p>
                                </div>
                            </div>
                            <button type="button" wire:click.prevent="addPrize" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase hover:bg-blue-500 transition-all shadow-lg">
                                + ADICIONAR SLOT
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            @foreach ($prizes as $index => $prize)
                                <div wire:key="prize-{{ $prize['temp_id'] }}" 
                                    class="bg-[#0b0d11] border border-white/10 rounded-3xl p-6 space-y-4 relative group hover:border-blue-500/30 transition-all">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                                            <span class="text-[9px] font-black text-slate-500 uppercase italic">Slot #{{ $index + 1 }}</span>
                                        </div>
                                        @if(count($prizes) > 1)
                                            <button type="button" wire:click="removePrize({{ $index }})" 
                                                class="text-red-900 hover:text-red-500 transition-all p-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                    <input type="text" wire:model.blur="prizes.{{ $index }}.name" 
                                        class="w-full bg-[#161920] border border-white/5 rounded-xl px-4 py-3 text-white text-[11px] font-black uppercase placeholder-white/10 focus:border-blue-500 transition-all" 
                                        placeholder="TITULO DO PRÊMIO">
                                    <textarea wire:model.blur="prizes.{{ $index }}.description" 
                                        class="w-full bg-[#161920] border border-white/5 rounded-xl px-4 py-3 text-white text-[10px] font-bold placeholder-white/5 focus:border-blue-500 transition-all" 
                                        placeholder="BREVE DESCRIÇÃO..." rows="2"></textarea>
                                    @error("prizes.$index.name") 
                                        <span class="text-red-500 text-[8px] font-black uppercase italic tracking-tighter">{{ $message }}</span> 
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-10">
                    <button type="submit"
                        class="w-full py-8 rounded-[2.5rem] font-black uppercase text-xl tracking-[0.5em] italic transition-all relative overflow-hidden group bg-blue-600 hover:bg-blue-500 text-white shadow-2xl shadow-blue-600/40">
                        SALVAR ALTERAÇÕES
                        <div class="absolute inset-0 bg-white/10 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700 skew-x-12"></div>
                    </button>

                    @if($this->canPublish)
                        <button type="button" wire:click="publish"
                            class="w-full py-8 rounded-[2.5rem] font-black uppercase text-xl tracking-[0.5em] italic transition-all relative overflow-hidden group bg-emerald-600 hover:bg-emerald-500 text-white shadow-2xl shadow-emerald-600/40">
                            PUBLICAR ARENA
                            <div class="absolute inset-0 bg-white/10 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700 skew-x-12"></div>
                        </button>
                    @else
                        <button type="button" disabled
                            class="w-full py-8 rounded-[2.5rem] font-black uppercase text-xl tracking-[0.5em] italic bg-white/5 text-slate-800 cursor-not-allowed border border-white/5">
                            JÁ PUBLICADA
                        </button>
                    @endif

                    <a href="{{ route('games.index', $game->uuid) }}" 
                        class="w-full text-center py-8 border border-white/10 rounded-[2.5rem] font-black uppercase text-[10px] tracking-widest text-slate-600 hover:text-white hover:bg-white/5 transition-all italic">
                        VOLTAR AO GERENCIAMENTO
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>