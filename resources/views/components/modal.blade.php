@props(['name', 'title' => '', 'maxWidth' => 'lg'])

@php
$maxWidthClass = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
    '4xl' => 'max-w-4xl',
    '7xl' => 'max-w-7xl',
    'full' => 'max-w-[95vw]',
][$maxWidth] ?? 'max-w-lg';
@endphp

<div 
    x-data="{ show: false }" 
    x-show="show" 
    x-on:open-modal.window="if ($event.detail.name === '{{ $name }}') show = true"
    x-on:close-modal.window="if ($event.detail.name === '{{ $name }}') show = false"
    x-on:keydown.escape.window="show = false"
    style="display: none;"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 overflow-y-auto"
>
    {{-- Overlay --}}
    <div 
        x-show="show" 
        x-transition.opacity 
        @click="show = false; $dispatch('close-modal', { name: '{{ $name }}' })" 
        class="fixed inset-0 bg-black/90 backdrop-blur-sm"
    ></div>

    {{-- Modal Content --}}
    <div 
        x-show="show" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        @click.stop
        class="relative w-full {{ $maxWidthClass }} bg-[#0f1117] border border-white/10 rounded-3xl shadow-2xl"
    >
        @if($title)
        <div class="flex items-center justify-between p-6 border-b border-white/10">
            <h3 class="text-xl font-bold text-white uppercase italic tracking-tight">{{ $title }}</h3>
            <button 
                @click="show = false; $dispatch('close-modal', { name: '{{ $name }}' })" 
                class="text-slate-500 hover:text-white transition-colors"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        @endif

        <div class="p-6 max-h-[calc(90vh-100px)] overflow-y-auto custom-scrollbar">
            {{ $slot }}
        </div>
    </div>
</div>

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
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #4b5563;
}
</style>