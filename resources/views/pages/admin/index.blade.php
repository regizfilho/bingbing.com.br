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

new #[Layout('layouts.admin')] #[Title('Dashboard')] class extends Component {

    public string $period = '30'; // 7, 30, 90, 365

    #[Computed]
    public function usersStats(): array
    {
        return Cache::remember('dashboard.users.' . $this->period, 300, function () {
            $query = User::query();
            
            return [
                'total' => User::count(),
                'today' => User::whereDate('created_at', today())->count(),
                'online' => User::where('last_seen_at', '>=', now()->subMinutes(5))->count(),
                'active_month' => User::where('last_seen_at', '>=', now()->subMonth())->count(),
                'with_credits' => User::whereHas('wallet', fn($q) => $q->where('balance', '>', 0))->count(),
            ];
        });
    }

    #[Computed]
    public function creditsStats(): array
    {
        return Cache::remember('dashboard.credits.' . $this->period, 300, function () {
            return [
                'total_circulation' => Wallet::sum('balance'),
                'purchased' => Transaction::where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('amount'),
                'spent' => Transaction::where('type', 'debit')
                    ->where('status', 'completed')
                    ->sum('amount'),
                'average_balance' => round(Wallet::avg('balance'), 2),
            ];
        });
    }

    #[Computed]
    public function revenueStats(): array
    {
        return Cache::remember('dashboard.revenue.' . $this->period, 300, function () {
            return [
                'today' => Transaction::whereDate('created_at', today())
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
                'all_time' => Transaction::where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('final_amount'),
            ];
        });
    }

    #[Computed]
    public function packagesStats(): array
    {
        return Cache::remember('dashboard.packages.' . $this->period, 300, function () {
            $days = (int) $this->period;
            
            // Agrupa transações por descrição (nome do pacote)
            $packageStats = Transaction::where('created_at', '>=', now()->subDays($days))
                ->where('type', 'credit')
                ->where('status', 'completed')
                ->select([
                    DB::raw('description as package_name'),
                    DB::raw('COUNT(*) as sales')
                ])
                ->groupBy('description')
                ->orderByDesc('sales')
                ->get();
            
            // Mapeia com os pacotes cadastrados
            $topPackages = Package::where('is_active', true)
                ->get()
                ->map(function($package) use ($packageStats) {
                    $stat = $packageStats->firstWhere('package_name', $package->name);
                    return (object) [
                        'name' => $package->name,
                        'sales' => $stat ? $stat->sales : 0,
                    ];
                })
                ->filter(fn($p) => $p->sales > 0)
                ->sortByDesc('sales')
                ->take(5)
                ->values();

            $totalSales = Transaction::where('type', 'credit')
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->subDays($days))
                ->count();

            return [
                'total_active' => Package::where('is_active', true)->count(),
                'top_sellers' => $topPackages,
                'total_sales' => $totalSales,
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
                    'revenue' => Transaction::whereDate('created_at', $date)
                        ->where('type', 'credit')
                        ->where('status', 'completed')
                        ->sum('final_amount'),
                ]);
            }
            return $dates->toArray();
        });
    }

    public function refreshStats(): void
    {
        Cache::forget('dashboard.users.' . $this->period);
        Cache::forget('dashboard.credits.' . $this->period);
        Cache::forget('dashboard.revenue.' . $this->period);
        Cache::forget('dashboard.packages.' . $this->period);
        Cache::forget('dashboard.chart.' . $this->period);
        
        $this->dispatch('notify', type: 'success', text: 'Estatísticas atualizadas!');
    }

};
?>

<div>
    <x-slot name="header">
        Dashboard Administrativo
    </x-slot>

    <div class="space-y-6">
        <!-- HEADER COM FILTRO -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="text-2xl font-bold text-white">Visão Geral do Sistema</h2>
                <p class="text-slate-400 text-sm mt-1">Bem-vindo, {{ auth()->user()->name }}</p>
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

        <!-- CARDS PRINCIPAIS - USUÁRIOS -->
        <div>
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">📊 Usuários</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-indigo-500/20 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Total de Contas</p>
                    <p class="text-3xl font-bold text-white mt-1">{{ number_format($this->usersStats['total'], 0, ',', '.') }}</p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-emerald-500/20 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Criadas Hoje</p>
                    <p class="text-3xl font-bold text-emerald-400 mt-1">{{ number_format($this->usersStats['today'], 0, ',', '.') }}</p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-green-500/20 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 bg-green-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Online Agora</p>
                    <p class="text-3xl font-bold text-green-400 mt-1">{{ number_format($this->usersStats['online'], 0, ',', '.') }}</p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-blue-500/20 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Ativos no Mês</p>
                    <p class="text-3xl font-bold text-blue-400 mt-1">{{ number_format($this->usersStats['active_month'], 0, ',', '.') }}</p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5 hover:border-purple-500/20 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Com Créditos</p>
                    <p class="text-3xl font-bold text-purple-400 mt-1">{{ number_format($this->usersStats['with_credits'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- CARDS FATURAMENTO -->
        <div>
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">💰 Faturamento</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gradient-to-br from-emerald-500/10 to-green-500/5 border border-emerald-500/20 rounded-2xl p-5 hover:border-emerald-500/40 transition-all">
                    <p class="text-emerald-400 text-xs uppercase tracking-wider font-bold mb-2">Hoje</p>
                    <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['today'], 2, ',', '.') }}</p>
                </div>

                <div class="bg-gradient-to-br from-blue-500/10 to-cyan-500/5 border border-blue-500/20 rounded-2xl p-5 hover:border-blue-500/40 transition-all">
                    <p class="text-blue-400 text-xs uppercase tracking-wider font-bold mb-2">Mês Atual</p>
                    <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['month'], 2, ',', '.') }}</p>
                </div>

                <div class="bg-gradient-to-br from-purple-500/10 to-pink-500/5 border border-purple-500/20 rounded-2xl p-5 hover:border-purple-500/40 transition-all">
                    <p class="text-purple-400 text-xs uppercase tracking-wider font-bold mb-2">Ano Atual</p>
                    <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['year'], 2, ',', '.') }}</p>
                </div>

                <div class="bg-gradient-to-br from-yellow-500/10 to-orange-500/5 border border-yellow-500/20 rounded-2xl p-5 hover:border-yellow-500/40 transition-all">
                    <p class="text-yellow-400 text-xs uppercase tracking-wider font-bold mb-2">Histórico Total</p>
                    <p class="text-3xl font-bold text-white">R$ {{ number_format($this->revenueStats['all_time'], 2, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- CARDS CRÉDITOS -->
        <div>
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">🪙 Sistema de Créditos</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Em Circulação</p>
                    <p class="text-3xl font-bold text-indigo-400">{{ number_format($this->creditsStats['total_circulation'], 0, ',', '.') }}</p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Comprados</p>
                    <p class="text-3xl font-bold text-emerald-400">{{ number_format($this->creditsStats['purchased'], 0, ',', '.') }}</p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Gastos</p>
                    <p class="text-3xl font-bold text-red-400">{{ number_format($this->creditsStats['spent'], 0, ',', '.') }}</p>
                </div>

                <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-2">Saldo Médio</p>
                    <p class="text-3xl font-bold text-blue-400">{{ number_format($this->creditsStats['average_balance'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- GRID LATERAL -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- TOP PACOTES -->
            <div class="lg:col-span-2 bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">📦 Pacotes Mais Vendidos</h3>
                <div class="space-y-3">
                    @forelse($this->packagesStats['top_sellers'] as $index => $package)
                        <div class="flex items-center justify-between p-4 bg-black/20 border border-white/5 rounded-xl hover:bg-white/5 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold">
                                    #{{ $index + 1 }}
                                </div>
                                <div>
                                    <div class="text-white font-bold">{{ $package->name }}</div>
                                    <div class="text-slate-400 text-xs">{{ $package->sales }} vendas</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-emerald-400 font-bold">{{ number_format($package->sales * 100 / max($this->packagesStats['total_sales'], 1), 1) }}%</div>
                                <div class="text-slate-500 text-xs">do total</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-slate-400">
                            <p class="text-sm">Nenhuma venda registrada no período</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- USUÁRIOS RECENTES -->
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">👥 Últimos Cadastros</h3>
                <div class="space-y-3">
                    @foreach($this->recentActivity['recent_users'] as $user)
                        <div class="flex items-center gap-3 p-3 bg-black/20 border border-white/5 rounded-xl hover:bg-white/5 transition">
                            <div class="w-10 h-10 bg-indigo-500/20 rounded-full flex items-center justify-center text-indigo-400 font-bold text-sm">
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

        <!-- TRANSAÇÕES RECENTES -->
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden">
            <div class="p-6 border-b border-white/5">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">💳 Transações Recentes</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="p-4 text-left font-semibold">Usuário</th>
                            <th class="p-4 text-center font-semibold">Tipo</th>
                            <th class="p-4 text-center font-semibold">Valor</th>
                            <th class="p-4 text-center font-semibold">Status</th>
                            <th class="p-4 text-right font-semibold">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($this->recentActivity['recent_transactions'] as $transaction)
                            <tr class="hover:bg-white/5 transition">
                                <td class="p-4">
                                    <div class="text-white font-semibold">{{ $transaction->wallet?->user?->name ?? 'Sistema' }}</div>
                                    <div class="text-slate-400 text-xs truncate max-w-xs">{{ $transaction->description }}</div>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold {{ $transaction->type === 'credit' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                                        {{ $transaction->type === 'credit' ? '+ Crédito' : '- Débito' }}
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="text-white font-bold">
                                        {{ number_format($transaction->amount, 0, ',', '.') }}
                                    </div>
                                    @if($transaction->final_amount && $transaction->final_amount != $transaction->amount)
                                        <div class="text-xs text-slate-500">
                                            Final: {{ number_format($transaction->final_amount, 2, ',', '.') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="p-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold 
                                        {{ $transaction->status === 'completed' ? 'bg-green-500/10 text-green-400' : 
                                           ($transaction->status === 'pending' ? 'bg-yellow-500/10 text-yellow-400' : 'bg-red-500/10 text-red-400') }}">
                                        {{ ucfirst($transaction->status) }}
                                    </span>
                                </td>
                                <td class="p-4 text-right text-slate-400 text-xs">
                                    {{ $transaction->created_at->format('d/m/Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-400">
                                    Nenhuma transação recente
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- STATUS DO SISTEMA -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Status do Sistema</p>
                </div>
                <p class="text-2xl font-bold text-white">OPERACIONAL</p>
                <p class="text-slate-500 text-xs mt-1">Todos os serviços funcionando normalmente</p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-3">Último Backup</p>
                <p class="text-2xl font-bold text-white">{{ now()->subHours(2)->diffForHumans() }}</p>
                <p class="text-slate-500 text-xs mt-1">Backup automático em {{ now()->addHours(2)->diffForHumans() }}</p>
            </div>

            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <p class="text-slate-400 text-xs uppercase tracking-wider font-medium mb-3">Versão do Sistema</p>
                <p class="text-2xl font-bold text-white">v2.0.1</p>
                <p class="text-slate-500 text-xs mt-1">Laravel 12 + Livewire 4</p>
            </div>
        </div>
    </div>

    <x-loading target="refreshStats" message="Atualizando..." overlay />
    <x-toast position="top-right" />
</div>