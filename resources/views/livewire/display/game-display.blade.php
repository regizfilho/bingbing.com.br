<div>
<div class="h-screen w-full p-8">
    <div class="h-full flex flex-col">
        <div class="text-center mb-8">
            <h1 class="text-6xl font-black text-white mb-4">{{ $game->name }}</h1>
            <div class="flex justify-center items-center gap-8 text-white text-2xl">
                <div class="bg-white/20 backdrop-blur px-8 py-3 rounded-full">
                    <span class="opacity-80">Rodada:</span>
                    <span class="font-bold ml-2">{{ $game->current_round }}/{{ $game->max_rounds }}</span>
                </div>
                <div class="bg-white/20 backdrop-blur px-8 py-3 rounded-full">
                    <span class="opacity-80">Jogadores:</span>
                    <span class="font-bold ml-2">{{ $game->players->count() }}</span>
                </div>
            </div>
        </div>

        @if ($game->status === 'waiting')
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="text-9xl mb-8 animate-pulse-slow">🎲</div>
                    <h2 class="text-5xl font-bold text-white mb-6">Aguardando Início...</h2>
                    <div class="bg-white/30 backdrop-blur rounded-3xl p-8 inline-block">
                        <div class="text-white text-2xl mb-4">Código de Convite:</div>
                        <div class="text-7xl font-black text-white tracking-wider">{{ $game->invite_code }}</div>
                    </div>
                </div>
            </div>
        @elseif($game->status === 'active')
            <div class="flex-1 grid grid-cols-3 gap-8">
                <div class="space-y-6">
                    <div class="bg-white/20 backdrop-blur rounded-3xl p-6">
                        <h3 class="text-2xl font-bold text-white mb-4 text-center">Últimos Sorteados</h3>
                        <div class="space-y-3">
                            @foreach ($currentDraws as $draw)
                                <div class="bg-white rounded-2xl p-4 flex items-center justify-center">
                                    <span class="text-5xl font-black text-purple-600">{{ $draw['number'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex flex-col justify-center">
                    @if (count($currentDraws) > 0)
                        <div class="bg-white rounded-[4rem] p-16 shadow-2xl bounce-in">
                            <div class="text-center">
                                <div class="text-3xl text-gray-600 mb-4">Número Sorteado</div>
                                <div class="text-[12rem] font-black text-purple-600 leading-none">
                                    {{ $currentDraws[0]['number'] }}
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bg-white/20 backdrop-blur rounded-[4rem] p-16 text-center">
                            <div class="text-5xl text-white font-bold animate-pulse-slow">
                                Aguardando primeiro sorteio...
                            </div>
                        </div>
                    @endif

                    <div class="mt-8 text-center">
                        <div class="bg-white/30 backdrop-blur rounded-full px-8 py-4 inline-block">
                            <span class="text-white text-3xl font-bold">{{ count($drawnNumbers) }} / 75</span>
                            <span class="text-white/80 text-xl ml-2">números sorteados</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white/20 backdrop-blur rounded-3xl p-6">
                        <h3 class="text-2xl font-bold text-white mb-4 text-center">Prêmios</h3>
                        <div class="space-y-3">
                            @foreach ($prizes as $prize)
                                <div
                                    class="bg-white rounded-2xl p-4 {{ $prize['winner'] ? 'ring-4 ring-yellow-400' : '' }}">
                                    <div class="font-bold text-xl text-gray-900 mb-1">
                                        {{ $prize['position'] }}º - {{ $prize['name'] }}
                                    </div>
                                    @if ($prize['winner'])
                                        <div class="text-green-600 font-semibold text-lg">
                                            {{ $prize['winner'] }}
                                        </div>
                                    @else
                                        <div class="text-gray-500 text-sm">Disponível</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if (count($roundWinners) > 0)
                            <h3 class="text-2xl font-bold text-white mt-6 mb-4 text-center">Vencedores</h3>
                            <div class="space-y-2">
                                @foreach ($roundWinners as $winner)
                                    <div class="bg-yellow-400 rounded-xl p-3 text-center">
                                        <div class="font-bold text-xl text-gray-900">{{ $winner['name'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <div class="bg-white/20 backdrop-blur rounded-3xl p-6">
                    <div class="grid grid-cols-15 gap-2">
                        @foreach (range(1, 75) as $num)
                            <div
                                class="aspect-square rounded-lg flex items-center justify-center font-bold text-xl {{ in_array($num, $drawnNumbers) ? 'bg-green-500 text-white' : 'bg-white/40 text-white/60' }}">
                                {{ $num }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @elseif($game->status === 'finished')
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <h2 class="text-6xl font-bold text-white mb-8">Partida Finalizada!</h2>
                    @if (count($roundWinners) > 0)
                        <div class="bg-white/30 backdrop-blur rounded-3xl p-8 inline-block">
                            <h3 class="text-3xl font-bold text-white mb-6">Campeões da Partida</h3>
                            <div class="space-y-4">
                                @foreach ($roundWinners as $winner)
                                    <div class="bg-yellow-400 rounded-2xl p-6">
                                        <div class="text-3xl font-black text-gray-900">{{ $winner['name'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="text-center mt-8 text-white/60 text-xl">
            Organizado por {{ $game->creator->name }}
        </div>
    </div>
</div>

<style>
    @keyframes pulse-slow {

        0%,
        100% {
            opacity: 1
        }

        50% {
            opacity: 0.6
        }
    }

    .animate-pulse-slow {
        animation: pulse-slow 2s ease-in-out infinite;
    }

    @keyframes bounce-in {
        0% {
            transform: scale(0);
            opacity: 0
        }

        50% {
            transform: scale(1.2)
        }

        100% {
            transform: scale(1);
            opacity: 1
        }
    }

    .bounce-in {
        animation: bounce-in 0.6s ease-out;
    }
</style>
</div>