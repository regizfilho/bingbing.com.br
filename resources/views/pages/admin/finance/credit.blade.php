<?php

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Wallet\Wallet;
use App\Models\Wallet\Transaction;

new #[Layout('layouts.admin')] #[Title('Gestão de Créditos')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sort = 'desc';
    public string $statusFilter = 'all'; // all, positive, zero, negative

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

    protected function messages(): array
    {
        return [
            'amount.required' => 'O valor é obrigatório.',
            'amount.min' => 'O valor mínimo é 1.',
            'description.max' => 'A descrição não pode ter mais de 255 caracteres.',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->with([
                'wallet' => function ($q) {
                    $q->withCount('transactions');
                },
            ])
            ->when($this->search, fn($q) => $q->where(fn($sub) => $sub->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->statusFilter !== 'all', function ($q) {
                $q->whereHas('wallet', function ($sub) {
                    if ($this->statusFilter === 'positive') {
                        $sub->where('balance', '>', 0);
                    } elseif ($this->statusFilter === 'zero') {
                        $sub->where('balance', '=', 0);
                    } elseif ($this->statusFilter === 'negative') {
                        $sub->where('balance', '<', 0);
                    }
                });
            })
            ->orderBy(Wallet::select('balance')->whereColumn('wallets.user_id', 'users.id')->limit(1), $this->sort)
            ->paginate(15);
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        return $this->selectedUserId
            ? User::with([
                'wallet' => function ($q) {
                    $q->withCount('transactions');
                },
            ])->find($this->selectedUserId)
            : null;
    }

    #[Computed]
    public function history()
    {
        if (!$this->selectedUserId) {
            return collect();
        }

        return Transaction::whereHas('wallet', fn($q) => $q->where('user_id', $this->selectedUserId))
            ->with(['coupon', 'transactionable', 'package', 'giftCard'])
            ->latest()
            ->paginate(20, ['*'], 'historyPage', $this->historyPage);
    }

    #[Computed]
    public function statistics(): array
    {
        return [
            'total_users' => User::count(),
            'users_with_balance' => Wallet::where('balance', '>', 0)->count(),
            'total_circulation' => Wallet::sum('balance'),
            'average_balance' => Wallet::avg('balance') ?? 0,
            'total_transactions_today' => Transaction::whereDate('created_at', today())->count(),
        ];
    }

    public function selectUser(int $id): void
    {
        $this->selectedUserId = $id;
        $this->showDrawer = true;
        $this->reset(['historyPage', 'amount', 'description', 'type']);
        $this->resetValidation();
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['amount', 'description', 'type', 'selectedUserId']);
        $this->resetValidation();
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

                // Atualizar saldo
                if ($this->type === 'credit') {
                    $wallet->balance += $this->amount;
                } else {
                    $wallet->balance -= $this->amount;
                }
                $wallet->save();

                // Criar transação
                $wallet->transactions()->create([
                    'uuid' => Str::uuid(),
                    'type' => $this->type,
                    'amount' => $this->amount,
                    'balance_after' => $wallet->balance,
                    'description' => $this->description ?? 'Ajuste manual pelo administrador',
                    'status' => 'completed',
                    'transactionable_type' => User::class,
                    'transactionable_id' => $user->id,
                    'coupon_id' => null,
                    'final_amount' => $this->amount,
                ]);

                // Notificação Push
                $pushService = app(\App\Services\PushNotificationService::class);

                if ($this->type === 'credit') {
                    $message = [
                        'title' => '💰 Créditos Adicionados',
                        'body' => "Você recebeu {$this->amount} créditos do administrador!",
                    ];
                } else {
                    $message = [
                        'title' => '⚠️ Créditos Debitados',
                        'body' => "{$this->amount} créditos foram removidos da sua conta.",
                    ];
                }

                $pushService->notifyUser($user->id, $message['title'], $message['body'], route('wallet.index'));
            });

            $this->dispatch('notify', type: 'success', text: 'Saldo atualizado com sucesso!');
            $this->reset(['amount', 'description']);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: $e->getMessage());
        }
    }

    public function exportUsers(): void
    {
        try {
            $users = User::with('wallet')
                ->get()
                ->map(function ($user) {
                    return [
                        'ID' => $user->id,
                        'Nome' => $user->name,
                        'Email' => $user->email,
                        'Saldo' => $user->wallet?->balance ?? 0,
                        'Transações' => $user->wallet?->transactions()->count() ?? 0,
                        'Última Transação' => $user->wallet?->transactions()->latest()->first()?->created_at?->format('d/m/Y H:i') ?? 'N/A',
                    ];
                });

            $filename = 'usuarios_creditos_' . now()->format('Y-m-d_His') . '.json';
            $path = storage_path('app/public/exports/' . $filename);

            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->dispatch('notify', type: 'success', text: 'Relatório exportado com sucesso!');

        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao exportar: ' . $e->getMessage());
        }
    }
};
?>

<div class="p-6 min-h-screen">
    <x-loading target="apply, exportUsers" message="PROCESSANDO..." overlay />

    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                💳 Gestão de Créditos
                <span
                    class="text-sm px-3 py-1 bg-indigo-500/10 text-indigo-400 rounded-lg border border-indigo-500/20 font-normal">
                    Admin Panel
                </span>
            </h1>
            <p class="text-slate-400 text-sm mt-1">Gerencie saldos e transações de usuários com segurança</p>
        </div>
        <div class="flex gap-3">
            <button wire:click="exportUsers"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 border border-emerald-500/30 rounded-xl text-sm text-white transition-all font-bold flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Exportar
            </button>
        </div>
    </div>

    {{-- ESTATÍSTICAS --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-indigo-500/20 transition-all">
            <div class="w-10 h-10 bg-indigo-500/10 rounded-xl flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Total Usuários</p>
            <p class="text-2xl font-bold text-white">
                {{ number_format($this->statistics['total_users'], 0) }}
            </p>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-emerald-500/20 transition-all">
            <div class="w-10 h-10 bg-emerald-500/10 rounded-xl flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Com Saldo</p>
            <p class="text-2xl font-bold text-emerald-400">
                {{ number_format($this->statistics['users_with_balance'], 0) }}
            </p>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-blue-500/20 transition-all">
            <div class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
            </div>
            <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Em Circulação</p>
            <p class="text-2xl font-bold text-blue-400">
                {{ number_format($this->statistics['total_circulation'], 0) }}
            </p>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-purple-500/20 transition-all">
            <div class="w-10 h-10 bg-purple-500/10 rounded-xl flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
            <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Saldo Médio</p>
            <p class="text-2xl font-bold text-purple-400">
                {{ number_format($this->statistics['average_balance'], 0) }}
            </p>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-yellow-500/20 transition-all">
            <div class="w-10 h-10 bg-yellow-500/10 rounded-xl flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
            </div>
            <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Trans. Hoje</p>
            <p class="text-2xl font-bold text-yellow-400">
                {{ number_format($this->statistics['total_transactions_today'], 0) }}
            </p>
        </div>
    </div>

    {{-- BUSCA E FILTROS --}}
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 mb-8">
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
                <select wire:model.live="statusFilter"
                    class="bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Todos os Status</option>
                    <option value="positive">Com Saldo Positivo</option>
                    <option value="zero">Saldo Zero</option>
                    <option value="negative">Saldo Negativo</option>
                </select>
                <select wire:model.live="sort"
                    class="bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="desc">Maior Saldo</option>
                    <option value="asc">Menor Saldo</option>
                </select>
            </div>
        </div>
    </div>

    {{-- TABELA --}}
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="p-4 text-left font-semibold">Usuário</th>
                        <th class="p-4 text-left font-semibold">Email</th>
                        <th class="p-4 text-center font-semibold">Transações</th>
                        <th class="p-4 text-right font-semibold">Saldo Atual</th>
                        <th class="p-4 text-right font-semibold">Última Atividade</th>
                        <th class="p-4 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($this->users as $user)
                        <tr class="hover:bg-white/5 transition-all group">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 bg-indigo-500/20 rounded-full flex items-center justify-center text-indigo-400 font-semibold text-xs">
                                        {{ Str::upper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-white font-medium">{{ $user->name }}</div>
                                        <div class="text-slate-400 text-xs">ID: {{ $user->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="text-slate-400 truncate max-w-xs">{{ $user->email }}</div>
                            </td>
                            <td class="p-4 text-center">
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                    {{ $user->wallet?->transactions_count ?? 0 }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                @php
                                    $balance = (float) ($user->wallet?->balance ?? 0);
                                    $colorClass =
                                        $balance > 0
                                            ? 'text-emerald-400'
                                            : ($balance < 0
                                                ? 'text-red-400'
                                                : 'text-slate-400');
                                @endphp
                                <div class="text-lg font-bold {{ $colorClass }}">
                                    {{ number_format($balance, 2, ',', '.') }}
                                </div>
                                <div class="text-slate-500 text-xs">créditos</div>
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
                            <td colspan="6" class="p-12 text-center text-slate-400">
                                <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <p class="text-sm font-medium">Nenhum usuário encontrado</p>
                                <p class="text-xs text-slate-500 mt-1">Tente ajustar os filtros de busca</p>
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

    {{-- DRAWER --}}
    @if ($showDrawer && $this->selectedUser)
        <x-drawer :show="$showDrawer" max-width="md" wire:model="showDrawer">
            <div class="border-b border-white/10 pb-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white">{{ $this->selectedUser->name }}</h2>
                        <p class="text-slate-400 text-sm">{{ $this->selectedUser->email }}</p>
                        <p class="text-slate-500 text-xs mt-1">ID: {{ $this->selectedUser->id }}</p>
                    </div>
                    <button wire:click="closeDrawer"
                        class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-white/5 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Saldo Atual --}}
            <div
                class="bg-gradient-to-br from-indigo-500/10 to-purple-500/5 border border-indigo-500/20 rounded-2xl p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-slate-400 text-sm font-medium">Saldo Atual</span>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                        <span class="text-xs text-slate-500">Ativo</span>
                    </div>
                </div>
                <div class="text-4xl font-black text-white mb-2">
                    {{ number_format((float) ($this->selectedUser->wallet?->balance ?? 0), 2, ',', '.') }}
                </div>
                <div class="text-xs text-slate-500">
                    {{ $this->selectedUser->wallet?->transactions_count ?? 0 }} transações realizadas
                </div>
                <div class="w-full bg-white/5 rounded-full h-2 mt-4">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-2 rounded-full transition-all"
                        style="width: {{ min((($this->selectedUser->wallet?->balance ?? 0) / 1000) * 100, 100) }}%">
                    </div>
                </div>
            </div>

            {{-- Formulário --}}
            <form wire:submit="apply" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Tipo de Operação</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" wire:click="$set('type', 'credit')"
                            class="p-4 rounded-xl border transition-all text-center {{ $type === 'credit' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-[#111827] border-white/10 text-slate-400' }}">
                            <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span class="text-xs font-bold uppercase">Adicionar</span>
                        </button>
                        <button type="button" wire:click="$set('type', 'debit')"
                            class="p-4 rounded-xl border transition-all text-center {{ $type === 'debit' ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-[#111827] border-white/10 text-slate-400' }}">
                            <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                            </svg>
                            <span class="text-xs font-bold uppercase">Remover</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Valor (Créditos)</label>
                    <input wire:model.defer="amount" type="number" min="1" step="1"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ex: 50">
                    @error('amount')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Descrição/Motivo</label>
                    <textarea wire:model.defer="description" rows="3"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Descreva o motivo deste ajuste (opcional)"></textarea>
                    @error('description')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:bg-indigo-800 transition py-3 rounded-xl text-sm font-bold text-white flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="apply">Aplicar Ajuste</span>
                    <span wire:loading wire:target="apply">Processando...</span>
                    <svg wire:loading.remove wire:target="apply" class="w-4 h-4" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <div wire:loading wire:target="apply"
                        class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin">
                    </div>
                </button>

                <button type="button" wire:click="$dispatch('open-modal', { name: 'history-modal' })"
                    class="w-full bg-gray-800 hover:bg-gray-700 transition py-3 rounded-xl text-sm text-indigo-400 font-medium flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Ver Histórico Completo
                </button>
            </form>
        </x-drawer>
    @endif

    {{-- MODAL DE HISTÓRICO --}}
    <x-modal name="history-modal" title="Histórico de Transações" maxWidth="4xl">
        <div class="space-y-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h4 class="text-lg font-semibold text-white">{{ $this->selectedUser?->name ?? 'Usuário' }}</h4>
                    <p class="text-slate-400 text-sm">
                        {{ $this->selectedUserId ? $this->history->total() ?? 0 : 0 }} transações •
                        Página {{ $this->selectedUserId ? $this->history->currentPage() : 1 }} de
                        {{ $this->selectedUserId ? $this->history->lastPage() : 1 }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button wire:click="previousHistory"
                        {{ $this->selectedUserId && !$this->history->onFirstPage() ? '' : 'disabled' }}
                        class="px-3 py-1 bg-gray-800 text-slate-400 rounded-lg disabled:opacity-50 text-sm font-medium">
                        Anterior
                    </button>
                    <button wire:click="nextHistory"
                        {{ $this->selectedUserId && $this->history->hasMorePages() ? '' : 'disabled' }}
                        class="px-3 py-1 bg-indigo-600 text-white rounded-lg disabled:opacity-50 text-sm font-medium">
                        Próxima
                    </button>
                </div>
            </div>

            @if ($this->selectedUserId && $this->history->count() > 0)
                <div class="grid gap-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    @foreach ($this->history as $tx)
                        <div class="border border-white/5 rounded-xl p-5 bg-black/10 hover:bg-white/5 transition-all">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-bold 
                                        {{ $tx->type === 'credit' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' }}">
                                        {{ $tx->type === 'credit' ? '+ ENTRADA' : '- SAÍDA' }}
                                    </span>

                                    {{-- Origem da transação --}}
                                    @if ($tx->package)
                                        <span
                                            class="px-2 py-1 rounded-lg text-xs font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                            📦 {{ $tx->package->name }}
                                        </span>
                                    @elseif($tx->giftCard)
                                        <span
                                            class="px-2 py-1 rounded-lg text-xs font-bold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                                            🎁 {{ $tx->giftCard->code }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div
                                        class="text-2xl font-bold {{ $tx->type === 'credit' ? 'text-emerald-400' : 'text-red-400' }}">
                                        {{ $tx->type === 'credit' ? '+' : '-' }}{{ number_format((float) $tx->amount, 0, ',', '.') }}
                                    </div>
                                    <div class="text-xs text-slate-400">
                                        Saldo: {{ number_format((float) $tx->balance_after, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>

                            <div class="text-sm text-slate-300 mb-3">{{ $tx->description }}</div>

                            @if ($tx->original_amount)
                                <div class="grid grid-cols-3 gap-2 text-xs bg-black/20 rounded-lg p-3 mb-3">
                                    <div class="text-slate-500">Pacote:</div>
                                    <div class="text-white font-semibold">
                                        R$ {{ number_format((float) $tx->original_amount, 2, ',', '.') }}
                                    </div>
                                    <div></div>
                                    @if ($tx->discount_amount > 0)
                                        <div class="text-yellow-500">Desconto:</div>
                                        <div class="text-yellow-500 font-semibold">
                                            -R$ {{ number_format((float) $tx->discount_amount, 2, ',', '.') }}
                                        </div>
                                        <div></div>
                                    @endif
                                    <div class="text-emerald-500 font-bold">Pago:</div>
                                    <div class="text-emerald-500 font-bold">
                                        R$ {{ number_format((float) $tx->final_amount, 2, ',', '.') }}
                                    </div>
                                    <div></div>
                                </div>
                            @endif

                            @if ($tx->coupon)
                                <div
                                    class="flex items-center gap-2 mb-3 p-2 bg-blue-500/10 rounded-lg border border-blue-500/20">
                                    <span class="px-2 py-1 bg-blue-500/20 text-blue-400 text-xs font-bold rounded">
                                        🎫 CUPOM
                                    </span>
                                    <span class="text-blue-300 font-semibold">{{ $tx->coupon->code }}</span>
                                </div>
                            @endif>

                            <div
                                class="flex justify-between items-center text-xs text-slate-500 pt-3 border-t border-white/5">
                                <span>{{ $tx->created_at->format('d/m/Y H:i:s') }}</span>
                                <span>ID: #{{ str_pad($tx->id, 8, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-16 text-slate-400">
                    <svg class="w-20 h-20 mx-auto mb-4 text-slate-500" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="font-medium">Nenhuma transação encontrada</p>
                    <p class="text-xs text-slate-500 mt-1">Este usuário ainda não realizou nenhuma transação</p>
                </div>
            @endif
        </div>
    </x-modal>

    <x-toast position="top-right" />

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #111827;
            border-radius: 3px;
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
