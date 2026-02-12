<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Wallet\Package;
use Livewire\Attributes\Layout;

new class extends Component {
    public ?int $selectedPackageId = null;
    public bool $showConfirmation = false;

    #[Computed]
    public function user() { return auth()->user(); }

    #[Computed]
    public function walletBalance()
    {
        return $this->user?->wallet?->balance ?? 0;
    }

    #[Computed]
    public function packages() { return Package::active()->get(); }

    #[Computed]
    public function selectedPackage(): ?Package
    {
        return $this->selectedPackageId ? Package::find($this->selectedPackageId) : null;
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
        $this->dispatch('notify', type: 'error', text: 'Pacote inválido.');
        return;
    }

    try {
        // Tenta buscar a carteira, se não existir, cria uma nova para o usuário
        $wallet = $this->user->wallet ?: $this->user->wallet()->create(['balance' => 0]);

        $wallet->credit(
            $this->selectedPackage->credits,
            "Compra: {$this->selectedPackage->name}",
            $this->selectedPackage
        );

        $this->dispatch('notify', 
            type: 'success', 
            text: "Recarga concluída! +{$this->selectedPackage->credits} créditos."
        );
        
        $this->cancelPurchase();
        
    } catch (\Exception $e) {
        $this->dispatch('notify', type: 'error', text: 'Falha: ' . $e->getMessage());
    }
}
};
?>

<div class="min-h-screen bg-[#0b0d11] text-slate-200 font-sans selection:bg-blue-500/30 overflow-x-hidden pb-20 relative">
    
    {{-- Background Glows --}}
    <div class="fixed top-0 right-1/4 w-[600px] h-[600px] bg-blue-600/5 rounded-full blur-[140px] -z-10"></div>
    <div class="fixed bottom-0 left-1/4 w-[400px] h-[400px] bg-cyan-500/5 rounded-full blur-[100px] -z-10"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-16">
        
        {{-- Header Section --}}
        <div class="relative mb-16">
            <div class="flex items-center gap-4 mb-4">
                <span class="h-[1px] w-16 bg-gradient-to-r from-blue-600 to-transparent"></span>
                <span class="text-blue-500 font-black tracking-[0.4em] uppercase text-[10px] italic">Módulo de Suprimentos</span>
            </div>
            <h1 class="text-5xl sm:text-7xl font-black text-white tracking-tighter uppercase italic leading-[0.8]">
                MINHA <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 via-blue-400 to-cyan-400">CARTEIRA</span>
            </h1>
        </div>

        {{-- Saldo Principal --}}
        <div class="relative mb-20 group">
            <div class="absolute -inset-1 bg-gradient-to-r from-blue-600/20 to-cyan-500/20 rounded-[3rem] blur opacity-30 group-hover:opacity-50 transition duration-1000"></div>
            <div class="relative bg-[#0f1218] border border-white/5 rounded-[3rem] p-10 sm:p-14 shadow-2xl overflow-hidden">
                {{-- Decor --}}
                <div class="absolute top-0 right-0 w-64 h-64 bg-blue-600/5 blur-3xl rounded-full -mr-20 -mt-20"></div>
                <div class="absolute bottom-0 left-0 w-40 h-40 bg-cyan-600/5 blur-2xl rounded-full -ml-10 -mb-10"></div>
                
                <div class="relative z-10 flex flex-col lg:flex-row justify-between lg:items-center gap-12">
                    <div class="space-y-2">
                        <span class="text-slate-500 text-[11px] font-black uppercase tracking-[0.3em] italic block">Créditos de Combate Disponíveis</span>
                        <div class="flex items-baseline gap-4">
                            <span class="text-blue-500 font-black text-2xl italic">C$</span>
                            <span class="text-7xl sm:text-9xl font-black text-white tracking-tighter italic leading-none tabular-nums">
                                {{ number_format($this->walletBalance, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-6">
                        <div class="px-8 py-6 bg-white/[0.03] border border-white/5 rounded-[2rem] backdrop-blur-md flex flex-col justify-center min-w-[200px]">
                            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2 italic text-center">Protocolo de Rede</p>
                            <div class="flex items-center justify-center gap-3">
                                <span class="w-2 h-2 bg-emerald-500 rounded-full shadow-[0_0_10px_#10b981] animate-pulse"></span>
                                <span class="text-xs font-black text-white uppercase italic tracking-widest">Operativo On</span>
                            </div>
                        </div>
                        <div class="px-8 py-6 bg-white/[0.03] border border-white/5 rounded-[2rem] backdrop-blur-md flex flex-col justify-center">
                            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 italic text-center">Segurança</p>
                            <span class="text-[10px] font-black text-blue-400 uppercase italic text-center tracking-widest leading-none">Criptografado SSL</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Grid de Pacotes --}}
        <div class="mb-20">
            <div class="flex items-center gap-6 mb-12 px-4">
                <h2 class="text-[11px] font-black text-white uppercase tracking-[0.4em] italic whitespace-nowrap">Recarga de Suprimentos</h2>
                <div class="h-[1px] w-full bg-white/5"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @forelse ($this->packages as $package)
                    <div class="relative group" wire:click="selectPackage({{ $package->id }})">
                        <div class="absolute -inset-0.5 bg-gradient-to-b from-blue-600/0 to-blue-600/0 rounded-[2.5rem] transition-all duration-500 group-hover:from-blue-600/20 group-hover:to-cyan-400/20"></div>
                        <div class="relative bg-[#0f1218] border border-white/5 rounded-[2.5rem] p-10 flex flex-col items-center cursor-pointer transition-all duration-500 group-hover:-translate-y-3 group-hover:border-blue-500/40">
                            
                            <span class="absolute top-6 left-8 text-[9px] font-black text-slate-600 uppercase tracking-widest italic group-hover:text-blue-500 transition-colors">Pacote {{ $loop->iteration }}</span>
                            
                            <div class="w-20 h-20 bg-white/[0.02] border border-white/5 rounded-3xl flex items-center justify-center text-4xl mb-8 group-hover:scale-110 group-hover:bg-blue-600/10 transition-all duration-500">
                                💎
                            </div>

                            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 italic">{{ $package->name }}</div>
                            <div class="text-5xl font-black text-white tracking-tighter mb-8 italic group-hover:scale-105 transition-transform">
                                {{ number_format($package->credits, 0, ',', '.') }}<span class="text-blue-600 text-sm ml-1 uppercase">c$</span>
                            </div>

                            <div class="w-full pt-8 border-t border-white/5 flex flex-col items-center">
                                <div class="text-3xl font-black text-white italic mb-6">
                                    <span class="text-xs font-bold text-slate-500 not-italic mr-1">R$</span>{{ number_format($package->price_brl, 2, ',', '.') }}
                                </div>
                                <button class="w-full py-4 bg-white/5 group-hover:bg-blue-600 text-[10px] font-black uppercase tracking-[0.2em] text-white rounded-2xl transition-all shadow-xl shadow-black/20">
                                    Selecionar
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-20 bg-[#0f1218] rounded-[3rem] border border-dashed border-white/10 flex flex-col items-center justify-center">
                        <span class="text-5xl mb-4 opacity-20">📦</span>
                        <p class="text-[10px] font-black text-slate-600 uppercase tracking-widest italic">Nenhum pacote disponível na central de comando</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Atalhos Rodapé --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <a href="{{ route('wallet.transactions') }}" class="group bg-[#0f1218] border border-white/5 rounded-[2rem] p-8 flex items-center justify-between transition-all hover:bg-white/[0.02] hover:border-blue-500/30">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 bg-white/5 rounded-2xl flex items-center justify-center text-2xl group-hover:rotate-12 transition-transform">📜</div>
                    <div>
                        <div class="text-sm font-black text-white uppercase italic tracking-tight">Histórico de Movimentação</div>
                        <div class="text-[9px] font-bold text-slate-600 uppercase tracking-widest mt-1">Logs de Auditoria Alpha-1</div>
                    </div>
                </div>
                <div class="w-10 h-10 border border-white/5 rounded-full flex items-center justify-center text-slate-700 group-hover:text-blue-500 group-hover:border-blue-500/50 transition-all italic font-black">→</div>
            </a>

            <a href="{{ route('dashboard') }}" class="group bg-[#0f1218] border border-white/5 rounded-[2rem] p-8 flex items-center justify-between transition-all hover:bg-white/[0.02] hover:border-blue-500/30">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 bg-white/5 rounded-2xl flex items-center justify-center text-2xl group-hover:-rotate-12 transition-transform">🏠</div>
                    <div>
                        <div class="text-sm font-black text-white uppercase italic tracking-tight">Centro de Comando</div>
                        <div class="text-[9px] font-bold text-slate-600 uppercase tracking-widest mt-1">Retornar ao Painel Inicial</div>
                    </div>
                </div>
                <div class="w-10 h-10 border border-white/5 rounded-full flex items-center justify-center text-slate-700 group-hover:text-blue-500 group-hover:border-blue-500/50 transition-all italic font-black">→</div>
            </a>
        </div>
    </div>

    {{-- Modal de Confirmação --}}
    @if ($showConfirmation && $this->selectedPackage)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-6">
            {{-- Overlay --}}
            <div class="absolute inset-0 bg-[#050608]/95 backdrop-blur-xl" wire:click="cancelPurchase"></div>
            
            {{-- Content --}}
            <div class="relative bg-[#0f1218] w-full max-w-lg rounded-[4rem] border border-blue-600/30 overflow-hidden shadow-[0_0_100px_rgba(37,99,235,0.15)] animate-in zoom-in duration-300">
                {{-- Glow superior na modal --}}
                <div class="h-2 bg-gradient-to-r from-blue-600 to-cyan-400"></div>

                <div class="px-10 py-14">
                    <div class="text-center mb-12">
                        <div class="w-24 h-24 bg-blue-600/10 border border-blue-500/30 rounded-[2.5rem] mx-auto flex items-center justify-center text-4xl mb-6 shadow-2xl">💎</div>
                        <h3 class="text-3xl font-black text-white uppercase italic tracking-tighter leading-none">AUTORIZAR <span class="text-blue-500">RECARGA</span></h3>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.4em] mt-3 italic">Confirme a requisição abaixo</p>
                    </div>

                    <div class="bg-white/[0.02] border border-white/5 rounded-[2.5rem] p-10 mb-10 space-y-6">
                        <div class="flex justify-between items-center border-b border-white/5 pb-6">
                            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest italic">Suprimento</span>
                            <span class="text-xs font-black text-white uppercase italic tracking-widest">{{ $this->selectedPackage->name }}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-white/5 pb-6">
                            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest italic">Volume de Créditos</span>
                            <span class="text-2xl font-black text-blue-500 italic">+{{ number_format($this->selectedPackage->credits, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-[10px] font-black text-blue-400 uppercase tracking-widest italic">Custo Total</span>
                            <span class="text-4xl font-black text-white italic tracking-tighter">R$ {{ number_format($this->selectedPackage->price_brl, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4">
                        <button wire:click="confirmPurchase"
                            class="w-full py-6 bg-blue-600 hover:bg-blue-500 text-white rounded-[2rem] text-[11px] font-black uppercase tracking-[0.3em] italic transition-all shadow-2xl shadow-blue-600/30 active:scale-95 group">
                            <span class="group-hover:tracking-[0.4em] transition-all">Confirmar Pagamento</span>
                        </button>
                        <button wire:click="cancelPurchase"
                            class="w-full py-5 bg-transparent text-slate-600 hover:text-white rounded-[2rem] text-[9px] font-black uppercase tracking-[0.2em] italic transition-all border border-white/5 hover:border-white/10">
                            Abortar Transação
                        </button>
                    </div>

                    <div class="mt-8 flex justify-center gap-2">
                        <div class="w-1 h-1 bg-blue-600 rounded-full animate-ping"></div>
                        <div class="w-1 h-1 bg-blue-600 rounded-full animate-ping delay-75"></div>
                        <div class="w-1 h-1 bg-blue-600 rounded-full animate-ping delay-150"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 
        SISTEMA DE NOTIFICAÇÃO (TOAST)
        Posicionado como último elemento para garantir prioridade de stack.
        O componente <x-toast /> contém o z-[9999].
    --}}
    <x-toast />
</div>