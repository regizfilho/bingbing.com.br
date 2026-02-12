<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new class extends Component {
    use WithPagination;

    #[Computed]
    public function user()
    {
        return auth()->user();
    }

    #[Computed]
    public function transactions()
    {
        return $this->user->wallet->transactions()
            ->with('transactionable')
            ->latest()
            ->paginate(15);
    }
};
?>

<div class="min-h-screen bg-[#0b0d11] text-slate-200 selection:bg-blue-500/30 overflow-x-hidden pb-20">
    {{-- Efeito de Luz de Fundo --}}
    <div class="fixed top-0 left-0 w-[600px] h-[600px] bg-blue-600/5 rounded-full blur-[140px] -z-10"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        
        {{-- Cabeçalho Estilizado --}}
        <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <span class="h-[2px] w-12 bg-blue-600"></span>
                    <span class="text-blue-500 font-black tracking-[0.3em] uppercase text-[10px] italic">Registros de Segurança</span>
                </div>
                <h1 class="text-4xl sm:text-5xl font-black text-white tracking-tighter uppercase italic leading-none">
                    LOG DE <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-cyan-400">OPERAÇÕES</span>
                </h1>
            </div>

            <a href="{{ route('wallet.index') }}" 
                class="inline-flex items-center gap-3 px-6 py-3 bg-white/5 border border-white/10 rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-white hover:bg-white/10 transition-all group">
                <span class="group-hover:-translate-x-1 transition-transform">←</span> Voltar para Carteira
            </a>
        </div>

        {{-- Container Principal da Tabela --}}
        <div class="bg-[#161920] border border-white/10 rounded-[2rem] shadow-2xl overflow-hidden">
            <div class="px-8 py-6 border-b border-white/5 bg-white/[0.02] flex items-center justify-between">
                <h3 class="text-xs font-black text-white uppercase tracking-widest italic">Histórico de Movimentação</h3>
                <div class="px-3 py-1 bg-blue-500/10 border border-blue-500/20 rounded-md text-[9px] font-black text-blue-400 uppercase tracking-tighter">
                    {{ $this->transactions->total() }} Registros
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/[0.01]">
                            <th class="px-8 py-5 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Data / ID</th>
                            <th class="px-8 py-5 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Descrição do Evento</th>
                            <th class="px-8 py-5 text-center text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Tipo</th>
                            <th class="px-8 py-5 text-right text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Montante</th>
                            <th class="px-8 py-5 text-right text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Saldo Final</th>
                            <th class="px-8 py-5 text-center text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($this->transactions as $transaction)
                            <tr class="group hover:bg-white/[0.02] transition-colors">
                                <td class="px-8 py-6 whitespace-nowrap">
                                    <div class="text-[11px] font-black text-white tracking-tight">
                                        {{ $transaction->created_at->format('d/m/Y') }}
                                    </div>
                                    <div class="text-[9px] font-bold text-slate-600 uppercase italic">
                                        {{ $transaction->created_at->format('H:i:s') }}
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="text-[11px] font-bold text-slate-300 uppercase tracking-wide group-hover:text-white transition-colors">
                                        {{ $transaction->description }}
                                    </div>
                                    <div class="text-[8px] font-black text-blue-500/50 uppercase tracking-[0.1em] mt-1">
                                        REF: #{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <span class="px-3 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest border
                                        @if ($transaction->type === 'credit') bg-emerald-500/10 border-emerald-500/20 text-emerald-500
                                        @elseif($transaction->type === 'debit') bg-red-500/10 border-red-500/20 text-red-500
                                        @else bg-blue-500/10 border-blue-500/20 text-blue-500 @endif">
                                        {{ $transaction->type === 'credit' ? 'Crédito' : 'Débito' }}
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-right whitespace-nowrap">
                                    <div class="text-lg font-black italic tabular-nums {{ $transaction->type === 'credit' ? 'text-emerald-500' : 'text-red-500' }}">
                                        {{ $transaction->type === 'credit' ? '+' : '-' }}{{ number_format(abs($transaction->amount), 0) }}
                                    </div>
                                    <div class="text-[8px] font-bold text-slate-600 uppercase">Unidades</div>
                                </td>
                                <td class="px-8 py-6 text-right whitespace-nowrap">
                                    <div class="text-sm font-black text-white tabular-nums tracking-tighter">
                                        {{ number_format($transaction->balance_after ?? 0, 0) }}
                                    </div>
                                    <div class="text-[8px] font-bold text-slate-600 uppercase tracking-tighter">Créditos</div>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full 
                                                @if ($transaction->status === 'completed') bg-emerald-500
                                                @elseif($transaction->status === 'pending') bg-yellow-500 animate-pulse
                                                @else bg-slate-500 @endif"></span>
                                            <span class="text-[9px] font-black uppercase tracking-widest
                                                @if ($transaction->status === 'completed') text-emerald-500
                                                @elseif($transaction->status === 'pending') text-yellow-500
                                                @else text-slate-500 @endif">
                                                {{ $transaction->status === 'completed' ? 'Sucesso' : ($transaction->status === 'pending' ? 'Pendente' : $transaction->status) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-8 py-24 text-center">
                                    <div class="inline-flex items-center justify-center w-20 h-20 bg-white/5 rounded-[2rem] text-3xl mb-4 border border-white/5">📂</div>
                                    <div class="text-xs font-black text-white uppercase tracking-[0.2em] mb-2">Arquivo Vazio</div>
                                    <p class="text-[10px] text-slate-600 font-bold uppercase tracking-widest italic">Nenhuma movimentação foi detectada em sua rede.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginação Customizada --}}
            @if ($this->transactions->hasPages())
                <div class="px-8 py-6 border-t border-white/5 bg-white/[0.01]">
                    {{ $this->transactions->links() }}
                </div>
            @endif
        </div>

        {{-- Nota de Rodapé Estilizada --}}
        <div class="mt-8 flex items-center gap-4 px-4 opacity-50">
            <div class="text-[10px] font-black text-blue-500 uppercase tracking-[0.3em] italic">Encryption Active</div>
            <div class="h-[1px] flex-1 bg-gradient-to-r from-blue-500/50 to-transparent"></div>
            <div class="text-[9px] font-bold text-slate-600 uppercase tracking-widest">End-to-End Log System</div>
        </div>
    </div>

    <style>
        /* Ajuste fino para paginação do Livewire combinar com o tema */
        .pagination span, .pagination a { 
            @apply border-white/10 bg-[#161920] text-slate-400 text-[10px] font-black uppercase rounded-lg !important;
        }
        .pagination .active span {
            @apply bg-blue-600 border-blue-500 text-white !important;
        }
    </style>
</div>