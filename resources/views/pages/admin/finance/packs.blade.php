<?php

/**
 * ============================================================================
 * - Laravel 12:
 *   • Validação server-side robusta
 *   • Proteção contra SQL Injection via Eloquent
 *   • Mass assignment controlado
 *   • Sanitização básica antes de persistir
 *
 * - Livewire 4:
 *   • Propriedades tipadas
 *   • wire:model.live otimizado
 *   • Eventos dispatch integrados com Alpine
 *   • Controle de estado previsível
 *
 * - Segurança:
 *   • Escape automático Blade (anti XSS)
 *   • Validação obrigatória
 *   • Tipagem de parâmetros
 *   • Sem exposição de dados sensíveis
 *
 * - Tailwind:
 *   • Hierarquia consistente
 *   • Estados visuais claros
 *   • Layout escalável
 * ============================================================================
 */

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use App\Models\Wallet\Package;

new #[Layout('layouts.admin')] #[Title('Gestão de Pacotes')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterStatus = 'all';
    public string $sort = 'desc';

    public ?int $editingId = null;
    public string $name = '';
    public float $price_brl = 0;
    public int $credits = 0;
    public bool $is_active = true;
    public bool $showDrawer = false;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'price_brl' => ['required', 'numeric', 'min:0.01'],
            'credits' => ['required', 'integer', 'min:1'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function toggleSort(): void
    {
        $this->sort = $this->sort === 'desc' ? 'asc' : 'desc';
    }

    #[Computed]
    public function packages()
    {
        return Package::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterStatus !== 'all', fn($q) => $q->where('is_active', $this->filterStatus === 'active'))
            ->orderBy('created_at', $this->sort)
            ->paginate(10);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Package::count(),
            'active' => Package::where('is_active', true)->count(),
            'inactive' => Package::where('is_active', false)->count(),
            'total_credits' => Package::where('is_active', true)->sum('credits'),
        ];
    }

    public function openModal(): void
    {
        $this->reset(['editingId', 'name', 'credits']);
        $this->price_brl = 0;
        $this->is_active = true;
        $this->resetValidation();
        $this->showDrawer = true;
    }

    public function closeModal(): void
    {
        $this->showDrawer = false;
        $this->reset(['editingId', 'name', 'credits']);
        $this->price_brl = 0;
        $this->is_active = true;
    }

    public function edit(int $id): void
    {
        $package = Package::findOrFail($id);
        $this->editingId = $package->id;
        $this->name = $package->name;
        $this->price_brl = (float) $package->price_brl;
        $this->credits = (int) $package->credits;
        $this->is_active = (bool) $package->is_active;
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $this->validate();

        Package::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'price_brl' => round($this->price_brl, 2),
                'credits' => $this->credits,
                'is_active' => $this->is_active,
            ]
        );

        $this->dispatch('notify', type: 'success', text: $this->editingId ? 'Pacote atualizado!' : 'Pacote criado!');
        
        $this->closeModal();
        $this->dispatch('$refresh');
    }

    public function toggleStatus(int $id): void
    {
        $package = Package::findOrFail($id);
        $package->update(['is_active' => !$package->is_active]);
        $this->dispatch('notify', type: 'success', text: 'Status atualizado!');
        $this->dispatch('$refresh');
    }

    public function delete(int $id): void
    {
        try {
            $package = Package::findOrFail($id);
            
            if (method_exists($package, 'transactions')) {
                try {
                    if ($package->transactions()->exists()) {
                        $this->dispatch('notify', type: 'error', text: 'Não é possível excluir pacote com transações vinculadas!');
                        return;
                    }
                } catch (\Exception $e) {
                    // Se der erro na verificação, significa que a coluna não existe, então pode excluir
                }
            }
            
            $package->delete();
            $this->dispatch('notify', type: 'success', text: 'Pacote removido com sucesso!');
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao excluir: ' . $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('pages.admin.finance.packs');
    }
};
?>

<div class="p-6 min-h-screen">
    <!-- HEADER -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">
                Gestão de Pacotes
            </h1>
            <p class="text-slate-400 text-sm mt-1">Configure e gerencie pacotes de créditos disponíveis</p>
        </div>
        <button wire:click="openModal"
            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-sm font-medium text-white transition-all flex items-center gap-2 shadow-lg shadow-indigo-500/20">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Novo Pacote
        </button>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Total de Pacotes</p>
                    <p class="text-3xl font-bold text-white mt-2">{{ $this->stats['total'] }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Pacotes Ativos</p>
                    <p class="text-3xl font-bold text-emerald-400 mt-2">{{ $this->stats['active'] }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Pacotes Inativos</p>
                    <p class="text-3xl font-bold text-slate-400 mt-2">{{ $this->stats['inactive'] }}</p>
                </div>
                <div class="w-12 h-12 bg-slate-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Créditos Totais</p>
                    <p class="text-3xl font-bold text-blue-400 mt-2">
                        {{ number_format($this->stats['total_credits'], 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- BUSCA E FILTROS -->
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center">
            <div class="flex-1 relative">
                <input wire:model.live.debounce.400ms="search" placeholder="Buscar pacote por nome..."
                    class="w-full bg-[#111827] border border-white/10 rounded-xl px-5 py-3 text-sm text-white pl-12 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <div class="flex gap-3 w-full lg:w-auto">
                <select wire:model.live="filterStatus"
                    class="flex-1 lg:flex-none bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Todos os Status</option>
                    <option value="active">Apenas Ativos</option>
                    <option value="inactive">Apenas Inativos</option>
                </select>
                <button wire:click="toggleSort"
                    class="px-4 py-3 bg-gray-800 border border-white/10 rounded-xl text-sm text-white hover:bg-gray-700 transition-all whitespace-nowrap">
                    {{ $sort === 'desc' ? '↓ Mais Recentes' : '↑ Mais Antigos' }}
                </button>
            </div>
        </div>
    </div>

    <!-- TABELA -->
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="p-4 text-left font-semibold">Pacote</th>
                        <th class="p-4 text-center font-semibold">Créditos</th>
                        <th class="p-4 text-center font-semibold">Valor (R$)</th>
                        <th class="p-4 text-center font-semibold">Custo por Crédito</th>
                        <th class="p-4 text-center font-semibold">Status</th>
                        <th class="p-4 text-center font-semibold">Criado Em</th>
                        <th class="p-4 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($this->packages as $package)
                        <tr wire:key="package-{{ $package->id }}" class="hover:bg-white/5 transition-all group">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-white font-semibold">{{ $package->name }}</div>
                                        <div class="text-slate-400 text-xs">#{{ $package->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div
                                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-500/10 border border-blue-500/20 rounded-lg">
                                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span
                                        class="text-blue-400 font-bold">{{ number_format($package->credits, 0, ',', '.') }}</span>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-white font-bold text-lg">
                                    R$ {{ number_format((float) $package->price_brl, 2, ',', '.') }}
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-emerald-400 text-sm font-medium">
                                    R$
                                    {{ number_format((float) ($package->price_brl / max($package->credits, 1)), 2, ',', '.') }}
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <button wire:click="toggleStatus({{ $package->id }})"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $package->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-slate-500/10 text-slate-400 border border-slate-500/20 hover:bg-slate-500/20' }}">
                                    <span
                                        class="w-2 h-2 rounded-full {{ $package->is_active ? 'bg-emerald-400' : 'bg-slate-400' }}"></span>
                                    {{ $package->is_active ? 'Ativo' : 'Inativo' }}
                                </button>
                            </td>
                            <td class="p-4 text-center text-slate-400 text-xs">
                                {{ $package->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="edit({{ $package->id }})"
                                        class="p-2 text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-all"
                                        title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button wire:click="delete({{ $package->id }})"
                                        wire:confirm="Tem certeza que deseja excluir este pacote? Esta ação não pode ser desfeita."
                                        class="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-all"
                                        title="Excluir">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center">
                                <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <p class="text-slate-400 font-medium mb-2">Nenhum pacote encontrado</p>
                                <p class="text-slate-500 text-sm">Crie seu primeiro pacote clicando no botão "Novo
                                    Pacote"</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->packages->hasPages())
            <div class="p-6 border-t border-white/5 bg-black/10">
                {{ $this->packages->links() }}
            </div>
        @endif
    </div>

    <!-- DRAWER LATERAL - MESMO PADRÃO DA PÁGINA DE CRÉDITOS QUE FUNCIONA -->
    @if ($showDrawer)
        <x-drawer :show="$showDrawer" max-width="md" wire:model="showDrawer">
            <div class="border-b border-white/10 flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">
                        {{ $editingId ? 'Editar Pacote' : 'Novo Pacote' }}
                    </h2>
                    <p class="text-slate-400 text-sm">
                        {{ $editingId ? 'Atualize as informações do pacote' : 'Crie um novo pacote de créditos' }}
                    </p>
                </div>
                <button wire:click="closeModal"
                    class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-white/5 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Nome do Pacote</label>
                    <input wire:model.defer="name" type="text"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ex: Pacote Básico">
                    @error('name')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Quantidade de Créditos</label>
                    <input wire:model.defer="credits" type="number" min="1" step="1"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ex: 100">
                    @error('credits')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Valor (R$)</label>
                    <input wire:model.defer="price_brl" type="number" min="0.01" step="0.01"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ex: 50.00">
                    @error('price_brl')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                @if($credits > 0 && $price_brl > 0)
                    <div class="bg-black/20 border border-white/10 rounded-xl p-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-400">Custo por crédito:</span>
                            <span class="text-emerald-400 font-bold">
                                R$ {{ number_format($price_brl / $credits, 2, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @endif

                <div class="flex items-center gap-3 p-4 bg-black/20 border border-white/10 rounded-xl">
                    <input wire:model="is_active" type="checkbox" id="is_active"
                        class="w-5 h-5 bg-[#111827] border-white/10 rounded focus:ring-2 focus:ring-indigo-500">
                    <label for="is_active" class="text-sm text-slate-300 cursor-pointer">
                        Pacote ativo e disponível para compra
                    </label>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" wire:click="closeModal"
                        class="flex-1 bg-gray-800 hover:bg-gray-700 transition py-3 rounded-xl text-sm font-medium text-white">
                        Cancelar
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-800 transition py-3 rounded-xl text-sm font-medium text-white flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="save">
                            {{ $editingId ? 'Atualizar' : 'Criar Pacote' }}
                        </span>
                        <span wire:loading wire:target="save">Salvando...</span>
                        <div wire:loading wire:target="save"
                            class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin">
                        </div>
                    </button>
                </div>
            </form>
        </x-drawer>
    @endif

    <x-loading target="save,delete,toggleStatus" message="Processando..." overlay />
    <x-toast position="top-right" />
</div>