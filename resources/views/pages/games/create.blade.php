<?php

use Livewire\Component;
use App\Models\Game\GamePackage;
use App\Models\Game\Game;

new class extends Component {
    public $user;
    public $packages;

    public string $name = '';
    public string $game_package_id = '';
    public string $draw_mode = 'manual';
    public int $auto_draw_seconds = 3;
    public array $prizes = [];

    public function mount()
    {
        $this->user = auth()->user();
        $this->packages = GamePackage::active()->get();

        $this->prizes = [
            ['name' => '1º Lugar', 'description' => ''],
            ['name' => '2º Lugar', 'description' => ''],
            ['name' => '3º Lugar', 'description' => ''],
        ];
    }

    public function addPrize()
    {
        $this->prizes[] = ['name' => '', 'description' => ''];
    }

    public function removePrize($index)
    {
        unset($this->prizes[$index]);
        $this->prizes = array_values($this->prizes);
    }

    public function create()
    {
        $this->validate([
            'name' => 'required|min:3|max:255',
            'game_package_id' => 'required|exists:game_packages,id',
            'draw_mode' => 'required|in:manual,automatic',
            'auto_draw_seconds' => 'required|integer|min:2|max:10',
            'prizes.*.name' => 'required|min:2|max:255',
        ]);

        $package = GamePackage::findOrFail($this->game_package_id);

        if (!$package->is_free && !$this->user->wallet->hasBalance($package->cost_credits)) {
            session()->flash('error', 'Saldo insuficiente para criar esta partida');
            return;
        }

        $game = Game::create([
            'creator_id' => $this->user->id,
            'game_package_id' => $this->game_package_id,
            'name' => $this->name,
            'draw_mode' => $this->draw_mode,
            'auto_draw_seconds' => $this->auto_draw_seconds,
            'status' => 'draft',
        ]);

        foreach ($this->prizes as $index => $prize) {
            $game->prizes()->create([
                'name' => $prize['name'],
                'description' => $prize['description'] ?? '',
                'position' => $index + 1,
            ]);
        }

        if (!$package->is_free) {
            $this->user->wallet->debit(
                $package->cost_credits,
                "Criação da partida: {$game->name}",
                $game
            );
        }

        session()->flash('success', 'Partida criada com sucesso!');
        return redirect()->route('games.edit', $game);
    }
};
?>

<div>
    <x-slot name="header">
        Criar Nova Partida
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Criar Nova Partida</h1>
            <p class="text-gray-600">Configure os detalhes da sua partida de bingo</p>
        </div>

        @if (session()->has('error'))
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                {{ session('error') }}
            </div>
        @endif

        <form wire:submit="create" class="space-y-6">
            <!-- Nome da Partida -->
            <div class="bg-white rounded-lg shadow p-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Partida</label>
                <input
                    type="text"
                    wire:model.live="name"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                    placeholder="Ex: Bingo de Natal 2024"
                >
                @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <!-- Pacote -->
            <div class="bg-white rounded-lg shadow p-6">
                <label class="block text-sm font-medium text-gray-700 mb-4">Escolha o Pacote</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($packages as $package)
                        <label class="border rounded-lg p-4 cursor-pointer transition hover:border-blue-500 {{ $game_package_id == $package->id ? 'border-blue-500 bg-blue-50' : '' }}">
                            <input type="radio" wire:model.live="game_package_id" value="{{ $package->id }}" class="sr-only">
                            <div class="text-center">
                                <div class="font-semibold text-gray-900 mb-2">{{ $package->name }}</div>
                                <div class="text-sm text-gray-600 mb-3">
                                    @if($package->is_free)
                                        <span class="text-green-600 font-semibold">Grátis</span>
                                    @else
                                        <span class="font-semibold">{{ number_format($package->cost_credits, 0) }} créditos</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 space-y-1">
                                    @foreach($package->features as $feature)
                                        <div>• {{ $feature }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('game_package_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <!-- Modo de Sorteio -->
            <div class="bg-white rounded-lg shadow p-6">
                <label class="block text-sm font-medium text-gray-700 mb-4">Modo de Sorteio</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <label class="border rounded-lg p-4 cursor-pointer transition hover:border-blue-500 {{ $draw_mode === 'manual' ? 'border-blue-500 bg-blue-50' : '' }}">
                        <input type="radio" wire:model.live="draw_mode" value="manual" class="sr-only">
                        <div class="font-semibold mb-1">Manual</div>
                        <div class="text-sm text-gray-600">Você controla quando cada número é sorteado</div>
                    </label>

                    <label class="border rounded-lg p-4 cursor-pointer transition hover:border-blue-500 {{ $draw_mode === 'automatic' ? 'border-blue-500 bg-blue-50' : '' }}">
                        <input type="radio" wire:model.live="draw_mode" value="automatic" class="sr-only">
                        <div class="font-semibold mb-1">Automático</div>
                        <div class="text-sm text-gray-600">Números sorteados automaticamente</div>
                    </label>
                </div>

                @if($draw_mode === 'automatic')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Intervalo entre sorteios (segundos)</label>
                        <input
                            type="number"
                            wire:model.live="auto_draw_seconds"
                            min="2"
                            max="10"
                            class="w-full px-4 py-2 border rounded-lg"
                        >
                        @error('auto_draw_seconds') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>

            <!-- Prêmios -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <label class="block text-sm font-medium text-gray-700">Prêmios</label>
                    <button
                        type="button"
                        wire:click="addPrize"
                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition"
                    >
                        + Adicionar Prêmio
                    </button>
                </div>

                <div class="space-y-4">
                    @foreach($prizes as $index => $prize)
                        <div class="border rounded-lg p-4">
                            <div class="flex gap-4 items-start">
                                <div class="flex-1">
                                    <input
                                        type="text"
                                        wire:model.live="prizes.{{ $index }}.name"
                                        placeholder="Nome do prêmio"
                                        class="w-full px-4 py-2 border rounded-lg mb-2"
                                    >
                                    @error("prizes.{$index}.name") <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                                    <textarea
                                        wire:model.live="prizes.{{ $index }}.description"
                                        placeholder="Descrição (opcional)"
                                        rows="2"
                                        class="w-full px-4 py-2 border rounded-lg"
                                    ></textarea>
                                </div>

                                @if($index > 0)
                                    <button
                                        type="button"
                                        wire:click="removePrize({{ $index }})"
                                        class="text-red-600 hover:text-red-700 px-3 py-2"
                                    >
                                        Remover
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-4">
                <button
                    type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition font-semibold"
                >
                    Criar Partida
                </button>

                <a
                    href="{{ route('games.index') }}"
                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg transition font-semibold text-center"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
