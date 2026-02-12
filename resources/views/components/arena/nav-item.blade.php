@props(['route', 'label', 'icon', 'active' => false])

<a href="{{ route($route) }}" 
    {{ $attributes->merge(['class' => 'px-4 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 ' . 
    ($active 
        ? 'bg-blue-600 text-white shadow-md shadow-blue-600/25' 
        : 'text-slate-400 hover:text-white hover:bg-white/[0.04]')]) }}>
    {{ $label }}
</a>