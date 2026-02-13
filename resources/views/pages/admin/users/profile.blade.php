<?php

/**
 * ============================================================================
 * Perfil Completo do Usuário
 * 
 * - Laravel 12:
 *   • Eager loading otimizado
 *   • Queries eficientes
 * 
 * - Livewire 4:
 *   • Propriedades tipadas
 *   • Real-time updates
 * 
 * - Features:
 *   • Dados completos do usuário
 *   • Estatísticas de uso
 *   • Histórico de créditos
 *   • Histórico de partidas
 *   • Ranking e pontuação
 *   • Ações administrativas
 * ============================================================================
 */

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Wallet\Transaction;

new #[Layout('layouts.admin')] #[Title('Perfil do Usuário')] class extends Component {
    use WithPagination;

    public string $uuid;
    public string $activeTab = 'overview'; // overview, credits, matches, stats

    // Modal de banimento
    public bool $showBanModal = false;
    public ?string $banReason = null;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    #[Computed]
    public function user(): ?User
    {
        return User::where('uuid', $this->uuid)
            ->with(['wallet', 'rank', 'wins'])
            ->withCount(['playedGames', 'createdGames', 'wins'])
            ->first();
    }

    #[Computed]
    public function creditHistory()
    {
        if (!$this->user) return collect();

        return Transaction::whereHas('wallet', fn($q) => $q->where('user_id', $this->user->id))
            ->with(['coupon', 'package'])
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function stats(): array
    {
        if (!$this->user) return [];

        $userId = $this->user->id;

        return [
            'total_spent' => Transaction::whereHas('wallet', fn($q) => $q->where('user_id', $userId))
                ->where('type', 'debit')
                ->where('status', 'completed')
                ->sum('amount'),
            'total_purchased' => Transaction::whereHas('wallet', fn($q) => $q->where('user_id', $userId))
                ->where('type', 'credit')
                ->where('status', 'completed')
                ->sum('final_amount'),
            'total_games' => $this->user->played_games_count ?? 0,
            'games_created' => $this->user->created_games_count ?? 0,
            'total_wins' => $this->user->wins_count ?? 0,
            'rank_points' => $this->user->rank?->points ?? 0,
            'win_rate' => ($this->user->played_games_count ?? 0) > 0 
                ? round((($this->user->wins_count ?? 0) / $this->user->played_games_count) * 100, 1)
                : 0,
        ];
    }

    #[Computed]
    public function recentGames()
    {
        if (!$this->user) return collect();

        return $this->user->playedGames()
            ->with(['creator', 'players'])
            ->withCount('players')
            ->latest()
            ->limit(10)
            ->get();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function openBanModal(): void
    {
        $this->showBanModal = true;
        $this->banReason = null;
    }

    public function closeBanModal(): void
    {
        $this->showBanModal = false;
        $this->banReason = null;
    }

    public function banUser(): void
    {
        $this->validate([
            'banReason' => ['required', 'string', 'min:10', 'max:500']
        ], [
            'banReason.required' => 'O motivo do banimento é obrigatório',
            'banReason.min' => 'O motivo deve ter no mínimo 10 caracteres',
            'banReason.max' => 'O motivo deve ter no máximo 500 caracteres',
        ]);

        try {
            $this->user->update([
                'ban_reason' => $this->banReason,
                'banned_at' => now(),
                'banned_by' => auth()->id(),
            ]);

            $this->dispatch('notify', type: 'success', text: 'Usuário banido com sucesso');
            $this->closeBanModal();
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao banir usuário');
        }
    }

    public function unbanUser(): void
    {
        try {
            $this->user->update([
                'ban_reason' => null,
                'banned_at' => null,
                'banned_by' => null,
            ]);

            $this->dispatch('notify', type: 'success', text: 'Usuário desbanido com sucesso');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao desbanir usuário');
        }
    }

    public function render(): View
    {
        return view('pages.admin.users.profile');
    }
};
?>

<div>
    <x-slot name="header">
        Perfil do Usuário
    </x-slot>

    @if($this->user)
        <div class="space-y-6">
            <!-- HEADER DO PERFIL -->
            <div class="bg-gradient-to-r from-indigo-500/10 to-purple-500/10 border border-indigo-500/20 rounded-2xl p-6">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center text-white font-bold text-2xl relative">
                            {{ strtoupper(substr($this->user->name, 0, 2)) }}
                            @if($this->user->banned_at)
                                <div class="absolute -top-2 -right-2 w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-xs">
                                    🚫
                                </div>
                            @endif
                        </div>
                        <div>
                            <div class="flex items-center gap-3">
                                <h2 class="text-2xl font-bold text-white">{{ $this->user->name }}</h2>
                                @if($this->user->banned_at)
                                    <span class="px-3 py-1 bg-red-500/20 text-red-400 text-xs font-bold rounded-lg border border-red-500/30">
                                        BANIDO
                                    </span>
                                @endif
                            </div>
                            <p class="text-slate-400">{{ $this->user->email }}</p>
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-xs text-slate-500">ID: #{{ $this->user->id }}</span>
                                <span class="text-xs text-slate-500">UUID: {{ $this->user->uuid }}</span>
                                <span class="text-xs text-slate-500">Cadastro: {{ $this->user->created_at->format('d/m/Y') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($this->user->banned_at)
                            <button wire:click="unbanUser" wire:confirm="Deseja realmente desbanir este usuário?"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 rounded-xl text-sm text-white transition-all flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Desbanir
                            </button>
                        @else
                            <button wire:click="openBanModal"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-xl text-sm text-white transition-all flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                Banir Usuário
                            </button>
                        @endif
                        <a href="{{ route('admin.users.home') }}" wire:navigate
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-xl text-sm text-white transition-all">
                            ← Voltar
                        </a>
                    </div>
                </div>
                
                @if($this->user->banned_at)
                    <div class="mt-4 p-4 bg-red-500/10 border border-red-500/20 rounded-xl">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-red-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div class="flex-1">
                                <p class="text-red-400 font-semibold text-sm">Motivo do Banimento:</p>
                                <p class="text-red-300 text-sm mt-1">{{ $this->user->ban_reason }}</p>
                                <p class="text-red-400/60 text-xs mt-2">
                                    Banido em {{ $this->user->banned_at->format('d/m/Y H:i') }}
                                    @if($this->user->bannedBy)
                                        por {{ $this->user->bannedBy->name }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- CARDS DE ESTATÍSTICAS RÁPIDAS -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-[#0f172a] border border-emerald-500/20 rounded-2xl p-5">
                    <p class="text-emerald-400 text-xs uppercase tracking-wider font-medium mb-2">Saldo Atual</p>
                    <p class="text-3xl font-bold text-white">{{ number_format($this->user->wallet?->balance ?? 0, 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 mt-1">créditos</p>
                </div>
                <div class="bg-[#0f172a] border border-blue-500/20 rounded-2xl p-5">
                    <p class="text-blue-400 text-xs uppercase tracking-wider font-medium mb-2">Total Gasto</p>
                    <p class="text-2xl font-bold text-white">R$ {{ number_format($this->stats['total_purchased'] ?? 0, 2, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 mt-1">em compras</p>
                </div>
                <div class="bg-[#0f172a] border border-purple-500/20 rounded-2xl p-5">
                    <p class="text-purple-400 text-xs uppercase tracking-wider font-medium mb-2">Total Jogos</p>
                    <p class="text-3xl font-bold text-white">{{ number_format($this->stats['total_games'] ?? 0, 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 mt-1">partidas jogadas</p>
                </div>
                <div class="bg-[#0f172a] border border-emerald-500/20 rounded-2xl p-5">
                    <p class="text-emerald-400 text-xs uppercase tracking-wider font-medium mb-2">Taxa de Vitória</p>
                    <p class="text-3xl font-bold text-white">{{ $this->stats['win_rate'] ?? 0 }}%</p>
                    <p class="text-xs text-slate-500 mt-1">{{ $this->stats['total_wins'] ?? 0 }} vitórias</p>
                </div>
                <div class="bg-[#0f172a] border border-yellow-500/20 rounded-2xl p-5">
                    <p class="text-yellow-400 text-xs uppercase tracking-wider font-medium mb-2">Pontos de Rank</p>
                    <p class="text-3xl font-bold text-white">{{ number_format($this->stats['rank_points'] ?? 0, 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 mt-1">pts no ranking</p>
                </div>
            </div>

            <!-- TABS DE NAVEGAÇÃO -->
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden">
                <div class="flex border-b border-white/5">
                    <button wire:click="setTab('overview')"
                        class="flex-1 px-6 py-4 text-sm font-medium transition {{ $activeTab === 'overview' ? 'bg-indigo-500/10 text-indigo-400 border-b-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-white/5' }}">
                        📊 Visão Geral
                    </button>
                    <button wire:click="setTab('credits')"
                        class="flex-1 px-6 py-4 text-sm font-medium transition {{ $activeTab === 'credits' ? 'bg-indigo-500/10 text-indigo-400 border-b-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-white/5' }}">
                        💰 Histórico de Créditos
                    </button>
                    <button wire:click="setTab('matches')"
                        class="flex-1 px-6 py-4 text-sm font-medium transition {{ $activeTab === 'matches' ? 'bg-indigo-500/10 text-indigo-400 border-b-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-white/5' }}">
                        🎮 Jogos
                    </button>
                    <button wire:click="setTab('stats')"
                        class="flex-1 px-6 py-4 text-sm font-medium transition {{ $activeTab === 'stats' ? 'bg-indigo-500/10 text-indigo-400 border-b-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-white/5' }}">
                        📈 Estatísticas
                    </button>
                </div>

                <div class="p-6">
                    @if($activeTab === 'overview')
                        <!-- VISÃO GERAL -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Informações do Usuário</h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between p-3 bg-black/20 rounded-lg">
                                        <span class="text-slate-400">Nome</span>
                                        <span class="text-white font-medium">{{ $this->user->name }}</span>
                                    </div>
                                    <div class="flex justify-between p-3 bg-black/20 rounded-lg">
                                        <span class="text-slate-400">Email</span>
                                        <span class="text-white font-medium">{{ $this->user->email }}</span>
                                    </div>
                                    <div class="flex justify-between p-3 bg-black/20 rounded-lg">
                                        <span class="text-slate-400">Último Acesso</span>
                                        <span class="text-white font-medium">
                                            {{ $this->user->last_seen_at?->diffForHumans() ?? 'Nunca' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between p-3 bg-black/20 rounded-lg">
                                        <span class="text-slate-400">Status</span>
                                        <span class="{{ $this->user->ban_reason ? 'text-red-400' : 'text-green-400' }} font-medium">
                                            {{ $this->user->ban_reason ? 'Banido' : 'Ativo' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Estatísticas de Jogos</h3>
                                <div class="space-y-4">
                                    <div>
                                        <div class="flex justify-between mb-2">
                                            <span class="text-sm text-slate-400">Jogos Jogados</span>
                                            <span class="text-sm text-white font-bold">{{ $this->stats['total_games'] ?? 0 }}</span>
                                        </div>
                                        <div class="w-full bg-gray-700 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full" style="width: 100%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between mb-2">
                                            <span class="text-sm text-slate-400">Vitórias</span>
                                            <span class="text-sm text-white font-bold">{{ $this->stats['total_wins'] ?? 0 }}</span>
                                        </div>
                                        <div class="w-full bg-gray-700 rounded-full h-2">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $this->stats['total_games'] > 0 ? (($this->stats['total_wins'] ?? 0) / $this->stats['total_games']) * 100 : 0 }}%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between mb-2">
                                            <span class="text-sm text-slate-400">Jogos Criados</span>
                                            <span class="text-sm text-white font-bold">{{ $this->stats['games_created'] ?? 0 }}</span>
                                        </div>
                                        <div class="w-full bg-gray-700 rounded-full h-2">
                                            <div class="bg-purple-500 h-2 rounded-full" style="width: {{ $this->stats['total_games'] > 0 ? (($this->stats['games_created'] ?? 0) / max($this->stats['total_games'], 1)) * 100 : 0 }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($activeTab === 'credits')
                        <!-- HISTÓRICO DE CRÉDITOS -->
                        <div class="space-y-4">
                            @forelse($this->creditHistory as $transaction)
                                <div class="p-4 bg-black/20 border border-white/5 rounded-xl">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="px-2 py-1 rounded text-xs font-bold {{ $transaction->type === 'credit' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                                                    {{ ucfirst($transaction->type) }}
                                                </span>
                                                @if($transaction->coupon)
                                                    <span class="px-2 py-1 bg-purple-500/10 text-purple-400 rounded text-xs font-bold">
                                                        {{ $transaction->coupon->code }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-white font-medium">{{ $transaction->description }}</p>
                                            <p class="text-xs text-slate-500 mt-1">{{ $transaction->created_at->format('d/m/Y H:i:s') }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-2xl font-bold {{ $transaction->type === 'credit' ? 'text-emerald-400' : 'text-red-400' }}">
                                                {{ $transaction->type === 'credit' ? '+' : '-' }}{{ number_format($transaction->amount, 0, ',', '.') }}
                                            </p>
                                            @if($transaction->final_amount)
                                                <p class="text-xs text-slate-500">R$ {{ number_format($transaction->final_amount, 2, ',', '.') }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center py-8 text-slate-400">Nenhuma transação encontrada</p>
                            @endforelse
                            
                            <div class="mt-4">
                                {{ $this->creditHistory->links() }}
                            </div>
                        </div>
                    @endif

                    @if($activeTab === 'matches')
                        <!-- JOGOS RECENTES -->
                        <div class="space-y-4">
                            @forelse($this->recentGames as $game)
                                <div class="p-4 bg-black/20 border border-white/5 rounded-xl hover:bg-white/5 transition">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="px-2 py-1 rounded text-xs font-bold bg-blue-500/10 text-blue-400">
                                                    Jogo #{{ $game->id }}
                                                </span>
                                                @if($game->status)
                                                    <span class="px-2 py-1 rounded text-xs font-bold {{ 
                                                        $game->status === 'completed' ? 'bg-green-500/10 text-green-400' : 
                                                        ($game->status === 'active' ? 'bg-yellow-500/10 text-yellow-400' : 
                                                        'bg-gray-500/10 text-gray-400')
                                                    }}">
                                                        {{ ucfirst($game->status) }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-white font-medium">{{ $game->name ?? 'Partida' }}</p>
                                            <div class="flex items-center gap-4 mt-2 text-xs text-slate-400">
                                                <span>👥 {{ $game->players_count ?? 0 }} jogadores</span>
                                                @if($game->creator)
                                                    <span>👤 Criador: {{ $game->creator->name }}</span>
                                                @endif
                                                <span>📅 {{ $game->created_at->format('d/m/Y H:i') }}</span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-slate-500">{{ $game->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-12 text-slate-400">
                                    <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                    </svg>
                                    <p class="text-sm">Nenhum jogo encontrado</p>
                                </div>
                            @endforelse
                        </div>
                    @endif

                    @if($activeTab === 'stats')
                        <!-- ESTATÍSTICAS DETALHADAS -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="p-5 bg-black/20 rounded-xl">
                                <h4 class="text-sm font-bold text-white mb-4">Resumo Financeiro</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Total Comprado</span>
                                        <span class="text-emerald-400 font-bold">R$ {{ number_format($this->stats['total_purchased'] ?? 0, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Total Gasto em Jogos</span>
                                        <span class="text-white font-bold">{{ number_format($this->stats['total_spent'] ?? 0, 0, ',', '.') }} CR</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Saldo Atual</span>
                                        <span class="text-blue-400 font-bold">{{ number_format($this->user->wallet?->balance ?? 0, 0, ',', '.') }} CR</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-5 bg-black/20 rounded-xl">
                                <h4 class="text-sm font-bold text-white mb-4">Resumo de Jogos</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Total de Jogos</span>
                                        <span class="text-white font-bold">{{ $this->stats['total_games'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Vitórias</span>
                                        <span class="text-emerald-400 font-bold">{{ $this->stats['total_wins'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Jogos Criados</span>
                                        <span class="text-purple-400 font-bold">{{ $this->stats['games_created'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Pontos de Ranking</span>
                                        <span class="text-yellow-400 font-bold">{{ $this->stats['rank_points'] ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-12 text-center">
            <p class="text-slate-400">Usuário não encontrado</p>
        </div>
    @endif

    <!-- MODAL DE BANIMENTO -->
    <x-modal name="ban-modal" :show="$showBanModal" max-width="md">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-red-500/20 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Banir Usuário</h3>
                    <p class="text-sm text-slate-400">{{ $this->user?->name }}</p>
                </div>
            </div>

            <form wire:submit="banUser" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">
                        Motivo do Banimento *
                    </label>
                    <textarea wire:model="banReason" rows="4"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500"
                        placeholder="Descreva o motivo do banimento (mínimo 10 caracteres)"></textarea>
                    @error('banReason')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="p-4 bg-red-500/10 border border-red-500/20 rounded-xl">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-red-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div class="text-xs text-red-300">
                            <p class="font-semibold mb-1">Atenção:</p>
                            <ul class="space-y-1 list-disc list-inside">
                                <li>O usuário não poderá acessar a plataforma</li>
                                <li>Todos os jogos ativos serão cancelados</li>
                                <li>Esta ação pode ser revertida posteriormente</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 justify-end">
                    <button type="button" wire:click="closeBanModal"
                        class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-xl text-sm text-white transition-all">
                        Cancelar
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-800 rounded-xl text-sm text-white transition-all flex items-center gap-2">
                        <span wire:loading.remove wire:target="banUser">Confirmar Banimento</span>
                        <span wire:loading wire:target="banUser">Banindo...</span>
                        <svg wire:loading.remove wire:target="banUser" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </x-modal>

    <x-toast position="top-right" />
</div>