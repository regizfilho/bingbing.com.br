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
    <x-slot name="header">Criar Nova Partida</x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Criar Nova Partida</h1>
            <p class="text-gray-600">Configure os detalhes da sua partida de bingo</p>
        </div>

        @if (session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4 flex justify-between items-center">
            <span class="text-sm text-blue-800">Seu saldo:</span>
            <span class="text-lg font-bold text-blue-900">
                {{ number_format($this->user->wallet->balance, 0) }} créditos
            </span>
        </div>

        <form wire:submit="create" class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6 border">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Partida</label>
                <input type="text" wire:model.blur="name"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                    placeholder="Ex: Bingo de Natal 2024">
                @error('name')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror
            </div>

            <div class="bg-white rounded-lg shadow p-6 border">
                <label class="block text-sm font-medium text-gray-700 mb-4">Escolha o Pacote</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach ($this->packages as $package)
                        @php
                            $hasBalance = $package->is_free || $this->user->wallet->hasBalance($package->cost_credits);
                        @endphp
                        <label
                            class="border-2 rounded-lg p-4 transition 
                            {{ $hasBalance ? 'cursor-pointer hover:border-blue-500' : 'opacity-50 cursor-not-allowed' }}
                            {{ $game_package_id == $package->id ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}">
                            <input type="radio" wire:model.live="game_package_id" value="{{ $package->id }}"
                                class="sr-only" {{ $hasBalance ? '' : 'disabled' }}>
                            <div class="text-center">
                                <div class="font-semibold text-gray-900 mb-2">{{ $package->name }}</div>
                                <div class="text-sm text-gray-600 mb-3">
                                    @if ($package->is_free)
                                        <span class="text-green-600 font-semibold">Grátis</span>
                                    @else
                                        <span class="font-semibold">{{ number_format($package->cost_credits, 0) }}
                                            créditos</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 space-y-1 text-left">
                                    @foreach ($package->features as $feature)
                                        <div>• {{ $feature }}</div>
                                    @endforeach
                                </div>
                                @if (!$hasBalance)
                                    <div class="mt-2 text-xs text-red-600 font-medium">Saldo insuficiente</div>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('game_package_id')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror
            </div>

            @if ($game_package_id && $this->selectedPackage)
                <div class="bg-white rounded-lg shadow p-6 border">
                    <label class="block text-sm font-medium text-gray-700 mb-4">Rodadas</label>
                    <div>
                        <label class="block text-sm text-gray-600 mb-2">
                            Número de Rodadas (máx: {{ $this->selectedPackage->max_rounds }})
                        </label>
                        <input type="number" wire:model.live="max_rounds" min="1"
                            max="{{ $this->selectedPackage->max_rounds }}"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">
                            Após cada rodada, novas cartelas serão geradas automaticamente
                        </p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border">
                    <label class="block text-sm font-medium text-gray-700 mb-4">Prêmios por Rodada</label>
                    <input type="number" wire:model.live="prizes_per_round" min="1" max="10"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('prizes_per_round') border-red-500 @enderror">

                    @error('prizes_per_round')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror

                    <p class="text-xs text-gray-500 mt-1">
                        Quantos prêmios serão distribuídos em cada rodada (Total disponível: {{ count($prizes) }})
                    </p>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border">
                    <label class="block text-sm font-medium text-gray-700 mb-4">Tamanho da Cartela</label>
                    <div class="grid grid-cols-3 gap-4" wire:key="card-size-selector-{{ $game_package_id }}">
                        @php
                            $allowedSizes = $this->selectedPackage->allowed_card_sizes ?? [24];
                        @endphp

                        @foreach ([9, 15, 24] as $size)
                            @php
                                $isDisabled = !in_array($size, array_map('intval', $allowedSizes));
                                $isActive = (int) $card_size === $size;
                            @endphp

                            <button type="button" wire:key="btn-size-{{ $size }}"
                                wire:click="setCardSize({{ $size }})" {{ $isDisabled ? 'disabled' : '' }}
                                class="border-2 rounded-lg p-4 transition text-center
                {{ $isDisabled ? 'opacity-40 cursor-not-allowed bg-gray-50 border-gray-100' : 'cursor-pointer' }} 
                {{ $isActive ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}">

                                <div class="font-bold text-2xl {{ $isActive ? 'text-blue-600' : 'text-gray-400' }}">
                                    {{ $size }}
                                </div>
                                <div class="text-sm {{ $isActive ? 'text-blue-700' : 'text-gray-500' }}">números</div>
                            </button>
                        @endforeach
                    </div>
                    @error('card_size')
                        <span class="text-red-500 text-sm mt-2 block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="bg-white rounded-lg shadow p-6 border">
                    <label class="block text-sm font-medium text-gray-700 mb-4">Cartelas por Jogador</label>
                    <input type="number" wire:model.live="cards_per_player" min="1" max="10"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">
                        Cada jogador receberá esta quantidade de cartelas ao entrar
                    </p>
                    @error('cards_per_player')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="bg-white rounded-lg shadow p-6 border">
                    <label class="block text-sm font-medium text-gray-700 mb-4">Controles de Visibilidade</label>
                    <div class="space-y-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="show_drawn_to_players"
                                class="w-5 h-5 text-blue-600 rounded">
                            <div>
                                <div class="font-medium">Mostrar números sorteados aos jogadores</div>
                                <div class="text-sm text-gray-500">Jogadores verão os números conforme são sorteados
                                </div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="show_player_matches"
                                class="w-5 h-5 text-blue-600 rounded">
                            <div>
                                <div class="font-medium">Destacar números correspondentes</div>
                                <div class="text-sm text-gray-500">Círculo amarelo nos números que o jogador possui
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border">
                    <label class="block text-sm font-medium text-gray-700 mb-4">Modo de Sorteio</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <label
                            class="border-2 rounded-lg p-4 cursor-pointer transition hover:border-blue-500 
            {{ $draw_mode === 'manual' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}">
                            <input type="radio" wire:model.live="draw_mode" value="manual" name="draw_mode"
                                class="sr-only">
                            <div class="font-semibold mb-1">Manual</div>
                            <div class="text-sm text-gray-600">Você controla quando cada número é sorteado</div>
                        </label>

                        <label
                            class="border-2 rounded-lg p-4 cursor-pointer transition hover:border-blue-500 
            {{ $draw_mode === 'automatic' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}">
                            <input type="radio" wire:model.live="draw_mode" value="automatic" name="draw_mode"
                                class="sr-only">
                            <div class="font-semibold mb-1">Automático</div>
                            <div class="text-sm text-gray-600">Números sorteados automaticamente</div>
                        </label>
                    </div>

                    @if ($draw_mode === 'automatic')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Intervalo entre sorteios (segundos)
                            </label>
                            <input type="number" wire:model.live="auto_draw_seconds" min="2" max="10"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            @error('auto_draw_seconds')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                            @enderror
                        </div>
                    @endif
                </div>

                <div class="bg-white rounded-lg shadow p-6 border">
                    <div class="flex justify-between items-center mb-4">
                        <label class="block text-sm font-medium text-gray-700">Prêmios por Rodada</label>
                        <button type="button" wire:click="addPrize"
                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition">
                            + Adicionar Prêmio
                        </button>
                    </div>

                    <div class="space-y-4">
                        @foreach ($prizes as $index => $prize)
                            <div class="border rounded-lg p-4">
                                <div class="flex gap-4 items-start">
                                    <div class="flex-1">
                                        <input type="text" wire:model.blur="prizes.{{ $index }}.name"
                                            placeholder="Nome do prêmio"
                                            class="w-full px-4 py-2 border rounded-lg mb-2 focus:ring-2 focus:ring-blue-500">
                                        @error("prizes.{$index}.name")
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror

                                        <textarea wire:model.blur="prizes.{{ $index }}.description" placeholder="Descrição (opcional)" rows="2"
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                                    </div>

                                    @if ($index > 0)
                                        <button type="button" wire:click="removePrize({{ $index }})"
                                            class="text-red-600 hover:text-red-700 px-3 py-2 font-medium">
                                            Remover
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex gap-4">
                <button type="submit" {{ $this->canCreate ? '' : 'disabled' }}
                    class="flex-1 px-6 py-3 rounded-lg transition font-semibold shadow
        {{ $this->canCreate ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-300 text-gray-500 cursor-not-allowed' }}">
                    <span wire:loading.remove wire:target="create">Criar Partida</span>
                    <span wire:loading wire:target="create">Criando...</span>
                </button>

                <a href="{{ route('games.index') }}"
                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg transition font-semibold text-center">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
