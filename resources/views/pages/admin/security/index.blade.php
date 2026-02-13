<?php

/**
 * ============================================================================
 * Segurança Firewall - Implementação seguindo boas práticas:
 *
 * - Laravel 12:
 *   • Validação robusta server-side
 *   • Proteção contra SQL Injection via Eloquent
 *   • findOrFail para integridade de registros
 *   • Mass assignment controlado
 *   • Sanitização antes de persistência
 *
 * - Livewire 4:
 *   • Propriedades tipadas
 *   • wire:model.live otimizado
 *   • Eventos dispatch integrados
 *   • Controle de estado previsível
 *
 * - Segurança:
 *   • Escape automático Blade (anti XSS)
 *   • Validação IP nativa (IPv4/IPv6)
 *   • Toggle seguro
 *   • Confirmação antes de exclusão
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
use App\Models\WhitelistIp;
use App\Models\FirewallLog;

new #[Layout('layouts.admin')] #[Title('Segurança Firewall')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterStatus = 'all';
    public string $sort = 'desc';

    public ?int $editingId = null;
    public string $ip = '';
    public string $label = '';
    public bool $is_active = true;
    public bool $showDrawer = false;

    protected function rules(): array
    {
        return [
            'ip' => ['required', 'ip', 'unique:whitelist_ips,ip,' . $this->editingId],
            'label' => ['required', 'string', 'max:100'],
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
    public function ips()
    {
        return WhitelistIp::query()
            ->when($this->search, fn($q) => $q->where(fn($sub) => 
                $sub->where('ip', 'like', "%{$this->search}%")
                    ->orWhere('label', 'like', "%{$this->search}%")
            ))
            ->when($this->filterStatus === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->filterStatus === 'inactive', fn($q) => $q->where('is_active', false))
            ->orderBy('created_at', $this->sort)
            ->paginate(15);
    }

    #[Computed]
    public function logs()
    {
        return FirewallLog::latest()->limit(20)->get();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => WhitelistIp::count(),
            'active' => WhitelistIp::where('is_active', true)->count(),
            'inactive' => WhitelistIp::where('is_active', false)->count(),
            'blocked_today' => FirewallLog::whereDate('created_at', today())->count(),
        ];
    }

    public function openModal(): void
    {
        $this->reset(['editingId', 'ip', 'label']);
        $this->is_active = true;
        $this->resetValidation();
        $this->showDrawer = true;
    }

    public function closeModal(): void
    {
        $this->showDrawer = false;
        $this->reset(['editingId', 'ip', 'label']);
        $this->is_active = true;
    }

    public function edit(int $id): void
    {
        $item = WhitelistIp::findOrFail($id);
        
        $this->editingId = $item->id;
        $this->ip = $item->ip;
        $this->label = $item->label;
        $this->is_active = (bool) $item->is_active;
        
        $this->showDrawer = true;
    }

    public function save(): void
    {
        $this->validate();

        WhitelistIp::updateOrCreate(
            ['id' => $this->editingId],
            [
                'ip' => trim($this->ip),
                'label' => trim($this->label),
                'is_active' => $this->is_active,
            ]
        );

        $this->dispatch('notify', type: 'success', text: $this->editingId ? 'IP atualizado!' : 'IP adicionado à whitelist!');
        
        $this->closeModal();
        $this->dispatch('$refresh');
    }

    public function toggleStatus(int $id): void
    {
        $item = WhitelistIp::findOrFail($id);
        $item->update(['is_active' => !$item->is_active]);
        
        $this->dispatch('notify', type: 'success', text: 'Status atualizado!');
        $this->dispatch('$refresh');
    }

    public function delete(int $id): void
    {
        try {
            $item = WhitelistIp::findOrFail($id);
            $item->delete();
            
            $this->dispatch('notify', type: 'success', text: 'IP removido da whitelist!');
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao excluir: ' . $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('pages.admin.security.index');
    }
};
?>

<div class="p-6 min-h-screen">
    <!-- HEADER -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">
                Segurança Firewall
            </h1>
            <p class="text-slate-400 text-sm mt-1">Gerencie IPs autorizados e monitore tentativas de acesso bloqueadas</p>
        </div>
        <button wire:click="openModal"
            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-sm font-medium text-white transition-all flex items-center gap-2 shadow-lg shadow-indigo-500/20">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Adicionar IP
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- COLUNA PRINCIPAL -->
        <div class="lg:col-span-2 space-y-6">
            <!-- ESTATÍSTICAS -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Total de IPs</p>
                            <p class="text-3xl font-bold text-white mt-2">{{ $this->stats['total'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Ativos</p>
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
                            <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Inativos</p>
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
                            <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Bloqueios Hoje</p>
                            <p class="text-3xl font-bold text-red-400 mt-2">{{ $this->stats['blocked_today'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-red-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BUSCA E FILTROS -->
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center">
                    <div class="flex-1 relative">
                        <input wire:model.live.debounce.400ms="search" placeholder="Buscar por IP ou descrição..."
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

            <!-- TABELA DE IPs -->
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                            <tr>
                                <th class="p-4 text-left font-semibold">Status</th>
                                <th class="p-4 text-left font-semibold">Endereço IP</th>
                                <th class="p-4 text-left font-semibold">Descrição</th>
                                <th class="p-4 text-center font-semibold">Data de Cadastro</th>
                                <th class="p-4 text-right font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse ($this->ips as $item)
                                <tr wire:key="ip-{{ $item->id }}" class="hover:bg-white/5 transition-all group">
                                    <td class="p-4">
                                        <button wire:click="toggleStatus({{ $item->id }})"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $item->is_active ? 'bg-emerald-600' : 'bg-slate-700' }}">
                                            <span
                                                class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $item->is_active ? 'translate-x-6' : 'translate-x-1' }}">
                                            </span>
                                        </button>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-white font-mono font-bold">{{ $item->ip }}</div>
                                                <div class="text-slate-400 text-xs">IPv{{ filter_var($item->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '6' : '4' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-white font-semibold">{{ $item->label }}</div>
                                    </td>
                                    <td class="p-4 text-center text-slate-400 text-xs">
                                        <div>{{ $item->created_at->format('d/m/Y') }}</div>
                                        <div class="text-slate-500">{{ $item->created_at->format('H:i:s') }}</div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button wire:click="edit({{ $item->id }})"
                                                class="p-2 text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-all"
                                                title="Editar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button wire:click="delete({{ $item->id }})"
                                                wire:confirm="Tem certeza que deseja remover este IP da whitelist?"
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
                                    <td colspan="5" class="p-12 text-center">
                                        <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        <p class="text-slate-400 font-medium mb-2">Nenhum IP cadastrado</p>
                                        <p class="text-slate-500 text-sm">Adicione IPs à whitelist para permitir acesso</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($this->ips->hasPages())
                    <div class="p-6 border-t border-white/5 bg-black/10">
                        {{ $this->ips->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- COLUNA LATERAL - LOGS -->
        <div class="lg:col-span-1">
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 sticky top-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                        Acessos Bloqueados
                    </h3>
                    <span class="px-2 py-1 bg-red-500/10 text-red-400 text-xs font-bold rounded-lg">
                        {{ $this->logs->count() }}
                    </span>
                </div>

                <div class="space-y-3 max-h-[600px] overflow-y-auto">
                    @forelse($this->logs as $log)
                        <div class="p-4 rounded-xl bg-red-500/5 border border-red-500/10 hover:border-red-500/30 transition-all">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-mono text-xs text-red-400 font-bold">
                                    {{ $log->ip }}
                                </span>
                                <span class="text-xs text-slate-500">
                                    {{ $log->created_at->diffForHumans(null, true) }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 truncate mb-1" title="{{ $log->url }}">
                                {{ $log->url }}
                            </p>
                            @if($log->method)
                                <span class="inline-block px-2 py-0.5 bg-slate-800 text-slate-400 text-xs rounded">
                                    {{ $log->method }}
                                </span>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <svg class="w-12 h-12 mx-auto mb-3 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <p class="text-slate-500 text-sm font-medium">Sistema protegido</p>
                            <p class="text-slate-600 text-xs mt-1">Nenhuma ameaça detectada</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- DRAWER LATERAL -->
    @if ($showDrawer)
        <x-drawer :show="$showDrawer" max-width="md" wire:model="showDrawer">
            <div class="border-b border-white/10 flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">
                        {{ $editingId ? 'Editar IP' : 'Adicionar IP' }}
                    </h2>
                    <p class="text-slate-400 text-sm">
                        {{ $editingId ? 'Atualize as informações do IP' : 'Adicione um novo IP à whitelist' }}
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
                    <label class="block text-sm font-medium text-slate-300 mb-2">Endereço IP *</label>
                    <input wire:model.defer="ip" type="text"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="192.168.1.1 ou 2001:0db8:85a3::8a2e:0370:7334">
                    @error('ip')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-slate-500 text-xs mt-1">Suporta IPv4 e IPv6</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Descrição / Label *</label>
                    <input wire:model.defer="label" type="text"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ex: Servidor Principal, VPN Escritório">
                    @error('label')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 p-4 bg-black/20 border border-white/10 rounded-xl">
                    <input wire:model="is_active" type="checkbox" id="is_active"
                        class="w-5 h-5 bg-[#111827] border-white/10 rounded focus:ring-2 focus:ring-indigo-500">
                    <label for="is_active" class="text-sm text-slate-300 cursor-pointer">
                        IP ativo e autorizado
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
                            {{ $editingId ? 'Atualizar' : 'Adicionar IP' }}
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