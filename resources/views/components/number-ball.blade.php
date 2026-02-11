@props(['number', 'drawn' => false, 'size' => 'md'])

@php
$sizeClasses = match($size) {
    'sm' => 'w-8 h-8 text-sm',
    'lg' => 'w-16 h-16 text-2xl',
    'xl' => 'w-20 h-20 text-3xl',
    default => 'w-12 h-12 text-lg',
};

$colorClasses = $drawn ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400';
@endphp

<div {{ $attributes->merge(['class' => "{$sizeClasses} {$colorClasses} rounded-full flex items-center justify-center font-bold"]) }}>
    {{ $number }}
</div>