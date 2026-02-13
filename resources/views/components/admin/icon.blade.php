@props(['name'])

@switch($name)

    @case('home')
        <svg {{ $attributes->merge(['class' => 'w-4 h-4']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-width="2" d="M3 9l9-7 9 7v11a2 2 0 01-2 2h-4a2 2 0 01-2-2V12H9v8a2 2 0 01-2 2H3z"/>
        </svg>
    @break

    @case('document-text')
        <svg {{ $attributes->merge(['class' => 'w-4 h-4']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-width="2" d="M9 12h6M9 16h6M13 2H6a2 2 0 00-2 2v16l4-4h9a2 2 0 002-2V9z"/>
        </svg>
    @break

    @case('shield')
        <svg {{ $attributes->merge(['class' => 'w-4 h-4']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-width="2" d="M12 3l8 4v5c0 5-3.8 9.7-8 11-4.2-1.3-8-6-8-11V7l8-4z"/>
        </svg>
    @break

    @case('ticket')
        <svg {{ $attributes->merge(['class' => 'w-4 h-4']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-width="2" d="M3 7h18v10H3zM7 7v10M17 7v10"/>
        </svg>
    @break

    @case('credit-card')
        <svg {{ $attributes->merge(['class' => 'w-4 h-4']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-width="2" d="M3 7h18v10H3zM3 11h18"/>
        </svg>
    @break

@endswitch
