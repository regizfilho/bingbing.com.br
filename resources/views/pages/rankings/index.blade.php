<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Ranking\Rank;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public string $tipoRanking = 'total';
    public string $papelContexto = 'jogador';
    public bool $mostrarModalPatentes = false;
    public ?User $jogadorSelecionado = null; 
    public string $filtroRegiao = 'todos';

    protected $traducoes = [
        'beginner' => 'Iniciante',
        'experienced' => 'Veterano',
        'master' => 'Mestre',
        'legend' => 'Lenda',
        'immortal' => 'Imortal',
        'sniper' => 'Sniper',
        'honor_guard' => 'Guardião'
    ];

    #[Computed]
    public function usuario() { return auth()->user(); }

    #[Computed]
    public function topRanking(): Collection
    {
        try {
            $coluna = $this->papelContexto === 'jogador' 
                ? match ($this->tipoRanking) { 
                    'semanal' => 'weekly_wins', 
                    'mensal' => 'monthly_wins', 
                    default => 'total_wins' 
                }
                : 'total_games_hosted'; 

            $query = Rank::with(['user.titles'])
                ->where($coluna, '>', 0);

            if ($this->filtroRegiao !== 'todos' && $this->papelContexto === 'jogador') {
                $query->whereHas('user', fn($q) => $q->where('state', $this->filtroRegiao));
            }

            return $query->orderByDesc($coluna)
                ->orderBy('total_games', 'asc')
                ->take(100)
                ->get()
                ->map(fn($rank, $index) => [
                    'posicao' => $index + 1,
                    'usuario' => $rank->user,
                    'vitorias' => $rank->total_wins,
                    'criadas' => $rank->total_games_hosted ?? 0,
                    'partidas' => max($rank->total_games, $rank->total_wins),
                    'taxaVitoria' => max($rank->total_games, $rank->total_wins) > 0 
                        ? ($rank->total_wins / max($rank->total_games, $rank->total_wins)) * 100 
                        : 0,
                    'ehUsuarioAtual' => $rank->user_id === $this->usuario()->id,
                    'status' => $this->getStatusJogador($rank->total_wins, $rank->total_games),
                    'streak' => $rank->current_streak ?? 0,
                ]);
        } catch (\Exception $e) {
            Log::error('Ranking load failed', [
                'tipo_ranking' => $this->tipoRanking,
                'papel_contexto' => $this->papelContexto,
                'filtro_regiao' => $this->filtroRegiao,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return collect();
        }
    }

    private function getStatusJogador($vitorias, $partidas): array
    {
        if ($vitorias >= 100) return ['label' => 'Lendário', 'color' => 'purple'];
        if ($vitorias >= 50) return ['label' => 'Elite', 'color' => 'yellow'];
        if ($vitorias >= 25) return ['label' => 'Veterano', 'color' => 'blue'];
        if ($vitorias >= 10) return ['label' => 'Experiente', 'color' => 'green'];
        return ['label' => 'Novato', 'color' => 'slate'];
    }

    #[Computed]
    public function estatisticasUsuario(): array
    {
        try {
            $rank = Rank::where('user_id', $this->usuario()->id)->first();
            $vitorias = $rank?->total_wins ?? 0;
            $partidas = max($rank?->total_games ?? 0, $vitorias);
            
            return [
                'vitorias' => $vitorias,
                'partidas' => $partidas,
                'taxa' => $partidas > 0 ? number_format(($vitorias / $partidas) * 100, 1) : '0.0',
                'posicao' => $this->calcularPosicaoUsuario(),
                'streak' => $rank?->current_streak ?? 0,
                'melhorStreak' => $rank?->best_streak ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('User stats load failed', [
                'user_id' => $this->usuario()->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'vitorias' => 0,
                'partidas' => 0,
                'taxa' => '0.0',
                'posicao' => null,
                'streak' => 0,
                'melhorStreak' => 0,
            ];
        }
    }

    private function calcularPosicaoUsuario(): ?int
    {
        try {
            $coluna = match ($this->tipoRanking) { 
                'semanal' => 'weekly_wins', 
                'mensal' => 'monthly_wins', 
                default => 'total_wins' 
            };
            $vitorias = Rank::where('user_id', $this->usuario()->id)->value($coluna);
            if ($vitorias === null) return null;
            return Rank::where($coluna, '>', $vitorias)->count() + 1;
        } catch (\Exception $e) {
            Log::error('User position calculation failed', [
                'user_id' => $this->usuario()->id,
                'tipo_ranking' => $this->tipoRanking,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    #[Computed]
    public function estatisticasGlobais(): array
    {
        try {
            return [
                'totalJogadores' => User::count(),
                'jogadoresAtivos' => User::where('last_seen_at', '>=', now()->subDays(7))->count(),
                'totalPartidas' => Rank::sum('total_games'),
                'melhorStreak' => Rank::max('best_streak'),
            ];
        } catch (\Exception $e) {
            Log::error('Global stats load failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'totalJogadores' => 0,
                'jogadoresAtivos' => 0,
                'totalPartidas' => 0,
                'melhorStreak' => 0,
            ];
        }
    }

    public function abrirDossie($userId)
    {
        try {
            $this->jogadorSelecionado = User::with(['titles', 'rank'])->find($userId);
            
            if (!$this->jogadorSelecionado) {
                Log::warning('Player dossier not found', ['user_id' => $userId]);
                $this->dispatch('notify', text: 'Jogador não encontrado.', type: 'error');
                return;
            }

            Log::info('Player dossier opened', [
                'viewer_id' => $this->usuario()->id,
                'target_user_id' => $userId,
            ]);

            $this->dispatch('notify', text: 'Dossiê carregado com sucesso.', type: 'info');
        } catch (\Exception $e) {
            Log::error('Dossier load failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('notify', text: 'Erro ao carregar dossiê.', type: 'error');
        }
    }

    public function fecharDossie() { 
        $this->jogadorSelecionado = null; 
    }

    public function setTipoRanking($tipo) { 
        $this->tipoRanking = $tipo; 
    }

    public function setPapelContexto($papel) { 
        $this->papelContexto = $papel;
        $this->filtroRegiao = 'todos';
    }

    public function setFiltroRegiao($regiao) {
        $this->filtroRegiao = $regiao;
    }

    public function alternarModalPatentes() { 
        $this->mostrarModalPatentes = !$this->mostrarModalPatentes; 
    }

    public function getDefinicoesPatentes(): array
    {
        return [
            ['name' => 'Imortal', 'icon' => '🔥', 'color' => 'bg-red-600', 'desc' => 'O topo da cadeia alimentar. Reservado para o #1 absoluto do ranking.', 'requisito' => '1º lugar global'],
            ['name' => 'Lenda', 'icon' => '🏆', 'color' => 'bg-purple-600', 'desc' => 'Histórico impecável com domínio comprovado em centenas de partidas.', 'requisito' => '100+ vitórias'],
            ['name' => 'Mestre', 'icon' => '👑', 'color' => 'bg-yellow-600', 'desc' => 'Domínio avançado do jogo com estratégias refinadas.', 'requisito' => '50+ vitórias'],
            ['name' => 'Sniper', 'icon' => '🎯', 'color' => 'bg-orange-500', 'desc' => 'Precisão cirúrgica demonstrada por alta taxa de aproveitamento.', 'requisito' => 'Taxa > 30%'],
            ['name' => 'Veterano', 'icon' => '⭐', 'color' => 'bg-blue-600', 'desc' => 'Combatente experiente com ampla vivência de campo.', 'requisito' => '25+ vitórias'],
            ['name' => 'Experiente', 'icon' => '🎖️', 'color' => 'bg-green-600', 'desc' => 'Jogador em ascensão demonstrando consistência.', 'requisito' => '10+ vitórias'],
            ['name' => 'Iniciante', 'icon' => '🌱', 'color' => 'bg-slate-600', 'desc' => 'O primeiro passo na jornada de conquistas.', 'requisito' => 'Primeiras partidas'],
        ];
    }

    public function traduzir($tipo) {
        return $this->traducoes[strtolower($tipo)] ?? $tipo;
    }
};
?>

<div class="min-h-screen bg-[#05070a] text-slate-200 selection:bg-blue-500/30 overflow-x-hidden pb-24 relative">
    
    <x-loading target="setTipoRanking, setPapelContexto, abrirDossie, setFiltroRegiao" message="Sincronizando..." />

    {{-- Efeitos de Fundo --}}
    <div class="fixed top-0 left-1/4 w-[600px] h-[600px] bg-blue-600/5 rounded-full blur-[140px] -z-10"></div>
    <div class="fixed bottom-0 right-1/4 w-[400px] h-[400px] bg-purple-600/5 rounded-full blur-[120px] -z-10"></div>

    <div class="max-w-7xl mx-auto px-6 py-12">

        {{-- Cabeçalho Principal --}}
        <div class="relative mb-16 flex flex-col lg:flex-row lg:items-end justify-between gap-8">
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 bg-blue-600 rounded-full shadow-[0_0_8px_#2563eb] animate-pulse"></div>
                    <span class="text-xs font-bold text-blue-500 uppercase tracking-widest">Hall da Fama</span>
                </div>
                <h1 class="text-5xl md:text-6xl font-bold text-white">
                    Ranking <span class="text-blue-600">Global</span>
                </h1>
                <p class="text-slate-400 text-sm max-w-2xl">
                    Acompanhe os melhores jogadores e anfitriões da plataforma. Conquiste vitórias e escale posições.
                </p>
            </div>

            <div class="flex bg-[#0b0d11] backdrop-blur-md p-2 rounded-xl border border-white/10 shadow-xl">
                <button wire:click="setPapelContexto('jogador')" 
                    class="px-6 py-3 rounded-lg text-xs font-bold uppercase tracking-wide transition-all {{ $papelContexto === 'jogador' ? 'bg-blue-600 text-white shadow-lg' : 'text-slate-400 hover:text-white' }}">
                    🎮 Jogadores
                </button>
                <button wire:click="setPapelContexto('anfitriao')" 
                    class="px-6 py-3 rounded-lg text-xs font-bold uppercase tracking-wide transition-all {{ $papelContexto === 'anfitriao' ? 'bg-purple-600 text-white shadow-lg' : 'text-slate-400 hover:text-white' }}">
                    🎙️ Anfitriões
                </button>
            </div>
        </div>

        {{-- Estatísticas Globais --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
            <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-6 hover:border-blue-500/30 transition">
                <div class="text-xs font-semibold text-slate-400 uppercase mb-2">Total de Jogadores</div>
                <div class="text-3xl font-bold text-white">{{ number_format($this->estatisticasGlobais['totalJogadores'], 0, ',', '.') }}</div>
            </div>
            <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-6 hover:border-green-500/30 transition">
                <div class="text-xs font-semibold text-slate-400 uppercase mb-2">Ativos (7 dias)</div>
                <div class="text-3xl font-bold text-green-400">{{ number_format($this->estatisticasGlobais['jogadoresAtivos'], 0, ',', '.') }}</div>
            </div>
            <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-6 hover:border-purple-500/30 transition">
                <div class="text-xs font-semibold text-slate-400 uppercase mb-2">Total de Partidas</div>
                <div class="text-3xl font-bold text-purple-400">{{ number_format($this->estatisticasGlobais['totalPartidas'], 0, ',', '.') }}</div>
            </div>
            <div class="bg-[#0b0d11] border border-white/10 rounded-xl p-6 hover:border-yellow-500/30 transition">
                <div class="text-xs font-semibold text-slate-400 uppercase mb-2">Melhor Sequência</div>
                <div class="text-3xl font-bold text-yellow-400">{{ $this->estatisticasGlobais['melhorStreak'] }}🔥</div>
            </div>
        </div>

        {{-- Seu Card de Performance --}}
        <div class="relative mb-16 group">
            <div class="absolute -inset-1 bg-gradient-to-r from-blue-600/20 to-cyan-500/20 rounded-2xl blur opacity-30 group-hover:opacity-50 transition"></div>
            <div class="relative bg-[#0b0d11] border border-white/10 rounded-2xl p-8 shadow-xl">
                <div class="flex flex-col lg:flex-row items-center gap-8">
                    <div class="relative flex-shrink-0">
                        <div class="w-32 h-32 bg-[#05070a] rounded-xl border-2 border-white/10 p-2 overflow-hidden group-hover:border-blue-500/50 transition">
                            @if($this->usuario->avatar_path)
                                <img src="{{ Storage::url($this->usuario->avatar_path) }}" class="w-full h-full object-cover rounded-lg">
                            @else
                                <div class="w-full h-full bg-blue-600 rounded-lg flex items-center justify-center text-5xl font-bold text-white">
                                    {{ substr($this->usuario->name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <div class="absolute -bottom-2 -right-2 bg-green-500 w-8 h-8 rounded-full border-4 border-[#0b0d11]"></div>
                    </div>
                    
                    <div class="flex-1 text-center lg:text-left space-y-4">
                        <div>
                            <span class="text-blue-500 text-xs font-bold uppercase tracking-wider">Seu Perfil</span>
                            <h2 class="text-3xl font-bold text-white">{{ $this->usuario->nickname ?: $this->usuario->name }}</h2>
                        </div>
                        <div class="flex flex-wrap justify-center lg:justify-start gap-2">
                            @forelse($this->usuario->titles as $t)
                                <span class="px-4 py-1.5 bg-blue-600/10 border border-blue-500/20 rounded-lg text-xs font-bold text-blue-400">
                                    {{ $this->traduzir($t->type) }}
                                </span>
                            @empty
                                <span class="px-4 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs font-semibold text-slate-500">Sem títulos</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-2 gap-4">
                        @php
                            $stats = [
                                ['label' => 'Vitórias', 'val' => $this->estatisticasUsuario['vitorias'], 'icon' => '🏆', 'color' => 'text-yellow-400'],
                                ['label' => 'Aproveitamento', 'val' => $this->estatisticasUsuario['taxa'].'%', 'icon' => '📊', 'color' => 'text-blue-400'],
                                ['label' => 'Posição', 'val' => '#'.($this->estatisticasUsuario['posicao'] ?? '--'), 'icon' => '🎯', 'color' => 'text-purple-400'],
                                ['label' => 'Sequência', 'val' => $this->estatisticasUsuario['streak'].'🔥', 'icon' => '⚡', 'color' => 'text-orange-400'],
                            ];
                        @endphp
                        @foreach($stats as $s)
                        <div class="bg-white/5 border border-white/10 rounded-xl p-4 text-center hover:bg-white/10 transition">
                            <div class="text-2xl mb-1">{{ $s['icon'] }}</div>
                            <div class="text-xs text-slate-400 font-semibold mb-1">{{ $s['label'] }}</div>
                            <div class="text-xl font-bold {{ $s['color'] }}">{{ $s['val'] }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- PÓDIO TOP 3 --}}
        <div class="mb-16">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                    <span class="text-3xl">🏆</span> Pódio dos Campeões
                </h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($this->topRanking->take(3) as $item)
                    <div wire:click="abrirDossie({{ $item['usuario']->id }})" 
                        class="cursor-pointer relative group bg-[#0b0d11] border border-white/10 rounded-2xl p-8 transition-all hover:-translate-y-2 hover:border-{{ ['yellow', 'slate', 'amber'][$item['posicao'] - 1] ?? 'blue' }}-500/40 hover:shadow-2xl {{ $item['posicao'] === 1 ? 'md:scale-105' : '' }}">
                        
                        <div class="absolute top-4 right-4 text-6xl font-black text-white/5">#{{ $item['posicao'] }}</div>
                        
                        <div class="relative mb-6 flex justify-center">
                            <div class="w-24 h-24 bg-[#05070a] rounded-xl border-2 {{ $item['posicao'] === 1 ? 'border-yellow-500' : ($item['posicao'] === 2 ? 'border-slate-400' : 'border-amber-700') }} p-2 overflow-hidden shadow-xl">
                                @if($item['usuario']->avatar_path)
                                    <img src="{{ Storage::url($item['usuario']->avatar_path) }}" class="w-full h-full object-cover rounded-lg">
                                @else
                                    <div class="w-full h-full bg-{{ ['yellow', 'slate', 'amber'][$item['posicao'] - 1] ?? 'blue' }}-600 flex items-center justify-center text-3xl font-bold text-white">
                                        {{ substr($item['usuario']->name, 0, 1) }}
                                    </div>
                                @endif
                            </div>
                            @if($item['posicao'] === 1)
                                <div class="absolute -top-4 -right-4 w-12 h-12 bg-yellow-500 rounded-xl flex items-center justify-center text-2xl shadow-xl animate-bounce">👑</div>
                            @elseif($item['posicao'] === 2)
                                <div class="absolute -top-4 -right-4 w-12 h-12 bg-slate-400 rounded-xl flex items-center justify-center text-2xl shadow-xl">🥈</div>
                            @else
                                <div class="absolute -top-4 -right-4 w-12 h-12 bg-amber-700 rounded-xl flex items-center justify-center text-2xl shadow-xl">🥉</div>
                            @endif
                        </div>

                        <h3 class="text-xl font-bold text-white text-center mb-2 group-hover:text-blue-400 transition truncate">
                            {{ $item['usuario']->nickname ?: $item['usuario']->name }}
                        </h3>
                        <div class="text-center text-blue-500 font-bold text-sm mb-6">
                            {{ $papelContexto === 'jogador' ? $item['vitorias'].' vitórias' : $item['criadas'].' salas criadas' }}
                        </div>

                        <div class="space-y-3">
                            <div class="flex justify-between text-xs font-semibold text-slate-400">
                                <span>Aproveitamento</span>
                                <span class="text-white">{{ number_format($item['taxaVitoria'], 1) }}%</span>
                            </div>
                            <div class="w-full h-2 bg-white/5 rounded-full overflow-hidden border border-white/10">
                                <div class="h-full bg-gradient-to-r from-blue-600 to-cyan-400 rounded-full shadow-lg transition-all duration-1000" style="width: {{ $item['taxaVitoria'] }}%"></div>
                            </div>
                            
                            @if($item['streak'] > 0)
                                <div class="flex items-center justify-center gap-2 pt-2">
                                    <span class="text-xs font-bold text-orange-400">🔥 {{ $item['streak'] }} sequência</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- TABELA DE CLASSIFICAÇÃO --}}
        <div class="bg-[#0b0d11] border border-white/10 rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-8 py-6 border-b border-white/10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 bg-white/5">
                <h3 class="text-lg font-bold text-white">Classificação Completa</h3>
                
                <div class="flex flex-wrap gap-3 w-full lg:w-auto">
                    {{-- Filtro Temporal --}}
                    <div class="flex bg-[#05070a] p-1 rounded-lg border border-white/10">
                        @foreach(['total' => 'Geral', 'mensal' => 'Mensal', 'semanal' => 'Semanal'] as $tipo => $rotulo)
                            <button wire:click="setTipoRanking('{{ $tipo }}')" 
                                class="px-4 py-2 rounded text-xs font-bold uppercase transition-all {{ $tipoRanking === $tipo ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white' }}">
                                {{ $rotulo }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Filtro Regional (apenas para jogadores) --}}
                    @if($papelContexto === 'jogador')
                        <select wire:model.live="filtroRegiao" class="bg-[#05070a] border border-white/10 rounded-lg px-4 py-2 text-xs font-bold text-white focus:ring-2 focus:ring-blue-500 transition">
                            <option value="todos">🌎 Todos os Estados</option>
                            <option value="SP">🏙️ São Paulo</option>
                            <option value="RJ">🌊 Rio de Janeiro</option>
                            <option value="MG">⛰️ Minas Gerais</option>
                            <option value="BA">🌴 Bahia</option>
                            <option value="PR">🌲 Paraná</option>
                            <option value="RS">🧉 Rio Grande do Sul</option>
                        </select>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-white/5 border-b border-white/10">
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase">Posição</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase">Jogador</th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-slate-400 uppercase">Status</th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-slate-400 uppercase">
                                {{ $papelContexto === 'jogador' ? 'Vitórias' : 'Criadas' }}
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-slate-400 uppercase">Taxa</th>
                            @if($papelContexto === 'jogador')
                                <th class="px-6 py-4 text-center text-xs font-bold text-slate-400 uppercase">Sequência</th>
                            @endif
                            <th class="px-6 py-4 text-right text-xs font-bold text-slate-400 uppercase">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($this->topRanking->skip(3) as $item)
                        <tr wire:click="abrirDossie({{ $item['usuario']->id }})" 
                            class="group hover:bg-blue-600/5 cursor-pointer transition {{ $item['ehUsuarioAtual'] ? 'bg-blue-600/10 border-l-4 border-blue-500' : '' }}">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl font-bold text-slate-700 group-hover:text-blue-500 transition w-12">
                                        #{{ str_pad($item['posicao'], 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    @if($item['posicao'] <= 10)
                                        <span class="text-lg">
                                            @if($item['posicao'] === 4) 🎖️
                                            @elseif($item['posicao'] === 5) 🎖️
                                            @elseif($item['posicao'] <= 10) ⭐
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-[#05070a] rounded-lg overflow-hidden border border-white/10 flex-shrink-0">
                                        @if($item['usuario']->avatar_path)
                                            <img src="{{ Storage::url($item['usuario']->avatar_path) }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center font-bold text-white text-lg">
                                                {{ substr($item['usuario']->name, 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-bold text-white text-sm group-hover:text-blue-400 transition">
                                            {{ $item['usuario']->nickname ?: $item['usuario']->name }}
                                        </div>
                                        @if($item['usuario']->city && $item['usuario']->state)
                                            <div class="text-xs text-slate-500">{{ $item['usuario']->city }}/{{ $item['usuario']->state }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 rounded-lg text-xs font-bold border
                                    bg-{{ $item['status']['color'] }}-500/10 
                                    text-{{ $item['status']['color'] }}-400 
                                    border-{{ $item['status']['color'] }}-500/20">
                                    {{ $item['status']['label'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="font-bold text-white text-lg">
                                    {{ $papelContexto === 'jogador' ? $item['vitorias'] : $item['criadas'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <span class="font-bold text-sm text-slate-300">{{ number_format($item['taxaVitoria'], 1) }}%</span>
                                    <div class="w-16 h-1.5 bg-white/5 rounded-full overflow-hidden">
                                        <div class="h-full bg-blue-600 rounded-full" style="width: {{ $item['taxaVitoria'] }}%"></div>
                                    </div>
                                </div>
                            </td>
                            @if($papelContexto === 'jogador')
                                <td class="px-6 py-4 text-center">
                                    @if($item['streak'] > 0)
                                        <span class="font-bold text-orange-400">🔥 {{ $item['streak'] }}</span>
                                    @else
                                        <span class="text-slate-600">—</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-6 py-4 text-right">
                                <button class="text-xs font-bold text-blue-500 hover:text-blue-400 transition">
                                    Ver Perfil →
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- BOTÃO FLUTUANTE --}}
        <button wire:click="alternarModalPatentes" 
            class="fixed bottom-8 right-8 bg-blue-600 hover:bg-blue-700 text-white p-4 rounded-full shadow-2xl transition-all hover:scale-110 z-40 group">
            <span class="text-2xl">📖</span>
            <span class="absolute right-full mr-3 bg-[#0b0d11] border border-white/10 px-4 py-2 rounded-lg text-xs font-bold whitespace-nowrap opacity-0 group-hover:opacity-100 transition">
                Manual de Patentes
            </span>
        </button>
    </div>

    {{-- MODAL: DOSSIÊ DO JOGADOR --}}
    @if($jogadorSelecionado)
    <div class="fixed inset-0 z-[110] flex items-center justify-center p-6">
        <div class="absolute inset-0 bg-black/90 backdrop-blur-md" wire:click="fecharDossie"></div>
        <div class="relative bg-[#0b0d11] w-full max-w-4xl rounded-3xl border border-blue-600/30 overflow-hidden shadow-2xl animate-in zoom-in duration-300">
            
            <div class="relative h-48 bg-gradient-to-r from-blue-900/40 to-cyan-900/40 border-b border-white/10">
                <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;"></div>
                <button wire:click="fecharDossie" class="absolute top-4 right-4 w-10 h-10 bg-red-500/20 hover:bg-red-500/30 border border-red-500/30 rounded-lg flex items-center justify-center text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-10 pb-10">
                <div class="relative -mt-20 mb-8 flex flex-col md:flex-row justify-between items-center md:items-end gap-6">
                    <div class="relative">
                        <div class="w-40 h-40 bg-[#0b0d11] rounded-2xl p-2 border-2 border-blue-500/40 shadow-2xl">
                            @if($jogadorSelecionado->avatar_path)
                                <img src="{{ Storage::url($jogadorSelecionado->avatar_path) }}" class="w-full h-full object-cover rounded-xl">
                            @else
                                <div class="w-full h-full bg-blue-600 rounded-xl flex items-center justify-center text-6xl font-bold text-white">
                                    {{ substr($jogadorSelecionado->name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="text-center md:text-right space-y-2">
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600/10 border border-blue-500/20 rounded-lg">
                            <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                            <span class="text-blue-400 text-xs font-bold uppercase">Perfil do Jogador</span>
                        </div>
                        <h3 class="text-3xl font-bold text-white">{{ $jogadorSelecionado->nickname ?: $jogadorSelecionado->name }}</h3>
                        <p class="text-slate-400 text-sm">
                            {{ $jogadorSelecionado->city ?: 'Localização' }} / {{ $jogadorSelecionado->state ?: '—' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    {{-- Coluna Esquerda --}}
                    <div class="space-y-6">
                        <div>
                            <div class="text-xs font-bold text-slate-400 uppercase mb-3">Títulos & Conquistas</div>
                            <div class="flex flex-wrap gap-2">
                                @forelse($jogadorSelecionado->titles as $t)
                                    <div class="px-4 py-2 bg-white/5 border border-white/10 rounded-lg">
                                        <span class="text-sm font-bold text-blue-400">{{ $this->traduzir($t->type) }}</span>
                                    </div>
                                @empty
                                    <span class="text-slate-500 text-sm">Sem títulos conquistados</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="p-6 bg-white/5 border border-white/10 rounded-xl">
                            <div class="text-xs font-bold text-slate-400 uppercase mb-4">Estatísticas de Combate</div>
                            @php
                                $rankInfo = $jogadorSelecionado->rank;
                                $vits = $rankInfo?->total_wins ?? 0;
                                $parts = max($rankInfo?->total_games ?? 0, $vits);
                                $tx = $parts > 0 ? ($vits / $parts) * 100 : 0;
                            @endphp
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-slate-400">Taxa de Vitória</span>
                                    <span class="text-2xl font-bold text-white">{{ number_format($tx, 1) }}%</span>
                                </div>
                                <div class="w-full h-2 bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-600 rounded-full shadow-lg" style="width: {{ $tx }}%"></div>
                                </div>
                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <div class="bg-white/5 p-4 rounded-lg text-center">
                                        <div class="text-xs text-slate-400 mb-1">Partidas</div>
                                        <div class="text-xl font-bold text-white">{{ $parts }}</div>
                                    </div>
                                    <div class="bg-blue-600/10 p-4 rounded-lg text-center border border-blue-500/20">
                                        <div class="text-xs text-blue-400 mb-1">Vitórias</div>
                                        <div class="text-xl font-bold text-blue-400">{{ $vits }}</div>
                                    </div>
                                </div>
                                @if($rankInfo?->current_streak > 0)
                                    <div class="flex items-center justify-center gap-2 p-3 bg-orange-500/10 border border-orange-500/20 rounded-lg">
                                        <span class="text-orange-400 font-bold">🔥 Sequência de {{ $rankInfo->current_streak }} vitórias</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Coluna Direita --}}
                    <div class="space-y-6">
                        <div class="p-6 bg-white/5 border border-white/10 rounded-xl">
                            <div class="text-xs font-bold text-slate-400 uppercase mb-4">Informações Pessoais</div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center bg-[#05070a] p-3 rounded-lg">
                                    <span class="text-xs text-slate-400">Instagram</span>
                                    <span class="text-sm font-bold text-blue-400">{{ $jogadorSelecionado->instagram ? '@'.$jogadorSelecionado->instagram : 'Não informado' }}</span>
                                </div>
                                <div class="flex justify-between items-center bg-[#05070a] p-3 rounded-lg">
                                    <span class="text-xs text-slate-400">Gênero</span>
                                    <span class="text-sm font-bold text-white">
                                        {{ match($jogadorSelecionado->gender) { 'male' => 'Masculino', 'female' => 'Feminino', 'other' => 'Outro', default => 'Privado' } }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs font-bold text-slate-400 uppercase mb-3">Biografia</div>
                            <div class="p-4 bg-white/5 border border-white/10 rounded-xl text-sm text-slate-300 leading-relaxed">
                                {{ $jogadorSelecionado->bio ?: 'Este jogador ainda não definiu uma biografia.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- MODAL: GUIA DE PATENTES --}}
    @if($mostrarModalPatentes)
    <div class="fixed inset-0 z-[120] flex items-center justify-center p-6">
        <div class="absolute inset-0 bg-black/95 backdrop-blur-xl" wire:click="alternarModalPatentes"></div>
        <div class="relative bg-[#0b0d11] w-full max-w-3xl rounded-3xl border border-white/10 p-10 shadow-2xl animate-in fade-in zoom-in duration-300">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-3xl font-bold text-white">Sistema de <span class="text-blue-500">Patentes</span></h3>
                <button wire:click="alternarModalPatentes" class="w-10 h-10 bg-red-500/20 hover:bg-red-500/30 border border-red-500/30 rounded-lg flex items-center justify-center text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="space-y-6 max-h-[60vh] overflow-y-auto pr-4">
                @foreach($this->getDefinicoesPatentes() as $def)
                    <div class="flex gap-6 p-5 bg-white/5 border border-white/10 rounded-xl hover:bg-white/10 transition group">
                        <div class="w-16 h-16 flex-shrink-0 {{ $def['color'] }} rounded-xl flex items-center justify-center text-3xl shadow-xl group-hover:scale-110 transition">
                            {{ $def['icon'] }}
                        </div>
                        <div class="flex-1 space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-white text-lg">{{ $def['name'] }}</h4>
                                <span class="px-3 py-1 bg-blue-600/10 border border-blue-500/20 rounded-lg text-xs font-bold text-blue-400">
                                    {{ $def['requisito'] }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-400 leading-relaxed">{{ $def['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <x-toast />
</div>