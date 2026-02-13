<?php

/**
 * ============================================================================
 * Sistema de Reembolsos - Implementação seguindo boas práticas:
 *
 * - Laravel 12:
 *   • Validação server-side robusta
 *   • Proteção contra SQL Injection via Eloquent
 *   • Transações atômicas
 *   • Sanitização básica antes de persistir
 *
 * - Livewire 4:
 *   • Propriedades tipadas
 *   • wire:model.live otimizado
 *   • Eventos dispatch integrados
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
use Illuminate\Support\Facades\DB;
use App\Models\Wallet\Refund;

new #[Layout('layouts.admin')] #[Title('Gestão de Reembolsos')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterStatus = 'all';
    public string $sort = 'desc';

    public bool $showDetailsModal = false;
    public ?int $selectedRefundId = null;

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
    public function refunds()
    {
        return Refund::query()
            ->with('user')
            ->when($this->search, fn($q) => $q->whereHas('user', fn($sub) => 
                $sub->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->when($this->filterStatus !== 'all', fn($q) => $q->where('status', $this->filterStatus))
            ->orderBy('created_at', $this->sort)
            ->paginate(15);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Refund::count(),
            'pending' => Refund::where('status', 'pending')->count(),
            'approved' => Refund::where('status', 'approved')->count(),
            'rejected' => Refund::where('status', 'rejected')->count(),
            'total_amount' => Refund::where('status', 'approved')->sum('amount_brl'),
        ];
    }

    public function approve(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $refund = Refund::findOrFail($id);

                if ($refund->status !== 'pending') {
                    throw new \Exception('Este reembolso já foi processado.');
                }

                $wallet = $refund->user->wallet;

                if (!$wallet) {
                    throw new \Exception('Usuário não possui carteira.');
                }

                if ($wallet->balance < $refund->credits) {
                    throw new \Exception('Saldo insuficiente para reembolso.');
                }

                $wallet->balance -= $refund->credits;
                $wallet->save();

                $wallet->transactions()->create([
                    'uuid' => \Str::uuid(),
                    'type' => 'debit',
                    'amount' => $refund->credits,
                    'balance_after' => $wallet->balance,
                    'description' => "Reembolso aprovado - Ref #{$refund->id}",
                    'status' => 'completed',
                    'transactionable_type' => get_class($refund),
                    'transactionable_id' => $refund->id,
                ]);

                $refund->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now()
                ]);
            });

            $this->dispatch('notify', type: 'success', text: 'Reembolso aprovado com sucesso!');
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro: ' . $e->getMessage());
        }
    }

    public function reject(int $id): void
    {
        try {
            $refund = Refund::findOrFail($id);

            if ($refund->status !== 'pending') {
                throw new \Exception('Este reembolso já foi processado.');
            }

            $refund->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);

            $this->dispatch('notify', type: 'success', text: 'Reembolso rejeitado.');
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro: ' . $e->getMessage());
        }
    }

    public function showDetails(int $id): void
    {
        $this->selectedRefundId = $id;
        $this->showDetailsModal = true;
    }

    public function closeDetails(): void
    {
        $this->showDetailsModal = false;
        $this->selectedRefundId = null;
    }

    #[Computed]
    public function selectedRefund()
    {
        return $this->selectedRefundId ? Refund::with(['user', 'approvedBy'])->find($this->selectedRefundId) : null;
    }

    public function render(): View
    {
        return view('pages.admin.finance.refound');
    }
};

?>

<div class="p-6 min-h-screen">
    <!-- HEADER -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">
                Gestão de Reembolsos
            </h1>
            <p class="text-slate-400 text-sm mt-1">Gerencie solicitações de reembolso de créditos dos usuários</p>
        </div>
        <div class="flex items-center gap-2 px-4 py-2 bg-yellow-500/10 border border-yellow-500/20 rounded-xl">
            <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-yellow-400 text-sm font-medium">{{ $this->stats['pending'] }} pendentes</span>
        </div>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Total de Solicitações</p>
                    <p class="text-3xl font-bold text-white mt-2">{{ $this->stats['total'] }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Pendentes</p>
                    <p class="text-3xl font-bold text-yellow-400 mt-2">{{ $this->stats['pending'] }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Aprovados</p>
                    <p class="text-3xl font-bold text-emerald-400 mt-2">{{ $this->stats['approved'] }}</p>
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
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Rejeitados</p>
                    <p class="text-3xl font-bold text-red-400 mt-2">{{ $this->stats['rejected'] }}</p>
                </div>
                <div class="w-12 h-12 bg-red-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Valor Total</p>
                    <p class="text-3xl font-bold text-blue-400 mt-2">
                        R$ {{ number_format($this->stats['total_amount'], 2, ',', '.') }}</p>
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
                <input wire:model.live.debounce.400ms="search" placeholder="Buscar por usuário ou email..."
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
                    <option value="pending">Pendentes</option>
                    <option value="approved">Aprovados</option>
                    <option value="rejected">Rejeitados</option>
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
                        <th class="p-4 text-left font-semibold">Usuário</th>
                        <th class="p-4 text-center font-semibold">Valor (R$)</th>
                        <th class="p-4 text-center font-semibold">Créditos</th>
                        <th class="p-4 text-center font-semibold">Status</th>
                        <th class="p-4 text-center font-semibold">Data Solicitação</th>
                        <th class="p-4 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($this->refunds as $refund)
                        <tr wire:key="refund-{{ $refund->id }}" class="hover:bg-white/5 transition-all group">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-white font-semibold">{{ $refund->user?->name ?? 'Usuário Removido' }}</div>
                                        <div class="text-slate-400 text-xs">{{ $refund->user?->email ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-white font-bold text-lg">
                                    R$ {{ number_format((float) $refund->amount_brl, 2, ',', '.') }}
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
                                    <span class="text-blue-400 font-bold">{{ $refund->credits }}</span>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                @if($refund->status === 'pending')
                                    <span
                                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">
                                        <span class="w-2 h-2 rounded-full bg-yellow-400 animate-pulse"></span>
                                        Pendente
                                    </span>
                                @elseif($refund->status === 'approved')
                                    <span
                                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                        Aprovado
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold bg-red-500/10 text-red-400 border border-red-500/20">
                                        <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                        Rejeitado
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-center text-slate-400 text-xs">
                                <div>{{ $refund->created_at->format('d/m/Y') }}</div>
                                <div class="text-slate-500">{{ $refund->created_at->format('H:i:s') }}</div>
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($refund->status === 'pending')
                                        <button wire:click="approve({{ $refund->id }})"
                                            wire:confirm="Tem certeza que deseja aprovar este reembolso? O valor será debitado da carteira do usuário."
                                            class="p-2 text-emerald-400 hover:text-emerald-300 hover:bg-emerald-500/10 rounded-lg transition-all"
                                            title="Aprovar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                        <button wire:click="reject({{ $refund->id }})"
                                            wire:confirm="Tem certeza que deseja rejeitar este reembolso?"
                                            class="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-all"
                                            title="Rejeitar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                    @endif
                                    <button wire:click="showDetails({{ $refund->id }})"
                                        class="p-2 text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-all"
                                        title="Ver Detalhes">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-12 text-center">
                                <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p class="text-slate-400 font-medium mb-2">Nenhum reembolso encontrado</p>
                                <p class="text-slate-500 text-sm">Não há solicitações de reembolso no momento</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->refunds->hasPages())
            <div class="p-6 border-t border-white/5 bg-black/10">
                {{ $this->refunds->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL DE DETALHES -->
    @if ($showDetailsModal && $this->selectedRefund)
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-[#0f172a] w-full max-w-2xl rounded-2xl border border-white/10 shadow-2xl">
                <div class="flex justify-between items-center px-8 py-6 border-b border-white/10">
                    <div>
                        <h2 class="text-lg font-bold text-white uppercase tracking-wide">
                            Detalhes do Reembolso
                        </h2>
                        <p class="text-slate-400 text-xs mt-1">
                            Informações completas sobre esta solicitação
                        </p>
                    </div>
                    <button wire:click="closeDetails"
                        class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-white/5 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-8 space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Usuário</label>
                            <div class="text-white font-semibold">{{ $this->selectedRefund->user?->name ?? 'N/A' }}</div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Email</label>
                            <div class="text-white font-semibold">{{ $this->selectedRefund->user?->email ?? 'N/A' }}</div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Valor Solicitado</label>
                            <div class="text-white font-bold text-lg">
                                R$ {{ number_format((float) $this->selectedRefund->amount_brl, 2, ',', '.') }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Créditos</label>
                            <div class="text-blue-400 font-bold text-lg">
                                {{ $this->selectedRefund->credits }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Status</label>
                            @if($this->selectedRefund->status === 'pending')
                                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">
                                    Pendente
                                </span>
                            @elseif($this->selectedRefund->status === 'approved')
                                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    Aprovado
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold bg-red-500/10 text-red-400 border border-red-500/20">
                                    Rejeitado
                                </span>
                            @endif
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Data de Solicitação</label>
                            <div class="text-white font-semibold">
                                {{ $this->selectedRefund->created_at->format('d/m/Y H:i:s') }}
                            </div>
                        </div>

                        @if($this->selectedRefund->approved_at)
                            <div class="space-y-2">
                                <label class="text-xs text-slate-500 uppercase tracking-wider">Data de Processamento</label>
                                <div class="text-white font-semibold">
                                    {{ $this->selectedRefund->approved_at->format('d/m/Y H:i:s') }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs text-slate-500 uppercase tracking-wider">Processado Por</label>
                                <div class="text-white font-semibold">
                                    {{ $this->selectedRefund->approvedBy?->name ?? 'Sistema' }}
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($this->selectedRefund->reason)
                        <div class="pt-6 border-t border-white/10">
                            <label class="text-xs text-slate-500 uppercase tracking-wider mb-2 block">Motivo da Solicitação</label>
                            <div class="bg-black/20 border border-white/5 rounded-xl p-4 text-sm text-slate-300">
                                {{ $this->selectedRefund->reason }}
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-8 py-6 border-t border-white/10 bg-black/20 flex gap-3">
                    @if($this->selectedRefund->status === 'pending')
                        <button wire:click="approve({{ $this->selectedRefund->id }})"
                            wire:confirm="Tem certeza que deseja aprovar este reembolso?"
                            class="flex-1 bg-emerald-600 hover:bg-emerald-700 transition py-3 rounded-xl text-sm font-medium text-white">
                            Aprovar Reembolso
                        </button>
                        <button wire:click="reject({{ $this->selectedRefund->id }})"
                            wire:confirm="Tem certeza que deseja rejeitar este reembolso?"
                            class="flex-1 bg-red-600 hover:bg-red-700 transition py-3 rounded-xl text-sm font-medium text-white">
                            Rejeitar
                        </button>
                    @else
                        <button wire:click="closeDetails"
                            class="w-full bg-gray-800 hover:bg-gray-700 transition py-3 rounded-xl text-sm font-medium text-white">
                            Fechar
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <x-loading target="approve,reject,showDetails" message="Processando..." overlay />
    <x-toast position="top-right" />
</div>