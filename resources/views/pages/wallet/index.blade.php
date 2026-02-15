<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Wallet\Package;
use App\Models\Coupon;
use App\Models\CouponUser;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {
    public bool $isProcessing = false;

    public ?string $param = null;
    public ?int $selectedPackageId = null;
    public bool $showConfirmation = false;

    public string $couponCode = '';
    public ?Coupon $appliedCoupon = null;
    public float $discountAmount = 0;

    public function mount(?string $param = null): void
    {
        $this->param = $param;

        if ($param === 'success') {
            $this->dispatch('notify', type: 'success', text: '🎉 Pagamento confirmado!');
        } elseif ($param === 'cancel') {
            $this->dispatch('notify', type: 'warning', text: 'Pagamento cancelado.');
        }
    }

    #[Computed]
    public function user()
    {
        return auth()->user();
    }

    #[Computed]
    public function walletBalance()
    {
        return $this->user?->wallet?->balance ?? 0;
    }

    #[Computed]
    public function walletStats(): array
    {
        $wallet = $this->user?->wallet;
        
        if (!$wallet) {
            return [
                'total_transactions' => 0,
                'total_earned' => 0,
                'total_spent' => 0,
                'last_transaction' => null,
            ];
        }

        $transactions = $wallet->transactions();

        return [
            'total_transactions' => $transactions->count(),
            'total_earned' => $transactions->where('type', 'credit')->sum('amount'),
            'total_spent' => $transactions->where('type', 'debit')->sum('amount'),
            'last_transaction' => $transactions->latest()->first(),
        ];
    }

    #[Computed]
    public function packages()
    {
        return Package::active()->orderBy('credits', 'asc')->get();
    }

    #[Computed]
    public function selectedPackage(): ?Package
    {
        return $this->selectedPackageId ? Package::find($this->selectedPackageId) : null;
    }

    #[Computed]
    public function finalPrice(): float
    {
        if (!$this->selectedPackage) {
            return 0;
        }
        return max(0, $this->selectedPackage->price_brl - $this->discountAmount);
    }

    public function selectPackage(int $packageId): void
    {
        if ($this->isProcessing) {
            return;
        }

        $this->reset(['couponCode', 'appliedCoupon', 'discountAmount']);
        $this->selectedPackageId = $packageId;
        $this->showConfirmation = true;
        $this->resetValidation();
    }

    public function cancelPurchase(): void
    {
        if ($this->isProcessing) {
            return;
        }

        $this->selectedPackageId = null;
        $this->showConfirmation = false;
        $this->reset(['couponCode', 'appliedCoupon', 'discountAmount']);
        $this->resetValidation();
    }

    public function applyCoupon(): void
    {
        if ($this->isProcessing) {
            return;
        }

        $this->validate([
            'couponCode' => 'required|string|min:3',
        ], [
            'couponCode.required' => 'Digite um código de cupom',
            'couponCode.min' => 'Código inválido',
        ]);

        try {
            $code = strtoupper(trim($this->couponCode));

            $coupon = Coupon::where('code', $code)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->first();

            if (!$coupon) {
                Log::info('Coupon not found or expired', [
                    'user_id' => $this->user->id,
                    'code' => $code,
                ]);

                $this->addError('couponCode', 'Cupom inválido ou expirado');
                return;
            }

            if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
                Log::info('Coupon max uses exceeded', [
                    'user_id' => $this->user->id,
                    'coupon_id' => $coupon->id,
                    'code' => $code,
                    'used_count' => $coupon->used_count,
                    'max_uses' => $coupon->max_uses,
                ]);

                $this->addError('couponCode', 'Cupom esgotado');
                return;
            }

            if ($coupon->max_uses_per_user) {
                $userUses = CouponUser::where('coupon_id', $coupon->id)
                    ->where('user_id', $this->user->id)
                    ->count();
                
                if ($userUses >= $coupon->max_uses_per_user) {
                    Log::info('Coupon user limit exceeded', [
                        'user_id' => $this->user->id,
                        'coupon_id' => $coupon->id,
                        'code' => $code,
                        'user_uses' => $userUses,
                        'max_uses_per_user' => $coupon->max_uses_per_user,
                    ]);

                    $this->addError('couponCode', 'Você já usou este cupom');
                    return;
                }
            }

            if ($coupon->min_purchase_amount && $this->selectedPackage->price_brl < $coupon->min_purchase_amount) {
                $this->addError('couponCode', 'Valor mínimo: R$ ' . number_format($coupon->min_purchase_amount, 2, ',', '.'));
                return;
            }

            if ($coupon->discount_type === 'percentage') {
                $this->discountAmount = ($this->selectedPackage->price_brl * $coupon->discount_value) / 100;
            } else {
                $this->discountAmount = $coupon->discount_value;
            }

            $this->discountAmount = min($this->discountAmount, $this->selectedPackage->price_brl);

            $this->appliedCoupon = $coupon;

            Log::info('Coupon applied', [
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
                'code' => $code,
                'discount_amount' => $this->discountAmount,
                'package_id' => $this->selectedPackage->id,
            ]);

            $this->dispatch('notify', type: 'success', text: '🎫 Cupom aplicado com sucesso!');

        } catch (\Exception $e) {
            Log::error('Coupon validation failed', [
                'user_id' => $this->user->id,
                'code' => strtoupper(trim($this->couponCode)),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addError('couponCode', 'Erro ao validar cupom');
        }
    }

    public function removeCoupon(): void
    {
        if ($this->isProcessing) {
            return;
        }

        $this->reset(['couponCode', 'appliedCoupon', 'discountAmount']);
        $this->dispatch('notify', type: 'info', text: 'Cupom removido');
    }

    public function confirmPurchase(): void
    {
        if ($this->isProcessing) {
            return;
        }

        $this->isProcessing = true;

        if (!$this->selectedPackage) {
            $this->dispatch('notify', type: 'error', text: 'Pacote inválido.');
            $this->isProcessing = false;
            return;
        }

        try {
            DB::transaction(function () {
                $wallet = $this->user->wallet ?: $this->user->wallet()->create(['balance' => 0]);

                $wallet->refresh();

                $originalAmount = $this->selectedPackage->price_brl;
                $discountAmount = $this->discountAmount;
                $finalAmount = $this->finalPrice;

                $wallet = $wallet->lockForUpdate()->first();

                $wallet->balance += $this->selectedPackage->credits;
                $wallet->save();

                $transaction = $wallet->transactions()->create([
                    'uuid' => Str::uuid(),
                    'type' => 'credit',
                    'amount' => $this->selectedPackage->credits,
                    'balance_after' => $wallet->balance,
                    'description' => "Recarga: {$this->selectedPackage->name}",
                    'transactionable_type' => Package::class,
                    'transactionable_id' => $this->selectedPackage->id,
                    'package_id' => $this->selectedPackage->id,
                    'status' => 'completed',
                    'coupon_id' => $this->appliedCoupon?->id,
                    'original_amount' => $originalAmount,
                    'discount_amount' => $discountAmount,
                    'final_amount' => $finalAmount,
                ]);

                if ($this->appliedCoupon) {
                    CouponUser::create([
                        'coupon_id' => $this->appliedCoupon->id,
                        'user_id' => $this->user->id,
                        'used_at' => now(),
                        'order_value' => $originalAmount,
                        'discount_amount' => $discountAmount,
                        'ip_address' => request()->ip(),
                    ]);

                    $this->appliedCoupon->increment('used_count');
                }

                Log::info('Package purchased', [
                    'user_id' => $this->user->id,
                    'package_id' => $this->selectedPackage->id,
                    'transaction_id' => $transaction->id,
                    'credits' => $this->selectedPackage->credits,
                    'original_amount' => $originalAmount,
                    'discount_amount' => $discountAmount,
                    'final_amount' => $finalAmount,
                    'coupon_id' => $this->appliedCoupon?->id,
                    'balance_after' => $wallet->balance,
                ]);
            });

            $this->dispatch('notify', type: 'success', text: 'Recarga realizada com sucesso!');
            $this->cancelPurchase();
            
            unset($this->walletStats);
            
        } catch (\Throwable $e) {
            Log::error('Package purchase failed', [
                'user_id' => $this->user->id,
                'package_id' => $this->selectedPackageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('notify', type: 'error', text: 'Erro na transação: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }
};
?>


<div class="min-h-screen bg-[#05070a] text-slate-200 pb-24 selection:bg-blue-500/30 overflow-x-hidden relative">

    {{-- Loading --}}
    <x-loading target="confirmPurchase, selectPackage, applyCoupon, removeCoupon" message="PROCESSANDO..." overlay />

    {{-- Efeitos de Fundo --}}
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
        <div class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-blue-600/10 blur-[120px] rounded-full"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[400px] h-[400px] bg-cyan-500/5 blur-[100px] rounded-full">
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-6 pt-16">

        {{-- Banner de Sucesso (aparece apenas quando param=success) --}}
        @if ($param === 'success')
            <div class="mb-12 animate-in fade-in slide-in-from-top duration-500" x-data="{ show: true }" x-show="show">
                <div
                    class="relative bg-gradient-to-r from-emerald-600/10 to-emerald-500/10 border border-emerald-500/30 rounded-3xl p-8 overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-600 to-emerald-400">
                    </div>

                    <button @click="show = false"
                        class="absolute top-4 right-4 text-emerald-400 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>

                    <div class="flex items-start gap-6">
                        <div
                            class="w-16 h-16 bg-emerald-500/20 rounded-2xl flex items-center justify-center flex-shrink-0 text-4xl">
                            🎉
                        </div>
                        <div class="flex-1">
                            <h3 class="text-2xl font-black text-emerald-400 uppercase italic mb-2">
                                Pagamento Confirmado!
                            </h3>
                            <p class="text-sm text-slate-300 leading-relaxed mb-4">
                                Sua compra foi processada com sucesso e os créditos já estão disponíveis na sua
                                carteira.
                                Enviamos um email de confirmação com todos os detalhes da transação.
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <a href="{{ route('games.create') }}"
                                    class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-wider transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z">
                                        </path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Começar a Jogar
                                </a>
                                <a href="{{ route('wallet.transactions') }}"
                                    class="inline-flex items-center gap-2 bg-white/5 hover:bg-white/10 border border-white/10 text-white px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-wider transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                        </path>
                                    </svg>
                                    Ver Extrato
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Cabeçalho --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 gap-8">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 bg-blue-600 rounded-full animate-pulse"></div>
                    <span class="text-[10px] font-black text-blue-500 uppercase tracking-[0.5em] italic">
                        Gerenciamento de Créditos
                    </span>
                </div>
                <h1 class="text-6xl font-black text-white uppercase italic tracking-tighter leading-none">
                    MINHA <span class="text-blue-600">CARTEIRA</span>
                </h1>
            </div>

            <div
                class="flex items-center gap-6 bg-white/[0.02] border border-white/5 p-4 rounded-3xl backdrop-blur-md shadow-2xl">
                <div class="text-right">
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic leading-none mb-1">
                        Status da Conta
                    </p>
                    <p class="text-[11px] font-black text-emerald-500 uppercase italic">Conectado com Segurança</p>
                </div>
                <div
                    class="w-12 h-12 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl flex items-center justify-center text-xl shadow-lg">
                    ✅
                </div>
            </div>
        </div>

        {{-- Card de Saldo Principal --}}
        <div class="relative mb-16 group">
            <div
                class="absolute -inset-0.5 bg-gradient-to-r from-blue-600/50 to-cyan-500/50 rounded-[3.5rem] blur opacity-20 transition duration-1000">
            </div>

            <div
                class="relative bg-[#0b0d11] border border-white/5 rounded-[3.5rem] p-10 md:p-16 shadow-2xl overflow-hidden">
                <div class="relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    <div class="space-y-6">
                        <div
                            class="inline-flex items-center px-4 py-2 bg-blue-600/10 border border-blue-500/20 rounded-full">
                            <span class="text-[9px] font-black text-blue-400 uppercase tracking-widest italic">
                                Saldo Disponível para Uso
                            </span>
                        </div>

                        <div class="flex items-start gap-4">
                            <span class="text-4xl font-black text-blue-600 italic mt-2 font-mono">C$</span>
                            <span
                                class="text-8xl md:text-9xl font-black text-white tracking-tighter italic leading-none tabular-nums">
                                {{ number_format($this->walletBalance, 0, ',', '.') }}
                            </span>
                        </div>

                        <div class="w-full h-2 bg-white/5 rounded-full overflow-hidden shadow-inner">
                            <div class="h-full bg-gradient-to-r from-blue-600 to-cyan-400 transition-all duration-1000"
                                style="width: {{ min(($this->walletBalance / 1000) * 100, 100) }}%">
                            </div>
                        </div>

                        {{-- Estatísticas Rápidas --}}
                        <div class="grid grid-cols-3 gap-4 pt-4">
                            <div class="text-center">
                                <div class="text-2xl font-black text-emerald-400">
                                    {{ number_format($this->walletStats['total_transactions'], 0) }}
                                </div>
                                <div class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mt-1">
                                    Transações
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-black text-blue-400">
                                    {{ number_format($this->walletStats['total_earned'], 0) }}
                                </div>
                                <div class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mt-1">
                                    Ganhos
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-black text-red-400">
                                    {{ number_format($this->walletStats['total_spent'], 0) }}
                                </div>
                                <div class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mt-1">
                                    Gastos
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div
                            class="bg-white/[0.03] border border-white/5 p-6 rounded-[2.5rem] backdrop-blur-sm shadow-xl">
                            <p class="text-[9px] font-black text-slate-600 uppercase tracking-widest mb-4 italic">
                                Conexão
                            </p>
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-2 h-2 bg-blue-500 rounded-full shadow-[0_0_8px_#3b82f6] animate-pulse"></span>
                                <span
                                    class="text-[10px] font-black text-white uppercase italic tracking-widest leading-none">
                                    Protegida
                                </span>
                            </div>
                        </div>
                        <div
                            class="bg-white/[0.03] border border-white/5 p-6 rounded-[2.5rem] backdrop-blur-sm shadow-xl">
                            <p class="text-[9px] font-black text-slate-600 uppercase tracking-widest mb-4 italic">
                                Processamento
                            </p>
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-2 h-2 bg-cyan-500 rounded-full shadow-[0_0_8px_#06b6d4] animate-pulse"></span>
                                <span
                                    class="text-[10px] font-black text-white uppercase italic tracking-widest leading-none">
                                    Imediato
                                </span>
                            </div>
                        </div>
                        <div
                            class="col-span-2 bg-white/[0.03] border border-white/5 p-6 rounded-[2.5rem] backdrop-blur-sm shadow-xl">
                            <p class="text-[9px] font-black text-slate-600 uppercase tracking-widest mb-3 italic">
                                Última Atividade
                            </p>
                            <div class="text-[10px] font-black text-white uppercase italic">
                                {{ $this->walletStats['last_transaction']
                                    ? $this->walletStats['last_transaction']->created_at->diffForHumans()
                                    : 'Nenhuma transação ainda' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Gift Cards --}}
        <a href="{{ route('wallet.gift') }}"
            class="group bg-[#0b0d11] border border-white/5 p-10 rounded-[2.5rem] flex items-center justify-between transition-all hover:bg-purple-600/[0.02] hover:border-purple-500/30 shadow-xl mb-16">
            <div class="flex items-center gap-6">
                <div
                    class="w-16 h-16 bg-purple-500/10 rounded-2xl flex items-center justify-center text-3xl shadow-inner group-hover:scale-110 transition-transform">
                    🎁
                </div>
                <div>
                    <p class="text-sm font-black text-white uppercase italic leading-none">Gift Cards</p>
                    <p class="text-[9px] font-bold text-slate-600 uppercase tracking-widest mt-2">
                        Comprar ou Resgatar Códigos de Presente
                    </p>
                </div>
            </div>
            <div class="text-slate-700 group-hover:text-purple-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </div>
        </a>

        {{-- Pacotes --}}
        <div class="mb-16">
            <div class="flex items-center gap-4 mb-12 px-2">
                <div class="h-8 w-1.5 bg-blue-600 rounded-full"></div>
                <h2 class="text-sm font-black text-white uppercase tracking-[0.3em] italic">
                    Escolha o seu Pacote de Recarga
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @forelse ($this->packages as $package)
                    <div wire:click="selectPackage({{ $package->id }})"
                        class="group relative bg-[#0b0d11] border border-white/5 rounded-[3rem] p-10 cursor-pointer transition-all duration-500 hover:border-blue-500/40 hover:-translate-y-4 shadow-2xl overflow-hidden">

                        {{-- Badge de melhor valor --}}
                        @if ($package->credits >= 1000)
                            <div
                                class="absolute top-4 right-4 px-3 py-1 bg-yellow-500/20 border border-yellow-500/30 rounded-full">
                                <span class="text-[8px] font-black text-yellow-400 uppercase tracking-wider">
                                    ⭐ Melhor Valor
                                </span>
                            </div>
                        @endif

                        <div
                            class="absolute inset-0 bg-gradient-to-b from-blue-600/[0.03] to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                        </div>

                        <div class="relative z-10 flex flex-col items-center">
                            <div
                                class="w-24 h-24 bg-white/[0.02] border border-white/5 rounded-3xl mb-10 flex items-center justify-center text-4xl shadow-inner group-hover:scale-110 group-hover:bg-blue-600/10 transition-all duration-500">
                                @if ($package->credits >= 5000)
                                    💎
                                @elseif($package->credits >= 1000)
                                    🛡️
                                @else
                                    📦
                                @endif
                            </div>

                            <span
                                class="text-[10px] font-black text-slate-600 uppercase tracking-[0.3em] mb-2 italic group-hover:text-blue-500 transition-colors">
                                {{ $package->name }}
                            </span>

                            <div
                                class="flex items-baseline gap-2 mb-10 group-hover:scale-110 transition-transform duration-500">
                                <span class="text-6xl font-black text-white italic tracking-tighter">
                                    {{ number_format($package->credits, 0, ',', '.') }}
                                </span>
                                <span class="text-blue-600 font-black text-sm italic uppercase tracking-widest">
                                    C$
                                </span>
                            </div>

                            <div class="w-full pt-10 border-t border-white/5 flex flex-col items-center">
                                <div class="flex items-start gap-1 mb-8">
                                    <span class="text-[10px] font-black text-slate-500 mt-1 uppercase italic">R$</span>
                                    <span class="text-4xl font-black text-white italic tracking-tighter">
                                        {{ number_format($package->price_brl, 2, ',', '.') }}
                                    </span>
                                </div>

                                <button
                                    class="w-full h-16 bg-white/[0.03] border border-white/5 group-hover:bg-blue-600 group-hover:border-blue-500 rounded-2xl text-[10px] font-black uppercase tracking-[0.4em] text-white transition-all shadow-xl italic">
                                    RECARREGAR AGORA
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div
                        class="col-span-full py-24 bg-white/[0.01] rounded-[4rem] border border-dashed border-white/5 flex flex-col items-center justify-center opacity-50">
                        <span class="text-6xl mb-6">📦</span>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest italic">
                            Nenhum pacote disponível no momento.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Atalhos --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <a href="{{ route('wallet.transactions') }}"
                class="group bg-[#0b0d11] border border-white/5 p-10 rounded-[2.5rem] flex items-center justify-between transition-all hover:bg-blue-600/[0.02] hover:border-blue-500/30 shadow-xl">
                <div class="flex items-center gap-6">
                    <div
                        class="w-16 h-16 bg-white/[0.03] rounded-2xl flex items-center justify-center text-3xl shadow-inner group-hover:scale-110 transition-transform">
                        📜
                    </div>
                    <div>
                        <p class="text-sm font-black text-white uppercase italic leading-none">Extrato da Conta</p>
                        <p class="text-[9px] font-bold text-slate-600 uppercase tracking-widest mt-2">
                            {{ $this->walletStats['total_transactions'] }} transações realizadas
                        </p>
                    </div>
                </div>
                <div class="text-slate-700 group-hover:text-blue-500 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </div>
            </a>

            <a href="{{ route('dashboard') }}"
                class="group bg-[#0b0d11] border border-white/5 p-10 rounded-[2.5rem] flex items-center justify-between transition-all hover:bg-blue-600/[0.02] hover:border-blue-500/30 shadow-xl">
                <div class="flex items-center gap-6">
                    <div
                        class="w-16 h-16 bg-white/[0.03] rounded-2xl flex items-center justify-center text-3xl shadow-inner group-hover:scale-110 transition-transform">
                        🏠
                    </div>
                    <div>
                        <p class="text-sm font-black text-white uppercase italic leading-none">Painel Inicial</p>
                        <p class="text-[9px] font-bold text-slate-600 uppercase tracking-widest mt-2">
                            Voltar para o centro de comando
                        </p>
                    </div>
                </div>
                <div class="text-slate-700 group-hover:text-blue-500 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </div>
            </a>
        </div>
    </div>

    {{-- Modal de Confirmação --}}
    @if ($showConfirmation && $this->selectedPackage)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-6">
            <div class="absolute inset-0 bg-[#05070a]/90 backdrop-blur-2xl animate-in fade-in"
                wire:click="cancelPurchase"></div>

            <div
                class="relative bg-[#0b0d11] w-full max-w-lg rounded-[4rem] border border-blue-600/30 overflow-hidden shadow-[0_0_150px_rgba(37,99,235,0.2)] animate-in zoom-in duration-300">
                <div class="h-1.5 bg-gradient-to-r from-blue-600 to-cyan-400"></div>

                <div class="p-12 sm:p-16">
                    <div class="text-center mb-12">
                        <div
                            class="w-24 h-24 bg-blue-600/10 border border-blue-500/30 rounded-[2.5rem] mx-auto flex items-center justify-center text-5xl mb-8 shadow-inner">
                            💰
                        </div>
                        <h3 class="text-3xl font-black text-white uppercase italic tracking-tighter">
                            Confirmar <span class="text-blue-500">Recarga</span>
                        </h3>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.4em] mt-3 italic">
                            Verifique os detalhes da compra
                        </p>
                    </div>

                    <div class="bg-white/[0.02] border border-white/5 rounded-[3rem] p-8 mb-10 space-y-6 shadow-inner">
                        <div class="flex justify-between items-center border-b border-white/5 pb-6">
                            <span
                                class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic">Produto</span>
                            <span class="text-xs font-black text-white uppercase italic tracking-widest">
                                {{ $this->selectedPackage->name }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center border-b border-white/5 pb-6">
                            <span
                                class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic">Créditos</span>
                            <span class="text-3xl font-black text-blue-500 italic">
                                +{{ number_format($this->selectedPackage->credits, 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic">Valor
                                Original</span>
                            <span class="text-2xl font-black text-white italic tracking-tighter">
                                R$ {{ number_format($this->selectedPackage->price_brl, 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    {{-- Cupom --}}
                    <div class="space-y-4 mb-6">
                        @if (!$appliedCoupon)
                            <div class="relative">
                                <input type="text" wire:model.defer="couponCode"
                                    placeholder="Digite seu cupom (opcional)"
                                    class="w-full h-14 bg-white/[0.02] border border-white/5 rounded-2xl px-6 text-xs font-black uppercase tracking-[0.3em] italic text-white focus:border-blue-500 outline-none transition-all @error('couponCode') border-red-500/50 @enderror">
                                @error('couponCode')
                                    <p class="text-red-400 text-[8px] font-bold uppercase tracking-wider mt-2">
                                        {{ $message }}</p>
                                @enderror
                            </div>

                            <button wire:click="applyCoupon" wire:loading.attr="disabled"
                                class="w-full py-4 bg-purple-600/10 hover:bg-purple-600 border border-purple-500/20 hover:border-purple-500 rounded-2xl text-[10px] font-black uppercase tracking-[0.4em] italic transition-all disabled:opacity-50">
                                <span wire:loading.remove wire:target="applyCoupon">APLICAR CUPOM</span>
                                <span wire:loading wire:target="applyCoupon">Verificando...</span>
                            </button>
                        @else
                            <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl">🎫</span>
                                        <div>
                                            <p
                                                class="text-[10px] font-black text-emerald-400 uppercase tracking-widest">
                                                Cupom Aplicado</p>
                                            <p class="text-sm font-black text-white uppercase italic mt-1">
                                                {{ $appliedCoupon->code }}</p>
                                        </div>
                                    </div>
                                    <button wire:click="removeCoupon"
                                        class="text-red-400 hover:text-red-300 transition text-xs font-bold">
                                        Remover
                                    </button>
                                </div>
                                <div class="flex justify-between items-center pt-4 border-t border-emerald-500/20">
                                    <span
                                        class="text-[9px] font-black text-emerald-400 uppercase tracking-widest italic">Desconto</span>
                                    <span class="text-2xl font-black text-emerald-400 italic">
                                        -R$ {{ number_format($discountAmount, 2, ',', '.') }}
                                    </span>
                                </div>
                            </div>

                            <div class="bg-blue-500/10 border border-blue-500/30 rounded-2xl p-6">
                                <div class="flex justify-between items-center">
                                    <span
                                        class="text-[10px] font-black text-blue-400 uppercase tracking-widest italic">Total
                                        a Pagar</span>
                                    <span class="text-4xl font-black text-white italic tracking-tighter">
                                        R$ {{ number_format($this->finalPrice, 2, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-4 mt-8">
                        <button wire:click="confirmPurchase" wire:loading.attr="disabled" @disabled($isProcessing)
                            class="w-full py-7 bg-blue-600 hover:bg-blue-500 
           disabled:bg-blue-900 disabled:opacity-50
           text-white rounded-[2.2rem] text-[11px] 
           font-black uppercase tracking-[0.5em] italic 
           transition-all shadow-2xl active:scale-95">

                            <span wire:loading.remove wire:target="confirmPurchase">
                                {{ $isProcessing ? 'PROCESSANDO...' : 'CONFIRMAR COMPRA' }}
                            </span>

                            <span wire:loading wire:target="confirmPurchase">
                                PROCESSANDO...
                            </span>
                        </button>

                        <button wire:click="cancelPurchase" wire:loading.attr="disabled"
                            class="w-full py-5 bg-transparent text-slate-600 hover:text-white rounded-[2rem] text-[9px] font-black uppercase tracking-[0.3em] italic transition-all border border-white/5 hover:border-white/10 disabled:opacity-50">
                            CANCELAR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-toast />
</div>
