@props(['size' => 5, 'color' => 'currentColor'])

<svg class="w-{{ $size }} h-{{ $size }}" fill="none" stroke="{{ $color }}" viewBox="0 0 24 24" {{ $attributes }}>
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14m-6 0H3a2 2 0 01-2-2V8a2 2 0 012-2h6a2 2 0 012 2v4a2 2 0 01-2 2z" />
</svg>