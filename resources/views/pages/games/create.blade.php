<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\Game\GamePackage;
use App\Models\Game\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
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

    public bool $show_drawn_to_players = false;
    public bool $show_player_matches = false;
    public bool $auto_claim_prizes = false;

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
            'prizes.*.name.required' => 'Dê um nome ao prêmio.',
            'name.required' => 'O nome da sala é obrigatório.',
            'game_package_id.required' => 'Escolha um tipo de sala.',
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
        $this->card_size = (int) $size;
        $this->dispatch('notify', type: 'info', text: "Cartela de $size números selecionada.");
    }

    public function updatedGamePackageId(): void
    {
        if ($package = $this->selectedPackage) {
            $this->max_rounds = (int) $package->max_rounds;
            $this->cards_per_player = (int) ($package->cards_per_player ?? 1);
            
            $allowed = !empty($package->allowed_card_sizes) 
                ? array_map('intval', (array)$package->allowed_card_sizes) 
                : [9, 15, 24];
                
            if (!in_array((int)$this->card_size, $allowed)) {
                $this->card_size = (int)$allowed[0];
            }
        }
        $this->dispatch('notify', type: 'info', text: 'Tipo de sala atualizado.');
    }

    public function addPrize(): void
    {
        $this->prizes[] = ['temp_id' => Str::random(8), 'name' => '', 'description' => ''];
    }

    public function removePrize(int $index): void
    {
        if (count($this->prizes) > 1) {
            array_splice($this->prizes, $index, 1);
        }
    }

    public function create(): void
    {
        try {
            $this->validate();
            
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
                    'status' => 'waiting',
                    'uuid' => (string) Str::uuid(),
                    'invite_code' => strtoupper(Str::random(10)),
                ]);

                foreach ($this->prizes as $index => $prize) {
                    $game->prizes()->create([
                        'uuid' => (string) Str::uuid(),
                        'name' => $prize['name'],
                        'description' => $prize['description'] ?? '',
                        'position' => $index + 1,
                    ]);
                }

                if (!$this->selectedPackage->is_free) {
                    $this->user->wallet->debit($this->selectedPackage->cost_credits, "Arena: {$this->name}", $game);
                }

                return redirect()->route('games.edit', $game->uuid);
            });
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao criar: ' . $e->getMessage());
        }
    }
};
?>

<div class="min-h-screen bg-[#05070a] text-slate-200 pb-20 italic">
    <x-loading target="create, updatedGamePackageId, setCardSize" message="CARREGANDO..." />

    <div class="max-w-6xl mx-auto px-6 py-12">
        
        {{-- CABEÇALHO --}}
        <div class="flex flex-col md:flex-row justify-between items-center gap-8 mb-16">
            <div>
                <h1 class="text-6xl font-black text-white uppercase italic tracking-tighter leading-none">
                    CRIAR <span class="text-blue-600">SALA</span>
                </h1>
                <p class="text-slate-500 font-bold mt-2 uppercase text-xs tracking-widest">Configure sua partida de bingo</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/5 p-4 pr-10 rounded-full flex items-center gap-6 shadow-2xl">
                <div class="w-14 h-14 bg-blue-600 rounded-full overflow-hidden border-2 border-white/10">
                    @if($this->user->avatar_path)
                        <img src="{{ Storage::url($this->user->avatar_path) }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center font-black text-white text-xl">{{ substr($this->user->name, 0, 1) }}</div>
                    @endif
                </div>
                <div>
                    <p class="text-[9px] font-black text-slate-600 uppercase mb-1">Seu Saldo</p>
                    <div class="text-3xl font-black text-white italic tracking-tighter">C$ {{ number_format($this->walletBalance, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>

        <form wire:submit.prevent="create" class="space-y-12">
            
            {{-- NOME DA SALA --}}
            <div class="bg-[#0b0d11] border border-white/5 rounded-[3rem] p-10">
                <label class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-4 block">1. Nome da sua Sala</label>
                <input type="text" wire:model.blur="name"
                    class="w-full bg-transparent border-b-2 border-white/10 text-white font-black uppercase italic tracking-tighter focus:border-blue-500 transition-all text-4xl p-0 pb-4 outline-none"
                    placeholder="DIGITE O NOME DA SALA...">
                @error('name') <span class="text-red-500 text-[10px] font-black mt-2 block">{{ $message }}</span> @enderror
            </div>

            {{-- ESCOLHA DO PACOTE --}}
            <div class="space-y-6">
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4">2. Escolha o tipo de sala</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($this->packages as $package)
                        @php $hasBalance = $package->is_free || ($this->walletBalance >= $package->cost_credits); @endphp
                        <div wire:click="$set('game_package_id', '{{ $package->id }}')" 
                            class="cursor-pointer border-2 rounded-[2.5rem] p-8 transition-all relative overflow-hidden
                            {{ !$hasBalance ? 'opacity-30 grayscale cursor-not-allowed bg-black' : '' }}
                            {{ $game_package_id == $package->id ? 'border-blue-600 bg-blue-600/10' : 'border-white/5 bg-[#0b0d11] hover:border-white/20' }}">
                            
                            <div class="flex justify-between items-center mb-6">
                                <span class="font-black text-[10px] text-slate-500 uppercase">{{ $package->name }}</span>
                                @if($game_package_id == $package->id) <div class="w-3 h-3 bg-blue-500 rounded-full shadow-[0_0_10px_#3b82f6]"></div> @endif
                            </div>
                            <div class="text-4xl font-black text-white mb-6 italic">
                                {{ $package->is_free ? 'GRÁTIS' : 'C$ '.number_format($package->cost_credits, 0) }}
                            </div>
                            <div class="space-y-2">
                                @foreach ($package->features ?? [] as $feature)
                                    <p class="text-[9px] text-slate-400 font-bold uppercase flex items-center gap-2">
                                        <span class="w-1 h-1 bg-blue-600 rounded-full"></span> {{ $feature }}
                                    </p>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($this->selectedPackage)
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                    
                    {{-- REGRAS --}}
                    <div class="lg:col-span-2 bg-[#0b0d11] border border-white/5 rounded-[3rem] p-10 space-y-10">
                        <div class="flex items-center gap-4 border-b border-white/5 pb-6">
                            <span class="text-2xl">⚙️</span>
                            <h3 class="font-black text-white uppercase italic tracking-widest text-sm">Regras do Jogo</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div class="space-y-4">
                                <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Total de Rodadas</label>
                                <input type="number" wire:model.live="max_rounds" min="1" max="{{ $this->selectedPackage->max_rounds }}"
                                    class="w-full bg-[#05070a] border border-white/10 rounded-2xl py-5 text-center font-black text-white text-2xl focus:border-blue-500 outline-none">
                            </div>
                            <div class="space-y-4">
                                <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Cartelas por Pessoa</label>
                                <input type="number" wire:model.live="cards_per_player" min="1" max="{{ $this->selectedPackage->max_cards_per_player ?? 10 }}"
                                    class="w-full bg-[#05070a] border border-white/10 rounded-2xl py-5 text-center font-black text-white text-2xl focus:border-blue-500 outline-none">
                            </div>
                            <div class="space-y-4">
                                <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Prêmios por Rodada</label>
                                <input type="number" wire:model.live="prizes_per_round" min="1" max="{{ count($prizes) }}"
                                    class="w-full bg-[#05070a] border border-white/10 rounded-2xl py-5 text-center font-black text-white text-2xl focus:border-blue-500 outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach([
                                ['m' => 'show_drawn_to_players', 't' => 'Ver Sorteados'],
                                ['m' => 'show_player_matches', 't' => 'Marcar Números'],
                                ['m' => 'auto_claim_prizes', 't' => 'Bater Automático'],
                            ] as $toggle)
                            <button type="button" wire:click="$toggle('{{ $toggle['m'] }}')"
                                class="flex items-center justify-between p-5 rounded-2xl border transition-all
                                {{ $this->{$toggle['m']} ? 'bg-blue-600/10 border-blue-600' : 'bg-[#05070a] border-white/5' }}">
                                <span class="text-[10px] font-black uppercase {{ $this->{$toggle['m']} ? 'text-blue-500' : 'text-slate-600' }}">{{ $toggle['t'] }}</span>
                                <div class="w-5 h-5 rounded-full border-2 {{ $this->{$toggle['m']} ? 'bg-blue-600 border-blue-400' : 'border-white/10' }}"></div>
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- TAMANHO DA CARTELA --}}
                    <div class="space-y-10">
                        <div class="bg-[#0b0d11] border border-white/5 rounded-[3rem] p-10">
                            <label class="text-[10px] font-black text-white uppercase tracking-widest mb-6 block text-center">Quantos números na cartela?</label>
                            <div class="grid grid-cols-3 gap-4">
                                @foreach ([9, 15, 24] as $size)
                                    <button type="button" wire:click="setCardSize({{ $size }})"
                                        class="aspect-square rounded-2xl border transition-all flex flex-col items-center justify-center
                                        {{ (int)$card_size === (int)$size ? 'bg-blue-600 border-blue-400 shadow-xl' : 'bg-[#05070a] border-white/5 text-slate-700' }}">
                                        <span class="text-3xl font-black italic {{ (int)$card_size === (int)$size ? 'text-white' : 'text-slate-800' }}">{{ $size }}</span>
                                        <span class="text-[8px] font-black uppercase">Números</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="bg-[#0b0d11] border border-white/5 rounded-[3rem] p-8">
                            <label class="text-[10px] font-black text-white uppercase tracking-widest mb-4 block text-center">Como será o sorteio?</label>
                            <div class="flex bg-[#05070a] p-2 rounded-2xl gap-2">
                                <button type="button" wire:click="$set('draw_mode', 'manual')" 
                                    class="flex-1 py-3 rounded-xl text-[10px] font-black uppercase transition-all {{ $draw_mode === 'manual' ? 'bg-blue-600 text-white' : 'text-slate-600' }}">Manual</button>
                                <button type="button" wire:click="$set('draw_mode', 'automatic')" 
                                    class="flex-1 py-3 rounded-xl text-[10px] font-black uppercase transition-all {{ $draw_mode === 'automatic' ? 'bg-blue-600 text-white' : 'text-slate-600' }}">Auto</button>
                            </div>
                        </div>
                    </div>

                    {{-- PRÊMIOS --}}
                    <div class="lg:col-span-3 bg-[#0b0d11] border border-white/5 rounded-[3.5rem] p-12">
                        <div class="flex justify-between items-center mb-10">
                            <h3 class="font-black text-white uppercase italic tracking-widest">🏆 Prêmios da Sala</h3>
                            <button type="button" wire:click="addPrize" class="text-blue-500 font-black text-[10px] uppercase border border-blue-500/30 px-4 py-2 rounded-xl hover:bg-blue-500 hover:text-white transition-all">+ Adicionar</button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            @foreach ($prizes as $index => $prize)
                                <div class="bg-[#05070a] border border-white/5 rounded-3xl p-6 space-y-4">
                                    <div class="flex justify-between text-[9px] font-black text-slate-600 uppercase">
                                        <span>Prêmio #{{ $index + 1 }}</span>
                                        @if(count($prizes) > 1) <button type="button" wire:click="removePrize({{ $index }})" class="text-red-900 font-black">Remover</button> @endif
                                    </div>
                                    <input type="text" wire:model.blur="prizes.{{ $index }}.name" 
                                        class="w-full bg-[#0b0d11] border border-white/10 rounded-xl px-4 py-3 text-white text-xs font-black uppercase outline-none focus:border-blue-500" placeholder="Ex: Pix de 50 reais">
                                    <textarea wire:model.blur="prizes.{{ $index }}.description" 
                                        class="w-full bg-[#0b0d11] border border-white/10 rounded-xl px-4 py-3 text-white text-[10px] font-bold outline-none focus:border-blue-500" placeholder="Descrição (opcional)"></textarea>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row gap-6 pt-10">
                    <button type="submit" {{ $this->canCreate ? '' : 'disabled' }}
                        class="flex-[2] py-8 rounded-[2.5rem] font-black uppercase text-2xl italic transition-all 
                        {{ $this->canCreate ? 'bg-blue-600 hover:bg-blue-500 text-white shadow-2xl shadow-blue-600/20' : 'bg-white/5 text-slate-800' }}">
                        CRIAR E ABRIR SALA AGORA
                    </button>
                    <a href="{{ route('games.index') }}" class="flex-1 py-8 border border-white/10 rounded-[2.5rem] font-black uppercase text-[10px] text-slate-600 flex items-center justify-center hover:text-white transition-all">CANCELAR</a>
                </div>
            @endif
        </form>
    </div>
    <x-toast />
</div>