<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Wallet\GiftCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Layout('layouts.admin')]
#[Title('Gestão de Gift Cards')]
class GiftCardManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all'; // all, active, redeemed, expired
    public string $sort = 'desc';

    // Drawer/Modal
    public bool $showCreateDrawer = false;
    public bool $showEditDrawer = false;
    public ?int $selectedGiftCardId = null;

    // Criar Gift Card
    public float $creditValue = 0;
    public ?float $priceBrl = null;
    public ?string $description = null;
    public ?string $expiresAt = null;

    // Editar Gift Card
    public ?string $editExpiresAt = null;
    public string $editStatus = 'active';

    protected function rules(): array
    {
        return [
            'creditValue' => 'required|numeric|min:1',
            'priceBrl' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'expiresAt' => 'nullable|date|after:now',
        ];
    }

    protected function editRules(): array
    {
        return [
            'editExpiresAt' => 'nullable|date',
            'editStatus' => 'required|in:active,redeemed,expired',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function giftCards()
    {
        return GiftCard::query()
            ->with(['createdBy', 'redeemedBy'])
            ->when($this->search, fn($q) => 
                $q->where('code', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhereHas('createdBy', fn($sub) => $sub->where('name', 'like', "%{$this->search}%"))
            )
            ->when($this->statusFilter !== 'all', fn($q) => 
                $this->statusFilter === 'expired' 
                    ? $q->expired() 
                    : $q->where('status', $this->statusFilter)
            )
            ->orderBy('created_at', $this->sort)
            ->paginate(20);
    }

    #[Computed]
    public function selectedGiftCard(): ?GiftCard
    {
        return $this->selectedGiftCardId 
            ? GiftCard::with(['createdBy', 'redeemedBy', 'redemptions.user'])->find($this->selectedGiftCardId) 
            : null;
    }

    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => GiftCard::count(),
            'active' => GiftCard::active()->count(),
            'redeemed' => GiftCard::redeemed()->count(),
            'expired' => GiftCard::expired()->count(),
            'total_value_active' => GiftCard::active()->sum('credit_value'),
            'total_value_redeemed' => GiftCard::redeemed()->sum('credit_value'),
        ];
    }

    public function openCreateDrawer(): void
    {
        $this->reset(['creditValue', 'priceBrl', 'description', 'expiresAt']);
        $this->showCreateDrawer = true;
    }

    public function closeCreateDrawer(): void
    {
        $this->showCreateDrawer = false;
        $this->reset(['creditValue', 'priceBrl', 'description', 'expiresAt']);
    }

    public function createGiftCard(): void
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $giftCard = GiftCard::create([
                    'uuid' => Str::uuid(),
                    'code' => GiftCard::generateUniqueCode(),
                    'credit_value' => $this->creditValue,
                    'price_brl' => $this->priceBrl,
                    'source' => 'admin',
                    'description' => $this->description ?? 'Gerado pelo admin',
                    'created_by_user_id' => auth()->id(),
                    'status' => 'active',
                    'expires_at' => $this->expiresAt ? \Carbon\Carbon::parse($this->expiresAt) : null,
                ]);

                $this->dispatch('notify', type: 'success', text: "Gift Card criado! Código: {$giftCard->code}");
            });

            $this->closeCreateDrawer();
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: $e->getMessage());
        }
    }

    public function openEditDrawer(int $id): void
    {
        $this->selectedGiftCardId = $id;
        $giftCard = $this->selectedGiftCard;
        
        if ($giftCard) {
            $this->editExpiresAt = $giftCard->expires_at?->format('Y-m-d\TH:i');
            $this->editStatus = $giftCard->status;
            $this->showEditDrawer = true;
        }
    }

    public function closeEditDrawer(): void
    {
        $this->showEditDrawer = false;
        $this->reset(['selectedGiftCardId', 'editExpiresAt', 'editStatus']);
    }

    public function updateGiftCard(): void
    {
        $this->validate($this->editRules());

        try {
            $giftCard = $this->selectedGiftCard;

            if (!$giftCard) {
                throw new \Exception('Gift Card não encontrado.');
            }

            $giftCard->update([
                'expires_at' => $this->editExpiresAt ? \Carbon\Carbon::parse($this->editExpiresAt) : null,
                'status' => $this->editStatus,
            ]);

            $this->dispatch('notify', type: 'success', text: 'Gift Card atualizado com sucesso!');
            $this->closeEditDrawer();
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: $e->getMessage());
        }
    }

    public function deleteGiftCard(int $id): void
    {
        try {
            $giftCard = GiftCard::findOrFail($id);

            if ($giftCard->status === 'redeemed') {
                throw new \Exception('Não é possível deletar um Gift Card já resgatado.');
            }

            $giftCard->delete();

            $this->dispatch('notify', type: 'success', text: 'Gift Card deletado.');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: $e->getMessage());
        }
    }

    public function markAsExpired(): void
    {
        try {
            $updated = GiftCard::active()
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            $this->dispatch('notify', type: 'success', text: "{$updated} Gift Cards marcados como expirados.");
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: $e->getMessage());
        }
    }

    public function copyCode(string $code): void
    {
        $this->dispatch('copy-to-clipboard', text: $code);
        $this->dispatch('notify', type: 'success', text: 'Código copiado!');
    }

   
}

?>

<div class="p-6 min-h-screen">
    <x-loading target="createGiftCard, updateGiftCard, deleteGiftCard, markAsExpired" message="PROCESSANDO..." overlay />

    {{-- Cabeçalho --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">Gestão de Gift Cards</h1>
            <p class="text-slate-400 text-sm mt-1">Crie, edite e gerencie códigos de presente</p>
        </div>
        <div class="flex gap-3">
            <button 
                wire:click="markAsExpired"
                class="px-4 py-2 bg-orange-600 hover:bg-orange-500 border border-orange-500/30 rounded-xl text-sm text-white transition-all font-bold">
                🕐 Expirar Vencidos
            </button>
            <button 
                wire:click="openCreateDrawer"
                class="px-6 py-2 bg-purple-600 hover:bg-purple-500 rounded-xl text-sm text-white font-bold transition-all shadow-lg">
                + Criar Gift Card
            </button>
        </div>
    </div>

    {{-- Estatísticas --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center text-2xl">🎁</div>
                <span class="text-xs text-slate-500 uppercase font-bold">Total</span>
            </div>
            <div class="text-3xl font-black text-white mb-1">{{ number_format($this->statistics['total'], 0) }}</div>
            <div class="text-xs text-slate-400">Gift Cards criados</div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center text-2xl">✅</div>
                <span class="text-xs text-slate-500 uppercase font-bold">Ativos</span>
            </div>
            <div class="text-3xl font-black text-emerald-400 mb-1">{{ number_format($this->statistics['active'], 0) }}</div>
            <div class="text-xs text-slate-400">C$ {{ number_format($this->statistics['total_value_active'], 0) }} disponíveis</div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center text-2xl">📥</div>
                <span class="text-xs text-slate-500 uppercase font-bold">Resgatados</span>
            </div>
            <div class="text-3xl font-black text-blue-400 mb-1">{{ number_format($this->statistics['redeemed'], 0) }}</div>
            <div class="text-xs text-slate-400">C$ {{ number_format($this->statistics['total_value_redeemed'], 0) }} resgatados</div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-red-500/10 rounded-xl flex items-center justify-center text-2xl">⏰</div>
                <span class="text-xs text-slate-500 uppercase font-bold">Expirados</span>
            </div>
            <div class="text-3xl font-black text-red-400 mb-1">{{ number_format($this->statistics['expired'], 0) }}</div>
            <div class="text-xs text-slate-400">Não utilizados</div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 mb-8 shadow-xl">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center">
            <div class="flex-1 relative">
                <input 
                    wire:model.live.debounce.400ms="search" 
                    placeholder="Buscar por código, descrição ou usuário..."
                    class="w-full bg-[#111827] border border-white/10 rounded-xl px-5 py-3 text-sm text-white pl-12 focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <select 
                wire:model.live="statusFilter"
                class="bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="all">Todos os Status</option>
                <option value="active">Ativos</option>
                <option value="redeemed">Resgatados</option>
                <option value="expired">Expirados</option>
            </select>

            <select 
                wire:model.live="sort"
                class="bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="desc">Mais Recentes</option>
                <option value="asc">Mais Antigos</option>
            </select>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="p-4 text-left font-semibold">Código</th>
                        <th class="p-4 text-left font-semibold">Valor</th>
                        <th class="p-4 text-left font-semibold">Origem</th>
                        <th class="p-4 text-left font-semibold">Criado Por</th>
                        <th class="p-4 text-left font-semibold">Status</th>
                        <th class="p-4 text-left font-semibold">Expira Em</th>
                        <th class="p-4 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($this->giftCards as $card)
                        <tr class="hover:bg-white/5 transition-all group">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div class="text-purple-400 text-lg">🎁</div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-white font-black tracking-wider">{{ $card->code }}</span>
                                            <button 
                                                wire:click="copyCode('{{ $card->code }}')"
                                                class="text-purple-500 hover:text-purple-400 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="text-slate-500 text-xs mt-1">{{ $card->created_at->format('d/m/Y H:i') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="text-white font-bold">C$ {{ number_format($card->credit_value, 0) }}</div>
                                @if($card->price_brl)
                                    <div class="text-emerald-500 text-xs">R$ {{ number_format($card->price_brl, 2, ',', '.') }}</div>
                                @endif
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs font-bold
                                    {{ $card->source === 'admin' ? 'bg-indigo-500/10 text-indigo-400' : 'bg-cyan-500/10 text-cyan-400' }}">
                                    {{ $card->source === 'admin' ? '👤 Admin' : '💳 Compra' }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-300">
                                {{ $card->createdBy?->name ?? 'Sistema' }}
                            </td>
                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full text-xs font-black uppercase
                                    {{ $card->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                    {{ $card->status === 'redeemed' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : '' }}
                                    {{ $card->status === 'expired' ? 'bg-red-500/10 text-red-400 border border-red-500/20' : '' }}">
                                    {{ $card->status }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-400 text-xs">
                                {{ $card->expires_at ? $card->expires_at->format('d/m/Y H:i') : 'Sem expiração' }}
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button 
                                        wire:click="openEditDrawer({{ $card->id }})"
                                        class="text-indigo-400 hover:text-indigo-300 transition px-3 py-1 rounded-lg hover:bg-indigo-500/10 text-xs font-medium">
                                        Editar
                                    </button>
                                    @if($card->status !== 'redeemed')
                                        <button 
                                            wire:click="deleteGiftCard({{ $card->id }})"
                                            wire:confirm="Tem certeza que deseja deletar este Gift Card?"
                                            class="text-red-400 hover:text-red-300 transition px-3 py-1 rounded-lg hover:bg-red-500/10 text-xs font-medium">
                                            Deletar
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                Nenhum Gift Card encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t border-white/5 bg-black/10">
            {{ $this->giftCards->links() }}
        </div>
    </div>

    {{-- DRAWER: CRIAR GIFT CARD --}}
    @if($showCreateDrawer)
        <x-drawer :show="$showCreateDrawer" max-width="md" wire:model="showCreateDrawer">
            <div class="border-b border-white/10 pb-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white">Criar Novo Gift Card</h2>
                        <p class="text-slate-400 text-sm">Gerar código promocional</p>
                    </div>
                    <button 
                        wire:click="closeCreateDrawer"
                        class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-white/5 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <form wire:submit="createGiftCard" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Valor em Créditos (C$) *</label>
                    <input 
                        type="number" 
                        wire:model.defer="creditValue" 
                        step="0.01" 
                        min="1"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="Ex: 100">
                    @error('creditValue') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Preço em Reais (R$) - Opcional</label>
                    <input 
                        type="number" 
                        wire:model.defer="priceBrl" 
                        step="0.01" 
                        min="0"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="Ex: 100.00 (deixe vazio se gratuito)">
                    @error('priceBrl') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Descrição</label>
                    <textarea 
                        wire:model.defer="description" 
                        rows="3"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="Ex: Promoção de Natal 2025"></textarea>
                    @error('description') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Data de Expiração (Opcional)</label>
                    <input 
                        type="datetime-local" 
                        wire:model.defer="expiresAt"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                    @error('expiresAt') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3 pt-4">
                    <button 
                        type="submit"
                        class="flex-1 bg-purple-600 hover:bg-purple-500 text-white py-3 rounded-xl text-sm font-medium transition-all">
                        Criar Gift Card
                    </button>
                    <button 
                        type="button"
                        wire:click="closeCreateDrawer"
                        class="px-6 bg-gray-800 hover:bg-gray-700 text-slate-300 py-3 rounded-xl text-sm font-medium transition-all">
                        Cancelar
                    </button>
                </div>
            </form>
        </x-drawer>
    @endif

    {{-- DRAWER: EDITAR GIFT CARD --}}
    @if($showEditDrawer && $this->selectedGiftCard)
        <x-drawer :show="$showEditDrawer" max-width="lg" wire:model="showEditDrawer">
            <div class="border-b border-white/10 pb-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white">Editar Gift Card</h2>
                        <p class="text-slate-400 text-sm">{{ $this->selectedGiftCard->code }}</p>
                    </div>
                    <button 
                        wire:click="closeEditDrawer"
                        class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-white/5 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Detalhes do Gift Card --}}
            <div class="bg-black/20 border border-white/10 rounded-xl p-6 mb-6 space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-slate-500">Valor:</span>
                        <span class="text-white font-bold ml-2">C$ {{ number_format($this->selectedGiftCard->credit_value, 0) }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500">Preço:</span>
                        <span class="text-emerald-400 font-bold ml-2">
                            {{ $this->selectedGiftCard->price_brl ? 'R$ ' . number_format($this->selectedGiftCard->price_brl, 2, ',', '.') : 'Gratuito' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-500">Origem:</span>
                        <span class="text-white font-bold ml-2">{{ $this->selectedGiftCard->source === 'admin' ? '👤 Admin' : '💳 Compra' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500">Criado por:</span>
                        <span class="text-white font-bold ml-2">{{ $this->selectedGiftCard->createdBy?->name ?? 'Sistema' }}</span>
                    </div>
                </div>

                @if($this->selectedGiftCard->description)
                    <div class="pt-4 border-t border-white/5">
                        <span class="text-slate-500 text-xs">Descrição:</span>
                        <p class="text-white text-sm mt-1">{{ $this->selectedGiftCard->description }}</p>
                    </div>
                @endif

                @if($this->selectedGiftCard->redeemed_by_user_id)
                    <div class="pt-4 border-t border-white/5 bg-blue-500/5 -m-6 mt-4 p-6 rounded-b-xl">
                        <div class="text-blue-400 text-sm font-bold mb-2">✅ Resgatado</div>
                        <div class="text-xs text-slate-400">
                            Por: <span class="text-white">{{ $this->selectedGiftCard->redeemedBy?->name }}</span><br>
                            Em: <span class="text-white">{{ $this->selectedGiftCard->redeemed_at?->format('d/m/Y H:i:s') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Histórico de Resgates --}}
            @if($this->selectedGiftCard->redemptions->count() > 0)
                <div class="mb-6">
                    <h3 class="text-sm font-bold text-white mb-4">📥 Histórico de Resgates</h3>
                    <div class="space-y-2 max-h-40 overflow-y-auto custom-scrollbar">
                        @foreach($this->selectedGiftCard->redemptions as $redemption)
                            <div class="bg-black/20 border border-white/5 rounded-lg p-3 text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="text-white font-bold">{{ $redemption->user->name }}</span>
                                    <span class="text-slate-500">{{ $redemption->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="text-slate-400 mt-1">
                                    IP: {{ $redemption->ip_address }} | C$ {{ number_format($redemption->credit_value, 0) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Formulário de Edição --}}
            <form wire:submit="updateGiftCard" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                    <select 
                        wire:model.defer="editStatus"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="active">Ativo</option>
                        <option value="expired">Expirado</option>
                        <option value="redeemed">Resgatado</option>
                    </select>
                    @error('editStatus') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Data de Expiração</label>
                    <input 
                        type="datetime-local" 
                        wire:model.defer="editExpiresAt"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                    @error('editExpiresAt') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3 pt-4">
                    <button 
                        type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white py-3 rounded-xl text-sm font-medium transition-all">
                        Salvar Alterações
                    </button>
                    <button 
                        type="button"
                        wire:click="closeEditDrawer"
                        class="px-6 bg-gray-800 hover:bg-gray-700 text-slate-300 py-3 rounded-xl text-sm font-medium transition-all">
                        Cancelar
                    </button>
                </div>
            </form>
        </x-drawer>
    @endif

    <x-toast position="top-right" />

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
            background: #111827;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }
    </style>
</div>