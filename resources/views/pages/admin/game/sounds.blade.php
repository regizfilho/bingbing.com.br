<?php

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\GameAudio;

new #[Layout('layouts.admin')] #[Title('Gestão de Áudios de Jogo')] class extends Component {

    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    // FILTROS
    public string $search = '';
    public string $filterType = 'all';
    public string $filterAudioType = 'all';
    public string $sort = 'desc';

    // FORM
    public ?int $editingId = null;

    public string $name = '';
    public string $type = 'system';
    public string $audio_type = 'mp3';

    public ?string $file_path = null;
    public ?string $tts_text = null;
    public ?string $tts_voice = null;
    public ?string $tts_language = 'pt-BR';

    public ?float $tts_rate = 1.0;
    public ?float $tts_pitch = 1.0;
    public ?float $tts_volume = 1.0;

    public bool $is_default = false;
    public bool $is_active = true;
    public int $order = 0;

    public bool $showDrawer = false;

    protected function rules(): array
    {
        return [
            'name' => ['required','string','max:255'],
            'type' => ['required','in:system,player'],
            'audio_type' => ['required','in:mp3,tts'],
            'file_path' => ['required_if:audio_type,mp3','nullable','string','max:500'],
            'tts_text' => ['required_if:audio_type,tts','nullable','string','max:500'],
            'tts_voice' => ['nullable','string','max:255'],
            'tts_language' => ['nullable','string','max:10'],
            'tts_rate' => ['nullable','numeric','min:0.1','max:10'],
            'tts_pitch' => ['nullable','numeric','min:0.1','max:2'],
            'tts_volume' => ['nullable','numeric','min:0','max:1'],
            'order' => ['required','integer','min:0'],
        ];
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterType() { $this->resetPage(); }
    public function updatingFilterAudioType() { $this->resetPage(); }

    public function toggleSort(): void
    {
        $this->sort = $this->sort === 'desc' ? 'asc' : 'desc';
    }

    #[Computed]
    public function audios()
    {
        return GameAudio::query()
            ->when($this->search, fn($q) => $q->where('name','like',"%{$this->search}%"))
            ->when($this->filterType !== 'all', fn($q) => $q->where('type',$this->filterType))
            ->when($this->filterAudioType !== 'all', fn($q) => $q->where('audio_type',$this->filterAudioType))
            ->orderBy('order')
            ->orderBy('created_at',$this->sort)
            ->paginate(10);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => GameAudio::count(),
            'active' => GameAudio::where('is_active',true)->count(),
            'inactive' => GameAudio::where('is_active',false)->count(),
            'system' => GameAudio::where('type','system')->where('is_active',true)->count(),
            'player' => GameAudio::where('type','player')->where('is_active',true)->count(),
        ];
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->showDrawer = true;
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->showDrawer = false;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->type = 'system';
        $this->audio_type = 'mp3';
        $this->file_path = null;
        $this->tts_text = null;
        $this->tts_voice = null;
        $this->tts_language = 'pt-BR';
        $this->tts_rate = 1.0;
        $this->tts_pitch = 1.0;
        $this->tts_volume = 1.0;
        $this->is_default = false;
        $this->is_active = true;
        $this->order = 0;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $audio = GameAudio::findOrFail($id);

        $this->editingId = $audio->id;
        $this->name = $audio->name;
        $this->type = $audio->type;
        $this->audio_type = $audio->audio_type;
        $this->file_path = $audio->file_path;
        $this->tts_text = $audio->tts_text;
        $this->tts_voice = $audio->tts_voice;
        $this->tts_language = $audio->tts_language ?? 'pt-BR';
        $this->tts_rate = $audio->tts_rate ?? 1.0;
        $this->tts_pitch = $audio->tts_pitch ?? 1.0;
        $this->tts_volume = $audio->tts_volume ?? 1.0;
        $this->is_default = (bool) $audio->is_default;
        $this->is_active = (bool) $audio->is_active;
        $this->order = $audio->order;

        $this->showDrawer = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->is_default) {
            GameAudio::where('type',$this->type)
                ->where('id','!=',$this->editingId ?? 0)
                ->update(['is_default'=>false]);
        }

        if ($this->editingId) {
            GameAudio::where('id',$this->editingId)->update($this->payload());
        } else {
            GameAudio::create($this->payload());
        }

        $this->closeModal();
    }

    private function payload(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'audio_type' => $this->audio_type,
            'file_path' => $this->audio_type === 'mp3' ? $this->file_path : null,
            'tts_text' => $this->audio_type === 'tts' ? $this->tts_text : null,
            'tts_voice' => $this->audio_type === 'tts' ? $this->tts_voice : null,
            'tts_language' => $this->audio_type === 'tts' ? $this->tts_language : null,
            'tts_rate' => $this->audio_type === 'tts' ? $this->tts_rate : null,
            'tts_pitch' => $this->audio_type === 'tts' ? $this->tts_pitch : null,
            'tts_volume' => $this->audio_type === 'tts' ? $this->tts_volume : null,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'order' => $this->order,
        ];
    }

    public function toggleStatus(int $id): void
    {
        $audio = GameAudio::findOrFail($id);
        $audio->update(['is_active'=>!$audio->is_active]);
    }

    public function delete(int $id): void
    {
        GameAudio::where('id',$id)->delete();
    }
};
?>

<div class="p-6 min-h-screen">
    <!-- HEADER -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">
                Gestão de Áudios de Jogo
            </h1>
            <p class="text-slate-400 text-sm mt-1">Configure sons e vozes para notificações e display</p>
        </div>
        <button wire:click="openModal"
            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-sm font-medium text-white transition-all flex items-center gap-2 shadow-lg shadow-indigo-500/20">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Novo Áudio
        </button>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Total de Áudios</p>
                    <p class="text-3xl font-bold text-white mt-2">{{ $this->stats['total'] }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Áudios Ativos</p>
                    <p class="text-3xl font-bold text-emerald-400 mt-2">{{ $this->stats['active'] }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Áudios Inativos</p>
                    <p class="text-3xl font-bold text-slate-400 mt-2">{{ $this->stats['inactive'] }}</p>
                </div>
                <div class="w-12 h-12 bg-slate-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Sons Sistema</p>
                    <p class="text-3xl font-bold text-blue-400 mt-2">{{ $this->stats['system'] }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a21.274 21.274 0 113.414 3.414L5.232 15.232" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Sons Display</p>
                    <p class="text-3xl font-bold text-purple-400 mt-2">{{ $this->stats['player'] }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- BUSCA E FILTROS -->
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center">
            <div class="flex-1 relative">
                <input wire:model.live.debounce.400ms="search" placeholder="Buscar áudio por nome..."
                    class="w-full bg-[#111827] border border-white/10 rounded-xl px-5 py-3 text-sm text-white pl-12 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <div class="flex gap-3 w-full lg:w-auto">
                <select wire:model.live="filterType"
                    class="flex-1 lg:flex-none bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Todos os Tipos</option>
                    <option value="system">Sistema</option>
                    <option value="player">Display</option>
                </select>
                <select wire:model.live="filterAudioType"
                    class="flex-1 lg:flex-none bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Todos os Formatos</option>
                    <option value="mp3">MP3</option>
                    <option value="tts">TTS (Voz)</option>
                </select>
                <button wire:click="toggleSort"
                    class="px-4 py-3 bg-gray-800 border border-white/10 rounded-xl text-sm text-white hover:bg-gray-700 transition-all whitespace-nowrap">
                    {{ $sort === 'desc' ? '↓ Mais Recentes' : '↑ Mais Antigos' }}
                </button>
            </div>
        </div>
    </div>

    <!-- TABELA -->
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="p-4 text-left font-semibold">Nome</th>
                        <th class="p-4 text-center font-semibold">Tipo</th>
                        <th class="p-4 text-center font-semibold">Formato</th>
                        <th class="p-4 text-center font-semibold">Padrão</th>
                        <th class="p-4 text-center font-semibold">Status</th>
                        <th class="p-4 text-center font-semibold">Criado Em</th>
                        <th class="p-4 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($this->audios as $audio)
                        <tr wire:key="audio-{{ $audio->id }}" class="hover:bg-white/5 transition-all group">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-white font-semibold">{{ $audio->name }}</div>
                                        <div class="text-slate-400 text-xs">{{ $audio->type }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <span
                                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-500/10 border border-blue-500/20 rounded-lg">
                                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span
                                        class="text-blue-400 font-bold">{{ ucfirst($audio->audio_type) }}</span>
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <div class="text-white font-bold text-lg">
                                    {{ $audio->tts_text ?? $audio->file_path ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold {{ $audio->is_default ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-500/10 text-slate-400 border border-slate-500/20' }}">
                                    {{ $audio->is_default ? 'Sim' : 'Não' }}
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <button wire:click="toggleStatus({{ $audio->id }})"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $audio->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-slate-500/10 text-slate-400 border border-slate-500/20 hover:bg-slate-500/20' }}">
                                    <span
                                        class="w-2 h-2 rounded-full {{ $audio->is_active ? 'bg-emerald-400' : 'bg-slate-400' }}"></span>
                                    {{ $audio->is_active ? 'Ativo' : 'Inativo' }}
                                </button>
                            </td>
                            <td class="p-4 text-center text-slate-400 text-xs">
                                {{ $audio->created_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="edit({{ $audio->id }})"
                                        class="p-2 text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-all"
                                        title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button wire:click="delete({{ $audio->id }})"
                                        wire:confirm="Tem certeza que deseja excluir este áudio? Esta ação não pode ser desfeita."
                                        class="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-all"
                                        title="Excluir">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center">
                                <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <p class="text-slate-400 font-medium mb-2">Nenhum áudio encontrado</p>
                                <p class="text-slate-500 text-sm">Crie seu primeiro áudio clicando no botão "Novo Áudio"</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->audios->hasPages())
            <div class="p-6 border-t border-white/5 bg-black/10">
                {{ $this->audios->links() }}
            </div>
        @endif
    </div>

    <!-- DRAWER LATERAL -->
    @if ($showDrawer)
        <x-drawer :show="$showDrawer" max-width="md" wire:model="showDrawer">
            <div class="border-b border-white/10 flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">
                        {{ $editingId ? 'Editar Áudio' : 'Novo Áudio' }}
                    </h2>
                    <p class="text-slate-400 text-sm">
                        {{ $editingId ? 'Atualize as configurações do áudio' : 'Crie um novo som ou voz para o jogo' }}
                    </p>
                </div>
                <button wire:click="closeModal"
                    class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-white/5 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Nome do Áudio</label>
                    <input wire:model.defer="name" type="text"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ex: Número sorteado">
                    @error('name')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Tipo</label>
                    <select wire:model="type"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="system">Sistema (Notificações)</option>
                        <option value="player">Display (Público)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Formato</label>
                    <select wire:model="audio_type"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="mp3">MP3 (Arquivo)</option>
                        <option value="tts">TTS (Voz Sintetizada)</option>
                    </select>
                </div>

                @if($audio_type === 'mp3')
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Caminho do Arquivo MP3</label>
                        <input wire:model.defer="file_path" type="text"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: sounds/numero.mp3">
                        @error('file_path')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Texto para TTS</label>
                        <textarea wire:model.defer="tts_text" rows="2"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: Número sorteado"></textarea>
                        @error('tts_text')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Voz TTS</label>
                            <input wire:model.defer="tts_voice" type="text"
                                class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="Ex: Google Português">
                            @error('tts_voice')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Idioma</label>
                            <input wire:model.defer="tts_language" type="text"
                                class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="Ex: pt-BR">
                            @error('tts_language')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Velocidade (1.0 normal)</label>
                            <input wire:model.defer="tts_rate" type="number" step="0.1" min="0.1" max="10"
                                class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @error('tts_rate')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Tom (1.0 normal)</label>
                            <input wire:model.defer="tts_pitch" type="number" step="0.1" min="0.1" max="2"
                                class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @error('tts_pitch')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Volume (0-1)</label>
                            <input wire:model.defer="tts_volume" type="number" step="0.1" min="0" max="1"
                                class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @error('tts_volume')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Padrão?</label>
                        <input wire:model="is_default" type="checkbox"
                            class="w-5 h-5 bg-[#111827] border-white/10 rounded focus:ring-2 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-slate-300">Definir como padrão para este tipo</span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Ordem de Exibição</label>
                        <input wire:model.defer="order" type="number" min="0"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: 0">
                        @error('order')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center gap-3 p-4 bg-black/20 border border-white/10 rounded-xl">
                    <input wire:model="is_active" type="checkbox" id="is_active"
                        class="w-5 h-5 bg-[#111827] border-white/10 rounded focus:ring-2 focus:ring-indigo-500">
                    <label for="is_active" class="text-sm text-slate-300 cursor-pointer">
                        Áudio ativo e disponível
                    </label>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" wire:click="closeModal"
                        class="flex-1 bg-gray-800 hover:bg-gray-700 transition py-3 rounded-xl text-sm font-medium text-white">
                        Cancelar
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-800 transition py-3 rounded-xl text-sm font-medium text-white flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="save">
                            {{ $editingId ? 'Atualizar' : 'Criar Áudio' }}
                        </span>
                        <span wire:loading wire:target="save">Salvando...</span>
                        <div wire:loading wire:target="save"
                            class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin">
                        </div>
                    </button>
                </div>
            </form>
        </x-drawer>
    @endif

    <x-loading target="save,delete,toggleStatus" message="Processando..." overlay />
    <x-toast position="top-right" />
</div>