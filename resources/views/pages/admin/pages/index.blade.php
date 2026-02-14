<?php

/**
 * ============================================================================
 * Gerenciamento de Páginas - Implementação seguindo boas práticas:
 *
 * - Laravel 12:
 *   • Validação server-side robusta
 *   • Proteção contra SQL Injection via Eloquent
 *   • Mass assignment controlado
 *   • Sanitização básica antes de persistir
 *
 * - Livewire 4:
 *   • Propriedades tipadas
 *   • wire:model.live otimizado
 *   • Eventos dispatch integrados
 *   • Controle de estado previsível
 *
 * - Segurança:
 *   • Escape automático Blade (anti XSS)
 *   • Validação obrigatória
 *   • Tipagem de parâmetros
 *   • Sem exposição de dados sensíveis
 *
 * - Tailwind:
 *   • Hierarquia consistente
 *   • Estados visuais claros
 *   • Layout escalável
 * ============================================================================
 */

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use App\Models\Page;
use Illuminate\Support\Str;

new #[Layout('layouts.admin')] #[Title('Gerenciamento de Páginas')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterStatus = 'all';
    public string $sort = 'desc';

    public ?int $editingId = null;
    public string $title = '';
    public string $slug = '';
    public string $content = '';
    public string $meta_description = '';
    public bool $is_active = true;
    public bool $showDrawer = false;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:pages,slug,' . $this->editingId],
            'content' => ['required', 'string'],
            'meta_description' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function toggleSort(): void
    {
        $this->sort = $this->sort === 'desc' ? 'asc' : 'desc';
    }

    public function updatedTitle(): void
    {
        if (!$this->editingId) {
            $this->slug = Str::slug($this->title);
        }
    }

    #[Computed]
    public function pages()
    {
        return Page::query()
            ->when($this->search, fn($q) => $q->where(fn($sub) => $sub->where('title', 'like', "%{$this->search}%")->orWhere('slug', 'like', "%{$this->search}%")))
            ->when($this->filterStatus === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->filterStatus === 'inactive', fn($q) => $q->where('is_active', false))
            ->orderBy('updated_at', $this->sort)
            ->paginate(15);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Page::count(),
            'active' => Page::where('is_active', true)->count(),
            'inactive' => Page::where('is_active', false)->count(),
            'updated_today' => Page::whereDate('updated_at', today())->count(),
        ];
    }

    public function openModal(): void
    {
        $this->reset(['editingId', 'title', 'slug', 'content', 'meta_description']);
        $this->is_active = true;
        $this->resetValidation();
        $this->showDrawer = true;
    }

    public function closeModal(): void
    {
        $this->showDrawer = false;
        $this->reset(['editingId', 'title', 'slug', 'content', 'meta_description']);
        $this->is_active = true;
    }

    public function edit(int $id): void
    {
        $page = Page::findOrFail($id);

        $this->editingId = $page->id;
        $this->title = $page->title;
        $this->slug = $page->slug;
        $this->content = $page->content;
        $this->meta_description = $page->meta_description ?? '';
        $this->is_active = (bool) $page->is_active;

        $this->showDrawer = true;
    }

    public function save(): void
    {
        $this->validate();

        Page::updateOrCreate(
            ['id' => $this->editingId],
            [
                'title' => trim($this->title),
                'slug' => Str::slug($this->slug),
                'content' => $this->content,
                'meta_description' => trim($this->meta_description),
                'is_active' => $this->is_active,
            ],
        );

        $this->dispatch('notify', type: 'success', text: $this->editingId ? 'Página atualizada!' : 'Página criada!');

        $this->closeModal();
        $this->dispatch('$refresh');
    }

    public function toggleStatus(int $id): void
    {
        $page = Page::findOrFail($id);
        $page->update(['is_active' => !$page->is_active]);

        $this->dispatch('notify', type: 'success', text: 'Status atualizado!');
        $this->dispatch('$refresh');
    }

    public function delete(int $id): void
    {
        try {
            $page = Page::findOrFail($id);
            $page->delete();

            $this->dispatch('notify', type: 'success', text: 'Página removida com sucesso!');
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', text: 'Erro ao excluir: ' . $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('pages.admin.pages.index');
    }
};
?>

<div class="p-6 min-h-screen">
    <!-- HEADER -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">
                Gerenciamento de Páginas
            </h1>
            <p class="text-slate-400 text-sm mt-1">Crie e gerencie páginas estáticas do sistema</p>
        </div>
        <button wire:click="openModal"
            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-sm font-medium text-white transition-all flex items-center gap-2 shadow-lg shadow-indigo-500/20">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nova Página
        </button>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Total de Páginas</p>
                    <p class="text-3xl font-bold text-white mt-2">{{ $this->stats['total'] }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Publicadas</p>
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
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Rascunhos</p>
                    <p class="text-3xl font-bold text-slate-400 mt-2">{{ $this->stats['inactive'] }}</p>
                </div>
                <div class="w-12 h-12 bg-slate-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-400 text-xs uppercase tracking-wider font-medium">Atualizadas Hoje</p>
                    <p class="text-3xl font-bold text-blue-400 mt-2">{{ $this->stats['updated_today'] }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- BUSCA E FILTROS -->
    <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center">
            <div class="flex-1 relative">
                <input wire:model.live.debounce.400ms="search" placeholder="Buscar por título ou slug..."
                    class="w-full bg-[#111827] border border-white/10 rounded-xl px-5 py-3 text-sm text-white pl-12 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <div class="flex gap-3 w-full lg:w-auto">
                <select wire:model.live="filterStatus"
                    class="flex-1 lg:flex-none bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Todos os Status</option>
                    <option value="active">Apenas Publicadas</option>
                    <option value="inactive">Apenas Rascunhos</option>
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
                        <th class="p-4 text-left font-semibold">Status</th>
                        <th class="p-4 text-left font-semibold">Título</th>
                        <th class="p-4 text-left font-semibold">Slug / URL</th>
                        <th class="p-4 text-center font-semibold">Última Atualização</th>
                        <th class="p-4 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($this->pages as $page)
                        <tr wire:key="page-{{ $page->id }}" class="hover:bg-white/5 transition-all group">
                            <td class="p-4">
                                <button wire:click="toggleStatus({{ $page->id }})"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $page->is_active ? 'bg-emerald-600' : 'bg-slate-700' }}">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $page->is_active ? 'translate-x-6' : 'translate-x-1' }}">
                                    </span>
                                </button>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 bg-gradient-to-br from-violet-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-white font-semibold">{{ $page->title }}</div>
                                        @if ($page->meta_description)
                                            <div class="text-slate-400 text-xs truncate max-w-md">
                                                {{ Str::limit($page->meta_description, 60) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-indigo-400 font-mono text-xs">/{{ $page->slug }}</span>
                                    <a href="/{{ $page->slug }}" target="_blank"
                                        class="p-1 text-slate-400 hover:text-indigo-400 transition-colors"
                                        title="Visualizar página">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                            <td class="p-4 text-center text-slate-400 text-xs">
                                <div>{{ $page->updated_at->format('d/m/Y') }}</div>
                                <div class="text-slate-500">{{ $page->updated_at->format('H:i:s') }}</div>
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="edit({{ $page->id }})"
                                        class="p-2 text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-all"
                                        title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button wire:click="delete({{ $page->id }})"
                                        wire:confirm="Tem certeza que deseja excluir esta página? Esta ação não pode ser desfeita."
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
                            <td colspan="5" class="p-12 text-center">
                                <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-slate-400 font-medium mb-2">Nenhuma página encontrada</p>
                                <p class="text-slate-500 text-sm">Crie sua primeira página clicando no botão "Nova
                                    Página"</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->pages->hasPages())
            <div class="p-6 border-t border-white/5 bg-black/10">
                {{ $this->pages->links() }}
            </div>
        @endif
    </div>

    <!-- DRAWER LATERAL -->
    @if ($showDrawer)
        <x-drawer :show="$showDrawer" max-width="2xl" wire:model="showDrawer">
            <div class="border-b border-white/10 flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white">
                        {{ $editingId ? 'Editar Página' : 'Nova Página' }}
                    </h2>
                    <p class="text-slate-400 text-sm">
                        {{ $editingId ? 'Atualize as informações da página' : 'Crie uma nova página estática' }}
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
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Título da Página *</label>
                        <input wire:model.live="title" type="text"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: Termos de Uso">
                        @error('title')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Slug / URL *</label>
                        <input wire:model.defer="slug" type="text"
                            class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-indigo-400 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ex: termos-de-uso">
                        @error('slug')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-slate-500 text-xs mt-1">URL: /{{ $slug ?: 'slug-da-pagina' }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Meta Descrição (SEO)</label>
                    <input wire:model.defer="meta_description" type="text"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Descrição curta para mecanismos de busca (máx. 160 caracteres)">
                    @error('meta_description')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-slate-500 text-xs mt-1">{{ Str::length($meta_description) }}/160 caracteres</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Conteúdo HTML *</label>
                    <textarea wire:model.defer="content" rows="16"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="<div>
    <h1>Título</h1>
    <p>Conteúdo da página...</p>
</div>"></textarea>
                    @error('content')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-slate-500 text-xs mt-1">Aceita HTML completo. Use com cuidado.</p>
                </div>

                <div class="flex items-center gap-3 p-4 bg-black/20 border border-white/10 rounded-xl">
                    <input wire:model="is_active" type="checkbox" id="is_active"
                        class="w-5 h-5 bg-[#111827] border-white/10 rounded focus:ring-2 focus:ring-indigo-500">
                    <label for="is_active" class="text-sm text-slate-300 cursor-pointer">
                        Página publicada e visível publicamente
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
                            {{ $editingId ? 'Atualizar Página' : 'Criar Página' }}
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
