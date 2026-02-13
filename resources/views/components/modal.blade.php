@props(['name', 'title'])
<div 
    x-data="{ show: false }" 
    x-show="show" 
    x-on:open-modal.window="if ($event.detail.name === '{{ $name }}') show = true"
    x-on:close-modal.window="show = false"
    x-on:keydown.escape.window="show = false"
    x-cloak
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 overflow-y-auto"
>
    {{-- Overlay --}}
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-black/90 backdrop-blur-sm"></div>

    {{-- Modal Content --}}
    <div 
        x-show="show" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="relative w-full max-w-lg bg-[#0f1117] border border-white/10 rounded-2xl shadow-2xl p-8"
    >
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white uppercase italic tracking-tight">{{ $title }}</h3>
            <button @click="show = false" class="text-slate-500 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>

        {{ $slot }}
    </div>
</div>