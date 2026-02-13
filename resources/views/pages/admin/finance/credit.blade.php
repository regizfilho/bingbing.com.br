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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Wallet\Wallet;
use App\Models\Wallet\Transaction;

new #[Layout('layouts.admin')] #[Title('Gestão de Créditos')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sort = 'desc';

    public ?int $selectedUserId = null;

    public bool $showDrawer = false;

    public int $amount = 0;
    public string $type = 'credit';
    public ?string $description = null;

    public int $historyPage = 1;

    protected function rules(): array
    {
        return [
            'selectedUserId' => ['required', 'exists:users,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:credit,debit'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggleSort(): void
    {
        $this->sort = $this->sort === 'desc' ? 'asc' : 'desc';
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->with('wallet')
            ->when($this->search, fn($q) => $q->where(fn($sub) => $sub->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
            ->orderBy(Wallet::select('balance')->whereColumn('wallets.user_id', 'users.id')->limit(1), $this->sort)
            ->paginate(15);
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        return $this->selectedUserId ? User::with('wallet')->find($this->selectedUserId) : null;
    }

    #[Computed]
    public function history()
    {
        if (!$this->selectedUserId) {
            return collect();
        }

        return Transaction::whereHas('wallet', fn($q) => $q->where('user_id', $this->selectedUserId))
            ->with(['coupon', 'transactionable'])
            ->latest()
            ->paginate(20, ['*'], 'historyPage', $this->historyPage);
    }

    public function selectUser(int $id): void
    {
        $this->selectedUserId = $id;
        $this->showDrawer = true;
        $this->reset('historyPage');
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['amount', 'description', 'type']);
    }

    public function previousHistory(): void
    {
        if ($this->selectedUserId && $this->history->onFirstPage() === false) {
            $this->historyPage--;
        }
    }

    public function nextHistory(): void
    {
        if ($this->selectedUserId && $this->history->hasMorePages()) {
            $this->historyPage++;
        }
    }

    public function apply(): void
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $user = User::findOrFail($this->selectedUserId);
                $wallet = $user->wallet ?? $user->wallet()->create(['balance' => 0]);

                if ($this->type === 'debit' && $this->amount > $wallet->balance) {
                    throw new \Exception('Saldo insuficiente para débito.');
                }

                $wallet->balance += $this->type === 'credit' ? $this->amount : -$this->amount;
                $wallet->save();

                $wallet->transactions()->create([
                    'uuid' => Str::uuid(),
                    'type' => $this->type,
                    'amount' => $this->amount,
                    'balance_after' => $wallet->balance,
                    'description' => $this->description ?? 'Ajuste manual admin',
                    'status' => 'completed',
                    'transactionable_type' => User::class,
                    'transactionable_id' => $user->id,
                    'coupon_id' => null,
                ]);
            });

            $this->dispatch('notify', type: 'success', text: 'Saldo atualizado.');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: $e->getMessage());
        }

        $this->reset(['amount', 'description']);
    }

    public function render(): View
    {
        return view('pages.admin.finance.credit');
    }
};
?>

<div class="p-6 min-h-screen ">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">
                Gestão de Créditos
            </h1>
            <p class="text-slate-400 text-sm mt-1">Gerencie saldos de usuários com precisão e segurança</p>
        </div>
        <div class="flex gap-3">
            <button wire:click="toggleSort"
                class="px-4 py-2 bg-gray-800 border border-white/10 rounded-xl text-sm text-white hover:bg-gray-700 transition-all">
                Ordenar: {{ $sort === 'desc' ? 'Maior Saldo' : 'Menor Saldo' }}
            </button>
        </div>
    </div>

    <!-- BUSCA E FILTROS -->
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center">
            <div class="flex-1 relative">
                <input wire:model.live.debounce.400ms="search" placeholder="Buscar por nome ou email..."
                    class="w-full bg-[#111827] border border-white/10 rounded-xl px-5 py-3 text-sm text-white pl-12 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <div class="flex gap-3">
                <select wire:model.live="sort"
                    class="bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="desc">Maior saldo primeiro</option>
                    <option value="asc">Menor saldo primeiro</option>
                </select>
                <button
                    class="px-4 py-3 bg-gray-800 border border-white/10 rounded-xl text-sm text-white hover:bg-gray-700 transition-all">
                    Filtros Avançados
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
                        <th class="p-4 text-left font-semibold">Email</th>
                        <th class="p-4 text-right font-semibold">Saldo Atual</th>
                        <th class="p-4 text-right font-semibold">Última Transação</th>
                        <th class="p-4 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($this->users as $user)
                        <tr class="hover:bg-white/5 transition-all group">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 bg-indigo-500/20 rounded-full flex items-center justify-center text-indigo-400 font-semibold">
                                        {{ Str::upper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-white font-medium">{{ $user->name }}</div>
                                        <div class="text-slate-400 text-xs">#{{ $user->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-slate-400 truncate max-w-xs">{{ $user->email }}</td>
                            <td class="p-4 text-right">
                                <div class="text-white font-bold text-lg">
                                    {{ number_format((float) ($user->wallet?->balance ?? 0), 2, ',', '.') }}
                                </div>
                                <div class="text-emerald-500 text-xs font-medium">R$
                                    {{ number_format((float) ($user->wallet?->balance ?? 0), 2, ',', '.') }}</div>
                            </td>
                            <td class="p-4 text-right text-slate-400 text-xs">
                                {{ $user->wallet?->transactions()->latest()->first()?->created_at?->format('d/m H:i') ?? 'N/A' }}
                            </td>
                            <td class="p-4 text-right">
                                <button wire:click="selectUser({{ $user->id }})"
                                    class="text-indigo-400 hover:text-indigo-300 text-sm font-medium transition-colors px-3 py-1 rounded-lg hover:bg-indigo-500/10">
                                    Gerenciar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-2 text-slate-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                </svg>
                                Nenhum usuário encontrado. Tente ajustar a busca.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t border-white/5 bg-black/10">
            {{ $this->users->links() }}
        </div>
    </div>

    <!-- DRAWER LATERAL -->
    @if ($showDrawer)
        <!-- DRAWER LATERAL -->
        <x-drawer :show="$showDrawer" max-width="md" wire:model="showDrawer">
            <div class="border-b border-white/10 flex items-center justify-between mb-6">
                <div>
                    @if ($this->selectedUser)
                        <h2 class="text-xl font-bold text-white">{{ $this->selectedUser->name }}</h2>
                        <p class="text-slate-400 text-sm">ID: {{ $this->selectedUser->id }}</p>
                    @endif
                </div>
                <button wire:click="closeDrawer"
                    class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-white/5 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            @if ($this->selectedUser)
                <div class="bg-black/20 border border-white/10 rounded-xl p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Saldo Atual</span>
                        <span class="text-white font-bold text-2xl">
                            {{ number_format((float) ($this->selectedUser->wallet?->balance ?? 0), 2, ',', '.') }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-2 mt-2">
                        <div class="bg-gradient-to-r from-indigo-500 to-emerald-500 h-2 rounded-full"
                            style="width: {{ min((($this->selectedUser->wallet?->balance ?? 0) / 1000) * 100, 100) }}%">
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Máx. R$ 1.000 (escala ilustrativa)</p>
                </div>

                <form wire:submit="apply" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Tipo de Ajuste</label>
                        <select wire:model="type"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="credit">Adicionar Crédito (+)</option>
                            <option value="debit">Remover Crédito (-)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Valor (R$)</label>
                        <input wire:model.defer="amount" type="number" min="1" step="1"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: 50">
                        @error('amount')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Descrição</label>
                        <textarea wire:model.defer="description" rows="3"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Motivo do ajuste (opcional)"></textarea>
                    </div>

                    <button type="submit" wire:loading.attr="disabled"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-800 transition py-3 rounded-xl text-sm font-medium text-white flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="apply">Aplicar Ajuste</span>
                        <span wire:loading wire:target="apply">Salvando...</span>
                        <svg wire:loading.remove wire:target="apply" class="w-4 h-4" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7" />
                        </svg>
                        <div wire:loading wire:target="apply"
                            class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin">
                        </div>
                    </button>

                    <button type="button" wire:click="$dispatch('open-modal', { name: 'history-modal' })"
                        class="w-full bg-gray-800 hover:bg-gray-700 transition py-3 rounded-xl text-sm text-indigo-400 font-medium">
                        Ver Histórico Detalhado
                    </button>
                </form>
            @endif
        </x-drawer>
    @endif



    <!-- MODAL DE HISTÓRICO -->
    <x-modal name="history-modal" title="Histórico de Transações" maxWidth="4xl">
        <div class="space-y-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h4 class="text-lg font-semibold text-white">{{ $this->selectedUser?->name ?? 'Usuário' }} -
                        Histórico Completo</h4>
                    <p class="text-slate-400 text-sm">Últimas
                        {{ $this->selectedUserId ? $this->history->total() ?? 0 : 0 }} transações (pág.
                        {{ $this->selectedUserId ? $this->history->currentPage() : 1 }} de
                        {{ $this->selectedUserId ? $this->history->lastPage() : 1 }})</p>
                </div>
                <div class="flex gap-2">
                    <button wire:click="previousHistory"
                        {{ $this->selectedUserId && !$this->history->onFirstPage() ? '' : 'disabled' }}
                        class="px-3 py-1 bg-gray-800 text-slate-400 rounded-lg disabled:opacity-50">Anterior</button>
                    <button wire:click="nextHistory"
                        {{ $this->selectedUserId && $this->history->hasMorePages() ? '' : 'disabled' }}
                        class="px-3 py-1 bg-indigo-600 text-white rounded-lg disabled:opacity-50">Próxima</button>
                </div>
            </div>

            @if ($this->selectedUserId && $this->history->count() > 0)
                <div class="grid gap-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    @foreach ($this->history as $tx)
                        <div class="border border-white/5 rounded-xl p-5 bg-black/10 hover:bg-white/5 transition-all">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-bold {{ $tx->type === 'credit' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' }}">
                                        {{ ucfirst($tx->type) }}
                                    </span>
                                    @if ($tx->transactionable)
                                        <span class="text-xs text-slate-500">via
                                            {{ class_basename($tx->transactionable_type) }}
                                            #{{ $tx->transactionable_id }}</span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div
                                        class="text-2xl font-bold {{ $tx->type === 'credit' ? 'text-emerald-500' : 'text-red-500' }}">
                                        {{ $tx->type === 'credit' ? '+' : '-' }}{{ number_format((float) $tx->amount, 2, ',', '.') }}
                                    </div>
                                    <div class="text-xs text-slate-400">Saldo após:
                                        {{ number_format((float) $tx->balance_after, 2, ',', '.') }}</div>
                                </div>
                            </div>

                            <div class="text-sm text-slate-300 mb-3">{{ $tx->description }}</div>

                            @if ($tx->original_amount)
                                <div class="grid grid-cols-3 gap-2 text-xs bg-black/20 rounded-lg p-3 mb-3">
                                    <div class="text-slate-500">Pacote:</div>
                                    <div class="text-white font-semibold">R$
                                        {{ number_format((float) $tx->original_amount, 2, ',', '.') }}</div>
                                    <div></div>
                                    @if ($tx->discount_amount > 0)
                                        <div class="text-red-500">- Desconto:</div>
                                        <div class="text-red-500 font-semibold">- R$
                                            {{ number_format((float) $tx->discount_amount, 2, ',', '.') }}</div>
                                        <div></div>
                                    @endif
                                    <div class="text-emerald-500 font-bold">Pago:</div>
                                    <div class="text-emerald-500 font-bold">R$
                                        {{ number_format((float) $tx->final_amount, 2, ',', '.') }}</div>
                                    <div></div>
                                </div>
                            @endif

                            @if ($tx->coupon)
                                <div class="flex items-center gap-2 mb-3 p-2 bg-blue-500/10 rounded-lg">
                                    <span
                                        class="px-2 py-1 bg-blue-500/20 text-blue-400 text-xs font-bold rounded">Cupom</span>
                                    <span class="text-blue-300 font-semibold">{{ $tx->coupon->code }}</span>
                                </div>
                            @endif

                            <div class="flex justify-between items-center text-xs text-slate-500">
                                <span>{{ $tx->created_at->format('d/m/Y H:i:s') }}</span>
                                <span>ID: #{{ str_pad($tx->id, 8, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-slate-400">
                    <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Nenhuma transação encontrada para este usuário.
                </div>
            @endif
        </div>
    </x-modal>

    <x-loading target="apply" message="Atualizando saldo..." overlay />

    <x-toast position="top-right" />

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #111827;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }
    </style>
</div>
