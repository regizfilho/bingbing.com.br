<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Coupon;
use Illuminate\Support\Carbon;

new #[Layout('layouts.admin')] #[Title('Sistema de Cupons')]
class extends Component {

    public ?int $coupon_id = null;

    public string $code = '';
    public string $description = '';
    public string $type = 'percent';
    public string $value = '';
    public ?string $expires_at = null;
    public ?int $usage_limit = null;
    public int $per_user_limit = 1;
    public ?float $min_order_value = null;
    public bool $is_active = true;

    public bool $showModal = false;

    protected function rules()
    {
        return [
            'code' => 'required|min:3|unique:coupons,code,' . $this->coupon_id,
            'value' => 'required|numeric|min:0',
        ];
    }

    public function create()
    {
        $this->reset();
        $this->type = 'percent';
        $this->is_active = true;
        $this->per_user_limit = 1;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $coupon = Coupon::findOrFail($id);

        $this->fill([
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'description' => $coupon->description,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'expires_at' => optional($coupon->expires_at)->format('Y-m-d\TH:i'),
            'usage_limit' => $coupon->usage_limit,
            'per_user_limit' => $coupon->per_user_limit,
            'min_order_value' => $coupon->min_order_value,
            'is_active' => $coupon->is_active,
        ]);

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        Coupon::updateOrCreate(
            ['id' => $this->coupon_id],
            [
                'code' => strtoupper($this->code),
                'description' => $this->description,
                'type' => $this->type,
                'value' => $this->value,
                'expires_at' => $this->expires_at,
                'usage_limit' => $this->usage_limit,
                'per_user_limit' => $this->per_user_limit,
                'min_order_value' => $this->min_order_value,
                'is_active' => $this->is_active,
            ]
        );

        $this->reset();
        $this->showModal = false;
    }
};
?>

<div class="space-y-8">

    {{-- DASHBOARD --}}
    <div class="grid grid-cols-3 gap-6">
        <div class="bg-black/40 p-6 rounded-xl">
            <div class="text-slate-400 text-xs uppercase">Total Cupons</div>
            <div class="text-2xl font-bold text-white">
                {{ \App\Models\Coupon::count() }}
            </div>
        </div>

        <div class="bg-black/40 p-6 rounded-xl">
            <div class="text-slate-400 text-xs uppercase">Ativos</div>
            <div class="text-2xl font-bold text-indigo-400">
                {{ \App\Models\Coupon::where('is_active',1)->count() }}
            </div>
        </div>

        <div class="bg-black/40 p-6 rounded-xl">
            <div class="text-slate-400 text-xs uppercase">Usos Totais</div>
            <div class="text-2xl font-bold text-green-400">
                {{ \App\Models\Coupon::sum('used_count') }}
            </div>
        </div>
    </div>

    {{-- LISTAGEM --}}
    <div class="bg-[#0a0c12] border border-white/5 rounded-2xl shadow-xl">

        <div class="p-6 border-b border-white/5 flex justify-between">
            <h3 class="text-sm font-bold text-white uppercase">Cupons</h3>
            <button wire:click="create"
                class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-xl text-xs font-black uppercase">
                + Novo
            </button>
        </div>

        <table class="w-full text-xs">
            <thead class="text-slate-500 uppercase bg-black/30">
                <tr>
                    <th class="p-4">Código</th>
                    <th>Valor</th>
                    <th>Restante</th>
                    <th>Status</th>
                    <th class="text-right pr-6">Ações</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-white/5">
            @foreach(\App\Models\Coupon::latest()->get() as $coupon)

                @php
                    $expired = $coupon->expires_at && $coupon->expires_at->isPast();
                @endphp

                <tr class="hover:bg-white/5">
                    <td class="p-4 text-white font-bold">
                        {{ $coupon->code }}
                        <div class="text-slate-500 text-[10px]">
                            {{ $coupon->description }}
                        </div>
                    </td>

                    <td class="text-indigo-400">
                        {{ $coupon->type === 'percent' ? $coupon->value.'%' : 'R$ '.$coupon->value }}
                    </td>

                    <td class="text-slate-400">
                        {{ $coupon->remaining }}
                    </td>

                    <td>
                        @if($expired)
                            <span class="text-red-500 font-bold">EXPIRADO</span>
                        @elseif(!$coupon->is_active)
                            <span class="text-yellow-400 font-bold">INATIVO</span>
                        @else
                            <span class="text-green-400 font-bold">ATIVO</span>
                        @endif
                    </td>

                    <td class="text-right pr-6">
                        <button wire:click="edit({{ $coupon->id }})"
                            class="text-indigo-400 hover:text-white font-bold uppercase">
                            Editar
                        </button>
                    </td>
                </tr>

            @endforeach
            </tbody>
        </table>
    </div>

    {{-- MODAL --}}
    @if($showModal)
    <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
        <div class="bg-[#0a0c12] w-full max-w-6xl rounded-2xl p-8 space-y-8">

            <form wire:submit.prevent="save" class="space-y-6">

                <div class="grid grid-cols-4 gap-4">
                    <input wire:model.live="code" placeholder="Código"
                        class="bg-black/40 border-white/10 rounded-xl text-white p-3 text-xs uppercase">

                    <input wire:model.live="description" placeholder="Descrição"
                        class="bg-black/40 border-white/10 rounded-xl text-white p-3 text-xs">

                    <select wire:model.live="type"
                        class="bg-black/40 border-white/10 rounded-xl text-white p-3 text-xs">
                        <option value="percent">Percentual</option>
                        <option value="fixed">Valor Fixo</option>
                    </select>

                    <input wire:model.live="value" type="number" step="0.01"
                        class="bg-black/40 border-white/10 rounded-xl text-white p-3 text-xs">
                </div>

                <div class="grid grid-cols-4 gap-4">
                    <input wire:model.live="expires_at" type="datetime-local"
                        class="bg-black/40 border-white/10 rounded-xl text-white p-3 text-xs">

                    <input wire:model.live="usage_limit" type="number"
                        placeholder="Limite Global"
                        class="bg-black/40 border-white/10 rounded-xl text-white p-3 text-xs">

                    <input wire:model.live="per_user_limit" type="number"
                        placeholder="Limite por Usuário"
                        class="bg-black/40 border-white/10 rounded-xl text-white p-3 text-xs">

                    <input wire:model.live="min_order_value" type="number"
                        placeholder="Pedido Mínimo"
                        class="bg-black/40 border-white/10 rounded-xl text-white p-3 text-xs">
                </div>

                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white py-3 rounded-xl text-xs font-black uppercase">
                    Salvar
                </button>
            </form>

            {{-- HISTÓRICO COMPLETO --}}
            @if($coupon_id)
            <div class="border-t border-white/10 pt-6">
                <h4 class="text-xs uppercase text-slate-400 mb-4">Histórico de Uso</h4>

                <div class="max-h-64 overflow-y-auto space-y-2">
                    @foreach(\App\Models\Coupon::find($coupon_id)->users()->latest()->get() as $user)
                        <div class="bg-black/40 p-4 rounded-xl text-xs flex justify-between">
                            <div>
                                <div class="text-white font-bold">{{ $user->name }}</div>
                                <div class="text-slate-500">
                                    IP: {{ $user->pivot->ip_address ?? 'N/A' }}
                                </div>
                            </div>

                            <div class="text-right text-slate-400">
                                <div>Pedido: R$ {{ $user->pivot->order_value }}</div>
                                <div>Desconto: R$ {{ $user->pivot->discount_amount }}</div>
                                <div>{{ $user->pivot->used_at }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <button wire:click="$set('showModal', false)"
                class="w-full bg-slate-800 hover:bg-slate-700 text-white py-3 rounded-xl text-xs uppercase">
                Fechar
            </button>

        </div>
    </div>
    @endif

</div>
