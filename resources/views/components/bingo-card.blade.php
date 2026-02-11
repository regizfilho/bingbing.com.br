@props(['numbers', 'marked' => [], 'drawnNumbers' => [], 'interactive' => true, 'isBingo' => false])

<div {{ $attributes->merge(['class' => 'bg-white rounded-lg shadow-lg p-4 ' . ($isBingo ? 'ring-4 ring-green-500' : '')]) }}>
    @if($isBingo)
        <div class="bg-green-600 text-white text-center py-2 rounded-lg mb-4 font-bold text-lg">
            🎉 BINGO! 🎉
        </div>
    @endif

    <div class="grid grid-cols-5 gap-2">
        @foreach($numbers as $number)
            <div class="aspect-square rounded-lg flex items-center justify-center font-bold text-lg transition
                {{ in_array($number, $marked) ? 'bg-blue-600 text-white' : 'bg-gray-100' }}
                {{ $interactive && in_array($number, $drawnNumbers) && !in_array($number, $marked) ? 'ring-2 ring-yellow-400 animate-pulse' : '' }}
                {{ $interactive ? 'cursor-pointer hover:bg-gray-200' : '' }}">
                {{ $number }}
            </div>
        @endforeach
    </div>

    <div class="mt-4 text-sm text-gray-600 text-center">
        {{ count($marked) }}/{{ count($numbers) }} marcados
    </div>
</div>