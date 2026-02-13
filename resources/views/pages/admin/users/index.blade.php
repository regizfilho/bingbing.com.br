<?php

/**
 * ============================================================================
 * Gestão Completa de Usuários - Index
 * 
 * - Laravel 12:
 *   • Queries otimizadas com eager loading
 *   • Paginação eficiente
 *   • Filtros e busca avançada
 * 
 * - Livewire 4:
 *   • Propriedades tipadas e computadas
 *   • Controle de estado previsível
 *   • Real-time updates
 * 
 * - Features:
 *   • Listagem completa de usuários
 *   • Filtros por status, créditos, ranking
 *   • Busca por nome, email, ID
 *   • Ações rápidas (editar, banir, créditos)
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
use App\Models\User;
use App\Models\Wallet\Transaction;

new #[Layout('layouts.admin')] #[Title('Gestão de Usuários')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterStatus = 'all'; // all, active, inactive, banned
    public string $filterCredits = 'all'; // all, with_credits, no_credits
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCredits(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->with(['wallet'])
            ->withCount(['playedGames'])
            ->when($this->search, function($q) {
                $q->where(function($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('id', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterStatus !== 'all', function($q) {
                if ($this->filterStatus === 'active') {
                    $q->where('last_seen_at', '>=', now()->subDays(7));
                } elseif ($this->filterStatus === 'inactive') {
                    $q->where('last_seen_at', '<', now()->subDays(7))
                      ->orWhereNull('last_seen_at');
                } elseif ($this->filterStatus === 'banned') {
                    $q->whereNotNull('ban_reason');
                }
            })
            ->when($this->filterCredits !== 'all', function($q) {
                if ($this->filterCredits === 'with_credits') {
                    $q->whereHas('wallet', fn($w) => $w->where('balance', '>', 0));
                } elseif ($this->filterCredits === 'no_credits') {
                    $q->whereDoesntHave('wallet')
                      ->orWhereHas('wallet', fn($w) => $w->where('balance', '<=', 0));
                }
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => User::count(),
            'active_today' => User::where('last_seen_at', '>=', today())->count(),
            'with_credits' => User::whereHas('wallet', fn($q) => $q->where('balance', '>', 0))->count(),
            'banned' => User::whereNotNull('ban_reason')->count(),
        ];
    }

    public function render(): View
    {
        return view('pages.admin.users.index');
    }
};
?>

<div>
    <x-slot name="header">
        Gestão de Usuários
    </x-slot>

    <div class="space-y-6">
        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                    👥 Gestão Completa de Usuários
                </h2>
                <p class="text-slate-400 text-sm mt-1">Visualize, edite e gerencie todos os usuários da plataforma</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.users.live') }}" wire:navigate
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-xl text-sm text-white transition-all flex items-center gap-2">
                    <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                    Tempo Real
                </a>
                <a href="{{ route('admin.users.anaytics') }}" wire:navigate
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-xl text-sm text-white transition-all">
                    📊 Analytics
                </a>
            </div>
        </div>

        <!-- CARDS DE ESTATÍSTICAS -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Total de Usuários</p>
                <p class="text-3xl font-bold text-white">{{ number_format($this->stats['total'], 0, ',', '.') }}</p>
            </div>
            <div class="bg-[#0f172a] border border-green-500/20 rounded-2xl p-5">
                <p class="text-green-400 text-xs uppercase tracking-wider font-medium mb-2">Ativos Hoje</p>
                <p class="text-3xl font-bold text-green-400">{{ number_format($this->stats['active_today'], 0, ',', '.') }}</p>
            </div>
            <div class="bg-[#0f172a] border border-emerald-500/20 rounded-2xl p-5">
                <p class="text-emerald-400 text-xs uppercase tracking-wider font-medium mb-2">Com Créditos</p>
                <p class="text-3xl font-bold text-emerald-400">{{ number_format($this->stats['with_credits'], 0, ',', '.') }}</p>
            </div>
            <div class="bg-[#0f172a] border border-red-500/20 rounded-2xl p-5">
                <p class="text-red-400 text-xs uppercase tracking-wider font-medium mb-2">Banidos</p>
                <p class="text-3xl font-bold text-red-400">{{ number_format($this->stats['banned'], 0, ',', '.') }}</p>
            </div>
        </div>

        <!-- FILTROS E BUSCA -->
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
            <div class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1 relative">
                    <input wire:model.live.debounce.400ms="search" 
                        placeholder="Buscar por nome, email ou ID..."
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-5 py-3 text-sm text-white pl-12 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <select wire:model.live="filterStatus"
                    class="bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Todos os Status</option>
                    <option value="active">Ativos (7 dias)</option>
                    <option value="inactive">Inativos</option>
                    <option value="banned">Banidos</option>
                </select>
                <select wire:model.live="filterCredits"
                    class="bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Todos os Créditos</option>
                    <option value="with_credits">Com Créditos</option>
                    <option value="no_credits">Sem Créditos</option>
                </select>
            </div>
        </div>

        <!-- TABELA -->
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="p-4 text-left font-semibold cursor-pointer hover:text-white transition" wire:click="sortBy('id')">
                                ID
                                @if($sortBy === 'id')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="p-4 text-left font-semibold cursor-pointer hover:text-white transition" wire:click="sortBy('name')">
                                Usuário
                                @if($sortBy === 'name')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="p-4 text-center font-semibold">Créditos</th>
                            <th class="p-4 text-center font-semibold">Partidas</th>
                            <th class="p-4 text-center font-semibold cursor-pointer hover:text-white transition" wire:click="sortBy('last_seen_at')">
                                Último Acesso
                                @if($sortBy === 'last_seen_at')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="p-4 text-center font-semibold cursor-pointer hover:text-white transition" wire:click="sortBy('created_at')">
                                Cadastro
                                @if($sortBy === 'created_at')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="p-4 text-right font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($this->users as $user)
                            <tr class="hover:bg-white/5 transition">
                                <td class="p-4 text-slate-400">#{{ $user->id }}</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-indigo-500/20 rounded-full flex items-center justify-center text-indigo-400 font-semibold text-sm">
                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="text-white font-medium">{{ $user->name }}</div>
                                            <div class="text-slate-400 text-xs">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="text-emerald-400 font-bold">
                                        {{ number_format($user->wallet?->balance ?? 0, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="text-blue-400 font-semibold">{{ $user->played_games_count ?? 0 }}</span>
                                </td>
                                <td class="p-4 text-center text-xs">
                                    @if($user->last_seen_at)
                                        <span class="text-slate-400">{{ $user->last_seen_at->diffForHumans() }}</span>
                                    @else
                                        <span class="text-slate-500">Nunca</span>
                                    @endif
                                </td>
                                <td class="p-4 text-center text-xs text-slate-400">
                                    {{ $user->created_at->format('d/m/Y') }}
                                </td>
                                <td class="p-4 text-right">
                                    <a href="{{ route('admin.users.profile', $user->uuid) }}" wire:navigate
                                        class="text-indigo-400 hover:text-indigo-300 text-sm font-medium transition px-3 py-1 rounded-lg hover:bg-indigo-500/10">
                                        Ver Perfil
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">
                                    Nenhum usuário encontrado
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
    </div>

    <x-toast position="top-right" />
</div>