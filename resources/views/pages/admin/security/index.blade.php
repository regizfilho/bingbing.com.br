<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\WhitelistIp;
use App\Models\FirewallLog;

new #[Layout('layouts.admin')] #[Title('Segurança Firewall')] 
class extends Component {
    public $ip, $label, $editingId;

    public function save() {
        $this->validate([
            'ip' => 'required|ip',
            'label' => 'required|string|max:50'
        ]);

        WhitelistIp::updateOrCreate(
            ['id' => $this->editingId],
            ['ip' => $this->ip, 'label' => $this->label]
        );

        $this->dispatch('close-modal');
        
        // PADRÃO: notify (conforme sua página de salas)
        $this->dispatch('notify', type: 'success', text: 'CONFIGURAÇÃO SALVA COM SUCESSO!');

        $this->reset(['ip', 'label', 'editingId']);
    }

    public function delete($id) {
        WhitelistIp::destroy($id);
        
        $this->dispatch('notify', type: 'warning', text: 'IP REMOVIDO DA WHITELIST.');
    }

    public function toggle($id) {
        $item = WhitelistIp::find($id);
        $item->update(['is_active' => !$item->is_active]);

        $status = $item->is_active ? 'ATIVADO' : 'DESATIVADO';
        $this->dispatch('notify', type: 'info', text: "IP {$item->ip} {$status}!");
    }

    public function render() {
        return view('pages.admin.security.index', [
            'ips' => WhitelistIp::latest()->get(),
            'logs' => FirewallLog::latest()->take(15)->get()
        ]);
    }
}; ?>

<div >
    {{-- LOADING: Agora com targets específicos para não piscar no poll --}}
    <x-loading target="save, delete, toggle" message="PROCESSANDO..." />

    <x-slot name="header">Segurança de Acesso</x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        {{-- Lista de Whitelist --}}
        <div class="lg:col-span-3">
            <div class="bg-[#0a0c12] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/[0.01]">
                    <div>
                        <h3 class="text-sm font-bold text-white uppercase tracking-widest">Endereços Autorizados</h3>
                        <p class="text-[10px] text-slate-500 mt-1 uppercase italic text-xs">Filtro de camada de aplicação (WAF)</p>
                    </div>
                    <button x-on:click="$dispatch('open-modal', { name: 'ip-modal' })" 
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black rounded-xl transition-all shadow-lg shadow-indigo-600/20 uppercase italic">
                        + NOVO IP
                    </button>
                </div>

                <table class="w-full text-left">
                    <thead class="bg-black/40 text-[10px] text-slate-500 uppercase font-bold tracking-widest">
                        <tr>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Endereço IP</th>
                            <th class="px-6 py-4">Descrição</th>
                            <th class="px-6 py-4">Data</th>
                            <th class="px-6 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 font-bold italic">
                        @foreach($ips as $item)
                        <tr class="hover:bg-white/[0.02] transition-colors group">
                            <td class="px-6 py-4">
                                {{-- Target no toggle para desabilitar durante a carga --}}
                                <button wire:click="toggle({{ $item->id }})" 
                                        wire:loading.attr="disabled"
                                        wire:target="toggle({{ $item->id }})"
                                        class="relative inline-flex h-5 w-10 items-center rounded-full transition-colors {{ $item->is_active ? 'bg-indigo-600' : 'bg-slate-800' }}">
                                    <span class="inline-block h-3 w-3 transform rounded-full bg-white transition {{ $item->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 font-mono text-indigo-400 text-sm tracking-tighter">{{ $item->ip }}</td>
                            <td class="px-6 py-4 text-slate-400 text-xs uppercase">{{ $item->label }}</td>
                            <td class="px-6 py-4 text-slate-500 text-[10px]">{{ $item->created_at->format('d/m/H:i') }}</td>
                            <td class="px-6 py-4 text-right">
                                <button wire:confirm="Confirmar exclusão deste IP?" 
                                        wire:click="delete({{ $item->id }})"
                                        wire:loading.attr="disabled"
                                        class="text-red-900 hover:text-red-500 transition-colors uppercase text-[10px] font-black">
                                    [ REMOVER ]
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Logs Laterais --}}
        <div class="lg:col-span-1">
            <div class="bg-[#0a0c12] border border-white/5 rounded-2xl p-6 shadow-xl h-full">
                <h3 class="text-xs font-bold text-red-500 uppercase tracking-[0.2em] mb-6 flex items-center gap-2 italic">
                    <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                    Tentativas Bloqueadas
                </h3>
                <div class="space-y-4 font-bold italic">
                    @forelse($logs as $log)
                    <div class="p-3 rounded-xl bg-red-500/5 border border-red-500/10 hover:border-red-500/30 transition-all group">
                        <div class="flex justify-between items-start mb-1">
                            <span class="font-mono text-[11px] text-red-400">{{ $log->ip }}</span>
                            <span class="text-[9px] text-slate-600 uppercase">{{ $log->created_at->diffForHumans(null, true) }}</span>
                        </div>
                        <p class="text-[9px] text-slate-500 truncate group-hover:text-slate-400 uppercase tracking-tighter">{{ $log->url }}</p>
                    </div>
                    @empty
                    <div class="text-center py-10">
                        <p class="text-[10px] text-slate-700 uppercase font-black tracking-widest italic">Nenhuma ameaça</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Modal --}}
    <x-modal name="ip-modal" title="Gerenciar Whitelist">
        <form wire:submit="save" class="space-y-5 italic font-bold">
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2">Endereço IP</label>
                <input wire:model="ip" type="text" placeholder="0.0.0.0" 
                       class="w-full bg-black/40 border-white/10 rounded-xl text-white py-3 px-4 focus:ring-2 focus:ring-indigo-500/50 outline-none transition-all placeholder:text-slate-800 uppercase">
                @error('ip') <span class="text-red-500 text-[10px] font-black mt-1 uppercase">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2">Referência</label>
                <input wire:model="label" type="text" placeholder="EX: SERVIDOR PRINCIPAL" 
                       class="w-full bg-black/40 border-white/10 rounded-xl text-white py-3 px-4 focus:ring-2 focus:ring-indigo-500/50 outline-none transition-all placeholder:text-slate-800 uppercase">
                @error('label') <span class="text-red-500 text-[10px] font-black mt-1 uppercase">{{ $message }}</span> @enderror
            </div>

            <div class="pt-4 flex flex-col gap-3">
                <button type="submit" 
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white py-4 rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-indigo-600/30">
                    <span wire:loading.remove wire:target="save">Confirmar Acesso</span>
                    <span wire:loading wire:target="save">Salvando...</span>
                </button>
                <button type="button" x-on:click="$dispatch('close-modal')" class="w-full py-2 text-[10px] font-black text-slate-600 uppercase tracking-widest hover:text-white transition-colors">
                    Voltar
                </button>
            </div>
        </form>
    </x-modal>

    {{-- TOAST: Chamada padrão conforme solicitado --}}
    <x-toast />
</div>