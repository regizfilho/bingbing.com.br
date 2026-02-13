<?php

/**
 * ============================================================================
 * Analytics de Usuários
 * 
 * - Laravel 12:
 *   • Queries otimizadas com agregações
 *   • Cache estratégico
 * 
 * - Livewire 4:
 *   • Computed properties
 *   • Real-time updates
 * 
 * - Features:
 *   • Crescimento de usuários
 *   • Retenção e churn
 *   • Padrões de uso
 *   • Segmentação demográfica
 *   • Análise de comportamento
 * ============================================================================
 */

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\Wallet\Transaction;

new #[Layout('layouts.admin')] #[Title('Analytics de Usuários')] class extends Component {

    public string $period = '30'; // 7, 30, 90, 365

    #[Computed]
    public function growthData(): array
    {
        return Cache::remember('analytics.growth.' . $this->period, 300, function() {
            $days = (int) $this->period;
            $data = collect();

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $data->push([
                    'date' => $date->format('d/m'),
                    'new_users' => User::whereDate('created_at', $date)->count(),
                    'active_users' => User::whereDate('last_seen_at', $date)->count(),
                ]);
            }

            return $data->toArray();
        });
    }

    #[Computed]
    public function retentionMetrics(): array
    {
        return Cache::remember('analytics.retention.' . $this->period, 300, function() {
            $days = (int) $this->period;
            
            $newUsers = User::where('created_at', '>=', now()->subDays($days))->count();
            $returnedUsers = User::where('created_at', '>=', now()->subDays($days))
                ->where('last_seen_at', '>=', now()->subDays(7))
                ->count();

            return [
                'new_users' => $newUsers,
                'returned_users' => $returnedUsers,
                'retention_rate' => $newUsers > 0 ? round(($returnedUsers / $newUsers) * 100, 1) : 0,
                'churn_rate' => $newUsers > 0 ? round((($newUsers - $returnedUsers) / $newUsers) * 100, 1) : 0,
            ];
        });
    }

    #[Computed]
    public function userSegmentation(): array
    {
        return Cache::remember('analytics.segmentation.' . $this->period, 300, function() {
            $days = (int) $this->period;

            return [
                'with_credits' => User::whereHas('wallet', fn($q) => $q->where('balance', '>', 0))->count(),
                'no_credits' => User::whereDoesntHave('wallet')
                    ->orWhereHas('wallet', fn($q) => $q->where('balance', '<=', 0))
                    ->count(),
                'active_7d' => User::where('last_seen_at', '>=', now()->subDays(7))->count(),
                'inactive_30d' => User::where('last_seen_at', '<', now()->subDays(30))
                    ->orWhereNull('last_seen_at')
                    ->count(),
                'with_purchases' => User::whereHas('wallet.transactions', function($q) {
                    $q->where('type', 'credit')->where('status', 'completed');
                })->count(),
            ];
        });
    }

    #[Computed]
    public function spendingBehavior(): array
    {
        return Cache::remember('analytics.spending.' . $this->period, 300, function() {
            $days = (int) $this->period;

            $transactions = Transaction::where('created_at', '>=', now()->subDays($days))
                ->where('type', 'credit')
                ->where('status', 'completed')
                ->get();

            $total = $transactions->sum('final_amount');
            $count = $transactions->count();
            $uniqueUsers = $transactions->pluck('wallet.user_id')->unique()->count();

            return [
                'total_revenue' => $total,
                'total_transactions' => $count,
                'unique_buyers' => $uniqueUsers,
                'avg_transaction' => $count > 0 ? $total / $count : 0,
                'avg_per_user' => $uniqueUsers > 0 ? $total / $uniqueUsers : 0,
            ];
        });
    }

    #[Computed]
    public function topSpenders()
    {
        return User::select('users.*')
            ->join('wallets', 'wallets.user_id', '=', 'users.id')
            ->join('transactions', 'transactions.wallet_id', '=', 'wallets.id')
            ->where('transactions.type', 'credit')
            ->where('transactions.status', 'completed')
            ->where('transactions.created_at', '>=', now()->subDays((int) $this->period))
            ->groupBy('users.id')
            ->orderByRaw('SUM(transactions.final_amount) DESC')
            ->limit(10)
            ->get()
            ->map(function($user) {
                $spent = Transaction::whereHas('wallet', fn($q) => $q->where('user_id', $user->id))
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->where('created_at', '>=', now()->subDays((int) $this->period))
                    ->sum('final_amount');

                return [
                    'user' => $user,
                    'total_spent' => $spent,
                ];
            });
    }

    #[Computed]
    public function engagementMetrics(): array
    {
        return Cache::remember('analytics.engagement.' . $this->period, 300, function() {
            $days = (int) $this->period;

            return [
                'daily_active' => User::where('last_seen_at', '>=', now()->subDay())->count(),
                'weekly_active' => User::where('last_seen_at', '>=', now()->subWeek())->count(),
                'monthly_active' => User::where('last_seen_at', '>=', now()->subMonth())->count(),
                'total_sessions' => User::where('last_seen_at', '>=', now()->subDays($days))->count(),
            ];
        });
    }

    public function render(): View
    {
        return view('pages.admin.users.anaytics');
    }
};
?>

<div>
    <x-slot name="header">
        Analytics de Usuários
    </x-slot>

    <div class="space-y-6">
        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                    📊 Analytics Avançado de Usuários
                </h2>
                <p class="text-slate-400 text-sm mt-1">Análise detalhada de comportamento e métricas</p>
            </div>
            <div class="flex gap-3">
                <select wire:model.live="period"
                    class="bg-[#111827] border border-white/10 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="7">Últimos 7 dias</option>
                    <option value="30">Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                    <option value="365">Último ano</option>
                </select>
                <a href="{{ route('admin.users.home') }}" wire:navigate
                    class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-xl text-sm text-white transition-all">
                    ← Voltar
                </a>
            </div>
        </div>

        <!-- MÉTRICAS DE RETENÇÃO -->
        <div>
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">📈 Retenção e Churn</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-[#0f172a] border border-blue-500/20 rounded-2xl p-5">
                    <p class="text-blue-400 text-xs uppercase tracking-wider font-medium mb-2">Novos Usuários</p>
                    <p class="text-3xl font-bold text-white">{{ number_format($this->retentionMetrics['new_users'], 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 mt-1">no período</p>
                </div>
                <div class="bg-[#0f172a] border border-emerald-500/20 rounded-2xl p-5">
                    <p class="text-emerald-400 text-xs uppercase tracking-wider font-medium mb-2">Retornaram</p>
                    <p class="text-3xl font-bold text-white">{{ number_format($this->retentionMetrics['returned_users'], 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 mt-1">usuários ativos</p>
                </div>
                <div class="bg-[#0f172a] border border-green-500/20 rounded-2xl p-5">
                    <p class="text-green-400 text-xs uppercase tracking-wider font-medium mb-2">Taxa de Retenção</p>
                    <p class="text-3xl font-bold text-white">{{ $this->retentionMetrics['retention_rate'] }}%</p>
                    <p class="text-xs text-slate-500 mt-1">dos novos usuários</p>
                </div>
                <div class="bg-[#0f172a] border border-red-500/20 rounded-2xl p-5">
                    <p class="text-red-400 text-xs uppercase tracking-wider font-medium mb-2">Taxa de Churn</p>
                    <p class="text-3xl font-bold text-white">{{ $this->retentionMetrics['churn_rate'] }}%</p>
                    <p class="text-xs text-slate-500 mt-1">abandonaram</p>
                </div>
            </div>
        </div>

        <!-- SEGMENTAÇÃO DE USUÁRIOS -->
        <div>
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">👥 Segmentação de Usuários</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Com Créditos</p>
                    <p class="text-2xl font-bold text-emerald-400">{{ number_format($this->userSegmentation['with_credits'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Sem Créditos</p>
                    <p class="text-2xl font-bold text-slate-400">{{ number_format($this->userSegmentation['no_credits'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Ativos (7d)</p>
                    <p class="text-2xl font-bold text-green-400">{{ number_format($this->userSegmentation['active_7d'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Inativos (30d)</p>
                    <p class="text-2xl font-bold text-red-400">{{ number_format($this->userSegmentation['inactive_30d'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Com Compras</p>
                    <p class="text-2xl font-bold text-purple-400">{{ number_format($this->userSegmentation['with_purchases'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- COMPORTAMENTO DE GASTOS -->
        <div>
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">💰 Comportamento de Compras</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-gradient-to-br from-emerald-500/10 to-green-500/5 border border-emerald-500/20 rounded-2xl p-5">
                    <p class="text-emerald-400 text-xs uppercase tracking-wider font-bold mb-2">Receita Total</p>
                    <p class="text-2xl font-bold text-white">R$ {{ number_format($this->spendingBehavior['total_revenue'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Transações</p>
                    <p class="text-2xl font-bold text-white">{{ number_format($this->spendingBehavior['total_transactions'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Compradores</p>
                    <p class="text-2xl font-bold text-blue-400">{{ number_format($this->spendingBehavior['unique_buyers'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Ticket Médio</p>
                    <p class="text-2xl font-bold text-purple-400">R$ {{ number_format($this->spendingBehavior['avg_transaction'], 2, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Média/Usuário</p>
                    <p class="text-2xl font-bold text-yellow-400">R$ {{ number_format($this->spendingBehavior['avg_per_user'], 2, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- ENGAJAMENTO -->
        <div>
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">🎯 Métricas de Engajamento</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">DAU (Diário)</p>
                    <p class="text-3xl font-bold text-white">{{ number_format($this->engagementMetrics['daily_active'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">WAU (Semanal)</p>
                    <p class="text-3xl font-bold text-white">{{ number_format($this->engagementMetrics['weekly_active'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">MAU (Mensal)</p>
                    <p class="text-3xl font-bold text-white">{{ number_format($this->engagementMetrics['monthly_active'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Total Sessões</p>
                    <p class="text-3xl font-bold text-white">{{ number_format($this->engagementMetrics['total_sessions'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- TOP GASTADORES -->
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">🏆 Top 10 Gastadores</h3>
            <div class="space-y-3">
                @forelse($this->topSpenders as $index => $item)
                    <div class="flex items-center justify-between p-4 bg-black/20 border border-white/5 rounded-xl hover:bg-white/5 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-xl flex items-center justify-center text-white font-bold">
                                #{{ $index + 1 }}
                            </div>
                            <div>
                                <div class="text-white font-bold">{{ $item['user']->name }}</div>
                                <div class="text-slate-400 text-xs">{{ $item['user']->email }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-emerald-400 font-bold text-xl">
                                R$ {{ number_format($item['total_spent'], 2, ',', '.') }}
                            </div>
                            <div class="text-xs text-slate-500">no período</div>
                        </div>
                    </div>
                @empty
                    <p class="text-center py-8 text-slate-400">Nenhuma compra no período</p>
                @endforelse
            </div>
        </div>
    </div>

    <x-toast position="top-right" />
</div>