<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Wallet\Package;
use Livewire\Attributes\Layout;

new  class extends Component {
    public ?int $selectedPackageId = null;
    public bool $showConfirmation = false;

    #[Computed]
    public function user() { return auth()->user(); }

    #[Computed]
    public function packages() { return Package::active()->get(); }

    #[Computed]
    public function selectedPackage(): ?Package
    {
        return $this->selectedPackageId 
            ? Package::find($this->selectedPackageId) 
            : null;
    }

    public function selectPackage(int $packageId): void
    {
        $this->selectedPackageId = $packageId;
        $this->showConfirmation = true;
    }

    public function cancelPurchase(): void
    {
        $this->selectedPackageId = null;
        $this->showConfirmation = false;
    }

    public function confirmPurchase(): void
    {
        if (!$this->selectedPackage) {
            session()->flash('error', 'Operação inválida. Selecione um pacote.');
            return;
        }

        $this->user->wallet->credit(
            $this->selectedPackage->credits,
            "Compra do pacote {$this->selectedPackage->name}",
            $this->selectedPackage
        );

        session()->flash('success', "Recarga concluída! +{$this->selectedPackage->credits} créditos adicionados.");
        
        $this->cancelPurchase();
        unset($this->user);
    }
};
?>

<div class="min-h-screen bg-[#0b0d11] text-slate-200 font-sans selection:bg-blue-500/30 overflow-x-hidden pb-20">
    {{-- Brilhos de Fundo --}}
    <div class="fixed top-0 right-1/4 w-[500px] h-[500px] bg-blue-600/5 rounded-full blur-[120px] -z-10"></div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        
        {{-- Cabeçalho --}}
        <div class="relative mb-10">
            <div class="flex items-center gap-3 mb-3">
                <span class="h-[2px] w-12 bg-blue-600"></span>
                <span class="text-blue-500 font-black tracking-[0.3em] uppercase text-[10px] italic">Financeiro & Créditos</span>
            </div>
            <h1 class="text-4xl sm:text-5xl font-black text-white tracking-tighter uppercase italic leading-none">
                MINHA <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-cyan-400">CARTEIRA</span>
            </h1>
        </div>

        {{-- Alerts --}}
        @if (session('success'))
            <div class="mb-8 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-6 py-4 rounded-2xl flex items-center gap-4 animate-in fade-in slide-in-from-top-4 duration-300">
                <span class="text-xl">✅</span>
                <span class="text-xs font-black uppercase tracking-widest">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-8 bg-red-500/10 border border-red-500/20 text-red-400 px-6 py-4 rounded-2xl flex items-center gap-4 animate-in fade-in slide-in-from-top-4 duration-300">
                <span class="text-xl">⚠️</span>
                <span class="text-xs font-black uppercase tracking-widest">{{ session('error') }}</span>
            </div>
        @endif

        {{-- Card de Saldo --}}
        <div class="relative mb-12 group">
            <div class="absolute -inset-1 bg-gradient-to-r from-blue-600/20 to-cyan-500/20 rounded-[2.5rem] blur opacity-25"></div>
            <div class="relative bg-[#161920] border border-white/10 rounded-[2.5rem] p-8 sm:p-10 shadow-2xl overflow-hidden">
                <div class="absolute top-0 right-0 p-8 opacity-5">
                    <svg class="w-32 h-32 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M21 18V19C21 20.1 20.1 21 19 21H5C3.89 21 3 20.1 3 19V5C3 3.9 3.89 3 5 3H19C20.1 3 21 3.9 21 5V6H12C10.89 6 10 6.89 10 8V16C10 17.11 10.89 18 12 18H21M12 16H22V8H12V16M16 13.5C15.17 13.5 14.5 12.83 14.5 12C14.5 11.17 15.17 10.5 16 10.5C16.83 10.5 17.5 11.17 17.5 12C17.5 12.83 16.83 13.5 16 13.5Z"/></svg>
                </div>
                
                <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-8">
                    <div>
                        <span class="text-blue-500 text-[10px] font-black uppercase tracking-[0.3em] mb-2 block">Saldo Disponível</span>
                        <div class="flex items-baseline gap-3">
                            <span class="text-5xl sm:text-7xl font-black text-white tracking-tighter italic">
                                {{ number_format($this->user->wallet->balance ?? 0, 0) }}
                            </span>
                            <span class="text-blue-500 font-black uppercase text-xs tracking-widest italic">Créditos</span>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-3 w-full md:w-auto">
                        <div class="px-6 py-4 bg-white/5 border border-white/5 rounded-2xl backdrop-blur-sm">
                            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Status da Conta</p>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                <span class="text-xs font-black text-white uppercase italic">Operativo Ativo</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Seleção de Pacotes --}}
        <div class="mb-12">
            <div class="flex items-center justify-between mb-8 px-2">
                <h2 class="text-sm font-black text-white uppercase tracking-[0.2em] italic">Pacotes de Suprimentos</h2>
                <span class="h-[1px] flex-1 mx-6 bg-white/5"></span>
            </div>

            @if ($this->packages->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($this->packages as $package)
                        <div class="relative group cursor-pointer" wire:click="selectPackage({{ $package->id }})">
                            <div class="absolute -inset-0.5 bg-white/5 rounded-[2rem] transition-all group-hover:bg-blue-600/20 group-hover:blur-sm"></div>
                            <div class="relative bg-[#161920] border border-white/5 rounded-[2rem] p-8 flex flex-col items-center transition-all group-hover:-translate-y-2 group-hover:border-blue-500/30">
                                
                                <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4 italic">{{ $package->name }}</div>
                                
                                <div class="w-16 h-16 bg-blue-600/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                    <span class="text-2xl">💎</span>
                                </div>

                                <div class="text-4xl font-black text-white tracking-tighter mb-1 tabular-nums">
                                    {{ number_format($package->credits, 0) }}
                                </div>
                                <div class="text-[9px] font-black text-blue-500 uppercase tracking-[0.2em] mb-8">Créditos</div>

                                <div class="w-full pt-6 border-t border-white/5 text-center">
                                    <div class="text-2xl font-black text-white italic mb-4">
                                        <span class="text-xs font-bold text-slate-500 not-italic mr-1">R$</span>{{ number_format($package->price_brl, 2, ',', '.') }}
                                    </div>
                                    <button class="w-full py-3 bg-white/5 group-hover:bg-blue-600 text-[10px] font-black uppercase tracking-widest text-white rounded-xl transition-all">
                                        Adquirir
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-[#161920] border border-dashed border-white/10 rounded-[2rem] py-16 text-center">
                    <span class="text-4xl block mb-4">📦</span>
                    <p class="text-xs font-black text-slate-500 uppercase tracking-widest italic">Nenhum suprimento disponível no momento</p>
                </div>
            @endif
        </div>

        {{-- Atalhos --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="{{ route('wallet.transactions') }}" 
                class="group bg-[#161920] border border-white/5 rounded-2xl p-6 flex items-center justify-between transition-all hover:bg-white/[0.03]">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white/5 rounded-xl flex items-center justify-center text-xl group-hover:scale-110 transition">📜</div>
                    <div>
                        <div class="text-xs font-black text-white uppercase tracking-tight">Histórico de Transações</div>
                        <div class="text-[9px] font-bold text-slate-500 uppercase italic">Logs de movimentação</div>
                    </div>
                </div>
                <span class="text-slate-700 group-hover:text-blue-500 transition">→</span>
            </a>

            <a href="{{ route('dashboard') }}" 
                class="group bg-[#161920] border border-white/5 rounded-2xl p-6 flex items-center justify-between transition-all hover:bg-white/[0.03]">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white/5 rounded-xl flex items-center justify-center text-xl group-hover:scale-110 transition">🏠</div>
                    <div>
                        <div class="text-xs font-black text-white uppercase tracking-tight">Voltar ao Painel</div>
                        <div class="text-[9px] font-bold text-slate-500 uppercase italic">Início da Arena</div>
                    </div>
                </div>
                <span class="text-slate-700 group-hover:text-blue-500 transition">→</span>
            </a>
        </div>
    </div>

    {{-- Modal de Confirmação --}}
    @if ($showConfirmation && $this->selectedPackage)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/95 backdrop-blur-md" wire:click="cancelPurchase"></div>
            <div class="relative bg-[#0f1115] w-full max-w-md rounded-[3rem] border border-blue-600/30 overflow-hidden shadow-2xl animate-in zoom-in duration-200">
                <div class="h-24 bg-gradient-to-r from-blue-900/40 to-cyan-900/40 border-b border-white/5"></div>
                
                <div class="px-8 pb-10">
                    <div class="relative -mt-12 mb-8 text-center">
                        <div class="w-24 h-24 bg-blue-600 rounded-[2rem] mx-auto flex items-center justify-center text-4xl shadow-2xl mb-4 border-4 border-[#0f1115]">
                            💎
                        </div>
                        <h3 class="text-2xl font-black text-white uppercase italic tracking-tighter">Confirmar Recarga</h3>
                        <p class="text-[9px] font-black text-blue-400 uppercase tracking-[0.2em] mt-1">Operação Protocolada</p>
                    </div>

                    <div class="bg-white/[0.03] border border-white/5 rounded-2xl p-6 mb-8">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Produto</span>
                            <span class="text-xs font-black text-white uppercase italic">{{ $this->selectedPackage->name }}</span>
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Créditos</span>
                            <span class="text-lg font-black text-blue-500">+{{ number_format($this->selectedPackage->credits, 0) }}</span>
                        </div>
                        <div class="h-[1px] bg-white/5 my-4"></div>
                        <div class="flex justify-between items-center">
                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Valor Final</span>
                            <span class="text-xl font-black text-white italic">R$ {{ number_format($this->selectedPackage->price_brl, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <button wire:click="confirmPurchase"
                            class="w-full py-4 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-[11px] font-black uppercase tracking-widest transition-all shadow-lg shadow-blue-600/20 active:scale-95">
                            Confirmar Pagamento
                        </button>
                        <button wire:click="cancelPurchase"
                            class="w-full py-4 bg-white/5 hover:bg-white/10 text-slate-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all border border-white/10">
                            Abortar Operação
                        </button>
                    </div>

                    <p class="text-[8px] text-slate-600 font-bold uppercase text-center mt-6 tracking-widest italic">
                        ⚠️ Sistema de simulação para desenvolvimento
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>