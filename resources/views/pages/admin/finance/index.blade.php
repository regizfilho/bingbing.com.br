<?php

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Wallet\Package;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\Wallet;
use App\Models\Wallet\Refund;
use App\Models\Wallet\GiftCard;
use App\Models\Coupon;
use App\Models\CouponUser;

new #[Layout('layouts.admin')] #[Title('Dashboard Financeiro')] class extends Component {
    public string $period = '30';

    #[Computed]
    public function revenueStats(): array
    {
        return Cache::remember("finance.revenue.{$this->period}", 300, function () {
            $days = (int) $this->period;

            $today = Transaction::whereDate('created_at', today())->where('type', 'credit')->where('status', 'completed')->sum('final_amount');

            $yesterday = Transaction::whereDate('created_at', today()->subDay())
                ->where('type', 'credit')
                ->where('status', 'completed')
                ->sum('final_amount');

            $periodRevenue = Transaction::where('created_at', '>=', now()->subDays($days))
                ->where('type', 'credit')
                ->where('status', 'completed')
                ->sum('final_amount');

            $previousPeriodRevenue = Transaction::where('created_at', '>=', now()->subDays($days * 2))
                ->where('created_at', '<', now()->subDays($days))
                ->where('type', 'credit')
                ->where('status', 'completed')
                ->sum('final_amount');

            return [
                'today' => (float) $today,
                'yesterday' => (float) $yesterday,
                'growth_today' => $yesterday > 0 ? (($today - $yesterday) / $yesterday) * 100 : 0,
                'week' => (float) Transaction::where('created_at', '>=', now()->subDays(7))
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
                'month' => (float) Transaction::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->where('type', 'credit')->where('status', 'completed')->sum('final_amount'),
                'year' => (float) Transaction::whereYear('created_at', now()->year)->where('type', 'credit')->where('status', 'completed')->sum('final_amount'),
                'period' => (float) $periodRevenue,
                'previous_period' => (float) $previousPeriodRevenue,
            ];
        });
    }

    #[Computed]
    public function creditsFlow(): array
    {
        return Cache::remember("finance.credits.{$this->period}", 300, function () {
            $days = (int) $this->period;

            return [
                'circulation' => (float) Wallet::sum('balance'),
                'purchased' => (float) Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('amount'),
                'spent' => (float) Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('type', 'debit')
                    ->where('status', 'completed')
                    ->sum('amount'),
                'average_transaction' =>
                    (float) (Transaction::where('created_at', '>=', now()->subDays($days))
                        ->where('status', 'completed')
                        ->avg('amount') ?? 0),
                'total_transactions' => Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('status', 'completed')
                    ->count(),
                'unique_buyers' => Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->distinct('wallet_id')
                    ->count('wallet_id'),
            ];
        });
    }

    #[Computed]
    public function packagesPerformance(): array
    {
        return Cache::remember("finance.packages.{$this->period}", 300, function () {
            $days = (int) $this->period;

            $packages = Package::where('is_active', true)
                ->get()
                ->map(function ($package) use ($days) {
                    $transactions = Transaction::where('package_id', $package->id)
                        ->where('created_at', '>=', now()->subDays($days))
                        ->where('type', 'credit')
                        ->where('status', 'completed');

                    $sales = $transactions->count();
                    $revenue = $transactions->sum('final_amount');
                    $credits = $transactions->sum('amount');

                    return [
                        'id' => $package->id,
                        'name' => $package->name,
                        'price' => $package->price_brl,
                        'credits' => $package->credits,
                        'sales' => $sales,
                        'revenue' => $revenue,
                        'credits_sold' => $credits,
                        'percentage' => 0,
                    ];
                })
                ->filter(fn($package) => $package['sales'] > 0)
                ->sortByDesc('revenue')
                ->values();

            $totalRevenue = $packages->sum('revenue');
            $totalSales = $packages->sum('sales');

            $packages = $packages->map(function ($package) use ($totalRevenue) {
                $package['percentage'] = $totalRevenue > 0 ? ($package['revenue'] / $totalRevenue) * 100 : 0;
                return $package;
            });

            return [
                'packages' => $packages->take(10),
                'total_revenue' => $totalRevenue,
                'total_sales' => $totalSales,
                'average_ticket' => $totalSales > 0 ? $totalRevenue / $totalSales : 0,
                'best_seller' => $packages->first(),
            ];
        });
    }

    #[Computed]
    public function couponsImpact(): array
    {
        return Cache::remember("finance.coupons.{$this->period}", 300, function () {
            $days = (int) $this->period;

            $couponUsers = CouponUser::where('used_at', '>=', now()->subDays($days))->get();

            $topCoupons = Coupon::withCount([
                'users' => function ($q) use ($days) {
                    $q->where('used_at', '>=', now()->subDays($days));
                },
            ])
                ->with([
                    'users' => function ($q) use ($days) {
                        $q->where('used_at', '>=', now()->subDays($days));
                    },
                ])
                ->get()
                ->filter(fn($coupon) => $coupon->users_count > 0)
                ->map(function ($coupon) {
                    return [
                        'code' => $coupon->code,
                        'uses' => $coupon->users_count,
                        'discount' => $coupon->users->sum('discount_amount'),
                        'revenue' => $coupon->users->sum('order_value'),
                        'type' => $coupon->type,
                        'value' => $coupon->value,
                    ];
                })
                ->sortByDesc('uses')
                ->take(5);

            return [
                'total_uses' => $couponUsers->count(),
                'total_discount' => $couponUsers->sum('discount_amount'),
                'total_revenue_with_coupon' => $couponUsers->sum('order_value'),
                'unique_users' => $couponUsers->unique('user_id')->count(),
                'average_discount' => $couponUsers->count() > 0 ? $couponUsers->avg('discount_amount') : 0,
                'discount_rate' => $couponUsers->sum('order_value') > 0 ? ($couponUsers->sum('discount_amount') / $couponUsers->sum('order_value')) * 100 : 0,
                'top_coupons' => $topCoupons,
            ];
        });
    }

    #[Computed]
    public function giftCardsStats(): array
    {
        return Cache::remember("finance.giftcards.{$this->period}", 300, function () {
            $days = (int) $this->period;

            return [
                'total' => GiftCard::count(),
                'active' => GiftCard::active()->count(),
                'redeemed' => GiftCard::where('status', 'redeemed')
                    ->where('redeemed_at', '>=', now()->subDays($days))
                    ->count(),
                'expired' => GiftCard::where('status', 'expired')->count(),
                'revenue_from_sales' => GiftCard::where('source', 'purchase')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->sum('price_brl'),
                'credits_redeemed' => GiftCard::where('status', 'redeemed')
                    ->where('redeemed_at', '>=', now()->subDays($days))
                    ->sum('credit_value'),
                'active_value' => GiftCard::active()->sum('credit_value'),
                'purchased_period' => GiftCard::where('source', 'purchase')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->count(),
            ];
        });
    }

    #[Computed]
    public function refundsStats(): array
    {
        return Cache::remember("finance.refunds.{$this->period}", 300, function () {
            $days = (int) $this->period;

            $approved = Refund::where('status', 'approved')
                ->where('created_at', '>=', now()->subDays($days))
                ->count();

            $rejected = Refund::where('status', 'rejected')
                ->where('created_at', '>=', now()->subDays($days))
                ->count();

            $totalRequests = $approved + $rejected;
            $refundRate = $totalRequests > 0 ? ($approved / $totalRequests) * 100 : 0;

            return [
                'pending' => Refund::where('status', 'pending')->count(),
                'approved' => $approved,
                'rejected' => $rejected,
                'total_refunded' => Refund::where('status', 'approved')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->sum('amount_brl'),
                'refund_rate' => $refundRate,
                'total_requests' => $totalRequests,
            ];
        });
    }

    #[Computed]
    public function revenueChart(): array
    {
        return Cache::remember("finance.chart.{$this->period}", 300, function () {
            $days = min((int) $this->period, 90);

            $data = collect();
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $revenue = Transaction::whereDate('created_at', $date)->where('type', 'credit')->where('status', 'completed')->sum('final_amount');

                $transactions = Transaction::whereDate('created_at', $date)->where('status', 'completed')->count();

                $data->push([
                    'date' => $date->format('d/m'),
                    'full_date' => $date->format('Y-m-d'),
                    'revenue' => (float) $revenue,
                    'transactions' => $transactions,
                    'average' => $transactions > 0 ? $revenue / $transactions : 0,
                ]);
            }

            return [
                'data' => $data->toArray(),
                'max_revenue' => $data->max('revenue') ?? 0,
                'min_revenue' => $data->min('revenue') ?? 0,
                'total_revenue' => $data->sum('revenue'),
                'average_daily' => $data->avg('revenue') ?? 0,
                'best_day' => $data->sortByDesc('revenue')->first(),
            ];
        });
    }

    #[Computed]
    public function topTransactions()
    {
        return Transaction::with(['wallet.user', 'coupon', 'package', 'giftCard'])
            ->where('type', 'credit')
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays((int) $this->period))
            ->orderByDesc('final_amount')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function kpis(): array
    {
        $revenue = $this->revenueStats;
        $credits = $this->creditsFlow;
        $packages = $this->packagesPerformance;

        $periodGrowth = isset($revenue['previous_period']) && $revenue['previous_period'] > 0 ? (($revenue['period'] - $revenue['previous_period']) / $revenue['previous_period']) * 100 : 0;

        $uniqueBuyers = $credits['unique_buyers'] ?? 0;
        $totalTransactions = $credits['total_transactions'] ?? 0;

        $conversionRate = $uniqueBuyers > 0 && $totalTransactions > 0 ? ($uniqueBuyers / $totalTransactions) * 100 : 0;

        // Taxa de retenção (compradores que compraram mais de uma vez)
        $repeatBuyers = Transaction::where('created_at', '>=', now()->subDays((int) $this->period))
            ->where('type', 'credit')
            ->where('status', 'completed')
            ->select('wallet_id', DB::raw('COUNT(*) as purchases'))
            ->groupBy('wallet_id')
            ->having('purchases', '>', 1)
            ->count();

        $retentionRate = $uniqueBuyers > 0 ? ($repeatBuyers / $uniqueBuyers) * 100 : 0;

        return [
            'period_growth' => $periodGrowth,
            'conversion_rate' => $conversionRate,
            'average_ticket' => $packages['average_ticket'] ?? 0,
            'total_customers' => $uniqueBuyers,
            'retention_rate' => $retentionRate,
            'repeat_buyers' => $repeatBuyers,
        ];
    }

    public function refreshStats(): void
    {
        $keys = ["finance.revenue.{$this->period}", "finance.credits.{$this->period}", "finance.packages.{$this->period}", "finance.coupons.{$this->period}", "finance.giftcards.{$this->period}", "finance.refunds.{$this->period}", "finance.chart.{$this->period}"];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        $this->dispatch('notify', type: 'success', text: 'Estatísticas atualizadas!');
    }

    public function exportData(): void
    {
        try {
            $data = [
                'period' => $this->period,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'revenue' => $this->revenueStats,
                'credits' => $this->creditsFlow,
                'packages' => $this->packagesPerformance,
                'coupons' => $this->couponsImpact,
                'gift_cards' => $this->giftCardsStats,
                'refunds' => $this->refundsStats,
                'kpis' => $this->kpis,
            ];

            // Criar CSV
            $filename = 'financial_report_' . now()->format('Y-m-d_His') . '.json';
            $path = storage_path('app/public/exports/' . $filename);

            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

            $this->dispatch('notify', type: 'success', text: 'Relatório exportado com sucesso!');

        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao exportar: ' . $e->getMessage());
        }
    }
};
?>

<div class="p-6 min-h-screen">
    <x-loading target="refreshStats, exportData" message="PROCESSANDO..." overlay />

    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                💰 Dashboard Financeiro
                <span
                    class="text-sm px-3 py-1 bg-emerald-500/10 text-emerald-400 rounded-lg border border-emerald-500/20 font-normal">
                    ● Tempo Real
                </span>
            </h1>
            <p class="text-slate-400 text-sm mt-1">
                Visão completa de receitas, métricas e performance •
                Última atualização: {{ now()->format('H:i:s') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <select wire:model.live="period"
                class="bg-[#111827] border border-white/10 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="7">Últimos 7 dias</option>
                <option value="30">Últimos 30 dias</option>
                <option value="90">Últimos 90 dias</option>
                <option value="365">Último ano</option>
            </select>
            <button wire:click="refreshStats"
                class="px-4 py-2 bg-gray-800 border border-white/10 rounded-xl text-sm text-white hover:bg-gray-700 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Atualizar
            </button>
            <button wire:click="exportData"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 border border-indigo-500/30 rounded-xl text-sm text-white transition-all font-bold flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Exportar
            </button>
        </div>
    </div>

    {{-- KPIs PRINCIPAIS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        {{-- Crescimento --}}
        <div
            class="bg-gradient-to-br from-emerald-500/10 to-green-500/5 border border-emerald-500/20 rounded-2xl p-5 hover:border-emerald-500/40 transition-all">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-emerald-500/20 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <span
                    class="text-xs px-2 py-1 rounded-full font-bold {{ $this->kpis['period_growth'] >= 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                    {{ $this->kpis['period_growth'] >= 0 ? '↑' : '↓' }}
                    {{ number_format(abs($this->kpis['period_growth']), 1) }}%
                </span>
            </div>
            <p class="text-slate-400 text-xs uppercase tracking-wider font-bold mb-1">Crescimento</p>
            <p class="text-2xl font-black text-white">
                R$ {{ number_format($this->revenueStats['period'], 0, ',', '.') }}
            </p>
        </div>

        {{-- Ticket Médio --}}
        <div
            class="bg-gradient-to-br from-blue-500/10 to-cyan-500/5 border border-blue-500/20 rounded-2xl p-5 hover:border-blue-500/40 transition-all">
            <div class="w-10 h-10 bg-blue-500/20 rounded-xl flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-slate-400 text-xs uppercase tracking-wider font-bold mb-1">Ticket Médio</p>
            <p class="text-2xl font-black text-white">
                R$ {{ number_format($this->kpis['average_ticket'], 2, ',', '.') }}
            </p>
        </div>

        {{-- Clientes --}}
        <div
            class="bg-gradient-to-br from-purple-500/10 to-pink-500/5 border border-purple-500/20 rounded-2xl p-5 hover:border-purple-500/40 transition-all">
            <div class="w-10 h-10 bg-purple-500/20 rounded-xl flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <p class="text-slate-400 text-xs uppercase tracking-wider font-bold mb-1">Clientes</p>
            <p class="text-2xl font-black text-white">
                {{ number_format($this->kpis['total_customers'], 0) }}
            </p>
        </div>

        {{-- Retenção --}}
        <div
            class="bg-gradient-to-br from-yellow-500/10 to-orange-500/5 border border-yellow-500/20 rounded-2xl p-5 hover:border-yellow-500/40 transition-all">
            <div class="w-10 h-10 bg-yellow-500/20 rounded-xl flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-slate-400 text-xs uppercase tracking-wider font-bold mb-1">Retenção</p>
            <p class="text-2xl font-black text-white">
                {{ number_format($this->kpis['retention_rate'], 1) }}%
            </p>
        </div>

        {{-- Hoje --}}
        <div
            class="bg-gradient-to-br from-pink-500/10 to-rose-500/5 border border-pink-500/20 rounded-2xl p-5 hover:border-pink-500/40 transition-all">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-pink-500/20 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span
                    class="text-xs px-2 py-1 rounded-full font-bold {{ $this->revenueStats['growth_today'] >= 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                    {{ $this->revenueStats['growth_today'] >= 0 ? '↑' : '↓' }}
                    {{ number_format(abs($this->revenueStats['growth_today']), 1) }}%
                </span>
            </div>
            <p class="text-slate-400 text-xs uppercase tracking-wider font-bold mb-1">Hoje</p>
            <p class="text-2xl font-black text-white">
                R$ {{ number_format($this->revenueStats['today'], 0, ',', '.') }}
            </p>
        </div>
    </div>

    {{-- RECEITA DETALHADA --}}
    <div class="mb-8">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
            <span>📈</span> Receita por Período
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-blue-500/20 transition-all">
                <p class="text-blue-400 text-xs uppercase tracking-wider font-bold mb-2">7 Dias</p>
                <p class="text-2xl font-bold text-white">R$
                    {{ number_format($this->revenueStats['week'], 2, ',', '.') }}</p>
                <p class="text-xs mt-2 text-slate-400">
                    R$ {{ number_format($this->revenueStats['week'] / 7, 2, ',', '.') }}/dia
                </p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-purple-500/20 transition-all">
                <p class="text-purple-400 text-xs uppercase tracking-wider font-bold mb-2">Mês</p>
                <p class="text-2xl font-bold text-white">R$
                    {{ number_format($this->revenueStats['month'], 2, ',', '.') }}</p>
                <p class="text-xs mt-2 text-slate-400">
                    {{ now()->day }} dias decorridos
                </p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-yellow-500/20 transition-all">
                <p class="text-yellow-400 text-xs uppercase tracking-wider font-bold mb-2">Ano</p>
                <p class="text-2xl font-bold text-white">R$
                    {{ number_format($this->revenueStats['year'], 2, ',', '.') }}</p>
                <p class="text-xs mt-2 text-slate-400">
                    {{ now()->month }} meses
                </p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-indigo-500/20 transition-all">
                <p class="text-indigo-400 text-xs uppercase tracking-wider font-bold mb-2">Período Selecionado</p>
                <p class="text-2xl font-bold text-white">R$
                    {{ number_format($this->revenueStats['period'], 2, ',', '.') }}</p>
                <p class="text-xs mt-2 text-slate-400">
                    {{ $period }} dias
                </p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-emerald-500/20 transition-all">
                <p class="text-emerald-400 text-xs uppercase tracking-wider font-bold mb-2">Média Diária</p>
                <p class="text-2xl font-bold text-white">
                    R$ {{ number_format($this->revenueChart['average_daily'], 2, ',', '.') }}
                </p>
                <p class="text-xs mt-2 text-slate-400">
                    no período
                </p>
            </div>
        </div>
    </div>

    {{-- FLUXO DE CRÉDITOS --}}
    <div class="mb-8">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
            <span>🪙</span> Fluxo de Créditos
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-indigo-500/20 transition-all">
                <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
                <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Circulação</p>
                <p class="text-2xl font-bold text-white">
                    {{ number_format($this->creditsFlow['circulation'], 0, ',', '.') }}
                </p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-emerald-500/20 transition-all">
                <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </div>
                <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Comprados</p>
                <p class="text-2xl font-bold text-emerald-400">
                    +{{ number_format($this->creditsFlow['purchased'], 0, ',', '.') }}
                </p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-red-500/20 transition-all">
                <div class="w-12 h-12 bg-red-500/10 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                </div>
                <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Gastos</p>
                <p class="text-2xl font-bold text-red-400">
                    -{{ number_format($this->creditsFlow['spent'], 0, ',', '.') }}
                </p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-blue-500/20 transition-all">
                <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Transações</p>
                <p class="text-2xl font-bold text-blue-400">
                    {{ number_format($this->creditsFlow['total_transactions'], 0, ',', '.') }}
                </p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-purple-500/20 transition-all">
                <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Média/Trans</p>
                <p class="text-2xl font-bold text-purple-400">
                    {{ number_format($this->creditsFlow['average_transaction'], 0, ',', '.') }}
                </p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-pink-500/20 transition-all">
                <div class="w-12 h-12 bg-pink-500/10 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-1">Compradores</p>
                <p class="text-2xl font-bold text-pink-400">
                    {{ number_format($this->creditsFlow['unique_buyers'], 0, ',', '.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- GRID PRINCIPAL --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

        {{-- PERFORMANCE PACOTES --}}
        <div class="lg:col-span-2 bg-[#0f172a] border border-white/5 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <span>📦</span> Performance de Pacotes
                </h3>
                @if ($this->packagesPerformance['packages']->count() > 0 && $this->packagesPerformance['best_seller'])
                    <span
                        class="text-xs px-3 py-1 bg-yellow-500/10 text-yellow-400 rounded-full border border-yellow-500/20 font-bold">
                        🏆 Melhor: {{ $this->packagesPerformance['best_seller']['name'] }}
                    </span>
                @endif
            </div>

            <div class="space-y-3 mb-6">
                @forelse($this->packagesPerformance['packages'] as $index => $package)
                    <div
                        class="flex items-center gap-4 p-4 bg-black/20 border border-white/5 rounded-xl hover:bg-white/5 transition group">
                        <div
                            class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold shrink-0">
                            #{{ $index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-white font-bold truncate">{{ $package['name'] }}</div>
                            <div class="text-slate-400 text-xs">
                                {{ number_format($package['credits'], 0) }} créditos •
                                R$ {{ number_format($package['price'], 2, ',', '.') }}
                            </div>
                            {{-- Barra de progresso --}}
                            <div class="w-full bg-white/5 rounded-full h-1.5 mt-2">
                                <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-1.5 rounded-full transition-all"
                                    style="width: {{ min($package['percentage'], 100) }}%"></div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-emerald-400 font-bold text-lg">
                                R$ {{ number_format($package['revenue'], 0, ',', '.') }}
                            </div>
                            <div class="text-slate-400 text-xs">
                                {{ $package['sales'] }} vendas
                            </div>
                            <div class="text-slate-500 text-xs mt-1">
                                {{ number_format($package['percentage'], 1) }}%
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-slate-400">
                        <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p class="text-sm font-medium">Nenhuma venda no período</p>
                    </div>
                @endforelse
            </div>

            @if ($this->packagesPerformance['packages']->count() > 0)
                <div class="pt-6 border-t border-white/5 grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider mb-2">Total Vendido</p>
                        <p class="text-white font-bold text-xl">
                            R$ {{ number_format($this->packagesPerformance['total_revenue'], 2, ',', '.') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider mb-2">Vendas</p>
                        <p class="text-white font-bold text-xl">
                            {{ number_format($this->packagesPerformance['total_sales'], 0, ',', '.') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider mb-2">Ticket Médio</p>
                        <p class="text-white font-bold text-xl">
                            R$ {{ number_format($this->packagesPerformance['average_ticket'], 2, ',', '.') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- COLUNA LATERAL --}}
        <div class="space-y-6">

            {{-- CUPONS --}}
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span>🎫</span> Cupons
                </h3>
                <div class="space-y-4">
                    <div
                        class="flex items-center justify-between p-3 bg-purple-500/10 border border-purple-500/20 rounded-xl">
                        <div>
                            <p class="text-purple-400 text-xs uppercase tracking-wider font-bold">Total Usos</p>
                            <p class="text-2xl font-bold text-white mt-1">
                                {{ number_format($this->couponsImpact['total_uses'], 0, ',', '.') }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                        </div>
                    </div>

                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Desconto Total</p>
                        <p class="text-xl font-bold text-yellow-400">
                            R$ {{ number_format($this->couponsImpact['total_discount'], 2, ',', '.') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Receita c/ Cupons</p>
                        <p class="text-xl font-bold text-emerald-400">
                            R$ {{ number_format($this->couponsImpact['total_revenue_with_coupon'], 2, ',', '.') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Taxa Desconto Média</p>
                        <p class="text-xl font-bold text-blue-400">
                            {{ number_format($this->couponsImpact['discount_rate'], 1) }}%
                        </p>
                    </div>

                    @if ($this->couponsImpact['top_coupons']->count() > 0)
                        <div class="pt-4 border-t border-white/5">
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-3">Top 3 Cupons</p>
                            <div class="space-y-2">
                                @foreach ($this->couponsImpact['top_coupons']->take(3) as $coupon)
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-white font-bold">{{ $coupon['code'] }}</span>
                                        <span class="text-slate-400">{{ $coupon['uses'] }} usos</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- GIFT CARDS --}}
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span>🎁</span> Gift Cards
                </h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl">
                            <p class="text-emerald-400 text-xs uppercase tracking-wider font-bold">Ativos</p>
                            <p class="text-2xl font-bold text-white mt-1">
                                {{ number_format($this->giftCardsStats['active'], 0) }}
                            </p>
                        </div>
                        <div class="p-3 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                            <p class="text-blue-400 text-xs uppercase tracking-wider font-bold">Resgatados</p>
                            <p class="text-2xl font-bold text-white mt-1">
                                {{ number_format($this->giftCardsStats['redeemed'], 0) }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Receita de Vendas</p>
                        <p class="text-xl font-bold text-emerald-400">
                            R$ {{ number_format($this->giftCardsStats['revenue_from_sales'], 2, ',', '.') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Créditos Resgatados</p>
                        <p class="text-xl font-bold text-purple-400">
                            {{ number_format($this->giftCardsStats['credits_redeemed'], 0) }} C$
                        </p>
                    </div>

                    <div>
                        <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Valor Ativo</p>
                        <p class="text-xl font-bold text-yellow-400">
                            {{ number_format($this->giftCardsStats['active_value'], 0) }} C$
                        </p>
                    </div>
                </div>
            </div>

            {{-- REEMBOLSOS --}}
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span>↩️</span> Reembolsos
                </h3>
                <div class="space-y-4">
                    @if ($this->refundsStats['pending'] > 0)
                        <div
                            class="flex items-center justify-between p-3 bg-yellow-500/10 border border-yellow-500/20 rounded-xl">
                            <div>
                                <p class="text-yellow-400 text-xs uppercase tracking-wider font-bold">⏳ Pendentes</p>
                                <p class="text-2xl font-bold text-white mt-1">
                                    {{ $this->refundsStats['pending'] }}
                                </p>
                            </div>
                            <div class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">✅ Aprovados</p>
                            <p class="text-xl font-bold text-emerald-400">
                                {{ $this->refundsStats['approved'] }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">❌ Rejeitados</p>
                            <p class="text-xl font-bold text-red-400">
                                {{ $this->refundsStats['rejected'] }}
                            </p>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-white/5">
                        <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Total Reembolsado</p>
                        <p class="text-2xl font-bold text-red-400">
                            R$ {{ number_format($this->refundsStats['total_refunded'], 2, ',', '.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TOP TRANSAÇÕES MELHORADO --}}
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden">
        <div class="p-6 border-b border-white/5 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <span>🏆</span> Maiores Transações do Período
                </h3>
                <p class="text-xs text-slate-500 mt-1">Top 10 transações por valor final</p>
            </div>
            <span
                class="text-xs px-3 py-1 bg-indigo-500/10 text-indigo-400 rounded-full border border-indigo-500/20 font-bold">
                {{ $this->topTransactions->count() }} registros
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="p-4 text-left font-semibold">Usuário</th>
                        <th class="p-4 text-center font-semibold">Origem</th>
                        <th class="p-4 text-center font-semibold">Créditos</th>
                        <th class="p-4 text-center font-semibold">Valor Original</th>
                        <th class="p-4 text-center font-semibold">Cupom</th>
                        <th class="p-4 text-center font-semibold">Desconto</th>
                        <th class="p-4 text-center font-semibold">Valor Final</th>
                        <th class="p-4 text-right font-semibold">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($this->topTransactions as $transaction)
                        <tr class="hover:bg-white/5 transition group">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 bg-indigo-500/20 rounded-full flex items-center justify-center text-indigo-400 font-semibold text-xs">
                                        {{ strtoupper(substr($transaction->wallet?->user?->name ?? 'S', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-white font-semibold">
                                            {{ $transaction->wallet?->user?->name ?? 'Sistema' }}</div>
                                        <div class="text-slate-400 text-xs">{{ $transaction->description }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                @if ($transaction->package)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                        📦 {{ $transaction->package->name }}
                                    </span>
                                @elseif($transaction->giftCard)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                                        🎁 {{ $transaction->giftCard->code }}
                                    </span>
                                @else
                                    <span class="text-slate-500 text-xs">—</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-blue-400 font-bold text-lg">
                                    {{ number_format($transaction->amount, 0, ',', '.') }}
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-white font-bold">
                                    R$
                                    {{ number_format($transaction->original_amount ?? $transaction->amount, 2, ',', '.') }}
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                @if ($transaction->coupon)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                                        🎫 {{ $transaction->coupon->code }}
                                    </span>
                                @else
                                    <span class="text-slate-500">—</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                @if ($transaction->discount_amount > 0)
                                    <div class="text-yellow-400 font-bold">
                                        -R$ {{ number_format($transaction->discount_amount, 2, ',', '.') }}
                                    </div>
                                @else
                                    <span class="text-slate-500">—</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-emerald-400 font-bold text-lg">
                                    R$ {{ number_format($transaction->final_amount, 2, ',', '.') }}
                                </div>
                            </td>
                            <td class="p-4 text-right">
                                <div class="text-slate-400 text-xs">
                                    {{ $transaction->created_at->format('d/m/Y') }}
                                </div>
                                <div class="text-slate-500 text-xs">
                                    {{ $transaction->created_at->format('H:i') }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-2 text-slate-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="font-medium">Nenhuma transação no período</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-toast position="top-right" />
</div>
