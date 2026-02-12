<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\Game\Game;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public Game $game;

    #[Validate('required|min:3|max:255')]
    public string $name = '';

    #[Validate('required|in:manual,automatic')]
    public string $draw_mode = 'manual';

    #[Validate('nullable|integer|min:2|max:10')]
    public ?int $auto_draw_seconds = 3;

    public int $prizes_per_round = 1;

    public array $prizes = [];

    /**
     * Regras de validação dinâmicas
     */
    public function rules(): array
    {
        return [
            'name' => 'required|min:3|max:255',
            'draw_mode' => 'required|in:manual,automatic',
            'auto_draw_seconds' => $this->draw_mode === 'automatic' ? 'required|integer|min:2|max:10' : 'nullable',
            // Valida que o número de prêmios por rodada não exceda o total cadastrado
            'prizes_per_round' => 'required|integer|min:1|max:' . count($this->prizes),
            'prizes.*.name' => 'required|min:2|max:255',
            'prizes.*.description' => 'nullable|string|max:500',
        ];
    }

    protected function messages(): array
    {
        return [
            'prizes_per_round.max' => 'O prêmio por rodada não pode ser maior que o total de prêmios cadastrados (' . count($this->prizes) . ').',
            'prizes.*.name.required' => 'O nome do prêmio é obrigatório.',
        ];
    }

    #[Computed]
    public function user()
    {
        return auth()->user();
    }

    public function setDrawMode(string $mode): void
    {
        $this->draw_mode = $mode;
        $this->auto_draw_seconds = ($mode === 'automatic') ? 3 : null;
    }

    public function mount(string $uuid): void
    {
        $this->game = Game::where('uuid', $uuid)
            ->with(['prizes', 'package', 'players'])
            ->firstOrFail();

        if ($this->game->creator_id !== $this->user->id) {
            abort(403, 'Você não é o criador desta partida.');
        }

        if ($this->game->status !== 'draft' && $this->game->status !== 'waiting') {
            session()->flash('error', 'Apenas partidas em rascunho ou aguardando podem ser editadas.');
            $this->redirect(route('games.index'));
            return;
        }

        $this->name = $this->game->name;
        $this->draw_mode = $this->game->draw_mode;
        $this->auto_draw_seconds = $this->game->auto_draw_seconds ?? 3;
        $this->prizes_per_round = $this->game->prizes_per_round ?? 1;

        $this->prizes = $this->game->prizes
            ->sortBy('position')
            ->map(fn($prize) => [
                'name' => $prize->name ?? '',
                'description' => $prize->description ?? '',
            ])
            ->toArray();
            
        if (empty($this->prizes)) {
            $this->addPrize();
        }
    }

    public function addPrize(): void
    {
        $this->prizes[] = ['name' => '', 'description' => ''];
    }

    public function removePrize(int $index): void
    {
        unset($this->prizes[$index]);
        $this->prizes = array_values($this->prizes);

        // Ajusta automaticamente o prizes_per_round se ele ficar órfão
        if ($this->prizes_per_round > count($this->prizes) && count($this->prizes) > 0) {
            $this->prizes_per_round = count($this->prizes);
        }
    }

    public function update(): void
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $this->game->update([
                    'name' => $this->name,
                    'draw_mode' => $this->draw_mode,
                    'auto_draw_seconds' => $this->draw_mode === 'automatic' ? $this->auto_draw_seconds : 3,
                    'prizes_per_round' => $this->prizes_per_round,
                ]);

                $this->game->prizes()->delete();
                foreach ($this->prizes as $index => $prize) {
                    $this->game->prizes()->create([
                        'name' => $prize['name'],
                        'description' => $prize['description'] ?? '',
                        'position' => $index + 1,
                    ]);
                }
            });

            $this->game->refresh();
            session()->flash('success', 'Alterações salvas com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao salvar: ' . $e->getMessage());
        }
    }

    public function publish(): void
    {
        $this->validate();

        if (empty($this->prizes)) {
            session()->flash('error', 'Adicione pelo menos um prêmio antes de publicar.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->game->update([
                    'name' => $this->name,
                    'draw_mode' => $this->draw_mode,
                    'auto_draw_seconds' => $this->draw_mode === 'automatic' ? $this->auto_draw_seconds : 3,
                    'prizes_per_round' => $this->prizes_per_round,
                    'status' => 'waiting',
                ]);

                $this->game->prizes()->delete();
                foreach ($this->prizes as $index => $prize) {
                    $this->game->prizes()->create([
                        'name' => $prize['name'],
                        'description' => $prize['description'] ?? '',
                        'position' => $index + 1,
                    ]);
                }

                if ($this->game->players()->count() > 0) {
                    $this->game->generateCardsForCurrentRound();
                }
            });

            session()->flash('success', 'Partida publicada com sucesso!');
            $this->redirect(route('games.play', $this->game->uuid));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao publicar: ' . $e->getMessage());
        }
    }
};
?>
<div>
    <x-slot name="header">Ajustar Missão</x-slot>

    <div class="max-w-5xl mx-auto px-4 py-12">
        
        {{-- Cabeçalho de Comando --}}
        <div class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-4 mb-3">
                    <div class="h-[1px] w-12 bg-gradient-to-r from-blue-600 to-transparent"></div>
                    <span class="text-blue-500/80 font-black tracking-[0.4em] uppercase text-[9px] italic">Mission Reconfiguration</span>
                </div>
                <h1 class="text-5xl font-black text-white tracking-tighter uppercase italic leading-none">
                    EDITAR <span class="text-blue-500">PARTIDA</span>
                </h1>
                <div class="flex items-center gap-2 mt-4">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest italic">{{ $game->name }}</p>
                </div>
            </div>

            <a href="{{ route('games.index') }}" 
                class="inline-flex items-center gap-4 px-6 py-3 bg-white/5 border border-white/10 rounded-2xl text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-white hover:bg-white/10 transition-all group italic">
                <span class="group-hover:-translate-x-1 transition-transform">←</span> Voltar ao Painel
            </a>
        </div>

        {{-- Alertas Táticos --}}
        @if (session('success'))
            <div class="mb-8 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-8 py-4 rounded-[1.5rem] flex items-center gap-4 animate-in fade-in slide-in-from-top-4 duration-500">
                <span class="text-xl">✅</span>
                <span class="text-[10px] font-black uppercase tracking-[0.2em] italic">{{ session('success') }}</span>
            </div>
        @endif

        <div class="space-y-10">
            {{-- Info do Pacote (Visual Dossiê) --}}
            <div class="relative overflow-hidden bg-[#0b0d11] border border-white/10 rounded-[2.5rem] p-10 shadow-2xl">
                <div class="absolute top-0 right-0 w-64 h-full bg-blue-600/[0.02] blur-3xl -rotate-12 translate-x-1/2"></div>
                
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="text-blue-500/50 text-xs">📁</span>
                        <span class="text-slate-500 text-[10px] font-black uppercase tracking-[0.3em] italic">Protocolo de Operação Ativo</span>
                    </div>

                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-8">
                        <div>
                            <h3 class="text-3xl font-black text-white uppercase italic tracking-tighter">{{ $game->package->name ?? 'Padrão' }}</h3>
                            <p class="text-[9px] font-bold text-slate-600 uppercase tracking-widest mt-1 italic">Este protocolo não pode ser alterado após inicialização</p>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-8 border-l border-white/5 pl-8">
                            <div class="flex flex-col">
                                <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2 italic">Capacidade</span>
                                <span class="text-sm font-black text-white uppercase italic tracking-tighter">{{ $game->package->max_players ?? '?' }} Players</span>
                            </div>
                            <div class="flex flex-col border-l border-white/5 pl-8">
                                <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2 italic">Cartelas</span>
                                <span class="text-sm font-black text-white uppercase italic tracking-tighter">{{ $game->cards_per_player ?? '?' }} / Un</span>
                            </div>
                            <div class="flex flex-col border-l border-white/5 pl-8">
                                <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-2 italic">Ciclos</span>
                                <span class="text-sm font-black text-white uppercase italic tracking-tighter">{{ $game->max_rounds ?? '?' }} Total</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form wire:submit="update" class="space-y-10">
                {{-- Configuração Básica --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] ml-6 italic">Nome da Partida</label>
                        <input type="text" wire:model.blur="name"
                            class="w-full bg-[#0b0d11] border border-white/10 rounded-[2rem] px-8 py-5 text-white font-black uppercase italic tracking-widest focus:border-blue-500/50 focus:ring-0 transition-all">
                        @error('name') <span class="text-red-500 text-[10px] font-black uppercase ml-6 tracking-widest italic">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-4">
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] ml-6 italic">Prêmios / Ciclo</label>
                        <div class="relative group">
                            <input type="number" wire:model.live="prizes_per_round" min="1"
                                class="w-full bg-[#0b0d11] border border-white/10 rounded-[2rem] px-8 py-5 text-white font-black text-2xl italic tracking-tighter focus:border-blue-500/50 focus:ring-0 transition-all">
                            <div class="absolute right-6 top-1/2 -translate-y-1/2 text-right">
                                <div class="text-[8px] font-black text-slate-600 uppercase italic">Total</div>
                                <div class="text-lg font-black text-blue-500 italic leading-none">{{ count($prizes) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Módulo de Sorteio --}}
                <div class="bg-[#0b0d11] border border-white/10 rounded-[2.5rem] p-10 shadow-2xl">
                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] mb-8 block italic">Módulo de Sorteio</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <button type="button" wire:click="setDrawMode('manual')"
                            class="relative p-6 rounded-2xl border transition-all flex items-center gap-4
                            {{ $draw_mode === 'manual' ? 'border-blue-500 bg-blue-500/10 shadow-[0_0_20px_rgba(59,130,246,0.1)]' : 'border-white/10 bg-white/5 grayscale' }}">
                            <span class="text-2xl">{{ $draw_mode === 'manual' ? '🕹️' : '⚪' }}</span>
                            <div class="text-left">
                                <div class="text-[11px] font-black uppercase italic {{ $draw_mode === 'manual' ? 'text-white' : 'text-slate-500' }}">Manual Command</div>
                                <div class="text-[9px] font-bold text-slate-600 uppercase mt-1 italic tracking-widest">Controle Tático Host</div>
                            </div>
                        </button>

                        <button type="button" wire:click="setDrawMode('automatic')"
                            class="relative p-6 rounded-2xl border transition-all flex items-center gap-4
                            {{ $draw_mode === 'automatic' ? 'border-blue-500 bg-blue-500/10 shadow-[0_0_20px_rgba(59,130,246,0.1)]' : 'border-white/10 bg-white/5 grayscale' }}">
                            <span class="text-2xl">{{ $draw_mode === 'automatic' ? '⚡' : '⚪' }}</span>
                            <div class="text-left">
                                <div class="text-[11px] font-black uppercase italic {{ $draw_mode === 'automatic' ? 'text-white' : 'text-slate-500' }}">Auto Engine</div>
                                <div class="text-[9px] font-bold text-slate-600 uppercase mt-1 italic tracking-widest">Sorteio Automatizado</div>
                            </div>
                        </button>
                    </div>

                    @if ($draw_mode === 'automatic')
                        <div class="mt-10 pt-10 border-t border-white/5 animate-in zoom-in duration-300">
                            <div class="flex justify-between items-center mb-6">
                                <label class="text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] italic">Intervalo de Varredura</label>
                                <span class="bg-blue-600 text-white px-5 py-1.5 rounded-xl font-black text-[10px] shadow-lg shadow-blue-600/30 tracking-widest">{{ $auto_draw_seconds }} SEG</span>
                            </div>
                            <input type="range" wire:model.live="auto_draw_seconds" min="2" max="10" step="1"
                                class="w-full h-1.5 bg-white/10 rounded-lg appearance-none cursor-pointer accent-blue-600">
                        </div>
                    @endif
                </div>

                {{-- Gestão de Prêmios --}}
                <div class="bg-[#0b0d11] border border-white/10 rounded-[2.5rem] p-10 shadow-2xl">
                    <div class="flex justify-between items-center mb-10">
                        <div class="flex items-center gap-3">
                            <span class="text-blue-500">🎁</span>
                            <h3 class="text-[11px] font-black text-white uppercase tracking-[0.2em] italic">Manifesto de Recompensas</h3>
                        </div>
                        <button type="button" wire:click="addPrize" class="text-[10px] font-black text-blue-500 hover:text-white uppercase tracking-[0.2em] italic transition-colors">
                            + Nova Recompensa
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach ($prizes as $index => $prize)
                            <div class="group bg-white/[0.02] border border-white/10 rounded-2xl p-6 transition-all hover:border-white/20" wire:key="prize-{{ $index }}">
                                <div class="flex justify-between mb-4">
                                    <span class="text-[9px] font-black text-slate-600 uppercase italic">Slot #{{ $index + 1 }}</span>
                                    @if (count($prizes) > 1)
                                        <button type="button" wire:click="removePrize({{ $index }})" class="text-red-900 group-hover:text-red-500 transition-colors text-[9px] font-black uppercase italic tracking-widest">Eliminar</button>
                                    @endif
                                </div>
                                <input type="text" wire:model.blur="prizes.{{ $index }}.name" placeholder="NOME DO ITEM"
                                    class="w-full bg-[#05070a] border border-white/5 rounded-xl px-4 py-3 text-white text-[11px] font-black uppercase italic tracking-widest focus:border-blue-500/50 focus:ring-0 mb-3 transition-all">
                                <textarea wire:model.blur="prizes.{{ $index }}.description" placeholder="DETALHES DA RECOMPENSA..." rows="2"
                                    class="w-full bg-[#05070a] border border-white/5 rounded-xl px-4 py-3 text-white text-[10px] font-bold focus:border-blue-500/50 focus:ring-0 transition-all"></textarea>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Ações de Comando --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-12">
                    <button type="submit"
                        class="group relative bg-blue-600 hover:bg-blue-500 text-white py-6 rounded-[2rem] font-black uppercase text-[11px] tracking-[0.3em] italic transition-all shadow-2xl shadow-blue-600/20 overflow-hidden">
                        <span class="relative z-10">Atualizar Missão</span>
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                    </button>

                    <button type="button" wire:click="publish"
                        class="group relative bg-emerald-600 hover:bg-emerald-500 text-white py-6 rounded-[2rem] font-black uppercase text-[11px] tracking-[0.3em] italic transition-all shadow-2xl shadow-emerald-600/20 overflow-hidden">
                        <span class="relative z-10">🚀 Publicar Arena</span>
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                    </button>

                    <a href="{{ route('games.index') }}"
                        class="bg-transparent hover:bg-white/5 text-slate-600 hover:text-white py-6 rounded-[2rem] font-black uppercase text-[11px] tracking-[0.3em] italic text-center transition-all border border-white/10">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>