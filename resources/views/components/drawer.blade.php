@props([
    'show' => false,
    'maxWidth' => 'md'
])

@php
$width = match($maxWidth) {
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    default => 'max-w-md'
};
@endphp

<div
    x-data="{ open: @entangle($attributes->wire('model')) }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[100] overflow-hidden"
>

    <!-- Overlay -->
    <div
        x-show="open"
        x-transition.opacity
        @click="open = false"
        class="absolute inset-0 bg-black/60 backdrop-blur-sm"
    ></div>

    <!-- Panel -->
    <div class="absolute inset-y-0 right-0 flex max-w-full">

        <div
            x-show="open"
            x-transition:enter="transform transition ease-in-out duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in-out duration-300"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="w-screen {{ $width }} bg-[#0e1422] border-l border-white/10 shadow-2xl"
        >
            <div class="h-full flex flex-col p-6 overflow-y-auto">
                {{ $slot }}
            </div>
        </div>

    </div>

</div>
