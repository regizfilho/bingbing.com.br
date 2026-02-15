<?php

namespace App\Livewire\Wallet;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Models\Wallet\GiftCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] #[Title('Gift Cards')] class extends Component {
    public ?string $param = null;
    public string $tab = 'redeem';

    public string $redeemCode = '';

    public ?int $selectedCreditValue = null;
    public array $availableValues = [100, 250, 500, 1000, 2500, 5000];
    public bool $showPurchaseConfirmation = false;
    public bool $isProcessing = false;

    public function mount(?string $param = null): void
    {
        $this->param = $param;

        if ($param === 'success') {
            $this->dispatch('notify', type: 'success', text: '🎉 Pagamento confirmado! Seu Gift Card foi criado com sucesso.');
            $this->tab = 'history';
        } elseif ($param === 'cancel') {
            $this->dispatch('notify', type: 'warning', text: 'Pagamento cancelado. Você pode tentar novamente quando quiser.');
        }
    }

    protected function rules(): array
    {
        return [
            'redeemCode' => 'required|string|size:14',
            'selectedCreditValue' => 'required|integer|min:100',
        ];
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
    public function myGiftCards()
    {
        return GiftCard::with('redeemedByUser')->where('created_by_user_id', $this->user->id)->where('source', 'purchase')->latest()->take(10)->get();
    }

    #[Computed]
    public function redemptionHistory()
    {
        return $this->user->wallet->transactions()->where('description', 'like', '%Gift Card%')->latest()->take(10)->get();
    }

    #[Computed]
    public function selectedPrice(): ?float
    {
        if (!$this->selectedCreditValue) {
            return null;
        }

        return (float) $this->selectedCreditValue;
    }

    #[Computed]
    public function canPurchase(): bool
    {
        if (!$this->selectedCreditValue) {
            return false;
        }
        return $this->walletBalance >= $this->selectedPrice;
    }

    public function selectValue(int $value): void
    {
        $this->selectedCreditValue = $value;
        $this->showPurchaseConfirmation = true;
    }

    public function cancelPurchase(): void
    {
        $this->showPurchaseConfirmation = false;
        $this->selectedCreditValue = null;
    }

    public function purchaseGiftCard(): void
    {
        if ($this->isProcessing) {
            return;
        }
        $this->isProcessing = true;

        $this->validate(['selectedCreditValue' => 'required|integer|min:100']);

        try {
            DB::transaction(function () {
                $wallet = $this->user->wallet ?? $this->user->wallet()->create(['balance' => 0]);

                $wallet = $wallet->lockForUpdate()->first();

                if ($wallet->balance < $this->selectedPrice) {
                    throw new \Exception('Saldo insuficiente para comprar o Gift Card.');
                }

                $giftCard = GiftCard::create([
                    'uuid' => Str::uuid(),
                    'code' => GiftCard::generateUniqueCode(),
                    'credit_value' => $this->selectedCreditValue,
                    'price_brl' => $this->selectedPrice,
                    'source' => 'purchase',
                    'description' => 'Comprado na Wallet pelo usuário',
                    'created_by_user_id' => $this->user->id,
                    'status' => 'active',
                    'expires_at' => now()->addYear(),
                ]);

                $wallet->debit($this->selectedPrice, "Gift Card: {$giftCard->code}", $giftCard);

                $this->user->notify(new \App\Notifications\GiftCardPurchaseNotification($giftCard));

                $pushService = app(\App\Services\PushNotificationService::class);
                $message = \App\Services\NotificationMessages::giftCardPurchased($giftCard->code, $this->selectedCreditValue);

                $pushService->notifyUser($this->user->id, $message['title'], $message['body'], route('wallet.gift'));

                Log::info('Gift card purchased', [
                    'user_id' => $this->user->id,
                    'gift_card_id' => $giftCard->id,
                    'code' => $giftCard->code,
                    'credit_value' => $this->selectedCreditValue,
                    'price' => $this->selectedPrice,
                ]);

                $this->dispatch('notify', type: 'success', text: "Gift Card criado! Código: {$giftCard->code}");
            });

            $this->cancelPurchase();
        } catch (\Throwable $e) {
            Log::error('Gift card purchase failed', [
                'user_id' => $this->user->id,
                'credit_value' => $this->selectedCreditValue,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('notify', type: 'error', text: $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function redeemGiftCard(): void
    {
        if ($this->isProcessing) {
            return;
        }
        $this->isProcessing = true;

        $this->validate(['redeemCode' => 'required|string|size:14']);

        try {
            DB::transaction(function () {
                $code = strtoupper(trim($this->redeemCode));

                $giftCard = GiftCard::where('code', $code)->lockForUpdate()->first();

                if (!$giftCard) {
                    throw new \Exception('Gift Card não encontrado.');
                }

                if (!$giftCard->canBeRedeemed()) {
                    Log::warning('Gift card redemption attempt failed', [
                        'user_id' => $this->user->id,
                        'code' => $code,
                        'status' => $giftCard->status,
                        'reason' => 'cannot_be_redeemed',
                    ]);

                    throw new \Exception('Este Gift Card não pode ser resgatado.');
                }

                $wallet = $this->user->wallet ?? $this->user->wallet()->create(['balance' => 0]);

                $wallet = $wallet->lockForUpdate()->first();

                $wallet->credit($giftCard->credit_value, "Gift Card resgatado: {$giftCard->code}", $giftCard);

                $giftCard->update([
                    'status' => 'redeemed',
                    'redeemed_by_user_id' => $this->user->id,
                    'redeemed_at' => now(),
                ]);

                $giftCard->redemptions()->create([
                    'uuid' => Str::uuid(),
                    'user_id' => $this->user->id,
                    'credit_value' => $giftCard->credit_value,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                $pushService = app(\App\Services\PushNotificationService::class);
                $message = \App\Services\NotificationMessages::giftCardRedeemed($giftCard->code, $giftCard->credit_value);

                $pushService->notifyUser($this->user->id, $message['title'], $message['body'], route('wallet.index'));

                Log::info('Gift card redeemed', [
                    'user_id' => $this->user->id,
                    'gift_card_id' => $giftCard->id,
                    'code' => $code,
                    'credit_value' => $giftCard->credit_value,
                    'ip_address' => request()->ip(),
                ]);

                $this->dispatch('notify', type: 'success', text: "Gift Card resgatado! +{$giftCard->credit_value} créditos.");

                $this->reset('redeemCode');
            });
        } catch (\Throwable $e) {
            Log::error('Gift card redemption failed', [
                'user_id' => $this->user->id,
                'code' => strtoupper(trim($this->redeemCode)),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('notify', type: 'error', text: $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function copyCode(string $code): void
    {
        $this->dispatch('copy-to-clipboard', text: $code);
        $this->dispatch('notify', type: 'success', text: 'Código copiado!');
    }
};
?>

<div class="min-h-screen bg-[#05070a] text-slate-200 pb-24 selection:bg-blue-500/30">


    {{-- Efeitos de Fundo --}}
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
        <div class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-purple-600/10 blur-[120px] rounded-full">
        </div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[400px] h-[400px] bg-pink-500/5 blur-[100px] rounded-full">
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-6 pt-16">

        {{-- Banner de Sucesso (aparece apenas quando param=success) --}}
        @if ($param === 'success')
            <div class="mb-12 animate-in fade-in slide-in-from-top duration-500" x-data="{ show: true }" x-show="show">
                <div
                    class="relative bg-gradient-to-r from-purple-600/10 to-pink-500/10 border border-purple-500/30 rounded-3xl p-8 overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-600 to-pink-400"></div>

                    <button @click="show = false"
                        class="absolute top-4 right-4 text-purple-400 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>

                    <div class="flex items-start gap-6">
                        <div
                            class="w-16 h-16 bg-purple-500/20 rounded-2xl flex items-center justify-center flex-shrink-0 text-4xl">
                            🎁
                        </div>
                        <div class="flex-1">
                            <h3 class="text-2xl font-black text-purple-400 uppercase italic mb-2">
                                Gift Card Criado!
                            </h3>
                            <p class="text-sm text-slate-300 leading-relaxed mb-4">
                                Seu pagamento foi processado com sucesso e o Gift Card já está disponível.
                                Você pode visualizar o código na aba "Meu Histórico" e compartilhá-lo com quem quiser!
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <a href="{{ route('wallet.transactions') }}"
                                    class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-500 text-white px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-wider transition-all">
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
                    <div class="w-2 h-2 bg-purple-600 rounded-full animate-pulse"></div>
                    <span class="text-[10px] font-black text-purple-500 uppercase tracking-[0.5em] italic">Presentes
                        Digitais</span>
                </div>
                <h1 class="text-6xl font-black text-white uppercase italic tracking-tighter leading-none">
                    GIFT <span class="text-purple-600">CARDS</span>
                </h1>
            </div>

            <div
                class="flex items-center gap-6 bg-white/[0.02] border border-white/5 p-4 rounded-3xl backdrop-blur-md shadow-2xl">
                <div class="text-right">
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic leading-none mb-1">
                        Seu Saldo</p>
                    <p class="text-2xl font-black text-white italic tracking-tighter">C$
                        {{ number_format($this->walletBalance, 0, ',', '.') }}</p>
                </div>
                <div
                    class="w-12 h-12 bg-purple-500/10 border border-purple-500/20 rounded-2xl flex items-center justify-center text-xl shadow-lg">
                    💰
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-4 mb-12 bg-[#0b0d11] border border-white/5 p-2 rounded-[2.5rem] shadow-2xl">
            <button wire:click="$set('tab', 'redeem')"
                class="flex-1 py-5 rounded-[2rem] text-sm font-black uppercase italic transition-all {{ $tab === 'redeem' ? 'bg-purple-600 text-white shadow-xl' : 'text-slate-600 hover:text-white' }}">
                🎁 Resgatar Código
            </button>
            <button wire:click="$set('tab', 'purchase')"
                class="flex-1 py-5 rounded-[2rem] text-sm font-black uppercase italic transition-all {{ $tab === 'purchase' ? 'bg-purple-600 text-white shadow-xl' : 'text-slate-600 hover:text-white' }}">
                💳 Comprar Gift Card
            </button>
            <button wire:click="$set('tab', 'history')"
                class="flex-1 py-5 rounded-[2rem] text-sm font-black uppercase italic transition-all {{ $tab === 'history' ? 'bg-purple-600 text-white shadow-xl' : 'text-slate-600 hover:text-white' }}">
                📜 Meu Histórico
            </button>
        </div>

        {{-- TAB: RESGATAR --}}
        @if ($tab === 'redeem')
            <div class="relative mb-24 group">
                <div
                    class="absolute -inset-0.5 bg-gradient-to-r from-purple-600/50 to-pink-500/50 rounded-[3.5rem] blur opacity-20">
                </div>

                <div class="relative bg-[#0b0d11] border border-white/5 rounded-[3.5rem] p-16 shadow-2xl">
                    <div class="max-w-2xl mx-auto text-center space-y-8">
                        <div
                            class="w-24 h-24 bg-purple-500/10 border border-purple-500/20 rounded-[2.5rem] mx-auto flex items-center justify-center text-5xl shadow-inner mb-8">
                            🎁
                        </div>

                        <h2 class="text-3xl font-black text-white uppercase italic tracking-tighter">Resgatar Gift Card
                        </h2>
                        <p class="text-slate-400 text-sm">Digite o código de 12 caracteres do seu Gift Card</p>

                        <div class="space-y-6">
                            <input type="text" wire:model.defer="redeemCode" placeholder="XXXX-XXXX-XXXX"
                                maxlength="14"
                                class="w-full h-20 bg-white/[0.03] border border-white/10 rounded-[2rem] px-8 text-center text-2xl font-black uppercase tracking-[0.5em] italic text-white focus:border-purple-500 outline-none shadow-inner"
                                x-on:input="$el.value = $el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').replace(/(.{4})/g, '$1-').slice(0, 14)">
                            @error('redeemCode')
                                <p class="text-red-400 text-xs font-black uppercase">{{ $message }}</p>
                            @enderror

                            <button wire:click="redeemGiftCard"
                                class="w-full h-16 bg-purple-600 hover:bg-purple-500 text-white rounded-[2rem] text-sm font-black uppercase tracking-[0.4em] italic transition-all shadow-2xl active:scale-95">
                                RESGATAR AGORA
                            </button>
                        </div>

                        <div class="pt-8 border-t border-white/5">
                            <p class="text-[10px] text-slate-500 uppercase tracking-widest italic">💡 O crédito será
                                adicionado automaticamente à sua carteira</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- TAB: COMPRAR --}}
        @if ($tab === 'purchase')
            <div class="mb-16">
                <div class="flex items-center gap-4 mb-12 px-2">
                    <div class="h-8 w-1.5 bg-purple-600 rounded-full"></div>
                    <h2 class="text-sm font-black text-white uppercase tracking-[0.3em] italic">Escolha o Valor do Gift
                        Card</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    @foreach ($availableValues as $value)
                        <div wire:click="selectValue({{ $value }})"
                            wire:loading.class="opacity-50 pointer-events-none" wire:target="selectValue"
                            class="group relative bg-[#0b0d11] border border-white/5 rounded-[3rem] p-10 cursor-pointer transition-all duration-500 hover:border-purple-500/40 hover:-translate-y-4 shadow-2xl">


                            <div
                                class="absolute inset-0 bg-gradient-to-b from-purple-600/[0.03] to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-[3rem]">
                            </div>

                            <div class="relative z-10 flex flex-col items-center">
                                <div
                                    class="w-20 h-20 bg-white/[0.02] border border-white/5 rounded-2xl mb-8 flex items-center justify-center text-3xl shadow-inner group-hover:scale-110 transition-transform">
                                    🎁
                                </div>

                                <div class="flex items-baseline gap-2 mb-8">
                                    <span
                                        class="text-5xl font-black text-white italic tracking-tighter">{{ number_format($value, 0, ',', '.') }}</span>
                                    <span class="text-purple-600 font-black text-sm italic uppercase">C$</span>
                                </div>

                                <div class="w-full pt-8 border-t border-white/5 flex flex-col items-center gap-4">
                                    <div class="text-slate-400 text-xs uppercase tracking-widest italic">Preço</div>
                                    <div class="text-2xl font-black text-white italic">R$
                                        {{ number_format($value, 2, ',', '.') }}</div>

                                    <button
                                        class="w-full h-14 bg-white/[0.03] border border-white/5 group-hover:bg-purple-600 group-hover:border-purple-500 rounded-2xl text-[10px] font-black uppercase tracking-[0.4em] text-white transition-all shadow-xl italic">
                                        COMPRAR
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- TAB: HISTÓRICO --}}
        @if ($tab === 'history')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {{-- Meus Gift Cards Criados --}}
                <div class="bg-[#0b0d11] border border-white/5 rounded-[3rem] p-10 shadow-2xl">
                    <h3 class="text-xl font-black text-white uppercase italic mb-8 flex items-center gap-3">
                        <span>🎫</span> Meus Gift Cards
                    </h3>

                    <div class="space-y-4 max-h-[600px] overflow-y-auto custom-scrollbar">
                        @forelse($this->myGiftCards as $card)
                            <div
                                class="bg-white/[0.02] border border-white/5 rounded-2xl p-6 hover:bg-white/[0.04] transition-all">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <div class="text-xs text-slate-500 uppercase mb-2">Código</div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="text-lg font-black text-white italic tracking-wider">{{ $card->code }}</span>
                                            <button wire:click="copyCode('{{ $card->code }}')"
                                                class="text-purple-500 hover:text-purple-400 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-black uppercase
                                        {{ $card->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                        {{ $card->status === 'redeemed' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : '' }}
                                        {{ $card->status === 'expired' ? 'bg-red-500/10 text-red-400 border border-red-500/20' : '' }}">
                                        {{ $card->status }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-4 text-xs mb-4">
                                    <div>
                                        <span class="text-slate-500">Valor:</span>
                                        <span class="text-white font-black ml-2">C$
                                            {{ number_format($card->credit_value, 0) }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500">Criado:</span>
                                        <span
                                            class="text-white font-black ml-2">{{ $card->created_at->format('d/m/Y') }}</span>
                                    </div>
                                </div>

                                @if ($card->expires_at)
                                    <div class="text-xs text-slate-400 mb-3">
                                        <span class="text-slate-500">Expira:</span>
                                        {{ $card->expires_at->format('d/m/Y H:i') }}
                                    </div>
                                @endif

                                @if ($card->status === 'redeemed' && $card->redeemedByUser)
                                    <div class="mt-4 pt-4 border-t border-white/5">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="w-10 h-10 bg-blue-500/10 border border-blue-500/20 rounded-xl flex items-center justify-center text-lg flex-shrink-0">
                                                👤
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div
                                                    class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-2">
                                                    Resgatado por</div>
                                                <div class="text-sm font-black text-white truncate">
                                                    {{ $card->redeemedByUser->name ?? ($card->redeemedByUser->nickname ?? 'Usuário') }}
                                                </div>
                                                <div class="text-xs text-slate-400 truncate mt-1">
                                                    {{ $card->redeemedByUser->email }}
                                                </div>
                                                <div class="text-[10px] text-slate-500 mt-2">
                                                    {{ $card->redeemed_at->format('d/m/Y') }} às
                                                    {{ $card->redeemed_at->format('H:i') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-12 text-slate-500">
                                <div class="text-4xl mb-4">🎁</div>
                                <p class="text-sm italic">Você ainda não comprou nenhum Gift Card</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Histórico de Resgates --}}
                <div class="bg-[#0b0d11] border border-white/5 rounded-[3rem] p-10 shadow-2xl">
                    <h3 class="text-xl font-black text-white uppercase italic mb-8 flex items-center gap-3">
                        <span>📥</span> Gift Cards Resgatados
                    </h3>

                    <div class="space-y-4 max-h-[600px] overflow-y-auto custom-scrollbar">
                        @forelse($this->redemptionHistory as $tx)
                            <div
                                class="bg-white/[0.02] border border-white/5 rounded-2xl p-6 hover:bg-white/[0.04] transition-all">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="text-emerald-400 text-2xl font-black italic">
                                        +{{ number_format($tx->amount, 0) }} C$
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ $tx->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                                <div class="text-sm text-slate-300">{{ $tx->description }}</div>
                            </div>
                        @empty
                            <div class="text-center py-12 text-slate-500">
                                <div class="text-4xl mb-4">📥</div>
                                <p class="text-sm italic">Você ainda não resgatou nenhum Gift Card</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        {{-- Voltar --}}
        <div class="mt-12">
            <a href="{{ route('wallet.index') }}"
                class="inline-flex items-center gap-3 px-8 py-4 bg-white/[0.02] border border-white/5 rounded-2xl text-sm font-black uppercase italic text-slate-400 hover:text-white hover:border-white/20 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Voltar para Carteira
            </a>
        </div>
    </div>

    {{-- Modal de Confirmação de Compra --}}
    @if ($showPurchaseConfirmation && $selectedCreditValue)
        <div class="fixed inset-0 z-[2000000000] flex items-center justify-center p-6">

            <div class="absolute inset-0 bg-[#05070a]/90 backdrop-blur-2xl" wire:click="cancelPurchase"></div>

            <div
                class="relative bg-[#0b0d11] w-full max-w-lg rounded-[4rem] border border-purple-600/30 overflow-hidden shadow-[0_0_150px_rgba(168,85,247,0.2)] animate-in zoom-in duration-300">
                <div class="h-1.5 bg-gradient-to-r from-purple-600 to-pink-400"></div>

                <div class="p-16">
                    <div class="text-center mb-12">
                        <div
                            class="w-24 h-24 bg-purple-600/10 border border-purple-500/30 rounded-[2.5rem] mx-auto flex items-center justify-center text-5xl mb-8">
                            🎁
                        </div>
                        <h3 class="text-3xl font-black text-white uppercase italic tracking-tighter">Confirmar <span
                                class="text-purple-500">Compra</span></h3>
                    </div>

                    <div class="bg-white/[0.02] border border-white/5 rounded-[3rem] p-8 mb-10 space-y-6">
                        <div class="flex justify-between items-center border-b border-white/5 pb-6">
                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest italic">Valor
                                do Gift Card</span>
                            <span
                                class="text-3xl font-black text-purple-500 italic">{{ number_format($selectedCreditValue, 0) }}
                                C$</span>
                        </div>
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-[9px] font-black text-purple-400 uppercase tracking-widest italic">Total
                                a Pagar</span>
                            <span class="text-4xl font-black text-white italic tracking-tighter">R$
                                {{ number_format($this->selectedPrice, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <button wire:click="purchaseGiftCard" wire:loading.attr="disabled"
                            @disabled(!$this->canPurchase || $isProcessing)
                            class="w-full py-7 rounded-[2.2rem] text-[11px] font-black uppercase tracking-[0.5em] italic transition-all shadow-2xl active:scale-95
    {{ $this->canPurchase ? 'bg-purple-600 hover:bg-purple-500 text-white' : 'bg-white/5 text-slate-700 cursor-not-allowed' }}
    disabled:opacity-50">

                            <span wire:loading.remove wire:target="purchaseGiftCard">
                                {{ $isProcessing ? 'PROCESSANDO...' : ($this->canPurchase ? 'CONFIRMAR COMPRA' : 'SALDO INSUFICIENTE') }}
                            </span>

                            <span wire:loading wire:target="purchaseGiftCard">
                                PROCESSANDO...
                            </span>
                        </button>

                        <button wire:click="cancelPurchase"
                            class="w-full py-5 bg-transparent text-slate-600 hover:text-white rounded-[2rem] text-[9px] font-black uppercase tracking-[0.3em] italic transition-all border border-white/5 hover:border-white/10">
                            CANCELAR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-loading target="redeemGiftCard, purchaseGiftCard, selectValue" message="PROCESSANDO..." />

    <x-toast />

    @script
        <script>
            $wire.on('copy-to-clipboard', (event) => {
                navigator.clipboard.writeText(event.text);
            });
        </script>
    @endscript

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #0b0d11;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }
    </style>
</div>
