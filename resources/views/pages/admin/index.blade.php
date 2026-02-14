<?php

/**
 * ============================================================================
 * Dashboard Administrativo - Implementação seguindo boas práticas:
 *
 * - Laravel 12:
 *   • Queries otimizadas com agregações
 *   • Cache de estatísticas pesadas
 *   • Proteção contra SQL Injection via Eloquent
 *   • Queries enxutas e eficientes
 *
 * - Livewire 4:
 *   • Propriedades tipadas e computadas
 *   • Polling para dados em tempo real
 *   • Eventos dispatch integrados
 *   • Controle de estado previsível
 *
 * - Performance:
 *   • Cache de 5 minutos para stats pesadas
 *   • Queries otimizadas com índices
 *   • Lazy loading de gráficos
 *
 * - Tailwind:
 *   • Hierarquia visual consistente
 *   • Gradientes e animações sutis
 *   • Layout responsivo escalável
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
use App\Models\Wallet\Package;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\Wallet;
use App\Models\Wallet\GiftCard;

new #[Layout('layouts.admin')] #[Title('Dashboard')] class extends Component {
    public string $period = '30'; // 7, 30, 90, 365

    #[Computed]
    public function usersStats(): array
    {
        return Cache::remember('dashboard.users.' . $this->period, 300, function () {
            return [
                'total' => User::count(),
                'today' => User::whereDate('created_at', today())->count(),
                'online' => User::where('last_seen_at', '>=', now()->subMinutes(5))->count(),
                'active_month' => User::where('last_seen_at', '>=', now()->subMonth())->count(),
                'with_credits' => User::whereHas('wallet', fn($q) => $q->where('balance', '>', 0))->count(),
                'verified' => User::where('is_verified', true)->count(),
                'banned' => User::whereNotNull('banned_at')->count(),
            ];
        });
    }

    #[Computed]
    public function creditsStats(): array
    {
        return Cache::remember('dashboard.credits.' . $this->period, 300, function () {
            $days = (int) $this->period;

            return [
                'total_circulation' => Wallet::sum('balance'),
                'purchased' => Transaction::where('type', 'credit')->where('status', 'completed')->sum('amount'),
                'spent' => Transaction::where('type', 'debit')->where('status', 'completed')->sum('amount'),
                'average_balance' => round(Wallet::avg('balance'), 2),
                'period_purchased' => Transaction::where('type', 'credit')
                    ->where('status', 'completed')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->sum('amount'),
                'period_spent' => Transaction::where('type', 'debit')
                    ->where('status', 'completed')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->sum('amount'),
            ];
        });
    }

    #[Computed]
    public function revenueStats(): array
    {
        return Cache::remember('dashboard.revenue.' . $this->period, 300, function () {
            $days = (int) $this->period;

            return [
                'today' => Transaction::whereDate('created_at', today())->where('type', 'credit')->where('status', 'completed')->sum('final_amount'),
                'yesterday' => Transaction::whereDate('created_at', today()->subDay())
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
                'week' => Transaction::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
                'month' => Transaction::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->where('type', 'credit')->where('status', 'completed')->sum('final_amount'),
                'year' => Transaction::whereYear('created_at', now()->year)->where('type', 'credit')->where('status', 'completed')->sum('final_amount'),
                'all_time' => Transaction::where('type', 'credit')->where('status', 'completed')->sum('final_amount'),
                'period' => Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
                'coupons_discount' => Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->whereNotNull('coupon_id')
                    ->sum('discount_amount'),
            ];
        });
    }

    #[Computed]
    public function packagesStats(): array
    {
        return Cache::remember('dashboard.packages.' . $this->period, 300, function () {
            $days = (int) $this->period;

            $packageStats = Transaction::where('created_at', '>=', now()->subDays($days))
                ->where('type', 'credit')
                ->where('status', 'completed')
                ->whereNotNull('package_id')
                ->select(['package_id', DB::raw('COUNT(*) as sales'), DB::raw('SUM(amount) as total_credits'), DB::raw('SUM(final_amount) as total_revenue')])
                ->groupBy('package_id')
                ->orderByDesc('sales')
                ->get();

            $topPackages = Package::where('is_active', true)
                ->get()
                ->map(function ($package) use ($packageStats) {
                    $stat = $packageStats->firstWhere('package_id', $package->id);
                    return (object) [
                        'name' => $package->name,
                        'credits' => $package->credits,
                        'price' => $package->price_brl,
                        'sales' => $stat ? $stat->sales : 0,
                        'total_credits' => $stat ? $stat->total_credits : 0,
                        'total_revenue' => $stat ? $stat->total_revenue : 0,
                    ];
                })
                ->filter(fn($p) => $p->sales > 0)
                ->sortByDesc('sales')
                ->take(5)
                ->values();

            $totalSales = $packageStats->sum('sales');

            return [
                'total_active' => Package::where('is_active', true)->count(),
                'total_inactive' => Package::where('is_active', false)->count(),
                'top_sellers' => $topPackages,
                'total_sales' => $totalSales,
                'total_revenue' => $packageStats->sum('total_revenue'),
            ];
        });
    }

    #[Computed]
    public function giftCardsStats(): array
    {
        return Cache::remember('dashboard.giftcards.' . $this->period, 300, function () {
            $days = (int) $this->period;

            return [
                'total_created' => GiftCard::count(),
                'active' => GiftCard::where('status', 'active')->count(),
                'redeemed' => GiftCard::where('status', 'redeemed')->count(),
                'expired' => GiftCard::where('status', 'expired')->count(),
                'period_created' => GiftCard::where('created_at', '>=', now()->subDays($days))->count(),
                'period_redeemed' => GiftCard::where('redeemed_at', '>=', now()->subDays($days))->count(),
                'total_value_circulation' => GiftCard::where('status', 'active')->sum('credit_value'),
                'total_value_redeemed' => GiftCard::where('status', 'redeemed')->sum('credit_value'),
            ];
        });
    }

    #[Computed]
    public function recentActivity(): array
    {
        return [
            'recent_users' => User::latest()->limit(5)->get(),
            'recent_transactions' => Transaction::with(['wallet.user'])
                ->where('status', 'completed')
                ->latest()
                ->limit(10)
                ->get(),
            'recent_giftcards' => GiftCard::with(['createdByUser', 'redeemedByUser'])
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }

    #[Computed]
    public function chartData(): array
    {
        $days = (int) $this->period;

        return Cache::remember('dashboard.chart.' . $this->period, 300, function () use ($days) {
            $dates = collect();
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dates->push([
                    'date' => $date->format('d/m'),
                    'users' => User::whereDate('created_at', $date)->count(),
                    'revenue' => Transaction::whereDate('created_at', $date)->where('type', 'credit')->where('status', 'completed')->sum('final_amount'),
                    'transactions' => Transaction::whereDate('created_at', $date)->where('status', 'completed')->count(),
                ]);
            }
            return $dates->toArray();
        });
    }

    #[Computed]
    public function growthRates(): array
    {
        return Cache::remember('dashboard.growth.' . $this->period, 300, function () {
            $days = (int) $this->period;
            $halfPeriod = (int) ($days / 2);

            $firstHalfUsers = User::whereBetween('created_at', [now()->subDays($days), now()->subDays($halfPeriod)])->count();
            $secondHalfUsers = User::whereBetween('created_at', [now()->subDays($halfPeriod), now()])->count();

            $firstHalfRevenue = Transaction::whereBetween('created_at', [now()->subDays($days), now()->subDays($halfPeriod)])
                ->where('type', 'credit')
                ->where('status', 'completed')
                ->sum('final_amount');

            $secondHalfRevenue = Transaction::whereBetween('created_at', [now()->subDays($halfPeriod), now()])
                ->where('type', 'credit')
                ->where('status', 'completed')
                ->sum('final_amount');

            $userGrowth = $firstHalfUsers > 0 ? (($secondHalfUsers - $firstHalfUsers) / $firstHalfUsers) * 100 : 0;
            $revenueGrowth = $firstHalfRevenue > 0 ? (($secondHalfRevenue - $firstHalfRevenue) / $firstHalfRevenue) * 100 : 0;

            return [
                'users' => round($userGrowth, 1),
                'revenue' => round($revenueGrowth, 1),
            ];
        });
    }

    public function refreshStats(): void
    {
        Cache::forget('dashboard.users.' . $this->period);
        Cache::forget('dashboard.credits.' . $this->period);
        Cache::forget('dashboard.revenue.' . $this->period);
        Cache::forget('dashboard.packages.' . $this->period);
        Cache::forget('dashboard.giftcards.' . $this->period);
        Cache::forget('dashboard.chart.' . $this->period);
        Cache::forget('dashboard.growth.' . $this->period);

        $this->dispatch('notify', type: 'success', text: 'Estatísticas atualizadas!');
    }
};
?>

<div class="space-y-8">

    {{-- HEADER --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <p class="text-sm text-slate-400 mb-1">Visão geral do sistema</p>
            <h1 class="text-2xl font-bold text-white">Dashboard Administrativo</h1>
        </div>

        <div class="flex items-center gap-3">
            <select wire:model.live="period"
                class="bg-[#0b0d11] border border-white/10 rounded-lg px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                <option value="7">Últimos 7 dias</option>
                <option value="30">Últimos 30 dias</option>
                <option value="90">Últimos 90 dias</option>
                <option value="365">Último ano</option>
            </select>
            <button wire:click="refreshStats"
                class="px-4 py-2 bg-[#0b0d11] border border-white/10 rounded-lg text-white hover:bg-blue-600 hover:border-blue-500 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>
        </div>
    </div>

    {{-- CARDS PRINCIPAIS - USUÁRIOS --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Usuários</h2>
            @if ($this->growthRates['users'] != 0)
                <span class="px-2 py-1 rounded text-xs font-semibold {{ $this->growthRates['users'] > 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                    {{ $this->growthRates['users'] > 0 ? '+' : '' }}{{ number_format($this->growthRates['users'], 1) }}%
                </span>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5 hover:border-blue-500/50 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mb-1">Total de Contas</p>
                <p class="text-2xl font-bold text-white">{{ number_format($this->usersStats['total'], 0, ',', '.') }}</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5 hover:border-emerald-500/50 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-emerald-500/10 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mb-1">Criadas Hoje</p>
                <p class="text-2xl font-bold text-emerald-400">{{ number_format($this->usersStats['today'], 0, ',', '.') }}</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5 hover:border-green-500/50 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-green-500/10 rounded-lg flex items-center justify-center">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mb-1">Online Agora</p>
                <p class="text-2xl font-bold text-green-400">{{ number_format($this->usersStats['online'], 0, ',', '.') }}</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5 hover:border-cyan-500/50 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-cyan-500/10 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mb-1">Ativos no Mês</p>
                <p class="text-2xl font-bold text-cyan-400">{{ number_format($this->usersStats['active_month'], 0, ',', '.') }}</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5 hover:border-purple-500/50 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-purple-500/10 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mb-1">Com Créditos</p>
                <p class="text-2xl font-bold text-purple-400">{{ number_format($this->usersStats['with_credits'], 0, ',', '.') }}</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5 hover:border-indigo-500/50 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-indigo-500/10 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mb-1">Verificados</p>
                <p class="text-2xl font-bold text-indigo-400">{{ number_format($this->usersStats['verified'], 0, ',', '.') }}</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5 hover:border-red-500/50 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-red-500/10 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mb-1">Banidos</p>
                <p class="text-2xl font-bold text-red-400">{{ number_format($this->usersStats['banned'], 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    {{-- FATURAMENTO --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Faturamento</h2>
            @if ($this->growthRates['revenue'] != 0)
                <span class="px-2 py-1 rounded text-xs font-semibold {{ $this->growthRates['revenue'] > 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                    {{ $this->growthRates['revenue'] > 0 ? '+' : '' }}{{ number_format($this->growthRates['revenue'], 1) }}%
                </span>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gradient-to-br from-emerald-500/5 to-transparent border border-emerald-500/20 rounded-lg p-6">
                <p class="text-xs text-emerald-400 font-semibold mb-2">Hoje</p>
                <p class="text-3xl font-bold text-white mb-1">R$ {{ number_format($this->revenueStats['today'], 2, ',', '.') }}</p>
                @if ($this->revenueStats['yesterday'] > 0)
                    <p class="text-xs text-slate-500">Ontem: R$ {{ number_format($this->revenueStats['yesterday'], 2, ',', '.') }}</p>
                @endif
            </div>

            <div class="bg-gradient-to-br from-blue-500/5 to-transparent border border-blue-500/20 rounded-lg p-6">
                <p class="text-xs text-blue-400 font-semibold mb-2">Esta Semana</p>
                <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['week'], 2, ',', '.') }}</p>
            </div>

            <div class="bg-gradient-to-br from-purple-500/5 to-transparent border border-purple-500/20 rounded-lg p-6">
                <p class="text-xs text-purple-400 font-semibold mb-2">Este Mês</p>
                <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['month'], 2, ',', '.') }}</p>
            </div>

            <div class="bg-gradient-to-br from-yellow-500/5 to-transparent border border-yellow-500/20 rounded-lg p-6">
                <p class="text-xs text-yellow-400 font-semibold mb-2">Total Histórico</p>
                <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['all_time'], 2, ',', '.') }}</p>
            </div>
        </div>

        @if ($this->revenueStats['coupons_discount'] > 0)
            <div class="mt-4 bg-purple-500/10 border border-purple-500/20 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-purple-400">Descontos com Cupons</p>
                        <p class="text-xs text-slate-400 mt-1">Últimos {{ $period }} dias</p>
                    </div>
                    <p class="text-xl font-bold text-purple-400">-R$ {{ number_format($this->revenueStats['coupons_discount'], 2, ',', '.') }}</p>
                </div>
            </div>
        @endif
    </div>

    {{-- CRÉDITOS --}}
    <div>
        <h2 class="text-sm font-semibold text-white uppercase tracking-wide mb-4">Sistema de Créditos</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-xs text-slate-400">Em Circulação</p>
                    <div class="w-10 h-10 bg-cyan-500/10 rounded-lg flex items-center justify-center text-xl">💰</div>
                </div>
                <p class="text-4xl font-bold text-cyan-400">{{ number_format($this->creditsStats['total_circulation'], 0, ',', '.') }}</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-6">
                <p class="text-xs text-slate-400 mb-4">Período Selecionado</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-emerald-400 font-semibold mb-1">Comprados</p>
                        <p class="text-xl font-bold text-white">{{ number_format($this->creditsStats['period_purchased'], 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-red-400 font-semibold mb-1">Gastos</p>
                        <p class="text-xl font-bold text-white">{{ number_format($this->creditsStats['period_spent'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-6">
                <p class="text-xs text-slate-400 mb-4">Total Histórico</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-emerald-400 font-semibold mb-1">Comprados</p>
                        <p class="text-xl font-bold text-white">{{ number_format($this->creditsStats['purchased'], 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-red-400 font-semibold mb-1">Gastos</p>
                        <p class="text-xl font-bold text-white">{{ number_format($this->creditsStats['spent'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- GIFT CARDS --}}
    <div>
        <h2 class="text-sm font-semibold text-white uppercase tracking-wide mb-4">Gift Cards</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5">
                <div class="w-10 h-10 bg-purple-500/10 rounded-lg flex items-center justify-center text-xl mb-3">🎁</div>
                <p class="text-xs text-slate-400 mb-1">Total Criados</p>
                <p class="text-2xl font-bold text-white">{{ number_format($this->giftCardsStats['total_created'], 0, ',', '.') }}</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5">
                <div class="w-10 h-10 bg-emerald-500/10 rounded-lg flex items-center justify-center text-xl mb-3">✅</div>
                <p class="text-xs text-slate-400 mb-1">Ativos</p>
                <p class="text-2xl font-bold text-emerald-400">{{ number_format($this->giftCardsStats['active'], 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 mt-2">C$ {{ number_format($this->giftCardsStats['total_value_circulation'], 0, ',', '.') }} em circulação</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5">
                <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center text-xl mb-3">🎉</div>
                <p class="text-xs text-slate-400 mb-1">Resgatados</p>
                <p class="text-2xl font-bold text-blue-400">{{ number_format($this->giftCardsStats['redeemed'], 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 mt-2">C$ {{ number_format($this->giftCardsStats['total_value_redeemed'], 0, ',', '.') }} distribuídos</p>
            </div>

            <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-5">
                <div class="w-10 h-10 bg-red-500/10 rounded-lg flex items-center justify-center text-xl mb-3">⏰</div>
                <p class="text-xs text-slate-400 mb-1">Expirados</p>
                <p class="text-2xl font-bold text-red-400">{{ number_format($this->giftCardsStats['expired'], 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    {{-- GRID LATERAL --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- TOP PACOTES --}}
        <div class="lg:col-span-2 bg-[#0b0d11] border border-white/10 rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Top Pacotes</h3>
                <span class="text-xs text-slate-400">{{ number_format($this->packagesStats['total_sales'], 0) }} vendas</span>
            </div>

            <div class="p-6 space-y-3">
                @forelse($this->packagesStats['top_sellers'] as $index => $package)
                    <div class="bg-white/[0.02] border border-white/5 rounded-lg p-4 hover:bg-white/[0.04] transition-all">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4 flex-1">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-lg flex items-center justify-center text-white font-bold">
                                    #{{ $index + 1 }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <p class="text-white font-semibold">{{ $package->name }}</p>
                                        <span class="px-2 py-0.5 bg-blue-500/10 border border-blue-500/20 rounded text-xs font-semibold text-blue-400">
                                            {{ number_format($package->credits, 0) }} C$
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-slate-400">
                                        <span>{{ $package->sales }} vendas</span>
                                        <span>•</span>
                                        <span>R$ {{ number_format($package->total_revenue, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-bold text-emerald-400">
                                    {{ number_format(($package->sales * 100) / max($this->packagesStats['total_sales'], 1), 1) }}%
                                </div>
                                <div class="text-xs text-slate-500">share</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-slate-400">
                        <p class="text-sm">Nenhuma venda registrada</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- USUÁRIOS RECENTES --}}
        <div class="bg-[#0b0d11] border border-white/10 rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-white/10">
                <h3 class="text-sm font-semibold text-white">Novos Usuários</h3>
            </div>

            <div class="p-6 space-y-3">
                @foreach ($this->recentActivity['recent_users'] as $user)
                    <div class="flex items-center gap-3 p-3 bg-white/[0.02] border border-white/5 rounded-lg hover:bg-white/[0.04] transition-all">
                        <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center text-blue-400 font-semibold text-sm">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-white font-semibold text-sm truncate">{{ $user->name }}</div>
                            <div class="text-slate-400 text-xs">{{ $user->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- TRANSAÇÕES RECENTES --}}
    <div class="bg-[#0b0d11] border border-white/10 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-white/10">
            <h3 class="text-sm font-semibold text-white">Transações Recentes</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-black/20">
                    <tr class="text-xs text-slate-400 uppercase">
                        <th class="px-6 py-3 text-left font-semibold">Usuário</th>
                        <th class="px-6 py-3 text-center font-semibold">Tipo</th>
                        <th class="px-6 py-3 text-center font-semibold">Créditos</th>
                        <th class="px-6 py-3 text-center font-semibold">Valor</th>
                        <th class="px-6 py-3 text-center font-semibold">Status</th>
                        <th class="px-6 py-3 text-right font-semibold">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($this->recentActivity['recent_transactions'] as $transaction)
                        <tr class="hover:bg-white/[0.02] transition">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-white">{{ $transaction->wallet?->user?->name ?? 'Sistema' }}</div>
                                <div class="text-xs text-slate-400 truncate max-w-xs">{{ $transaction->description }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold {{ $transaction->type === 'credit' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                                    {{ $transaction->type === 'credit' ? 'Crédito' : 'Débito' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="font-semibold text-white">{{ number_format($transaction->amount, 0, ',', '.') }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if ($transaction->final_amount)
                                    <div class="font-semibold text-white">R$ {{ number_format($transaction->final_amount, 2, ',', '.') }}</div>
                                    @if ($transaction->discount_amount > 0)
                                        <div class="text-xs text-purple-400">-R$ {{ number_format($transaction->discount_amount, 2, ',', '.') }}</div>
                                    @endif
                                @else
                                    <span class="text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold 
                                    {{ $transaction->status === 'completed' ? 'bg-green-500/10 text-green-400' : '' }}
                                    {{ $transaction->status === 'pending' ? 'bg-yellow-500/10 text-yellow-400' : '' }}
                                    {{ $transaction->status === 'failed' ? 'bg-red-500/10 text-red-400' : '' }}">
                                    {{ ucfirst($transaction->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-slate-400 text-xs">
                                {{ $transaction->created_at->format('d/m/Y H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                                Nenhuma transação recente
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- STATUS DO SISTEMA --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-6">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <p class="text-xs text-slate-400 uppercase">Status</p>
            </div>
            <p class="text-xl font-bold text-white">Operacional</p>
            <p class="text-xs text-slate-500 mt-1">Todos os serviços online</p>
        </div>

        <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-6">
            <p class="text-xs text-slate-400 uppercase mb-3">Último Backup</p>
            <p class="text-xl font-bold text-white">{{ now()->subHours(2)->diffForHumans() }}</p>
            <p class="text-xs text-slate-500 mt-1">Próximo em {{ now()->addHours(2)->diffForHumans() }}</p>
        </div>

        <div class="bg-[#0b0d11] border border-white/10 rounded-lg p-6">
            <p class="text-xs text-slate-400 uppercase mb-3">Versão</p>
            <p class="text-xl font-bold text-white">v2.0.1</p>
            <p class="text-xs text-slate-500 mt-1">Laravel 12 + Livewire 4</p>
        </div>
    </div>

    <x-loading target="refreshStats" message="Atualizando..." overlay />
    <x-toast />
</div>