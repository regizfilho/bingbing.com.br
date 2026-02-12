<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Ranking\Rank;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;

new #[Layout('layouts.arena')] class extends Component {
    public string $tipoRanking = 'total';
    public string $papelContexto = 'jogador';
    public bool $mostrarModalPatentes = false;
    public ?User $jogadorSelecionado = null; 

    // Mapeamento de Tradução para as Patentes
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
        $coluna = $this->papelContexto === 'jogador' 
            ? match ($this->tipoRanking) { 'semanal' => 'weekly_wins', 'mensal' => 'monthly_wins', default => 'total_wins' }
            : 'total_games_hosted'; 

        return Rank::with(['user.titles'])
            ->where($coluna, '>', 0)
            ->orderByDesc($coluna)
            ->orderBy('total_games', 'asc')
            ->take(50)
            ->get()
            ->map(fn($rank, $index) => [
                'posicao' => $index + 1,
                'usuario' => $rank->user,
                'vitorias' => $rank->total_wins,
                'criadas' => $rank->total_games_hosted ?? 0,
                'partidas' => max($rank->total_games, $rank->total_wins),
                'taxaVitoria' => max($rank->total_games, $rank->total_wins) > 0 ? ($rank->total_wins / max($rank->total_games, $rank->total_wins)) * 100 : 0,
                'titulos' => $rank->user->titles->map(fn($t) => $this->traducoes[strtolower($t->type)] ?? $t->type)->toArray(),
                'ehUsuarioAtual' => $rank->user_id === $this->usuario->id,
                'status' => $rank->total_wins > 50 ? 'Elite' : 'Ativo',
            ]);
    }

    #[Computed]
    public function estatisticasUsuario(): array
    {
        $rank = Rank::where('user_id', $this->usuario->id)->first();
        $vitorias = $rank?->total_wins ?? 0;
        $partidas = max($rank?->total_games ?? 0, $vitorias);
        
        return [
            'vitorias' => $vitorias,
            'partidas' => $partidas,
            'taxa' => $partidas > 0 ? number_format(($vitorias / $partidas) * 100, 1) : '0.0',
            'posicao' => $this->calcularPosicaoUsuario()
        ];
    }

    private function calcularPosicaoUsuario(): ?int
    {
        $coluna = match ($this->tipoRanking) { 'semanal' => 'weekly_wins', 'mensal' => 'monthly_wins', default => 'total_wins' };
        $vitorias = Rank::where('user_id', $this->usuario->id)->value($coluna);
        if (!$vitorias) return null;
        return Rank::where($coluna, '>', $vitorias)->count() + 1;
    }

    public function abrirDossie($userId) {
        $this->jogadorSelecionado = User::with(['titles', 'rank'])->find($userId);
    }

    public function fecharDossie() { $this->jogadorSelecionado = null; }

    public function setTipoRanking($tipo) { $this->tipoRanking = $tipo; }
    
    public function setPapelContexto($papel) { $this->papelContexto = $papel; }

    public function alternarModalPatentes() { $this->mostrarModalPatentes = !$this->mostrarModalPatentes; }

    public function getDefinicoesPatentes(): array
    {
        return [
            ['type' => 'immortal', 'name' => 'Imortal', 'icon' => '🔥', 'color' => 'bg-red-600', 'desc' => 'Top #1 Combatente absoluto da temporada.'],
            ['type' => 'legend', 'name' => 'Lenda', 'icon' => '🏆', 'color' => 'bg-purple-600', 'desc' => 'Mais de 100 vitórias conquistadas.'],
            ['type' => 'master', 'name' => 'Mestre', 'icon' => '👑', 'color' => 'bg-yellow-600', 'desc' => 'Consistência impecável com 50+ vitórias.'],
            ['type' => 'sniper', 'name' => 'Sniper', 'icon' => '🎯', 'color' => 'bg-orange-500', 'desc' => 'Precisão fatal: Taxa de vitória acima de 25%.'],
            ['type' => 'experienced', 'name' => 'Veterano', 'icon' => '⭐', 'color' => 'bg-blue-600', 'desc' => 'Herói resiliente com 10+ vitórias no currículo.'],
            ['type' => 'beginner', 'name' => 'Iniciante', 'icon' => '🌱', 'color' => 'bg-green-600', 'desc' => 'O início da jornada. Conquistou sua 1ª vitória.'],
        ];
    }

    // Helper para traduzir no Blade
    public function traduzir($tipo) {
        return $this->traducoes[strtolower($tipo)] ?? $tipo;
    }
};
?>

<div class="min-h-screen bg-[#0b0d11] text-slate-200 font-sans selection:bg-blue-500/30 overflow-x-hidden pb-20">
    {{-- Brilhos de Fundo --}}
    <div class="fixed top-0 left-1/4 w-[500px] h-[500px] bg-blue-600/5 rounded-full blur-[120px] -z-10"></div>
    <div class="fixed bottom-0 right-1/4 w-[500px] h-[500px] bg-purple-600/5 rounded-full blur-[120px] -z-10"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-16">

        {{-- Cabeçalho Principal --}}
        <div class="relative mb-12 flex flex-col lg:flex-row lg:items-end justify-between gap-8">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <span class="h-1 w-12 bg-blue-600 rounded-full"></span>
                    <span class="text-blue-500 font-black tracking-[0.3em] uppercase text-xs italic">Protocolo de Elite 2026</span>
                </div>
                <h1 class="text-6xl sm:text-8xl font-black text-white tracking-tighter uppercase italic leading-none">
                    HALL DA <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-cyan-400">FAMA</span>
                </h1>
            </div>

            <div class="flex flex-col gap-4">
                <div class="flex bg-white/5 backdrop-blur-md p-1.5 rounded-2xl border border-white/10 shadow-2xl">
                    <button wire:click="setPapelContexto('jogador')" 
                        class="flex-1 px-8 py-4 rounded-xl text-xs font-black uppercase tracking-[0.2em] transition-all {{ $papelContexto === 'jogador' ? 'bg-blue-600 text-white shadow-[0_0_20px_rgba(37,99,235,0.4)]' : 'text-slate-500 hover:text-white' }}">
                        🛡️ Lutadores
                    </button>
                    <button wire:click="setPapelContexto('anfitriao')" 
                        class="flex-1 px-8 py-4 rounded-xl text-xs font-black uppercase tracking-[0.2em] transition-all {{ $papelContexto === 'anfitriao' ? 'bg-purple-600 text-white shadow-[0_0_20px_rgba(147,51,234,0.4)]' : 'text-slate-500 hover:text-white' }}">
                        🎙️ Anfitriões
                    </button>
                </div>
            </div>
        </div>

        {{-- Seu Card de Perfil --}}
        <div class="relative group mb-16">
            <div class="absolute -inset-1 bg-gradient-to-r from-blue-600 to-cyan-500 rounded-[2.5rem] blur opacity-20 group-hover:opacity-30 transition duration-1000"></div>
            <div class="relative bg-[#161920] border border-white/10 rounded-[2rem] p-10 shadow-2xl overflow-hidden">
                <div class="relative z-10 flex flex-col xl:flex-row items-center gap-12">
                    <div class="relative">
                        <div class="w-32 h-32 sm:w-40 sm:h-40 bg-gradient-to-tr from-blue-600 to-cyan-400 rounded-3xl rotate-3 flex items-center justify-center text-6xl font-black text-white shadow-2xl">
                            {{ substr($this->usuario->name, 0, 1) }}
                        </div>
                        <div class="absolute -bottom-2 -right-2 bg-green-500 w-8 h-8 rounded-full border-4 border-[#161920] animate-pulse"></div>
                    </div>
                    
                    <div class="flex-1 text-center xl:text-left">
                        <span class="text-blue-500 text-xs font-black uppercase tracking-[0.3em]">Registro Oficial</span>
                        <h2 class="text-4xl sm:text-6xl font-black text-white mb-4 tracking-tight uppercase italic">{{ $this->usuario->name }}</h2>
                        <div class="flex flex-wrap justify-center xl:justify-start gap-3">
                            @forelse($this->usuario->titles as $t)
                                <span class="px-4 py-2 bg-blue-500/10 border border-blue-500/20 rounded-lg text-xs font-black uppercase text-blue-400 tracking-wider">
                                    {{ $this->traduzir($t->type) }}
                                </span>
                            @empty
                                <span class="px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-xs font-black uppercase text-slate-500">Sem Patente</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 w-full xl:w-auto">
                        @php
                            $meusStatus = [
                                ['label' => 'Vitórias', 'val' => $this->estatisticasUsuario['vitorias'], 'cor' => 'text-white'],
                                ['label' => 'Partidas', 'val' => $this->estatisticasUsuario['partidas'], 'cor' => 'text-slate-400'],
                                ['label' => 'Taxa', 'val' => $this->estatisticasUsuario['taxa'].'%', 'cor' => 'text-blue-400'],
                                ['label' => 'Rank', 'val' => '#'.($this->estatisticasUsuario['posicao'] ?? '--'), 'cor' => 'text-yellow-500'],
                            ];
                        @endphp
                        @foreach($meusStatus as $s)
                        <div class="bg-white/5 border border-white/5 rounded-3xl p-6 text-center min-w-[130px]">
                            <div class="text-[10px] font-black text-slate-500 uppercase mb-2 tracking-widest">{{ $s['label'] }}</div>
                            <div class="text-3xl font-black {{ $s['cor'] }} tracking-tighter">{{ $s['val'] }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Pódio - Os 3 Melhores --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-20">
            @foreach($this->topRanking->take(3) as $item)
                <div wire:click="abrirDossie({{ $item['usuario']->id }})" 
                    class="cursor-pointer relative group bg-[#161920] border border-white/5 rounded-[3rem] p-12 flex flex-col items-center transition-all hover:-translate-y-3 hover:border-blue-500/30 shadow-2xl overflow-hidden">
                    
                    <div class="absolute top-6 right-10 text-8xl font-black text-white/5 italic">#{{ $item['posicao'] }}</div>
                    
                    <div class="relative mb-8">
                        <div class="w-28 h-28 bg-slate-800 rounded-[2rem] flex items-center justify-center text-5xl font-black text-white border border-white/10 rotate-3 group-hover:rotate-6 transition-transform shadow-2xl">
                            {{ substr($item['usuario']->name, 0, 1) }}
                        </div>
                        @if($item['posicao'] == 1)
                            <div class="absolute -top-6 -right-6 w-14 h-14 bg-yellow-500 rounded-2xl flex items-center justify-center text-3xl shadow-lg shadow-yellow-500/40 animate-bounce">👑</div>
                        @endif
                    </div>

                    <h3 class="text-2xl font-black text-white uppercase mb-2 group-hover:text-blue-400 transition">{{ $item['usuario']->name }}</h3>
                    <div class="text-blue-500 font-black text-sm uppercase tracking-[0.2em] mb-8">
                        {{ $papelContexto === 'jogador' ? $item['vitorias'].' Vitórias' : $item['criadas'].' Criadas' }}
                    </div>

                    <div class="w-full space-y-3">
                        <div class="flex justify-between text-xs font-black uppercase text-slate-500">
                            <span>Eficiência de Combate</span>
                            <span>{{ number_format($item['taxaVitoria'], 0) }}%</span>
                        </div>
                        <div class="w-full h-2.5 bg-white/5 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-blue-600 to-cyan-400 shadow-[0_0_15px_rgba(37,99,235,0.6)]" style="width: {{ $item['taxaVitoria'] }}%"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tabela Geral --}}
        <div class="bg-[#161920] border border-white/10 rounded-[3rem] shadow-2xl overflow-hidden">
            <div class="px-10 py-8 border-b border-white/5 flex justify-between items-center bg-white/[0.02]">
                <h3 class="text-lg font-black text-white uppercase tracking-tighter italic">Quadro de Engajamento</h3>
                <div class="flex gap-4">
                    @foreach(['total' => 'Geral', 'mensal' => 'Mensal', 'semanal' => 'Semanal'] as $tipo => $rotulo)
                        <button wire:click="setTipoRanking('{{ $tipo }}')" 
                            class="px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition {{ $tipoRanking === $tipo ? 'bg-blue-600/20 text-blue-400' : 'text-slate-600 hover:text-slate-400' }}">
                            {{ $rotulo }}
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-white/[0.01]">
                            <th class="px-10 py-6 text-xs font-black text-slate-500 uppercase tracking-widest">Rank</th>
                            <th class="px-10 py-6 text-xs font-black text-slate-500 uppercase tracking-widest">Competidor</th>
                            <th class="px-10 py-6 text-center text-xs font-black text-slate-500 uppercase tracking-widest">Status</th>
                            <th class="px-10 py-6 text-center text-xs font-black text-slate-500 uppercase tracking-widest">{{ $papelContexto === 'jogador' ? 'Vitórias' : 'Salas' }}</th>
                            <th class="px-10 py-6 text-right text-xs font-black text-slate-500 uppercase tracking-widest">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($this->topRanking->skip(3) as $item)
                        <tr wire:click="abrirDossie({{ $item['usuario']->id }})" 
                            class="group hover:bg-white/[0.03] cursor-pointer transition-colors {{ $item['ehUsuarioAtual'] ? 'bg-blue-600/5' : '' }}">
                            <td class="px-10 py-8 text-2xl font-black text-slate-700 italic group-hover:text-blue-500 transition-colors">#{{ str_pad($item['posicao'], 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-10 py-8">
                                <div class="flex items-center gap-5">
                                    <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center font-black text-white uppercase text-lg group-hover:scale-110 transition">{{ substr($item['usuario']->name, 0, 1) }}</div>
                                    <div class="font-black text-slate-200 uppercase text-md tracking-tight">{{ $item['usuario']->name }}</div>
                                </div>
                            </td>
                            <td class="px-10 py-8 text-center">
                                <span class="px-4 py-1.5 bg-emerald-500/10 text-emerald-500 text-[10px] font-black uppercase rounded-full border border-emerald-500/20">{{ $item['status'] }}</span>
                            </td>
                            <td class="px-10 py-8 text-center font-black text-white text-xl tabular-nums">
                                {{ $papelContexto === 'jogador' ? $item['vitorias'] : $item['criadas'] }}
                            </td>
                            <td class="px-10 py-8 text-right">
                                <button class="text-xs font-black text-blue-500 uppercase tracking-widest italic group-hover:underline">Analisar Dossiê</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Botão Flutuante --}}
        <button wire:click="alternarModalPatentes" class="fixed bottom-10 right-10 bg-blue-600 hover:bg-blue-500 text-white px-8 py-5 rounded-[2rem] shadow-[0_15px_50px_rgba(37,99,235,0.4)] transition-all hover:scale-105 z-40 flex items-center gap-3">
            <span class="text-xl">📖</span>
            <span class="font-black text-xs uppercase tracking-[0.2em]">Manual de Patentes</span>
        </button>
    </div>

    {{-- ============================================= --}}
    {{-- MODAL: DOSSIÊ DO JOGADOR --}}
    {{-- ============================================= --}}
    @if($jogadorSelecionado)
    <div class="fixed inset-0 z-[110] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/95 backdrop-blur-md" wire:click="fecharDossie"></div>
        <div class="relative bg-[#0f1115] w-full max-w-3xl rounded-[4rem] border border-blue-600/30 overflow-hidden shadow-[0_0_120px_rgba(37,99,235,0.15)] animate-in fade-in zoom-in duration-300">
            
            <div class="relative h-48 bg-gradient-to-r from-blue-900/40 to-purple-900/40 border-b border-white/5">
                <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20"></div>
            </div>

            <div class="px-12 pb-12">
                <div class="relative -mt-24 mb-10 flex flex-col md:flex-row justify-between items-center md:items-end gap-8">
                    <div class="relative">
                        <div class="absolute -inset-1 bg-blue-600 rounded-[3rem] blur opacity-40"></div>
                        <div class="relative w-44 h-44 bg-[#0f1115] rounded-[2.8rem] p-3 border border-blue-500/50">
                            <div class="w-full h-full bg-blue-600 rounded-[2.2rem] flex items-center justify-center text-7xl font-black text-white uppercase italic">
                                {{ substr($jogadorSelecionado->name, 0, 1) }}
                            </div>
                        </div>
                    </div>
                    <div class="text-center md:text-right">
                        <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-blue-500/10 border border-blue-500/20 rounded-full mb-3">
                            <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                            <span class="text-blue-400 text-xs font-black uppercase tracking-[0.2em]">Perfil Verificado</span>
                        </div>
                        <h3 class="text-5xl font-black text-white uppercase italic tracking-tighter">{{ $jogadorSelecionado->name }}</h3>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-10 mb-10">
                    <div class="space-y-8">
                        <div>
                            <div class="text-xs font-black text-slate-500 uppercase mb-4 tracking-[0.3em] italic">🏅 Patentes do Operativo</div>
                            <div class="flex flex-wrap gap-3">
                                @forelse($jogadorSelecionado->titles as $t)
                                    <div class="px-5 py-3 bg-blue-600/10 border border-blue-500/30 rounded-2xl">
                                        <span class="text-sm font-black text-blue-400 uppercase tracking-widest">{{ $this->traduzir($t->type) }}</span>
                                    </div>
                                @empty
                                    <span class="text-slate-600 text-sm font-bold italic uppercase">Nenhuma conquista registrada.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="p-8 bg-white/[0.03] border border-white/5 rounded-[2.5rem]">
                            <div class="text-xs font-black text-slate-500 uppercase mb-6 tracking-[0.2em]">📈 Performance em Tempo Real</div>
                            <div class="space-y-6">
                                @php
                                    $rankInfo = $jogadorSelecionado->rank;
                                    $vits = $rankInfo?->total_wins ?? 0;
                                    $parts = max($rankInfo?->total_games ?? 0, $vits);
                                    $tx = $parts > 0 ? ($vits / $parts) * 100 : 0;
                                @endphp
                                <div class="flex justify-between items-end">
                                    <span class="text-xs font-black text-slate-400 uppercase">Aproveitamento Final</span>
                                    <span class="text-3xl font-black text-white">{{ number_format($tx, 1) }}%</span>
                                </div>
                                <div class="w-full h-3 bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-blue-600 to-cyan-400 shadow-[0_0_15px_rgba(37,99,235,0.4)]" style="width: {{ $tx }}%"></div>
                                </div>
                                <div class="grid grid-cols-2 gap-6 pt-4">
                                    <div class="bg-white/5 p-4 rounded-2xl text-center">
                                        <div class="text-[10px] font-black text-slate-600 uppercase mb-1">Total de Partidas</div>
                                        <div class="text-2xl font-black text-white italic">{{ $parts }}</div>
                                    </div>
                                    <div class="bg-blue-600/5 p-4 rounded-2xl text-center border border-blue-600/10">
                                        <div class="text-[10px] font-black text-slate-600 uppercase mb-1">Total de Vitórias</div>
                                        <div class="text-2xl font-black text-blue-500 italic">{{ $vits }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 bg-white/[0.03] border border-white/5 rounded-[2.5rem]">
                        <div class="text-xs font-black text-slate-500 uppercase mb-6 tracking-[0.2em]">🕒 Vitórias Recentes Confiadas</div>
                        <div class="space-y-4">
                            @for($i=1; $i<=3; $i++)
                                <div class="flex items-center gap-5 p-4 bg-[#161920] border border-white/5 rounded-3xl group">
                                    <div class="w-12 h-12 bg-green-500/10 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition">🏆</div>
                                    <div class="flex-1">
                                        <div class="text-xs font-black text-white uppercase tracking-tight">Sucesso Confirmado</div>
                                        <div class="text-[10px] font-bold text-slate-500 uppercase">Operação Alpha-{{rand(100,999)}}</div>
                                    </div>
                                    <div class="text-green-500 font-black text-xs italic">+XP</div>
                                </div>
                            @endfor
                            <div class="pt-6 border-t border-white/5">
                                <p class="text-[10px] text-slate-500 font-bold leading-relaxed italic uppercase">
                                    Registro oficial iniciado em {{ $jogadorSelecionado->created_at->format('d/m/Y') }}. 
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <button wire:click="fecharDossie" class="w-full py-6 bg-white/5 hover:bg-white/10 border border-white/10 rounded-3xl text-sm font-black uppercase tracking-[0.4em] text-white transition-all active:scale-95 shadow-xl">
                    Fechar Arquivo do Gladiador
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- MODAL: GUIA DE PATENTES --}}
    @if($mostrarModalPatentes)
    <div class="fixed inset-0 z-[120] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/90 backdrop-blur-xl" wire:click="alternarModalPatentes"></div>
        <div class="relative bg-[#161920] w-full max-w-2xl rounded-[4rem] border border-white/10 p-12 shadow-2xl">
            <h3 class="text-4xl font-black text-white italic uppercase tracking-tighter mb-10">Glossário de <span class="text-blue-500">Patentes</span></h3>
            <div class="space-y-8 max-h-[50vh] overflow-y-auto pr-6 no-scrollbar">
                @foreach($this->getDefinicoesPatentes() as $def)
                    <div class="flex gap-8 group">
                        <div class="w-20 h-20 flex-shrink-0 {{ $def['color'] }} rounded-3xl flex items-center justify-center text-4xl shadow-2xl transition-transform group-hover:scale-110">{{ $def['icon'] }}</div>
                        <div class="flex flex-col justify-center">
                            <div class="font-black text-white uppercase text-xl tracking-widest mb-1">{{ $def['name'] }}</div>
                            <p class="text-sm text-slate-500 font-medium leading-relaxed">{{ $def['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <button wire:click="alternarModalPatentes" class="w-full mt-12 py-6 bg-blue-600 hover:bg-blue-500 text-white rounded-3xl font-black uppercase text-xs tracking-[0.3em] shadow-2xl transition-all">Sair do Manual</button>
        </div>
    </div>
    @endif

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</div>