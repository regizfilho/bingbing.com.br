@props([
    'route',
    'label',
    'icon' => null
])

@php
    $active = request()->routeIs($route);
@endphp

<a href="{{ route($route) }}"
   class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors
   {{ $active ? 'bg-blue-600 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">

    @if($icon)
        <x-admin.icon :name="$icon" class="w-5 h-5 {{ $active ? 'text-white' : 'text-slate-400' }}"/>
    @endif

    <span>{{ $label }}</span>
</a>