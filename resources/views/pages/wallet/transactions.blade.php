<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Computed]
    public function user()
    {
        return auth()->user();
    }

    #[Computed]
    public function transactions()
    {
        try {
            if (!$this->user->wallet) {
                return collect();
            }
            
            return $this->user->wallet
                ->transactions()
                ->with(['transactionable', 'coupon'])
                ->latest()
                ->paginate(15);
        } catch (\Exception $e) {
            Log::error('Transaction history load failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return collect();
        }
    }
};
?>

<div class="min-h-screen bg-[#05070a] text-slate-200 selection:bg-blue-500/30 overflow-x-hidden pb-24 relative">

    <x-loading target="gotoPage, nextPage, previousPage" message="ATUALIZANDO REGISTROS..." />

    <div
        class="fixed top-0 right-0 w-[500px] h-[500px] bg-blue-600/5 rounded-full blur-[120px] -z-10 pointer-events-none">
    </div>

    <div class="max-w-6xl mx-auto px-6 py-12">

        {{-- Cabeçalho --}}
        <div class="mb-16 flex flex-col md:flex-row md:items-end justify-between gap-8">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 bg-blue-600 rounded-full shadow-[0_0_8px_#2563eb]"></div>
                    <span class="text-[10px] font-black text-blue-500 uppercase tracking-[0.5em] italic">
                        Histórico de Atividades
                    </span>
                </div>
                <h1 class="text-6xl font-black text-white uppercase italic tracking-tighter leading-none">
                    EXTRATO <span class="text-blue-600">GERAL</span>
                </h1>
            </div>

            <a href="{{ route('wallet.index') }}"
                class="px-8 py-4 bg-white/[0.03] border border-white/10 rounded-2xl text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-white hover:bg-blue-600 hover:border-blue-500 transition-all group italic">
                <span class="inline-block group-hover:-translate-x-1 transition-transform mr-2">←</span>
                Voltar para Carteira
            </a>
        </div>

        {{-- Tabela --}}
        <div class="relative group">
            <div
                class="absolute -inset-0.5 bg-gradient-to-r from-blue-600/20 to-cyan-500/20 rounded-[3.5rem] blur opacity-50">
            </div>

            <div class="relative bg-[#0b0d11] border border-white/5 rounded-[3.5rem] shadow-2xl overflow-hidden">

                {{-- Topo --}}
                <div
                    class="px-10 py-8 border-b border-white/5 bg-white/[0.01] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-widest italic">
                            Movimentações da Conta
                        </h3>
                        <p class="text-[9px] font-bold text-slate-600 uppercase tracking-[0.2em] mt-1 italic">
                            Dados atualizados em tempo real
                        </p>
                    </div>
                    <div class="inline-flex items-center px-4 py-2 bg-blue-600/10 border border-blue-500/20 rounded-xl">
                        <span class="text-[10px] font-black text-blue-400 uppercase italic">
                            {{ $this->transactions->total() }} Operações Registradas
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto min-h-[400px]">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white/[0.02]">
                                <th
                                    class="px-10 py-6 text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] italic">
                                    Data & Hora</th>
                                <th
                                    class="px-10 py-6 text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] italic">
                                    Descrição do Evento</th>
                                <th
                                    class="px-10 py-6 text-center text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] italic">
                                    Tipo</th>
                                <th
                                    class="px-10 py-6 text-right text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] italic">
                                    Valor</th>
                                <th
                                    class="px-10 py-6 text-right text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] italic">
                                    Saldo Final</th>
                                <th
                                    class="px-10 py-6 text-center text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] italic">
                                    Status</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-white/5">
                            @forelse ($this->transactions as $transaction)
                                <tr class="group hover:bg-blue-600/[0.02] transition-colors italic">

                                    {{-- Data --}}
                                    <td class="px-10 py-8 whitespace-nowrap">
                                        <div class="text-[12px] font-black text-white tracking-tight">
                                            {{ $transaction->created_at->format('d/m/Y') }}
                                        </div>
                                        <div class="text-[10px] font-bold text-slate-600 uppercase mt-1">
                                            {{ $transaction->created_at->format('H:i:s') }}
                                        </div>
                                    </td>

                                    {{-- Descrição --}}
                                    <td class="px-10 py-8">
                                        <div
                                            class="text-[11px] font-black text-slate-300 uppercase tracking-wider group-hover:text-blue-400 transition-colors">
                                            {{ $transaction->description }}
                                        </div>

                                        {{-- Informações do Pacote --}}
                                        @if ($transaction->package)
                                            <div class="mt-3 flex items-center gap-2">
                                                <span
                                                    class="px-3 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest 
            bg-indigo-600/10 border border-indigo-500/20 text-indigo-400">
                                                    📦 PACOTE
                                                </span>
                                                <span class="text-[9px] font-black text-indigo-500 tracking-widest">
                                                    {{ $transaction->package->name }}
                                                </span>
                                            </div>
                                        @endif

                                        {{-- Informações do Gift Card --}}
                                        @if ($transaction->giftCard)
                                            <div class="mt-3 flex items-center gap-2">
                                                <span
                                                    class="px-3 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest 
            bg-purple-600/10 border border-purple-500/20 text-purple-400">
                                                    🎁 GIFT CARD
                                                </span>
                                                <span class="text-[9px] font-black text-purple-500 tracking-widest">
                                                    {{ $transaction->giftCard->code }}
                                                </span>
                                            </div>
                                        @endif

                                        {{-- 💰 INFORMAÇÕES FINANCEIRAS ADICIONADAS --}}
                                        @if ($transaction->original_amount)
                                            <div class="mt-4 space-y-1">

                                                <div
                                                    class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">
                                                    Valor do pacote:
                                                    <span class="text-white">
                                                        R$
                                                        {{ number_format($transaction->original_amount, 2, ',', '.') }}
                                                    </span>
                                                </div>

                                                @if ($transaction->discount_amount > 0)
                                                    <div
                                                        class="text-[9px] font-bold text-red-500 uppercase tracking-widest">
                                                        Desconto aplicado:
                                                        - R$
                                                        {{ number_format($transaction->discount_amount, 2, ',', '.') }}
                                                    </div>
                                                @endif

                                                <div
                                                    class="text-[9px] font-black text-emerald-500 uppercase tracking-widest">
                                                    Valor pago:
                                                    R$ {{ number_format($transaction->final_amount, 2, ',', '.') }}
                                                </div>

                                            </div>
                                        @endif

                                        {{-- Cupom --}}
                                        @if ($transaction->coupon)
                                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                                <span
                                                    class="px-3 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest 
                                                    bg-blue-600/10 border border-blue-500/20 text-blue-400">
                                                    CUPOM APLICADO
                                                </span>

                                                <span class="text-[9px] font-black text-blue-500 tracking-widest">
                                                    {{ $transaction->coupon->code }}
                                                </span>
                                            </div>
                                        @endif

                                        <div class="text-[8px] font-bold text-slate-600 uppercase tracking-widest mt-2">
                                            ID: #{{ str_pad($transaction->id, 8, '0', STR_PAD_LEFT) }}
                                        </div>
                                    </td>

                                    {{-- Tipo --}}
                                    <td class="px-10 py-8 text-center">
                                        <span
                                            class="px-4 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest border
                                            @if ($transaction->type === 'credit') bg-emerald-500/10 border-emerald-500/20 text-emerald-500
                                            @else 
                                                bg-red-500/10 border-red-500/20 text-red-500 @endif">
                                            {{ $transaction->type === 'credit' ? 'ENTRADA' : 'SAÍDA' }}
                                        </span>
                                    </td>

                                    {{-- Valor --}}
                                    <td class="px-10 py-8 text-right whitespace-nowrap">
                                        <div
                                            class="text-xl font-black tabular-nums {{ $transaction->type === 'credit' ? 'text-emerald-500' : 'text-red-500' }}">
                                            {{ $transaction->type === 'credit' ? '+' : '-' }}
                                            {{ number_format(abs($transaction->amount), 0, ',', '.') }}
                                        </div>
                                        <div
                                            class="text-[8px] font-black text-slate-600 uppercase tracking-tighter mt-1">
                                            Créditos
                                        </div>
                                    </td>

                                    {{-- Saldo --}}
                                    <td class="px-10 py-8 text-right whitespace-nowrap">
                                        <div class="text-sm font-black text-white tabular-nums tracking-tighter">
                                            {{ number_format($transaction->balance_after ?? 0, 0, ',', '.') }}
                                        </div>
                                        <div
                                            class="text-[8px] font-black text-slate-600 uppercase tracking-tighter mt-1">
                                            Saldo Após
                                        </div>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-10 py-8 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <div
                                                class="w-1.5 h-1.5 rounded-full 
                                                @if ($transaction->status === 'completed') bg-emerald-500 shadow-[0_0_8px_#10b981]
                                                @elseif($transaction->status === 'pending') 
                                                    bg-yellow-500 animate-pulse shadow-[0_0_8px_#f59e0b]
                                                @else 
                                                    bg-slate-500 @endif">
                                            </div>

                                            <span
                                                class="text-[10px] font-black uppercase tracking-widest
                                                @if ($transaction->status === 'completed') text-emerald-500
                                                @elseif($transaction->status === 'pending') 
                                                    text-yellow-500
                                                @else 
                                                    text-slate-500 @endif">
                                                {{ $transaction->status === 'completed' ? 'SUCESSO' : ($transaction->status === 'pending' ? 'AGUARDANDO' : 'FALHA') }}
                                            </span>
                                        </div>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-10 py-32 text-center">
                                        <div
                                            class="inline-flex items-center justify-center w-24 h-24 bg-white/[0.02] rounded-[2.5rem] text-4xl mb-6 border border-white/5 shadow-inner">
                                            📜
                                        </div>
                                        <div
                                            class="text-xs font-black text-white uppercase tracking-[0.3em] mb-2 italic">
                                            Sem Movimentações
                                        </div>
                                        <p
                                            class="text-[10px] text-slate-600 font-bold uppercase tracking-widest italic">
                                            Nenhum registro foi encontrado nesta conta até o momento.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($this->transactions->hasPages())
                    <div class="px-10 py-10 border-t border-white/5 bg-white/[0.01]">
                        {{ $this->transactions->links() }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Rodapé --}}
        <div class="mt-12 flex flex-col md:flex-row items-center gap-6 px-4 opacity-40 italic">
            <div class="flex items-center gap-3">
                <span class="text-[10px] font-black text-blue-500 uppercase tracking-[0.3em]">
                    Conexão Segura
                </span>
                <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                <span class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em]">
                    Logs Verificados
                </span>
            </div>
            <div class="h-[1px] flex-1 bg-gradient-to-r from-white/10 to-transparent hidden md:block"></div>
            <div class="text-[9px] font-bold text-slate-600 uppercase tracking-widest italic text-center md:text-right">
                As transações podem levar alguns minutos para serem processadas pela rede.
            </div>
        </div>
    </div>

    <x-toast />
</div>
