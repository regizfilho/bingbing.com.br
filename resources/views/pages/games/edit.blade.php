<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\Game\Game;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public Game $game;

    #[Validate('required|min:3|max:255')]
    public string $name = '';

    #[Validate('required|in:manual,automatic')]
    public string $draw_mode = 'manual';

    #[Validate('nullable|integer|min:2|max:10')]
    public ?int $auto_draw_seconds = 3;

    public int $prizes_per_round = 1;

    public array $prizes = [];

    /**
     * Regras de validação dinâmicas
     */
    public function rules(): array
    {
        return [
            'name' => 'required|min:3|max:255',
            'draw_mode' => 'required|in:manual,automatic',
            'auto_draw_seconds' => $this->draw_mode === 'automatic' ? 'required|integer|min:2|max:10' : 'nullable',
            // Valida que o número de prêmios por rodada não exceda o total cadastrado
            'prizes_per_round' => 'required|integer|min:1|max:' . count($this->prizes),
            'prizes.*.name' => 'required|min:2|max:255',
            'prizes.*.description' => 'nullable|string|max:500',
        ];
    }

    protected function messages(): array
    {
        return [
            'prizes_per_round.max' => 'O prêmio por rodada não pode ser maior que o total de prêmios cadastrados (' . count($this->prizes) . ').',
            'prizes.*.name.required' => 'O nome do prêmio é obrigatório.',
        ];
    }

    #[Computed]
    public function user()
    {
        return auth()->user();
    }

    public function setDrawMode(string $mode): void
    {
        $this->draw_mode = $mode;
        $this->auto_draw_seconds = ($mode === 'automatic') ? 3 : null;
    }

    public function mount(string $uuid): void
    {
        $this->game = Game::where('uuid', $uuid)
            ->with(['prizes', 'package', 'players'])
            ->firstOrFail();

        if ($this->game->creator_id !== $this->user->id) {
            abort(403, 'Você não é o criador desta partida.');
        }

        if ($this->game->status !== 'draft' && $this->game->status !== 'waiting') {
            session()->flash('error', 'Apenas partidas em rascunho ou aguardando podem ser editadas.');
            $this->redirect(route('games.index'));
            return;
        }

        $this->name = $this->game->name;
        $this->draw_mode = $this->game->draw_mode;
        $this->auto_draw_seconds = $this->game->auto_draw_seconds ?? 3;
        $this->prizes_per_round = $this->game->prizes_per_round ?? 1;

        $this->prizes = $this->game->prizes
            ->sortBy('position')
            ->map(fn($prize) => [
                'name' => $prize->name ?? '',
                'description' => $prize->description ?? '',
            ])
            ->toArray();
            
        if (empty($this->prizes)) {
            $this->addPrize();
        }
    }

    public function addPrize(): void
    {
        $this->prizes[] = ['name' => '', 'description' => ''];
    }

    public function removePrize(int $index): void
    {
        unset($this->prizes[$index]);
        $this->prizes = array_values($this->prizes);

        // Ajusta automaticamente o prizes_per_round se ele ficar órfão
        if ($this->prizes_per_round > count($this->prizes) && count($this->prizes) > 0) {
            $this->prizes_per_round = count($this->prizes);
        }
    }

    public function update(): void
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $this->game->update([
                    'name' => $this->name,
                    'draw_mode' => $this->draw_mode,
                    'auto_draw_seconds' => $this->draw_mode === 'automatic' ? $this->auto_draw_seconds : 3,
                    'prizes_per_round' => $this->prizes_per_round,
                ]);

                $this->game->prizes()->delete();
                foreach ($this->prizes as $index => $prize) {
                    $this->game->prizes()->create([
                        'name' => $prize['name'],
                        'description' => $prize['description'] ?? '',
                        'position' => $index + 1,
                    ]);
                }
            });

            $this->game->refresh();
            session()->flash('success', 'Alterações salvas com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao salvar: ' . $e->getMessage());
        }
    }

    public function publish(): void
    {
        $this->validate();

        if (empty($this->prizes)) {
            session()->flash('error', 'Adicione pelo menos um prêmio antes de publicar.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->game->update([
                    'name' => $this->name,
                    'draw_mode' => $this->draw_mode,
                    'auto_draw_seconds' => $this->draw_mode === 'automatic' ? $this->auto_draw_seconds : 3,
                    'prizes_per_round' => $this->prizes_per_round,
                    'status' => 'waiting',
                ]);

                $this->game->prizes()->delete();
                foreach ($this->prizes as $index => $prize) {
                    $this->game->prizes()->create([
                        'name' => $prize['name'],
                        'description' => $prize['description'] ?? '',
                        'position' => $index + 1,
                    ]);
                }

                if ($this->game->players()->count() > 0) {
                    $this->game->generateCardsForCurrentRound();
                }
            });

            session()->flash('success', 'Partida publicada com sucesso!');
            $this->redirect(route('games.play', $this->game->uuid));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao publicar: ' . $e->getMessage());
        }
    }
};
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-8 flex justify-between items-center flex-wrap gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Editar Partida</h1>
            <p class="text-gray-600">{{ $game->name }}</p>
        </div>
        <a href="{{ route('games.index') }}" class="text-gray-600 hover:text-gray-900">
            ← Voltar
        </a>
    </div>

    @if (session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="text-sm text-blue-800 font-medium mb-2">Pacote Selecionado</div>
            <div class="text-lg font-semibold text-blue-900">{{ $game->package->name ?? '—' }}</div>
            <div class="text-sm text-blue-700 mt-2">
                Máx. {{ $game->package->max_players ?? '?' }} jogadores •
                {{ $game->cards_per_player ?? '?' }} cartela(s) por jogador •
                {{ $game->max_rounds ?? '?' }} rodada(s)
            </div>
        </div>

        <form wire:submit="update" class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6 border">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Partida</label>
                <input type="text" wire:model.blur="name"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                @error('name')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror
            </div>

            <div class="bg-white rounded-lg shadow p-6 border">
                <label class="block text-sm font-medium text-gray-700 mb-2">Prêmios por Rodada</label>
                <input type="number" wire:model.live="prizes_per_round" min="1"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('prizes_per_round') border-red-500 @enderror">
                <p class="text-xs text-gray-500 mt-1">Total de prêmios cadastrados: {{ count($prizes) }}</p>
                @error('prizes_per_round')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror
            </div>

            <div class="bg-white rounded-lg shadow p-6 border">
                <label class="block text-sm font-medium text-gray-700 mb-4">Modo de Sorteio</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <button type="button" wire:click="setDrawMode('manual')"
                        class="border-2 rounded-lg p-4 text-left transition
                        {{ $draw_mode === 'manual' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-500' }}">
                        <div class="font-semibold mb-1">Manual</div>
                        <div class="text-sm text-gray-600">Você controla cada sorteio</div>
                    </button>

                    <button type="button" wire:click="setDrawMode('automatic')"
                        class="border-2 rounded-lg p-4 text-left transition
                        {{ $draw_mode === 'automatic' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-500' }}">
                        <div class="font-semibold mb-1">Automático</div>
                        <div class="text-sm text-gray-600">Sorteios automáticos a cada intervalo</div>
                    </button>
                </div>

                @if ($draw_mode === 'automatic')
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Intervalo (segundos)</label>
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
                    <label class="block text-sm font-medium text-gray-700">Prêmios</label>
                    <button type="button" wire:click="addPrize"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm transition">
                        + Adicionar Prêmio
                    </button>
                </div>

                <div class="space-y-4">
                    @foreach ($prizes as $index => $prize)
                        <div class="border rounded-lg p-4" wire:key="prize-{{ $index }}">
                            <div class="flex gap-4 items-start">
                                <div class="flex-1">
                                    <input type="text" wire:model.blur="prizes.{{ $index }}.name"
                                        placeholder="Nome do prêmio" 
                                        class="w-full px-4 py-2 border rounded-lg mb-2 focus:ring-2 focus:ring-blue-500">
                                    @error("prizes.{$index}.name")
                                        <span class="text-red-500 text-sm">{{ $message }}</span>
                                    @enderror

                                    <textarea wire:model.blur="prizes.{{ $index }}.description" 
                                        placeholder="Descrição (opcional)" rows="2"
                                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                                </div>

                                @if (count($prizes) > 1)
                                    <button type="button" wire:click="removePrize({{ $index }})"
                                        class="text-red-600 hover:text-red-800 self-start mt-1 font-medium">
                                        Remover
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition font-semibold">
                    Salvar Alterações
                </button>

                <button type="button" wire:click="publish"
                    class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition font-semibold">
                    Publicar Partida
                </button>

                <a href="{{ route('games.index') }}"
                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg transition font-semibold text-center">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>