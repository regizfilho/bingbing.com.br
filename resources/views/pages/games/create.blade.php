<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\Game\GamePackage;
use App\Models\Game\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component {
    #[Validate('required|min:3|max:255')]
    public string $name = '';

    #[Validate('required|exists:game_packages,id')]
    public string $game_package_id = '';

    #[Validate('required|in:manual,automatic')]
    public string $draw_mode = 'manual';

    public ?int $auto_draw_seconds = 3;
    public int $prizes_per_round = 1;

    #[Validate('required|in:9,15,24')]
    public int $card_size = 24;

    #[Validate('required|integer|min:1|max:10')]
    public int $cards_per_player = 1;

    public bool $show_drawn_to_players = true;
    public bool $show_player_matches = true;
    public bool $auto_claim_prizes = true;

    #[Validate('required|integer|min:1')]
    public int $max_rounds = 1;

    public array $prizes = [];

    public function rules(): array
    {
        return [
            'name' => 'required|min:3|max:255',
            'game_package_id' => 'required|exists:game_packages,id',
            'draw_mode' => 'required|in:manual,automatic',
            'max_rounds' => 'required|integer|min:1',
            'prizes_per_round' => 'required|integer|min:1|max:' . count($this->prizes),
            'card_size' => 'required|in:9,15,24',
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
            'game_package_id.required' => 'Selecione um pacote.',
        ];
    }

    #[Computed]
    public function user() { return auth()->user(); }

    #[Computed]
    public function walletBalance() { return $this->user?->wallet?->balance ?? 0; }

    #[Computed]
    public function packages() { return GamePackage::active()->get(); }

    #[Computed]
    public function selectedPackage(): ?GamePackage
    {
        return $this->game_package_id ? GamePackage::find($this->game_package_id) : null;
    }

    #[Computed]
    public function canCreate(): bool
    {
        if (!$this->selectedPackage || !$this->user) return false;
        if ($this->selectedPackage->is_free) return true;
        return (float)$this->walletBalance >= (float)$this->selectedPackage->cost_credits;
    }

    public function mount(): void
    {
        if (empty($this->prizes)) {
            $this->prizes = [
                ['temp_id' => Str::random(8), 'name' => '1º Prêmio', 'description' => ''],
                ['temp_id' => Str::random(8), 'name' => '2º Prêmio', 'description' => ''],
                ['temp_id' => Str::random(8), 'name' => '3º Prêmio', 'description' => '']
            ];
        }
    }

    public function setCardSize($size): void
    {
        $size = (int) $size;
        $allowed = !empty($this->selectedPackage?->allowed_card_sizes) 
            ? array_map('intval', (array)$this->selectedPackage->allowed_card_sizes) 
            : [9, 15, 24];

        if (in_array($size, $allowed)) {
            $this->card_size = $size;
        } else {
            $this->dispatch('notify', type: 'error', text: 'Tamanho restrito para este pacote.');
        }
    }

    public function updatedGamePackageId(): void
    {
        if ($package = $this->selectedPackage) {
            $this->max_rounds = (int) $package->max_rounds;
            $this->cards_per_player = (int) ($package->cards_per_player ?? 1);
            $allowed = !empty($package->allowed_card_sizes) ? array_map('intval', (array)$package->allowed_card_sizes) : [9, 15, 24];
            if (!in_array((int)$this->card_size, $allowed)) $this->card_size = (int)$allowed[0];
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

    public function create(): void
    {
        $this->prizes = array_filter($this->prizes, fn($prize) => !empty(trim($prize['name'] ?? '')));
        $this->prizes = array_values($this->prizes);

        if (empty($this->prizes)) {
            $this->dispatch('notify', type: 'error', text: 'Adicione pelo menos um prêmio válido.');
            return;
        }

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('notify', type: 'error', text: $firstError);
            throw $e;
        }

        if (!$this->canCreate) {
            $this->dispatch('notify', type: 'error', text: 'Saldo insuficiente.');
            return;
        }

        try {
           DB::transaction(function () {
                $game = Game::create([
                    'creator_id' => $this->user->id,
                    'game_package_id' => $this->game_package_id,
                    'name' => $this->name,
                    'draw_mode' => $this->draw_mode,
                    'auto_draw_seconds' => $this->draw_mode === 'automatic' ? ($this->auto_draw_seconds ?? 3) : null,
                    'card_size' => $this->card_size,
                    'cards_per_player' => $this->cards_per_player,
                    'prizes_per_round' => $this->prizes_per_round,
                    'show_drawn_to_players' => $this->show_drawn_to_players,
                    'show_player_matches' => $this->show_player_matches,
                    'auto_claim_prizes' => $this->auto_claim_prizes,
                    'max_rounds' => min($this->max_rounds, $this->selectedPackage->max_rounds),
                    'current_round' => 1,
                    'status' => 'draft',
                ]);

                foreach ($this->prizes as $index => $prize) {
                    $game->prizes()->create([
                        'name' => $prize['name'],
                        'description' => $prize['description'] ?? '',
                        'position' => $index + 1,
                    ]);
                }

                if (!$this->selectedPackage->is_free) {
                    $this->user->wallet->debit($this->selectedPackage->cost_credits, "Arena: {$this->name}", $game);
                }

                $this->dispatch('notify', type: 'success', text: 'Arena lançada com sucesso!');
                $this->redirect(route('games.edit', $game->uuid), navigate: true);
            });
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: $e->getMessage());
        }
    }
};
?>

<div class="relative min-h-screen bg-[#0b0d11] text-slate-200 pb-20">
    <x-slot name="header">Configurar Arena</x-slot>
    
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
                    <span class="text-white/60 text-[8px] font-bold uppercase tracking-widest italic">Host System</span>
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
                    <span class="text-blue-500 font-black tracking-[0.4em] uppercase text-[9px] italic">Host Control Center</span>
                </div>
                <h1 class="text-6xl font-black text-white tracking-tighter uppercase italic leading-none">
                    NOVA <span class="text-blue-500">ARENA</span>
                </h1>
                <p class="text-slate-500 text-sm font-bold mt-3">Configure sua partida personalizada com prêmios e regras exclusivas</p>
            </div>

            <div class="bg-[#161920] border border-white/10 rounded-3xl p-6 flex items-center gap-8 shadow-2xl">
                <div>
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 italic text-right">Saldo em Carteira</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-blue-500 font-black text-sm italic">C$</span>
                        <span class="text-4xl font-black text-white italic tracking-tighter tabular-nums">{{ number_format($this->walletBalance, 0, ',', '.') }}</span>
                    </div>
                </div>
                <a href="{{ route('wallet.index') }}" class="p-4 bg-white/5 hover:bg-blue-600 border border-white/10 rounded-2xl transition-all group">
                    <span class="text-lg group-hover:scale-110 block">➕</span>
                </a>
            </div>
        </div>

        <form wire:submit.prevent="create" class="space-y-10">
            <div class="bg-[#161920] border border-white/5 rounded-[2.5rem] p-10">
                <label class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 block italic">1. Identificação da Partida</label>
                <p class="text-[9px] text-slate-600 font-bold mb-4">Escolha um nome único e atrativo para sua arena</p>
                <input type="text" wire:model.blur="name"
                    class="w-full bg-[#0b0d11] border border-white/10 rounded-2xl px-8 py-6 text-white font-black uppercase italic tracking-widest focus:border-blue-500 transition-all text-2xl"
                    placeholder="EX: ARENA PRO #99">
                @error('name') <span class="text-red-500 text-[10px] font-black uppercase mt-2 block italic">{{ $message }}</span> @enderror
            </div>

            <div class="space-y-6">
                <div>
                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] ml-6 italic">2. Selecione o Protocolo</label>
                    <p class="text-[9px] text-slate-600 font-bold ml-6 mt-1">Cada pacote oferece recursos e limites diferentes</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($this->packages as $package)
                        @php $hasBalance = $package->is_free || ($this->walletBalance >= $package->cost_credits); @endphp
                        <label class="relative cursor-pointer group">
                            <input type="radio" wire:model.live="game_package_id" value="{{ $package->id }}" class="sr-only" {{ $hasBalance ? '' : 'disabled' }}>
                            <div class="h-full border border-white/10 rounded-[2.5rem] p-8 transition-all relative overflow-hidden
                                {{ !$hasBalance ? 'opacity-20 grayscale cursor-not-allowed' : 'hover:border-blue-500/50' }}
                                {{ $game_package_id == $package->id ? 'bg-blue-600/10 border-blue-500 ring-2 ring-blue-500/20 shadow-2xl shadow-blue-600/20' : 'bg-[#161920]' }}">
                                <div class="font-black text-white uppercase text-xs tracking-widest mb-4 italic">{{ $package->name }}</div>
                                <div class="text-4xl font-black text-white italic mb-6">{{ $package->is_free ? 'GRÁTIS' : number_format($package->cost_credits, 0) . ' C$' }}</div>
                                <ul class="space-y-2">
                                    @foreach ($package->features ?? [] as $feature)
                                        <li class="text-[9px] text-slate-500 font-black uppercase flex items-center gap-2 italic">
                                            <span class="w-1 h-1 bg-blue-500 rounded-full"></span> {{ $feature }}
                                        </li>
                                    @endforeach
                                </ul>
                                @if(!$hasBalance)
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm rounded-[2.5rem]">
                                        <span class="text-red-500 font-black text-xs uppercase italic">Saldo Insuficiente</span>
                                    </div>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            @if ($this->selectedPackage)
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
                            <div class="space-y-4">
                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic">Máximo de Rodadas</label>
                                    <p class="text-[8px] text-slate-700 font-bold mt-1">Limite: {{ $this->selectedPackage->max_rounds }} rodadas</p>
                                </div>
                                <input type="number" wire:model.live="max_rounds" min="1" max="{{ $this->selectedPackage->max_rounds }}"
                                    class="w-full bg-[#0b0d11] border border-white/10 rounded-2xl py-4 text-center font-black text-white text-xl focus:border-blue-500">
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic">Prêmios por Rodada</label>
                                    <p class="text-[8px] text-slate-700 font-bold mt-1">Distribua entre {{ count($prizes) }} prêmios cadastrados</p>
                                </div>
                                <input type="number" wire:model.live="prizes_per_round" min="1" max="{{ count($prizes) }}"
                                    class="w-full bg-[#0b0d11] border border-white/10 rounded-2xl py-4 text-center font-black text-white text-xl focus:border-blue-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <button type="button" wire:click="$toggle('show_drawn_to_players')" class="flex items-center justify-between p-5 rounded-2xl border transition-all {{ $show_drawn_to_players ? 'bg-blue-600/10 border-blue-500 text-white' : 'bg-[#0b0d11] border-white/5 opacity-50' }}">
        <div class="text-left">
            <span class="text-[10px] font-black uppercase italic block">Exibir Sorteados</span>
            <span class="text-[7px] text-slate-600 font-bold">Mostrar números aos jogadores</span>
        </div>
        <div class="w-4 h-4 rounded {{ $show_drawn_to_players ? 'bg-blue-500' : 'bg-slate-800' }}"></div>
    </button>
    <button type="button" wire:click="$toggle('show_player_matches')" class="flex items-center justify-between p-5 rounded-2xl border transition-all {{ $show_player_matches ? 'bg-blue-600/10 border-blue-500 text-white' : 'bg-[#0b0d11] border-white/5 opacity-50' }}">
        <div class="text-left">
            <span class="text-[10px] font-black uppercase italic block">Marcar Acertos</span>
            <span class="text-[7px] text-slate-600 font-bold">Destacar números na cartela</span>
        </div>
        <div class="w-4 h-4 rounded {{ $show_player_matches ? 'bg-blue-500' : 'bg-slate-800' }}"></div>
    </button>
    <button type="button" wire:click="$toggle('auto_claim_prizes')" class="flex items-center justify-between p-5 rounded-2xl border transition-all {{ $auto_claim_prizes ? 'bg-blue-600/10 border-blue-500 text-white' : 'bg-[#0b0d11] border-white/5 opacity-50' }}">
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
                                <label class="text-[10px] font-black text-white uppercase tracking-widest mb-2 block italic">📏 Arquitetura da Cartela</label>
                                <p class="text-[8px] text-slate-600 font-bold mb-6">Tamanho do grid de números</p>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                @foreach ([9, 15, 24] as $size)
                                    @php 
                                        $allowedFromDb = $this->selectedPackage?->allowed_card_sizes;
                                        $allowedArray = !empty($allowedFromDb) ? array_map('intval', (array) $allowedFromDb) : [9, 15, 24];
                                        $isSelected = (int)$this->card_size === (int)$size;
                                    @endphp
                                    <button type="button" wire:click="setCardSize({{ $size }})" {{ !in_array((int)$size, $allowedArray) ? 'disabled' : '' }}
                                        class="py-8 rounded-2xl border transition-all flex flex-col items-center {{ !in_array((int)$size, $allowedArray) ? 'opacity-10 cursor-not-allowed bg-black' : 'hover:scale-105 active:scale-95' }} {{ $isSelected ? 'border-blue-500 bg-blue-600/20 shadow-xl' : 'border-white/5 bg-[#0b0d11]' }}">
                                        <span class="text-3xl font-black italic {{ $isSelected ? 'text-white' : 'text-slate-700' }}">{{ $size }}</span>
                                        <span class="text-[7px] font-black uppercase {{ $isSelected ? 'text-blue-500' : 'text-slate-900' }}">CASAS</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="bg-[#161920] border border-white/5 rounded-[3rem] p-10">
                            <div>
                                <label class="text-[10px] font-black text-white uppercase tracking-widest mb-2 block italic">⚡ Sistema de Sorteio</label>
                                <p class="text-[8px] text-slate-600 font-bold mb-6">Controle manual ou automático</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" wire:click="$set('draw_mode', 'manual')" class="py-4 rounded-xl border font-black uppercase italic text-[10px] {{ $draw_mode === 'manual' ? 'bg-blue-600 border-blue-500 text-white' : 'bg-[#0b0d11] border-white/5 text-slate-600' }}">Manual</button>
                                <button type="button" wire:click="$set('draw_mode', 'automatic')" class="py-4 rounded-xl border font-black uppercase italic text-[10px] {{ $draw_mode === 'automatic' ? 'bg-blue-600 border-blue-500 text-white' : 'bg-[#0b0d11] border-white/5 text-slate-600' }}">Auto</button>
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
                                    <p class="text-[8px] text-blue-500 font-bold mt-2">💡 Dica: Preencha todos os títulos antes de lançar</p>
                                </div>
                            </div>
                            <button type="button" wire:click.prevent="addPrize" class="px-6 py-2 bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase hover:bg-blue-500 transition-all shadow-lg">+ ADICIONAR SLOT</button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            @foreach ($prizes as $index => $prize)
                                <div wire:key="prize-{{ $prize['temp_id'] }}" class="bg-[#0b0d11] border border-white/10 rounded-3xl p-6 space-y-4 relative group hover:border-blue-500/30 transition-all">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                                            <span class="text-[9px] font-black text-slate-500 uppercase italic">Slot #{{ $index + 1 }}</span>
                                        </div>
                                        @if(count($prizes) > 1)
                                            <button type="button" wire:click="removePrize({{ $index }})" class="text-red-900 hover:text-red-500 transition-all p-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        @endif
                                    </div>
                                    <input type="text" wire:model.blur="prizes.{{ $index }}.name" 
                                        class="w-full bg-[#161920] border border-white/5 rounded-xl px-4 py-3 text-white text-[11px] font-black uppercase placeholder-white/10 focus:border-blue-500 transition-all" 
                                        placeholder="TITULO DO PRÊMIO">
                                    <textarea wire:model.blur="prizes.{{ $index }}.description" 
                                        class="w-full bg-[#161920] border border-white/5 rounded-xl px-4 py-3 text-white text-[10px] font-bold placeholder-white/5 focus:border-blue-500 transition-all" 
                                        placeholder="BREVE DESCRIÇÃO..." rows="2"></textarea>
                                    @error("prizes.$index.name") <span class="text-red-500 text-[8px] font-black uppercase italic tracking-tighter">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row gap-6 items-center pt-10">
                    <button type="submit" {{ $this->canCreate ? '' : 'disabled' }}
                        class="flex-[2] w-full py-8 rounded-[2.5rem] font-black uppercase text-xl tracking-[0.5em] italic transition-all relative overflow-hidden group {{ $this->canCreate ? 'bg-blue-600 hover:bg-blue-500 text-white shadow-2xl shadow-blue-600/40 cursor-pointer' : 'bg-white/5 text-slate-800 cursor-not-allowed border border-white/5' }}">
                        LANÇAR OPERAÇÃO ARENA
                        <div class="absolute inset-0 bg-white/10 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700 skew-x-12"></div>
                    </button>
                    <a href="{{ route('games.index') }}" class="flex-1 w-full text-center py-8 border border-white/10 rounded-[2.5rem] font-black uppercase text-[10px] tracking-widest text-slate-600 hover:text-white hover:bg-white/5 transition-all italic">ABORTAR MISSÃO</a>
                </div>
            @endif
        </form>
    </div>
</div>