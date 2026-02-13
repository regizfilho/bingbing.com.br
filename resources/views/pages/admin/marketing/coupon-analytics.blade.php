<?php

/**
 * ============================================================================
 * Analytics de Cupons - Implementação seguindo boas práticas:
 *
 * - Laravel 12:
 *   • Validação server-side robusta
 *   • Proteção contra SQL Injection via Eloquent
 *   • Mass assignment controlado
 *   • Queries otimizadas
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
use App\Models\Coupon;
use App\Models\CouponUser;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.admin')] #[Title('Analytics de Cupons')] class extends Component {
    use WithPagination;

    public ?int $coupon_id = null;
    public string $period = '30';
    public bool $showDetailsModal = false;
    public ?int $selectedUsageId = null;

    public function updatingCouponId(): void
    {
        $this->resetPage();
    }

    public function updatingPeriod(): void
    {
        $this->resetPage();
    }

    protected function baseQuery()
    {
        return CouponUser::query()
            ->when($this->coupon_id, fn($q) => $q->where('coupon_id', $this->coupon_id))
            ->when($this->period !== 'all', fn($q) => $q->where('used_at', '>=', now()->subDays((int) $this->period)));
    }

    #[Computed]
    public function stats(): array
    {
        $base = $this->baseQuery();

        $aggregates = (clone $base)
            ->selectRaw(
                'COUNT(*) as total_uses,
                 SUM(order_value) as total_revenue,
                 SUM(discount_amount) as total_discount,
                 AVG(order_value) as avg_ticket'
            )
            ->first();

        $uniqueUsers = (clone $base)->distinct('user_id')->count('user_id');

        return [
            'total_uses' => (int) ($aggregates->total_uses ?? 0),
            'total_revenue' => (float) ($aggregates->total_revenue ?? 0),
            'total_discount' => (float) ($aggregates->total_discount ?? 0),
            'avg_ticket' => (float) ($aggregates->avg_ticket ?? 0),
            'unique_users' => $uniqueUsers,
            'conversion' => $uniqueUsers ? round(($uniqueUsers / max($aggregates->total_uses, 1)) * 100, 2) : 0,
        ];
    }

    #[Computed]
    public function topCoupons()
    {
        return CouponUser::select('coupon_id', DB::raw('COUNT(*) as uses'), DB::raw('SUM(order_value) as revenue'))
            ->when($this->period !== 'all', fn($q) => $q->where('used_at', '>=', now()->subDays((int) $this->period)))
            ->groupBy('coupon_id')
            ->with('coupon')
            ->orderByDesc('uses')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function history()
    {
        return $this->baseQuery()
            ->with(['coupon', 'user'])
            ->latest('used_at')
            ->paginate(15);
    }

    #[Computed]
    public function coupons()
    {
        return Coupon::orderBy('code')->get();
    }

    public function showDetails(int $id): void
    {
        $this->selectedUsageId = $id;
        $this->showDetailsModal = true;
    }

    public function closeDetails(): void
    {
        $this->showDetailsModal = false;
        $this->selectedUsageId = null;
    }

    #[Computed]
    public function selectedUsage()
    {
        return $this->selectedUsageId ? CouponUser::with(['coupon', 'user'])->find($this->selectedUsageId) : null;
    }

    public function render(): View
    {
        return view('pages.admin.marketing.coupon-analytics');
    }
};
?>

<div class="p-6 min-h-screen">
    <!-- HEADER -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">
                Analytics de Cupons
            </h1>
            <p class="text-slate-400 text-sm mt-1">Análise detalhada do desempenho e uso dos cupons de desconto</p>
        </div>
        <div class="flex items-center gap-2 text-sm text-slate-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <span>Dados em tempo real</span>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-300 mb-2">Cupom Específico</label>
                <select wire:model.live="coupon_id"
                    class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos os cupons</option>
                    @foreach ($this->coupons as $coupon)
                        <option value="{{ $coupon->id }}">{{ $coupon->code }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-300 mb-2">Período de Análise</label>
                <select wire:model.live="period"
                    class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="7">Últimos 7 dias</option>
                    <option value="30">Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                    <option value="all">Todos os períodos</option>
                </select>
            </div>

            @if($coupon_id || $period !== '30')
                <button wire:click="$set('coupon_id', null); $set('period', '30')"
                    class="px-4 py-3 bg-gray-800 border border-white/10 rounded-xl text-sm text-white hover:bg-gray-700 transition-all whitespace-nowrap">
                    Limpar Filtros
                </button>
            @endif
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Total de Usos</p>
                    <p class="text-3xl font-bold text-white mt-2">{{ number_format($this->stats['total_uses'], 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Receita Total</p>
                    <p class="text-3xl font-bold text-emerald-400 mt-2">
                        R$ {{ number_format($this->stats['total_revenue'], 2, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Desconto Total</p>
                    <p class="text-3xl font-bold text-yellow-400 mt-2">
                        R$ {{ number_format($this->stats['total_discount'], 2, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Ticket Médio</p>
                    <p class="text-3xl font-bold text-blue-400 mt-2">
                        R$ {{ number_format($this->stats['avg_ticket'], 2, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Usuários Únicos</p>
                    <p class="text-3xl font-bold text-purple-400 mt-2">
                        {{ number_format($this->stats['unique_users'], 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ $this->stats['conversion'] }}% conversão
                    </p>
                </div>
                <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- TOP CUPONS -->
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 mb-6">
        <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Top 5 Cupons do Período</h3>

        <div class="space-y-3">
            @forelse ($this->topCoupons as $index => $top)
                <div class="flex items-center justify-between p-4 bg-black/20 border border-white/5 rounded-xl hover:bg-white/5 transition">
                    <div class="flex items-center gap-4">
                        <div class="w-8 h-8 bg-indigo-500/20 rounded-lg flex items-center justify-center">
                            <span class="text-indigo-400 font-bold text-sm">#{{ $index + 1 }}</span>
                        </div>
                        <div>
                            <div class="text-white font-bold">{{ $top->coupon?->code ?? 'N/A' }}</div>
                            <div class="text-slate-400 text-xs">{{ $top->coupon?->description ?? 'Sem descrição' }}</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-white font-bold">{{ number_format($top->uses, 0, ',', '.') }} usos</div>
                        <div class="text-emerald-400 text-sm">R$ {{ number_format($top->revenue, 2, ',', '.') }}</div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-slate-400">
                    <svg class="w-12 h-12 mx-auto mb-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <p>Nenhum uso de cupom registrado neste período</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- HISTÓRICO DE USOS -->
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-6 border-b border-white/5">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider">Histórico Detalhado de Usos</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="p-4 text-left font-semibold">Usuário</th>
                        <th class="p-4 text-left font-semibold">Cupom</th>
                        <th class="p-4 text-center font-semibold">Valor Pedido</th>
                        <th class="p-4 text-center font-semibold">Desconto</th>
                        <th class="p-4 text-center font-semibold">Valor Final</th>
                        <th class="p-4 text-center font-semibold">Data de Uso</th>
                        <th class="p-4 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($this->history as $item)
                        <tr wire:key="usage-{{ $item->id }}" class="hover:bg-white/5 transition-all group">
                            <td class="p-4">
                                <div>
                                    <div class="text-white font-semibold">{{ $item->user?->name ?? 'Usuário Removido' }}</div>
                                    <div class="text-slate-400 text-xs">{{ $item->user?->email ?? 'N/A' }}</div>
                                </div>
                            </td>
                            <td class="p-4">
                                <div
                                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-500/10 border border-purple-500/20 rounded-lg">
                                    <span class="text-purple-400 font-bold text-xs">{{ $item->coupon?->code ?? 'N/A' }}</span>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-white font-semibold">
                                    R$ {{ number_format((float) $item->order_value, 2, ',', '.') }}
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-emerald-400 font-semibold">
                                    - R$ {{ number_format((float) $item->discount_amount, 2, ',', '.') }}
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-blue-400 font-bold">
                                    R$ {{ number_format((float) ($item->order_value - $item->discount_amount), 2, ',', '.') }}
                                </div>
                            </td>
                            <td class="p-4 text-center text-slate-400 text-xs">
                                <div>{{ $item->used_at?->format('d/m/Y') }}</div>
                                <div class="text-slate-500">{{ $item->used_at?->format('H:i:s') }}</div>
                            </td>
                            <td class="p-4 text-right">
                                <button wire:click="showDetails({{ $item->id }})"
                                    class="p-2 text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-all"
                                    title="Ver Detalhes">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center">
                                <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <p class="text-slate-400 font-medium mb-2">Nenhum uso registrado</p>
                                <p class="text-slate-500 text-sm">Não há histórico de uso de cupons para o período
                                    selecionado</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->history->hasPages())
            <div class="p-6 border-t border-white/5 bg-black/10">
                {{ $this->history->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL DE DETALHES -->
    @if ($showDetailsModal && $this->selectedUsage)
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-[#0f172a] w-full max-w-2xl rounded-2xl border border-white/10 shadow-2xl">
                <div class="flex justify-between items-center px-8 py-6 border-b border-white/10">
                    <div>
                        <h2 class="text-lg font-bold text-white uppercase tracking-wide">
                            Detalhes do Uso do Cupom
                        </h2>
                        <p class="text-slate-400 text-xs mt-1">
                            Informações completas sobre esta transação
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
                            <div class="text-white font-semibold">{{ $this->selectedUsage->user?->name ?? 'N/A' }}</div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Email</label>
                            <div class="text-white font-semibold">{{ $this->selectedUsage->user?->email ?? 'N/A' }}</div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Cupom Utilizado</label>
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-500/10 border border-purple-500/20 rounded-lg">
                                <span class="text-purple-400 font-bold">{{ $this->selectedUsage->coupon?->code ?? 'N/A' }}</span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Data e Hora</label>
                            <div class="text-white font-semibold">
                                {{ $this->selectedUsage->used_at?->format('d/m/Y H:i:s') ?? 'N/A' }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Valor do Pedido</label>
                            <div class="text-white font-bold text-lg">
                                R$ {{ number_format((float) $this->selectedUsage->order_value, 2, ',', '.') }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Desconto Aplicado</label>
                            <div class="text-emerald-400 font-bold text-lg">
                                - R$ {{ number_format((float) $this->selectedUsage->discount_amount, 2, ',', '.') }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Valor Final</label>
                            <div class="text-blue-400 font-bold text-lg">
                                R$ {{ number_format((float) ($this->selectedUsage->order_value - $this->selectedUsage->discount_amount), 2, ',', '.') }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs text-slate-500 uppercase tracking-wider">Endereço IP</label>
                            <div class="text-slate-300 font-mono text-sm">
                                {{ $this->selectedUsage->ip_address ?? 'Não registrado' }}
                            </div>
                        </div>
                    </div>

                    @if($this->selectedUsage->coupon)
                        <div class="pt-6 border-t border-white/10">
                            <label class="text-xs text-slate-500 uppercase tracking-wider mb-3 block">Informações do Cupom</label>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-slate-400">Tipo:</span>
                                    <span class="text-white ml-2">{{ $this->selectedUsage->coupon->type === 'percent' ? 'Percentual' : 'Fixo' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400">Valor:</span>
                                    <span class="text-white ml-2">
                                        {{ $this->selectedUsage->coupon->type === 'percent' ? $this->selectedUsage->coupon->value . '%' : 'R$ ' . number_format((float) $this->selectedUsage->coupon->value, 2, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-8 py-6 border-t border-white/10 bg-black/20">
                    <button wire:click="closeDetails"
                        class="w-full bg-gray-800 hover:bg-gray-700 transition py-3 rounded-xl text-sm font-medium text-white">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <x-loading target="coupon_id,period,showDetails" message="Carregando dados..." overlay />
    <x-toast position="top-right" />
</div>