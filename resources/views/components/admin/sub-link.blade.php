@props(['route', 'label'])

@php
    $active = request()->routeIs($route);
@endphp

<a href="{{ route($route) }}" 
   class="block py-2 text-sm transition-colors {{ $active ? 'text-indigo-400 font-bold' : 'text-slate-500 hover:text-slate-300' }}">
   <span class="mr-2 opacity-50">•</span>{{ $label }}
</a>