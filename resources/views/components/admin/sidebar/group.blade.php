@props([
    'label',
    'icon' => null,
    'active' => false
])

<div 
    x-data="{ open: {{ $active ? 'true' : 'false' }} }"
    class="space-y-1"
>

    <button 
        @click="open = !open"
        class="w-full flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg hover:bg-white/5 transition-colors group text-slate-400 hover:text-white"
    >
        <div class="flex items-center gap-3">
           @if($icon)
    <x-admin.icon :name="$icon" class="text-slate-400 group-hover:text-blue-400"/>
@endif
            <span>{{ $label }}</span>
        </div>

        <svg :class="open ? 'rotate-180' : ''" class="w-3 h-3 transition-transform"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div 
        x-show="open"
        x-cloak
        class="ml-4 border-l border-white/5 pl-4 space-y-1"
    >
        {{ $slot }}
    </div>

</div>
