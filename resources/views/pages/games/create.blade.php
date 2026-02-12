<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\Game\GamePackage;
use App\Models\Game\Game;
use Illuminate\Support\Facades\DB;

new class extends Component {
    #[Validate('required|min:3|max:255')]
    public string $name = '';

    #[Validate('required|exists:game_packages,id')]
    public string $game_package_id = '';

    #[Validate('required|in:manual,automatic')]
    public string $draw_mode = 'manual';

    #[Validate('nullable|integer|min:2|max:10')]
    public ?int $auto_draw_seconds = null;

    public int $prizes_per_round = 1; // Removido o Validate fixo daqui para usar no rules()

    #[Validate('required|in:9,15,24')]
    public int $card_size = 24;

    #[Validate('required|integer|min:1|max:10')]
    public int $cards_per_player = 1;

    public bool $show_drawn_to_players = true;
    public bool $show_player_matches = true;

    #[Validate('required|integer|min:1')]
    public int $max_rounds = 1;

    public array $prizes = [];

    /**
     * Define as regras de validação dinamicamente.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|min:3|max:255',
            'game_package_id' => 'required|exists:game_packages,id',
            'draw_mode' => 'required|in:manual,automatic',
            'max_rounds' => 'required|integer|min:1',
            // O número de prêmios por rodada não pode ser maior que o total de prêmios cadastrados
            'prizes_per_round' => 'required|integer|min:1|max:' . count($this->prizes),
            'card_size' => 'required|in:9,15,24',
            'cards_per_player' => 'required|integer|min:1|max:10',
            'prizes.*.name' => 'required|min:2|max:255',
            'prizes.*.description' => 'nullable|string|max:500',
        ];
    }

    /**
     * Mensagens de erro personalizadas.
     */
    protected function messages(): array
    {
        return [
            'prizes_per_round.max' => 'Você quer distribuir :max prêmios por rodada, mas só cadastrou ' . count($this->prizes) . ' prêmio(s) no total.',
            'prizes.*.name.required' => 'O nome do prêmio é obrigatório.',
        ];
    }

    // --- COMPUTED PROPERTIES ---

    #[Computed]
    public function user()
    {
        return auth()->user();
    }

    #[Computed]
    public function packages()
    {
        return GamePackage::active()->get();
    }

    #[Computed]
    public function selectedPackage(): ?GamePackage
    {
        return $this->game_package_id ? GamePackage::find($this->game_package_id) : null;
    }

    #[Computed]
    public function canCreate(): bool
    {
        if (!$this->selectedPackage) return false;
        if ($this->selectedPackage->is_free) return true;
        
        return $this->user->wallet->balance >= $this->selectedPackage->cost_credits;
    }

    // --- AÇÕES ---

    public function mount(): void
    {
        $this->prizes = [
            ['name' => '1º Premio', 'description' => ''],
            ['name' => '2º Premio', 'description' => ''],
            ['name' => '3º Premio', 'description' => '']
        ];
    }

    public function setCardSize(int $size): void
    {
        $allowed = $this->selectedPackage->allowed_card_sizes ?? [24];
        if (in_array($size, array_map('intval', $allowed))) {
            $this->card_size = $size;
        }
    }

    public function updatedGamePackageId(): void
    {
        if ($package = $this->selectedPackage) {
            $this->max_rounds = (int) $package->max_rounds;
            $this->cards_per_player = (int) ($package->cards_per_player ?? 1);
            
            $allowed = array_map('intval', $package->allowed_card_sizes ?? [24]);
            if (!in_array((int)$this->card_size, $allowed)) {
                $this->card_size = (int)$allowed[0];
            }
        }
    }

    public function updatedDrawMode(): void
    {
        $this->auto_draw_seconds = ($this->draw_mode === 'automatic') ? 3 : null;
    }

    public function addPrize(): void
    {
        $this->prizes[] = ['name' => '', 'description' => ''];
    }

    public function removePrize(int $index): void
    {
        unset($this->prizes[$index]);
        $this->prizes = array_values($this->prizes);
        
        // Se após remover, o prêmios_por_rodada ficar maior que o total, ajustamos para o máximo
        if ($this->prizes_per_round > count($this->prizes) && count($this->prizes) > 0) {
            $this->prizes_per_round = count($this->prizes);
        }
    }

    public function create(): void
    {
        $this->validate();

        if (!$this->canCreate) {
            session()->flash('error', 'Saldo insuficiente.');
            return;
        }

        try {
            DB::transaction(function () {
                $game = Game::create([
                    'creator_id' => $this->user->id,
                    'game_package_id' => $this->game_package_id,
                    'name' => $this->name,
                    'draw_mode' => $this->draw_mode,
                    'auto_draw_seconds' => $this->draw_mode === 'automatic' ? $this->auto_draw_seconds : 3,
                    'card_size' => $this->card_size,
                    'cards_per_player' => $this->cards_per_player,
                    'prizes_per_round' => $this->prizes_per_round,
                    'show_drawn_to_players' => $this->show_drawn_to_players,
                    'show_player_matches' => $this->show_player_matches,
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
                    $this->user->wallet->debit($this->selectedPackage->cost_credits, "Criação: {$game->name}", $game);
                }

                session()->flash('success', 'Partida criada!');
                $this->redirect(route('games.edit', $game->uuid));
            });
        } catch (\Exception $e) {
            session()->flash('error', 'Erro: ' . $e->getMessage());
        }
    }
};
?>
<div>
    <x-slot name="header">Configurar Missão</x-slot>

    <div class="max-w-5xl mx-auto px-4 py-12">
        
        {{-- Header Tático --}}
        <div class="mb-12">
            <div class="flex items-center gap-4 mb-3">
                <div class="h-[1px] w-12 bg-gradient-to-r from-blue-600 to-transparent"></div>
                <span class="text-blue-500/80 font-black tracking-[0.4em] uppercase text-[9px] italic">Host Command Center</span>
            </div>
            <h1 class="text-5xl font-black text-white tracking-tighter uppercase italic leading-none">
                CRIAR NOVA <span class="text-blue-500">PARTIDA</span>
            </h1>
        </div>

        {{-- Barra de Recursos --}}
        <div class="mb-10 relative overflow-hidden bg-[#0b0d11] border border-white/10 rounded-[2rem] p-8 shadow-2xl">
            <div class="absolute top-0 right-0 w-32 h-full bg-blue-600/5 blur-3xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1 italic">Créditos de Operação</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-blue-500 font-black text-sm italic">C$</span>
                        <span class="text-4xl font-black text-white italic tracking-tighter">
                            {{ number_format($this->user->wallet->balance, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                <a href="{{ route('wallet.index') }}" class="group flex items-center gap-3 px-6 py-3 bg-white/5 hover:bg-white/10 border border-white/10 rounded-2xl transition-all">
                    <span class="text-[10px] font-black text-white uppercase tracking-widest italic">Recarregar Saldo</span>
                    <span class="text-blue-500 group-hover:translate-x-1 transition-transform">➕</span>
                </a>
            </div>
        </div>

        <form wire:submit="create" class="space-y-10">
            
            {{-- Identificação --}}
            <div class="space-y-4">
                <label class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] ml-6 italic">Identificação da Partida</label>
                <div class="relative group">
                    <div class="absolute -inset-1 bg-blue-600/5 rounded-[2.5rem] blur opacity-0 group-focus-within:opacity-100 transition duration-500"></div>
                    <input type="text" wire:model.blur="name"
                        class="relative w-full bg-[#0b0d11] border border-white/10 rounded-[2rem] px-8 py-6 text-white font-black uppercase italic tracking-widest focus:border-blue-500/50 focus:ring-0 transition-all placeholder:text-slate-800 text-lg"
                        placeholder="NOME DA SALA EX: ALPHA LOBBY">
                </div>
                @error('name') <span class="text-red-500 text-[10px] font-black uppercase ml-6 tracking-widest italic">{{ $message }}</span> @enderror
            </div>

            {{-- Seleção de Protocolo (Pacotes) --}}
            <div class="space-y-6">
                <label class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] ml-6 italic">Protocolo de Partida (Pacote)</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($this->packages as $package)
                        @php $hasBalance = $package->is_free || $this->user->wallet->hasBalance($package->cost_credits); @endphp
                        <label class="relative group cursor-pointer">
                            <input type="radio" wire:model.live="game_package_id" value="{{ $package->id }}" class="sr-only" {{ $hasBalance ? '' : 'disabled' }}>
                            <div class="h-full border border-white/10 rounded-[2.5rem] p-8 transition-all relative overflow-hidden
                                {{ !$hasBalance ? 'opacity-20 cursor-not-allowed grayscale' : 'hover:border-blue-500/50' }}
                                {{ $game_package_id == $package->id ? 'bg-blue-600/10 border-blue-500 ring-1 ring-blue-500/50' : 'bg-[#0b0d11]' }}">
                                
                                <div class="font-black text-white uppercase text-xs tracking-widest mb-6 italic">{{ $package->name }}</div>

                                <div class="mb-8">
                                    @if ($package->is_free)
                                        <span class="text-emerald-500 font-black text-2xl italic tracking-tighter">FREE</span>
                                    @else
                                        <span class="text-white font-black text-3xl italic tracking-tighter">{{ number_format($package->cost_credits, 0) }}</span>
                                        <span class="text-blue-500 text-[10px] font-black ml-1 uppercase">Créditos</span>
                                    @endif
                                </div>

                                <ul class="space-y-3">
                                    @foreach ($package->features as $feature)
                                        <li class="text-[9px] text-slate-500 font-black uppercase flex items-center gap-2 italic">
                                            <span class="w-1 h-1 bg-blue-500 rounded-full shadow-[0_0_5px_#3b82f6]"></span> {{ $feature }}
                                        </li>
                                    @endforeach
                                </ul>

                                @if($game_package_id == $package->id)
                                    <div class="absolute top-6 right-6 w-2 h-2 rounded-full bg-blue-500 shadow-[0_0_15px_#3b82f6] animate-pulse"></div>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            @if ($game_package_id && $this->selectedPackage)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 animate-in fade-in slide-in-from-bottom-6 duration-700">
                    
                    {{-- Parâmetros da Missão --}}
                    <div class="bg-[#0b0d11] border border-white/10 rounded-[2.5rem] p-10 space-y-10">
                        <div>
                            <div class="flex items-center gap-3 mb-8">
                                <span class="text-blue-500">🔄</span>
                                <h3 class="text-[11px] font-black text-white uppercase tracking-[0.2em] italic">Configuração de Fluxo</h3>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-8">
                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-3 block italic">Rodadas Máx.</label>
                                    <input type="number" wire:model.live="max_rounds" min="1" max="{{ $this->selectedPackage->max_rounds }}"
                                        class="w-full bg-white/5 border border-white/5 rounded-2xl py-4 text-center font-black text-white text-xl focus:border-blue-500/50 focus:ring-0 transition-all">
                                </div>
                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-3 block italic">Prêmios / Rodada</label>
                                    <input type="number" wire:model.live="prizes_per_round" min="1" max="10"
                                        class="w-full bg-white/5 border border-white/5 rounded-2xl py-4 text-center font-black text-white text-xl focus:border-blue-500/50 focus:ring-0 transition-all">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 pt-6 border-t border-white/5">
                            <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block italic mb-2">Interface do Jogador</label>
                            <label class="flex items-center justify-between p-5 bg-white/[0.02] border border-white/5 rounded-2xl cursor-pointer group hover:bg-white/5 transition-all">
                                <div>
                                    <div class="text-[10px] font-black text-white uppercase italic">Números Sorteados</div>
                                    <div class="text-[8px] font-bold text-slate-600 uppercase mt-1">Visibilidade Global</div>
                                </div>
                                <input type="checkbox" wire:model.live="show_drawn_to_players" class="w-6 h-6 bg-[#05070a] border-white/10 rounded-lg text-blue-600 focus:ring-0">
                            </label>
                        </div>
                    </div>

                    {{-- Geometria e Sorteio --}}
                    <div class="space-y-8">
                        {{-- Tamanho da Cartela --}}
                        <div class="bg-[#0b0d11] border border-white/10 rounded-[2.5rem] p-10">
                            <div class="flex items-center gap-3 mb-8">
                                <span class="text-blue-500">📏</span>
                                <h3 class="text-[11px] font-black text-white uppercase tracking-[0.2em] italic">Geometria da Cartela</h3>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                @foreach ([9, 15, 24] as $size)
                                    @php $allowed = in_array($size, array_map('intval', $this->selectedPackage->allowed_card_sizes ?? [24])); @endphp
                                    <button type="button" wire:click="setCardSize({{ $size }})" {{ !$allowed ? 'disabled' : '' }}
                                        class="py-6 rounded-2xl border transition-all flex flex-col items-center
                                        {{ !$allowed ? 'opacity-10 grayscale' : 'hover:scale-105 active:scale-95' }}
                                        {{ (int)$card_size === $size ? 'border-blue-500 bg-blue-500/10' : 'border-white/10 bg-white/5' }}">
                                        <div class="text-3xl font-black italic tracking-tighter {{ (int)$card_size === $size ? 'text-white' : 'text-slate-600' }}">{{ $size }}</div>
                                        <div class="text-[8px] font-black uppercase mt-1 {{ (int)$card_size === $size ? 'text-blue-500' : 'text-slate-700' }}">Slots</div>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Modo de Sorteio --}}
                        <div class="bg-[#0b0d11] border border-white/10 rounded-[2.5rem] p-10">
                            <div class="flex items-center gap-3 mb-8">
                                <span class="text-blue-500">⚡</span>
                                <h3 class="text-[11px] font-black text-white uppercase tracking-[0.2em] italic">Módulo de Sorteio</h3>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <button type="button" wire:click="$set('draw_mode', 'manual')"
                                    class="p-5 rounded-2xl border transition-all {{ $draw_mode === 'manual' ? 'border-blue-500 bg-blue-500/10 text-white' : 'border-white/10 bg-white/5 text-slate-500' }}">
                                    <span class="text-[10px] font-black uppercase italic">Manual</span>
                                </button>
                                <button type="button" wire:click="$set('draw_mode', 'automatic')"
                                    class="p-5 rounded-2xl border transition-all {{ $draw_mode === 'automatic' ? 'border-blue-500 bg-blue-500/10 text-white' : 'border-white/10 bg-white/5 text-slate-500' }}">
                                    <span class="text-[10px] font-black uppercase italic">Automático</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Manifesto de Prêmios --}}
                <div class="bg-[#0b0d11] border border-white/10 rounded-[2.5rem] p-10">
                    <div class="flex justify-between items-center mb-10">
                        <div class="flex items-center gap-3">
                            <span class="text-blue-500">🎁</span>
                            <h3 class="text-[11px] font-black text-white uppercase tracking-[0.2em] italic">Manifesto de Prêmios</h3>
                        </div>
                        <button type="button" wire:click="addPrize" class="text-[10px] font-black text-blue-500 hover:text-white uppercase tracking-widest transition-colors">
                            + Adicionar Recompensa
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach ($prizes as $index => $prize)
                            <div class="group bg-white/[0.02] border border-white/10 rounded-2xl p-6 transition-all hover:border-white/20">
                                <div class="flex justify-between mb-4">
                                    <span class="text-[9px] font-black text-slate-600 uppercase italic">Slot #{{ $index + 1 }}</span>
                                    @if ($index > 0)
                                        <button type="button" wire:click="removePrize({{ $index }})" class="text-red-900 group-hover:text-red-600 transition-colors text-[9px] font-black uppercase italic">Eliminar</button>
                                    @endif
                                </div>
                                <input type="text" wire:model.blur="prizes.{{ $index }}.name" placeholder="NOME DO ITEM"
                                    class="w-full bg-[#05070a] border border-white/5 rounded-xl px-4 py-3 text-white text-[11px] font-black uppercase italic tracking-widest focus:border-blue-500/50 focus:ring-0 mb-3 transition-all">
                                <textarea wire:model.blur="prizes.{{ $index }}.description" placeholder="DETALHES DA RECOMPENSA..." rows="2"
                                    class="w-full bg-[#05070a] border border-white/5 rounded-xl px-4 py-3 text-white text-[10px] font-bold focus:border-blue-500/50 focus:ring-0 transition-all"></textarea>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Ações Finais --}}
            <div class="flex flex-col sm:flex-row gap-6 pt-12">
                <button type="submit" {{ $this->canCreate ? '' : 'disabled' }}
                    class="flex-[2] py-6 rounded-[2rem] font-black uppercase text-xs tracking-[0.4em] italic transition-all relative overflow-hidden group
                    {{ $this->canCreate ? 'bg-blue-600 hover:bg-blue-500 text-white shadow-2xl shadow-blue-600/20' : 'bg-white/5 text-slate-800 cursor-not-allowed border border-white/5' }}">
                    <span class="relative z-10">Inicializar Protocolo Arena</span>
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                </button>

                <a href="{{ route('games.index') }}"
                    class="flex-1 bg-transparent hover:bg-white/5 text-slate-600 hover:text-white py-6 rounded-[2rem] font-black uppercase text-xs tracking-[0.4em] italic text-center transition-all border border-white/10">
                    Abortar
                </a>
            </div>
        </form>
    </div>
</div>