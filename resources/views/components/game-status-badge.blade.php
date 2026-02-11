@props(['status'])

@php
$classes = match($status) {
    'active' => 'bg-green-100 text-green-800',
    'waiting' => 'bg-yellow-100 text-yellow-800',
    'finished' => 'bg-gray-100 text-gray-800',
    'paused' => 'bg-orange-100 text-orange-800',
    default => 'bg-blue-100 text-blue-800',
};

$labels = [
    'draft' => 'Rascunho',
    'waiting' => 'Aguardando',
    'active' => 'Ativa',
    'paused' => 'Pausada',
    'finished' => 'Finalizada',
];
@endphp

<span {{ $attributes->merge(['class' => "px-3 py-1 text-xs rounded-full font-semibold {$classes}"]) }}>
    {{ $labels[$status] ?? ucfirst($status) }}
</span>