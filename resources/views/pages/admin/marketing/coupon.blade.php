<?php

/**
 * ============================================================================
 * Sistema de Cupons - Implementação seguindo boas práticas:
 *
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
use App\Models\Coupon;

new #[Layout('layouts.admin')] #[Title('Gestão de Cupons')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterStatus = 'all';
    public string $sort = 'desc';

    public ?int $editingId = null;
    public string $code = '';
    public string $description = '';
    public string $type = 'percent';
    public string $value = '';
    public ?string $expires_at = null;
    public ?int $usage_limit = null;
    public int $per_user_limit = 1;
    public ?float $min_order_value = null;
    public bool $is_active = true;
    public bool $showDrawer = false;

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:50', 'unique:coupons,code,' . $this->editingId],
            'description' => ['nullable', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'in:percent,fixed'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['required', 'integer', 'min:1'],
            'min_order_value' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
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
    public function coupons()
    {
        return Coupon::query()
            ->when($this->search, fn($q) => $q->where(fn($sub) => 
                $sub->where('code', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
            ))
            ->when($this->filterStatus === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->filterStatus === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($this->filterStatus === 'expired', fn($q) => $q->whereNotNull('expires_at')->where('expires_at', '<', now()))
            ->orderBy('created_at', $this->sort)
            ->paginate(15);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Coupon::count(),
            'active' => Coupon::where('is_active', true)->count(),
            'expired' => Coupon::whereNotNull('expires_at')->where('expires_at', '<', now())->count(),
            'total_uses' => Coupon::sum('used_count'),
        ];
    }

    public function openModal(): void
    {
        $this->reset(['editingId', 'code', 'description', 'value', 'expires_at', 'usage_limit', 'min_order_value']);
        $this->type = 'percent';
        $this->is_active = true;
        $this->per_user_limit = 1;
        $this->resetValidation();
        $this->showDrawer = true;
    }

    public function closeModal(): void
    {
        $this->showDrawer = false;
        $this->reset(['editingId', 'code', 'description', 'value', 'expires_at', 'usage_limit', 'min_order_value']);
        $this->type = 'percent';
        $this->is_active = true;
        $this->per_user_limit = 1;
    }

    public function edit(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        
        $this->editingId = $coupon->id;
        $this->code = $coupon->code;
        $this->description = $coupon->description ?? '';
        $this->type = $coupon->type;
        $this->value = (string) $coupon->value;
        $this->expires_at = $coupon->expires_at?->format('Y-m-d\TH:i');
        $this->usage_limit = $coupon->usage_limit;
        $this->per_user_limit = $coupon->per_user_limit;
        $this->min_order_value = $coupon->min_order_value;
        $this->is_active = (bool) $coupon->is_active;
        
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $this->validate();

        Coupon::updateOrCreate(
            ['id' => $this->editingId],
            [
                'code' => strtoupper(trim($this->code)),
                'description' => trim($this->description),
                'type' => $this->type,
                'value' => $this->value,
                'expires_at' => $this->expires_at,
                'usage_limit' => $this->usage_limit,
                'per_user_limit' => $this->per_user_limit,
                'min_order_value' => $this->min_order_value,
                'is_active' => $this->is_active,
            ]
        );

        $this->dispatch('notify', type: 'success', text: $this->editingId ? 'Cupom atualizado!' : 'Cupom criado!');
        
        $this->closeModal();
        $this->dispatch('$refresh');
    }

    public function toggleStatus(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);
        $this->dispatch('notify', type: 'success', text: 'Status atualizado!');
        $this->dispatch('$refresh');
    }

    public function delete(int $id): void
    {
        try {
            $coupon = Coupon::findOrFail($id);
            
            if ($coupon->used_count > 0) {
                $this->dispatch('notify', type: 'error', text: 'Não é possível excluir cupom já utilizado!');
                return;
            }
            
            $coupon->delete();
            $this->dispatch('notify', type: 'success', text: 'Cupom removido com sucesso!');
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao excluir: ' . $e->getMessage());
        }
    }

 
};
?>

<div class="p-6 min-h-screen">
    <!-- HEADER -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">
                Gestão de Cupons
            </h1>
            <p class="text-slate-400 text-sm mt-1">Configure cupons de desconto com regras e limites personalizados</p>
        </div>
        <button wire:click="openModal"
            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-sm font-medium text-white transition-all flex items-center gap-2 shadow-lg shadow-indigo-500/20">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Novo Cupom
        </button>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Total de Cupons</p>
                    <p class="text-3xl font-bold text-white mt-2">{{ $this->stats['total'] }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Cupons Ativos</p>
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
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Cupons Expirados</p>
                    <p class="text-3xl font-bold text-red-400 mt-2">{{ $this->stats['expired'] }}</p>
                </div>
                <div class="w-12 h-12 bg-red-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Total de Usos</p>
                    <p class="text-3xl font-bold text-blue-400 mt-2">
                        {{ number_format($this->stats['total_uses'], 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- BUSCA E FILTROS -->
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center">
            <div class="flex-1 relative">
                <input wire:model.live.debounce.400ms="search" placeholder="Buscar por código ou descrição..."
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
                    <option value="expired">Expirados</option>
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
                        <th class="p-4 text-left font-semibold">Código</th>
                        <th class="p-4 text-center font-semibold">Tipo</th>
                        <th class="p-4 text-center font-semibold">Valor</th>
                        <th class="p-4 text-center font-semibold">Usos</th>
                        <th class="p-4 text-center font-semibold">Validade</th>
                        <th class="p-4 text-center font-semibold">Status</th>
                        <th class="p-4 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($this->coupons as $coupon)
                        @php
                            $expired = $coupon->expires_at && $coupon->expires_at->isPast();
                            $remaining = $coupon->usage_limit ? ($coupon->usage_limit - $coupon->used_count) : '∞';
                        @endphp
                        <tr wire:key="coupon-{{ $coupon->id }}" class="hover:bg-white/5 transition-all group">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-white font-bold">{{ $coupon->code }}</div>
                                        <div class="text-slate-400 text-xs">{{ $coupon->description ?: 'Sem descrição' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold {{ $coupon->type === 'percent' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' }}">
                                    {{ $coupon->type === 'percent' ? 'Percentual' : 'Fixo' }}
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-white font-bold text-lg">
                                    {{ $coupon->type === 'percent' ? $coupon->value . '%' : 'R$ ' . number_format((float) $coupon->value, 2, ',', '.') }}
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-slate-300 font-semibold">
                                    {{ $coupon->used_count }} / {{ $coupon->usage_limit ?? '∞' }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1">
                                    Restante: {{ $remaining }}
                                </div>
                            </td>
                            <td class="p-4 text-center text-xs">
                                @if($coupon->expires_at)
                                    <div class="{{ $expired ? 'text-red-400' : 'text-slate-300' }}">
                                        {{ $coupon->expires_at->format('d/m/Y H:i') }}
                                    </div>
                                    @if(!$expired)
                                        <div class="text-slate-500 mt-1">
                                            {{ $coupon->expires_at->diffForHumans() }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-slate-500">Sem validade</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                @if($expired)
                                    <span
                                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold bg-red-500/10 text-red-400 border border-red-500/20">
                                        <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                        Expirado
                                    </span>
                                @else
                                    <button wire:click="toggleStatus({{ $coupon->id }})"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $coupon->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-slate-500/10 text-slate-400 border border-slate-500/20 hover:bg-slate-500/20' }}">
                                        <span
                                            class="w-2 h-2 rounded-full {{ $coupon->is_active ? 'bg-emerald-400' : 'bg-slate-400' }}"></span>
                                        {{ $coupon->is_active ? 'Ativo' : 'Inativo' }}
                                    </button>
                                @endif
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="edit({{ $coupon->id }})"
                                        class="p-2 text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-all"
                                        title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button wire:click="delete({{ $coupon->id }})"
                                        wire:confirm="Tem certeza que deseja excluir este cupom? Esta ação não pode ser desfeita."
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
                                        d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                </svg>
                                <p class="text-slate-400 font-medium mb-2">Nenhum cupom encontrado</p>
                                <p class="text-slate-500 text-sm">Crie seu primeiro cupom clicando no botão "Novo
                                    Cupom"</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->coupons->hasPages())
            <div class="p-6 border-t border-white/5 bg-black/10">
                {{ $this->coupons->links() }}
            </div>
        @endif
    </div>

    <!-- DRAWER LATERAL -->
    @if ($showDrawer)
        <x-drawer :show="$showDrawer" max-width="2xl" wire:model="showDrawer">
            <div class="border-b border-white/10 flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">
                        {{ $editingId ? 'Editar Cupom' : 'Novo Cupom' }}
                    </h2>
                    <p class="text-slate-400 text-sm">
                        {{ $editingId ? 'Atualize as informações do cupom' : 'Configure um novo cupom de desconto' }}
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

            <form wire:submit="save" class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Código do Cupom *</label>
                        <input wire:model.defer="code" type="text"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white uppercase focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: PROMO10">
                        @error('code')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Descrição Interna</label>
                        <input wire:model.defer="description" type="text"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: Promoção de Natal">
                        @error('description')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Tipo de Desconto *</label>
                        <select wire:model.live="type"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="percent">Percentual (%)</option>
                            <option value="fixed">Valor Fixo (R$)</option>
                        </select>
                        @error('type')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Valor do Desconto *</label>
                        <input wire:model.defer="value" type="number" step="0.01" min="0"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="{{ $type === 'percent' ? 'Ex: 10' : 'Ex: 20.00' }}">
                        @error('value')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-slate-500 text-xs mt-1">
                            {{ $type === 'percent' ? 'Percentual de desconto' : 'Valor fixo em reais' }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Data de Expiração</label>
                        <input wire:model.defer="expires_at" type="datetime-local"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('expires_at')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-slate-500 text-xs mt-1">Deixe vazio para sem validade</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Limite Global de Uso</label>
                        <input wire:model.defer="usage_limit" type="number" min="1"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: 100">
                        @error('usage_limit')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-slate-500 text-xs mt-1">Deixe vazio para ilimitado</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Limite por Usuário *</label>
                        <input wire:model.defer="per_user_limit" type="number" min="1"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: 1">
                        @error('per_user_limit')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-slate-500 text-xs mt-1">Quantas vezes cada usuário pode usar</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Valor Mínimo do Pedido</label>
                        <input wire:model.defer="min_order_value" type="number" step="0.01" min="0"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: 50.00">
                        @error('min_order_value')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-slate-500 text-xs mt-1">Deixe vazio para sem mínimo</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-4 bg-black/20 border border-white/10 rounded-xl">
                    <input wire:model="is_active" type="checkbox" id="is_active"
                        class="w-5 h-5 bg-[#111827] border-white/10 rounded focus:ring-2 focus:ring-indigo-500">
                    <label for="is_active" class="text-sm text-slate-300 cursor-pointer">
                        Cupom ativo e disponível para uso
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
                            {{ $editingId ? 'Atualizar Cupom' : 'Criar Cupom' }}
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