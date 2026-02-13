<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Page;
use Illuminate\Support\Str;

new #[Layout('layouts.admin')] #[Title('Gerenciar Páginas')]
class extends Component {

    public ?int $page_id = null;
    public string $title = '';
    public string $slug = '';
    public string $content = '';
    public string $meta_description = '';
    public bool $is_active = true;

    public bool $showModal = false;

    protected function rules()
    {
        return [
            'title' => 'required|min:3',
            'slug' => 'required|unique:pages,slug,' . $this->page_id,
            'content' => 'required',
        ];
    }

    public function create()
    {
        $this->reset();
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $page = Page::findOrFail($id);

        $this->fill([
            'page_id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'content' => $page->content,
            'meta_description' => $page->meta_description ?? '',
            'is_active' => (bool) $page->is_active,
        ]);

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        Page::updateOrCreate(
            ['id' => $this->page_id],
            [
                'title' => $this->title,
                'slug' => Str::slug($this->slug),
                'content' => $this->content,
                'meta_description' => $this->meta_description,
                'is_active' => $this->is_active,
            ]
        );

        $this->reset();
        $this->is_active = true;
        $this->showModal = false;

        $this->dispatch('notify', type: 'success', text: 'PÁGINA SINCRONIZADA COM SUCESSO!');
    }

    public function toggle($id)
    {
        $page = Page::findOrFail($id);
        $page->update(['is_active' => !$page->is_active]);

        $status = $page->is_active ? 'PUBLICADA' : 'RASCUNHO';
        $this->dispatch('notify', type: 'info', text: "STATUS: {$status}");
    }
};
?>

<div>

    <x-loading target="save, toggle, edit, create" message="PROCESSANDO..." />

    <div class="grid grid-cols-1 gap-8">
        <div class="bg-[#0a0c12] border border-white/5 rounded-2xl overflow-hidden shadow-xl">

            <div class="p-6 border-b border-white/5 flex justify-between items-center">
                <h3 class="text-sm font-bold text-white uppercase tracking-widest">
                    Gerenciamento de Páginas
                </h3>

                <button wire:click="create"
                    class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black rounded-xl uppercase">
                    + NOVA PÁGINA
                </button>
            </div>

            <table class="w-full text-left">
                <thead class="bg-black/40 text-[10px] text-slate-500 uppercase font-bold tracking-widest">
                <tr>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Título</th>
                    <th class="px-6 py-4">Slug</th>
                    <th class="px-6 py-4">Data</th>
                    <th class="px-6 py-4 text-right">Ações</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-white/5 font-bold italic">
                @foreach(\App\Models\Page::latest()->get() as $item)
                    <tr>
                        <td class="px-6 py-4">
                            <button wire:click="toggle({{ $item->id }})"
                                class="relative inline-flex h-5 w-10 items-center rounded-full {{ $item->is_active ? 'bg-indigo-600' : 'bg-slate-800' }}">
                                <span class="inline-block h-3 w-3 transform rounded-full bg-white {{ $item->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </td>

                        <td class="px-6 py-4 text-white text-sm uppercase">
                            {{ $item->title }}
                        </td>

                        <td class="px-6 py-4 text-indigo-400 text-[11px]">
                            /{{ $item->slug }}
                        </td>

                        <td class="px-6 py-4 text-slate-500 text-[10px]">
                            {{ $item->updated_at->format('d/m/H:i') }}
                        </td>

                        <td class="px-6 py-4 text-right">
                            <button wire:click="edit({{ $item->id }})"
                                class="text-indigo-400 hover:text-white uppercase text-[10px] font-black">
                                [ EDITAR ]
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

        </div>
    </div>

    {{-- Modal controlada por propriedade Livewire --}}
    @if($showModal)
        <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
            <div class="bg-[#0a0c12] w-full max-w-4xl rounded-2xl p-8">

                <form wire:submit.prevent="save" class="space-y-6">

                    <div class="grid grid-cols-2 gap-6">
                        <input wire:model.live="title" type="text"
                            placeholder="Título"
                            class="w-full bg-black/40 border-white/10 rounded-xl text-white py-3 px-4 text-xs">

                        <input wire:model.live="slug" type="text"
                            placeholder="Slug"
                            class="w-full bg-black/40 border-white/10 rounded-xl text-indigo-400 py-3 px-4 text-xs font-mono">
                    </div>

                    <input wire:model.live="meta_description" type="text"
                        placeholder="Meta descrição"
                        class="w-full bg-black/40 border-white/10 rounded-xl text-slate-400 py-3 px-4 text-xs">

                    <textarea wire:model.live="content" rows="12"
                        class="w-full bg-black/40 border-white/10 rounded-xl text-slate-300 py-4 px-4 font-mono text-xs"></textarea>

                    <div class="flex gap-4">
                        <button type="submit"
                            class="w-full bg-indigo-600 hover:bg-indigo-500 text-white py-4 rounded-xl text-xs font-black uppercase">
                            Salvar
                        </button>

                        <button type="button"
                            wire:click="$set('showModal', false)"
                            class="w-full bg-slate-800 hover:bg-slate-700 text-white py-4 rounded-xl text-xs font-black uppercase">
                            Cancelar
                        </button>
                    </div>

                </form>

            </div>
        </div>
    @endif

    <x-toast />

</div>
