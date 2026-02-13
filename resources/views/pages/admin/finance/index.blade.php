<?php

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Wallet\Package;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\Wallet;
use App\Models\Wallet\Refund;
use App\Models\Coupon;
use App\Models\CouponUser;

new #[Layout('layouts.admin')] #[Title('Dashboard Financeiro')] class extends Component {

    public string $period = '30';

    #[Computed]
    public function revenueStats(): array
    {
        return Cache::remember('finance.revenue.' . $this->period, 300, function () {
            $days = (int) $this->period;
            
            return [
                'today' => Transaction::whereDate('created_at', today())
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
                'yesterday' => Transaction::whereDate('created_at', today()->subDay())
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
                'week' => Transaction::where('created_at', '>=', now()->subDays(7))
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
                'month' => Transaction::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
                'year' => Transaction::whereYear('created_at', now()->year)
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
                'period' => Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
            ];
        });
    }

    #[Computed]
    public function creditsFlow(): array
    {
        return Cache::remember('finance.credits.' . $this->period, 300, function () {
            $days = (int) $this->period;
            
            return [
                'circulation' => Wallet::sum('balance'),
                'purchased' => Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('amount'),
                'spent' => Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('type', 'debit')
                    ->where('status', 'completed')
                    ->sum('amount'),
                'average_transaction' => Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('status', 'completed')
                    ->avg('amount'),
                'total_transactions' => Transaction::where('created_at', '>=', now()->subDays($days))
                    ->where('status', 'completed')
                    ->count(),
            ];
        });
    }

    #[Computed]
    public function packagesPerformance(): array
    {
        return Cache::remember('finance.packages.' . $this->period, 300, function () {
            $days = (int) $this->period;
            
            $packages = Package::where('is_active', true)->get()->map(function($package) use ($days) {
                $transactions = Transaction::where('transactionable_type', Package::class)
                    ->where('transactionable_id', $package->id)
                    ->where('created_at', '>=', now()->subDays($days))
                    ->where('type', 'credit')
                    ->where('status', 'completed');
                
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'price' => $package->price_brl,
                    'credits' => $package->credits,
                    'sales' => $transactions->count(),
                    'revenue' => $transactions->sum('final_amount'),
                    'conversion_rate' => 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();

            $totalRevenue = $packages->sum('revenue');
            $totalSales = $packages->sum('sales');

            return [
                'packages' => $packages->take(10),
                'total_revenue' => $totalRevenue,
                'total_sales' => $totalSales,
                'average_ticket' => $totalSales > 0 ? $totalRevenue / $totalSales : 0,
            ];
        });
    }

    #[Computed]
    public function couponsImpact(): array
    {
        return Cache::remember('finance.coupons.' . $this->period, 300, function () {
            $days = (int) $this->period;
            
            $topCoupons = Coupon::withCount([
                'users' => function($q) use ($days) {
                    $q->where('used_at', '>=', now()->subDays($days));
                }
            ])
            ->get()
            ->filter(fn($coupon) => $coupon->users_count > 0)
            ->sortByDesc('users_count')
            ->take(5);
            
            return [
                'total_uses' => CouponUser::where('used_at', '>=', now()->subDays($days))->count(),
                'total_discount' => CouponUser::where('used_at', '>=', now()->subDays($days))
                    ->sum('discount_amount'),
                'total_revenue_with_coupon' => CouponUser::where('used_at', '>=', now()->subDays($days))
                    ->sum('order_value'),
                'unique_users' => CouponUser::where('used_at', '>=', now()->subDays($days))
                    ->distinct('user_id')
                    ->count('user_id'),
                'top_coupons' => $topCoupons,
            ];
        });
    }

    #[Computed]
    public function refundsStats(): array
    {
        return Cache::remember('finance.refunds.' . $this->period, 300, function () {
            $days = (int) $this->period;
            
            return [
                'pending' => Refund::where('status', 'pending')->count(),
                'approved' => Refund::where('status', 'approved')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->count(),
                'rejected' => Refund::where('status', 'rejected')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->count(),
                'total_refunded' => Refund::where('status', 'approved')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->sum('amount_brl'),
            ];
        });
    }

    #[Computed]
    public function revenueChart(): array
    {
        return Cache::remember('finance.chart.' . $this->period, 300, function () {
            $days = min((int) $this->period, 30);
            
            $data = collect();
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $data->push([
                    'date' => $date->format('d/m'),
                    'revenue' => Transaction::whereDate('created_at', $date)
                        ->where('type', 'credit')
                        ->where('status', 'completed')
                        ->sum('final_amount'),
                    'transactions' => Transaction::whereDate('created_at', $date)
                        ->where('status', 'completed')
                        ->count(),
                ]);
            }
            return $data->toArray();
        });
    }

    #[Computed]
    public function topTransactions()
    {
        return Transaction::with(['wallet.user', 'coupon', 'transactionable'])
            ->where('type', 'credit')
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays((int) $this->period))
            ->orderByDesc('final_amount')
            ->limit(10)
            ->get();
    }

    public function refreshStats(): void
    {
        Cache::forget('finance.revenue.' . $this->period);
        Cache::forget('finance.credits.' . $this->period);
        Cache::forget('finance.packages.' . $this->period);
        Cache::forget('finance.coupons.' . $this->period);
        Cache::forget('finance.refunds.' . $this->period);
        Cache::forget('finance.chart.' . $this->period);
        
        $this->dispatch('notify', type: 'success', text: 'Estatísticas financeiras atualizadas!');
    }

};
?>

<div>
    <x-slot name="header">
        Dashboard Financeiro
    </x-slot>

    <div class="space-y-6">
        <!-- HEADER COM FILTRO -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                    💰 Visão Financeira Completa
                    <span class="text-sm px-3 py-1 bg-emerald-500/10 text-emerald-400 rounded-lg border border-emerald-500/20">
                        Tempo Real
                    </span>
                </h2>
                <p class="text-slate-400 text-sm mt-1">Acompanhamento detalhado de receitas, créditos e desempenho</p>
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
                    class="px-4 py-2 bg-gray-800 border border-white/10 rounded-xl text-sm text-white hover:bg-gray-700 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- RECEITA - CARDS PRINCIPAIS -->
        <div>
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">📈 Receita Bruta</h3>
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="bg-gradient-to-br from-emerald-500/10 to-green-500/5 border border-emerald-500/20 rounded-2xl p-5 hover:border-emerald-500/40 transition-all">
                    <p class="text-emerald-400 text-xs uppercase tracking-wider font-bold mb-2">Hoje</p>
                    <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['today'], 2, ',', '.') }}</p>
                    @php
                        $growth = $this->revenueStats['yesterday'] > 0 
                            ? (($this->revenueStats['today'] - $this->revenueStats['yesterday']) / $this->revenueStats['yesterday']) * 100
                            : 0;
                    @endphp
                    <p class="text-xs mt-2 {{ $growth >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                        {{ $growth >= 0 ? '↑' : '↓' }} {{ number_format(abs($growth), 1) }}% vs ontem
                    </p>
                </div>

                <div class="bg-gradient-to-br from-blue-500/10 to-cyan-500/5 border border-blue-500/20 rounded-2xl p-5 hover:border-blue-500/40 transition-all">
                    <p class="text-blue-400 text-xs uppercase tracking-wider font-bold mb-2">7 Dias</p>
                    <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['week'], 2, ',', '.') }}</p>
                    <p class="text-xs mt-2 text-slate-400">
                        Média: R$ {{ number_format($this->revenueStats['week'] / 7, 2, ',', '.') }}/dia
                    </p>
                </div>

                <div class="bg-gradient-to-br from-purple-500/10 to-pink-500/5 border border-purple-500/20 rounded-2xl p-5 hover:border-purple-500/40 transition-all">
                    <p class="text-purple-400 text-xs uppercase tracking-wider font-bold mb-2">Mês Atual</p>
                    <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['month'], 2, ',', '.') }}</p>
                    <p class="text-xs mt-2 text-slate-400">
                        {{ now()->day }} dias
                    </p>
                </div>

                <div class="bg-gradient-to-br from-yellow-500/10 to-orange-500/5 border border-yellow-500/20 rounded-2xl p-5 hover:border-yellow-500/40 transition-all">
                    <p class="text-yellow-400 text-xs uppercase tracking-wider font-bold mb-2">Ano Atual</p>
                    <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['year'], 2, ',', '.') }}</p>
                    <p class="text-xs mt-2 text-slate-400">
                        {{ now()->month }} meses
                    </p>
                </div>

                <div class="bg-gradient-to-br from-indigo-500/10 to-blue-500/5 border border-indigo-500/20 rounded-2xl p-5 hover:border-indigo-500/40 transition-all">
                    <p class="text-indigo-400 text-xs uppercase tracking-wider font-bold mb-2">Período</p>
                    <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['period'], 2, ',', '.') }}</p>
                    <p class="text-xs mt-2 text-slate-400">
                        {{ $period }} dias
                    </p>
                </div>

                <div class="bg-gradient-to-br from-pink-500/10 to-rose-500/5 border border-pink-500/20 rounded-2xl p-5 hover:border-pink-500/40 transition-all">
                    <p class="text-pink-400 text-xs uppercase tracking-wider font-bold mb-2">Ticket Médio</p>
                    <p class="text-3xl font-bold text-white">
                        R$ {{ number_format($this->packagesPerformance['average_ticket'], 2, ',', '.') }}
                    </p>
                    <p class="text-xs mt-2 text-slate-400">
                        por venda
                    </p>
                </div>
            </div>
        </div>

        <!-- FLUXO DE CRÉDITOS -->
        <div>
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">🪙 Fluxo de Créditos</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-indigo-500/20 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Em Circulação</p>
                    <p class="text-3xl font-bold text-white mt-1">
                        {{ number_format($this->creditsFlow['circulation'], 0, ',', '.') }}
                    </p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-emerald-500/20 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Comprados</p>
                    <p class="text-3xl font-bold text-emerald-400 mt-1">
                        {{ number_format($this->creditsFlow['purchased'], 0, ',', '.') }}
                    </p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-red-500/20 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 bg-red-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20 12H4" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Gastos</p>
                    <p class="text-3xl font-bold text-red-400 mt-1">
                        {{ number_format($this->creditsFlow['spent'], 0, ',', '.') }}
                    </p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-blue-500/20 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Transações</p>
                    <p class="text-3xl font-bold text-blue-400 mt-1">
                        {{ number_format($this->creditsFlow['total_transactions'], 0, ',', '.') }}
                    </p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-purple-500/20 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Média/Trans.</p>
                    <p class="text-3xl font-bold text-purple-400 mt-1">
                        {{ number_format($this->creditsFlow['average_transaction'], 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- GRID LATERAL -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- PERFORMANCE DE PACOTES -->
            <div class="lg:col-span-2 bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">📦 Performance de Pacotes</h3>
                <div class="space-y-3">
                    @forelse($this->packagesPerformance['packages'] as $index => $package)
                        <div class="flex items-center justify-between p-4 bg-black/20 border border-white/5 rounded-xl hover:bg-white/5 transition">
                            <div class="flex items-center gap-4 flex-1">
                                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold">
                                    #{{ $index + 1 }}
                                </div>
                                <div class="flex-1">
                                    <div class="text-white font-bold">{{ $package['name'] }}</div>
                                    <div class="text-slate-400 text-xs">
                                        {{ $package['credits'] }} créditos • R$ {{ number_format($package['price'], 2, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-emerald-400 font-bold text-lg">
                                    R$ {{ number_format($package['revenue'], 2, ',', '.') }}
                                </div>
                                <div class="text-slate-400 text-xs">
                                    {{ $package['sales'] }} vendas
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-slate-400">
                            <p class="text-sm">Nenhuma venda registrada no período</p>
                        </div>
                    @endforelse
                </div>

                @if($this->packagesPerformance['packages']->count() > 0)
                    <div class="mt-6 pt-6 border-t border-white/5 grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Total Vendido</p>
                            <p class="text-white font-bold text-lg">
                                R$ {{ number_format($this->packagesPerformance['total_revenue'], 2, ',', '.') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Total Vendas</p>
                            <p class="text-white font-bold text-lg">
                                {{ number_format($this->packagesPerformance['total_sales'], 0, ',', '.') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Ticket Médio</p>
                            <p class="text-white font-bold text-lg">
                                R$ {{ number_format($this->packagesPerformance['average_ticket'], 2, ',', '.') }}
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- CUPONS E REEMBOLSOS -->
            <div class="space-y-6">
                <!-- CUPONS -->
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">🎫 Impacto dos Cupons</h3>
                    <div class="space-y-4">
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Usos no Período</p>
                            <p class="text-2xl font-bold text-white">
                                {{ number_format($this->couponsImpact['total_uses'], 0, ',', '.') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Desconto Total</p>
                            <p class="text-2xl font-bold text-yellow-400">
                                R$ {{ number_format($this->couponsImpact['total_discount'], 2, ',', '.') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Receita com Cupons</p>
                            <p class="text-2xl font-bold text-emerald-400">
                                R$ {{ number_format($this->couponsImpact['total_revenue_with_coupon'], 2, ',', '.') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Usuários Únicos</p>
                            <p class="text-2xl font-bold text-blue-400">
                                {{ number_format($this->couponsImpact['unique_users'], 0, ',', '.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- REEMBOLSOS -->
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">↩️ Reembolsos</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-yellow-500/10 border border-yellow-500/20 rounded-xl">
                            <div>
                                <p class="text-yellow-400 text-xs uppercase tracking-wider font-bold">Pendentes</p>
                                <p class="text-2xl font-bold text-white mt-1">
                                    {{ $this->refundsStats['pending'] }}
                                </p>
                            </div>
                            <div class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Aprovados</p>
                            <p class="text-xl font-bold text-emerald-400">
                                {{ $this->refundsStats['approved'] }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Rejeitados</p>
                            <p class="text-xl font-bold text-red-400">
                                {{ $this->refundsStats['rejected'] }}
                            </p>
                        </div>
                        <div class="pt-3 border-t border-white/5">
                            <p class="text-slate-400 text-xs uppercase tracking-wider mb-1">Total Reembolsado</p>
                            <p class="text-xl font-bold text-red-400">
                                R$ {{ number_format($this->refundsStats['total_refunded'], 2, ',', '.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOP TRANSAÇÕES -->
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden">
            <div class="p-6 border-b border-white/5">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">🏆 Maiores Transações do Período</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="p-4 text-left font-semibold">Usuário</th>
                            <th class="p-4 text-center font-semibold">Créditos</th>
                            <th class="p-4 text-center font-semibold">Valor Original</th>
                            <th class="p-4 text-center font-semibold">Cupom</th>
                            <th class="p-4 text-center font-semibold">Valor Final</th>
                            <th class="p-4 text-right font-semibold">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($this->topTransactions as $transaction)
                            <tr class="hover:bg-white/5 transition">
                                <td class="p-4">
                                    <div class="text-white font-semibold">{{ $transaction->wallet?->user?->name ?? 'Sistema' }}</div>
                                    <div class="text-slate-400 text-xs">{{ $transaction->description }}</div>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="text-blue-400 font-bold">
                                        {{ number_format($transaction->amount, 0, ',', '.') }}
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="text-white font-bold">
                                        R$ {{ number_format($transaction->original_amount ?? $transaction->amount, 2, ',', '.') }}
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    @if($transaction->coupon)
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                                            {{ $transaction->coupon->code }}
                                        </span>
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="p-4 text-center">
                                    <div class="text-emerald-400 font-bold text-lg">
                                        R$ {{ number_format($transaction->final_amount, 2, ',', '.') }}
                                    </div>
                                    @if($transaction->discount_amount > 0)
                                        <div class="text-xs text-yellow-400">
                                            -R$ {{ number_format($transaction->discount_amount, 2, ',', '.') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="p-4 text-right text-slate-400 text-xs">
                                    {{ $transaction->created_at->format('d/m/Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400">
                                    Nenhuma transação no período
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-loading target="refreshStats" message="Atualizando dados financeiros..." overlay />
    <x-toast position="top-right" />
</div>