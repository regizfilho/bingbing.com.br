<?php

use Livewire\Component;
use App\Models\Game\Game;

new class extends Component {
    public $game;
    public $user;

    public string $name = '';
    public string $draw_mode = 'manual';
    public int $auto_draw_seconds = 3;
    public array $prizes = [];

    public function mount($game)
    {
        $this->user = auth()->user();
        $this->game = Game::where('uuid', $game)
            ->with(['prizes', 'package'])
            ->firstOrFail();

        if ($this->game->creator_id !== $this->user->id) {
            abort(403, 'Você não é o criador desta partida.');
        }

        if ($this->game->status !== 'draft') {
            session()->flash('error', 'Apenas partidas em rascunho podem ser editadas.');
            $this->redirectRoute('games.index', navigate: true);
        }

        $this->name = $this->game->name;
        $this->draw_mode = $this->game->draw_mode;
        $this->auto_draw_seconds = $this->game->auto_draw_seconds ?? 3;

        $this->prizes = $this->game->prizes
            ->sortBy('position')
            ->map(fn ($prize) => [
                'id' => $prize->id,
                'name' => $prize->name ?? '',
                'description' => $prize->description ?? '',
            ])
            ->values()
            ->toArray();
    }

    public function addPrize()
    {
        $this->prizes[] = ['id' => null, 'name' => '', 'description' => ''];
    }

    public function removePrize($index)
    {
        unset($this->prizes[$index]);
        $this->prizes = array_values($this->prizes);
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|min:3|max:255',
            'draw_mode' => 'required|in:manual,automatic',
            'auto_draw_seconds' => 'required_if:draw_mode,automatic|integer|min:2|max:10',
            'prizes.*.name' => 'required|min:2|max:255',
        ]);

        $this->game->update([
            'name' => $this->name,
            'draw_mode' => $this->draw_mode,
            'auto_draw_seconds' => $this->auto_draw_seconds,
        ]);

        // Deleta prêmios antigos e cria novos
        $this->game->prizes()->delete();

        foreach ($this->prizes as $index => $prize) {
            $this->game->prizes()->create([
                'name' => $prize['name'],
                'description' => $prize['description'] ?? '',
                'position' => $index + 1,
            ]);
        }

        session()->flash('success', 'Partida atualizada com sucesso!');
    }

    public function publish()
    {
        if (empty($this->prizes)) {
            session()->flash('error', 'Adicione pelo menos um prêmio antes de publicar.');
            return;
        }

        $this->game->update(['status' => 'waiting']);

        session()->flash('success', 'Partida publicada! Compartilhe o código com os jogadores.');
        $this->redirectRoute('games.play', $this->game, navigate: true);
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

    @if (session()->has('success'))
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="update" class="space-y-6">
        <!-- Pacote Selecionado -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="text-sm text-blue-800 font-medium mb-2">Pacote Selecionado</div>
            <div class="text-lg font-semibold text-blue-900">{{ $game->package->name ?? '—' }}</div>
            <div class="text-sm text-blue-700 mt-2">
                Máx. {{ $game->package->max_players ?? '?' }} jogadores • 
                {{ $game->package->max_cards_per_player ?? '?' }} cartela(s) por jogador
            </div>
        </div>

        <!-- Nome -->
        <div class="bg-white rounded-lg shadow p-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Partida</label>
            <input type="text" wire:model.live="name" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <!-- Modo de Sorteio -->
        <div class="bg-white rounded-lg shadow p-6">
            <label class="block text-sm font-medium text-gray-700 mb-4">Modo de Sorteio</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <label class="border rounded-lg p-4 cursor-pointer transition hover:border-blue-500 {{ $draw_mode === 'manual' ? 'border-blue-500 bg-blue-50' : '' }}">
                    <input type="radio" wire:model.live="draw_mode" value="manual" class="sr-only">
                    <div class="font-semibold mb-1">Manual</div>
                    <div class="text-sm text-gray-600">Você controla cada sorteio</div>
                </label>

                <label class="border rounded-lg p-4 cursor-pointer transition hover:border-blue-500 {{ $draw_mode === 'automatic' ? 'border-blue-500 bg-blue-50' : '' }}">
                    <input type="radio" wire:model.live="draw_mode" value="automatic" class="sr-only">
                    <div class="font-semibold mb-1">Automático</div>
                    <div class="text-sm text-gray-600">Sorteios automáticos a cada intervalo</div>
                </label>
            </div>

            @if($draw_mode === 'automatic')
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Intervalo (segundos)</label>
                    <input type="number" wire:model.live="auto_draw_seconds" min="2" max="10" class="w-full px-4 py-2 border rounded-lg">
                    @error('auto_draw_seconds') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            @endif
        </div>

        <!-- Prêmios -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <label class="block text-sm font-medium text-gray-700">Prêmios</label>
                <button type="button" wire:click="addPrize" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm transition">
                    + Adicionar Prêmio
                </button>
            </div>

            <div class="space-y-4">
                @foreach($prizes as $index => $prize)
                    <div class="border rounded-lg p-4 relative">
                        <div class="flex gap-4 items-start">
                            <div class="flex-1">
                                <input type="text" wire:model.live="prizes.{{ $index }}.name" placeholder="Nome do prêmio" class="w-full px-4 py-2 border rounded-lg mb-2">
                                @error("prizes.{$index}.name") <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                                <textarea wire:model.live="prizes.{{ $index }}.description" placeholder="Descrição (opcional)" rows="2" class="w-full px-4 py-2 border rounded-lg"></textarea>
                            </div>

                            @if(count($prizes) > 1)
                                <button type="button" wire:click="removePrize({{ $index }})" class="text-red-600 hover:text-red-800 self-start mt-1">
                                    Remover
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Botões de ação -->
        <div class="flex flex-col sm:flex-row gap-4">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition font-semibold">
                Salvar Alterações
            </button>

            <button type="button" wire:click="publish" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition font-semibold">
                Publicar Partida
            </button>

            <a href="{{ route('games.index') }}" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg transition font-semibold text-center">
                Cancelar
            </a>
        </div>
    </form>
</div>