<?php

/**
 * ============================================================================
 * Usuários em Tempo Real - Live Dashboard
 * 
 * - Laravel 12:
 *   • Queries otimizadas para real-time
 *   • Cache estratégico
 * 
 * - Livewire 4:
 *   • Polling automático
 *   • Updates em tempo real
 *   • Performance otimizada
 * 
 * - Features:
 *   • Usuários online agora
 *   • Novos registros (últimas 24h)
 *   • Origem do tráfego
 *   • Atividade recente
 * ============================================================================
 */

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\AnonymousVisitor;

new #[Layout('layouts.admin')] #[Title('Usuários em Tempo Real')] class extends Component {

    public int $pollingInterval = 5000; // 5 segundos

    #[Computed]
    public function onlineUsers()
    {
        return User::where('last_seen_at', '>=', now()->subMinutes(5))
            ->with('wallet')
            ->latest('last_seen_at')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function onlineGuests()
    {
        return AnonymousVisitor::where('last_seen_at', '>=', now()->subMinutes(5))
            ->where('converted_to_user', false)
            ->latest('last_seen_at')
            ->limit(15)
            ->get();
    }

    #[Computed]
    public function newRegistrations()
    {
        return User::where('created_at', '>=', now()->subDay())
            ->with('wallet')
            ->latest('created_at')
            ->limit(15)
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        return Cache::remember('live.users.stats', 60, function() {
            $onlineUsers = User::where('last_seen_at', '>=', now()->subMinutes(5))->count();
            $onlineGuests = AnonymousVisitor::getOnline();

            return [
                'online_users' => $onlineUsers,
                'online_guests' => $onlineGuests,
                'online_total' => $onlineUsers + $onlineGuests,
                'online_1h' => User::where('last_seen_at', '>=', now()->subHour())->count(),
                'new_today' => User::whereDate('created_at', today())->count(),
                'new_week' => User::where('created_at', '>=', now()->subWeek())->count(),
                'visitors_today' => AnonymousVisitor::whereDate('first_seen_at', today())->count(),
            ];
        });
    }

    #[Computed]
    public function trafficSources(): array
    {
        return Cache::remember('live.traffic.sources', 60, function() {
            $sources = DB::table('traffic_sources')
                ->where('last_seen_at', '>=', now()->subWeek())
                ->select([
                    'source_name',
                    'source_type',
                    DB::raw('SUM(visits_count) as total_visits'),
                    DB::raw('SUM(signups_count) as total_signups'),
                ])
                ->groupBy('source_name', 'source_type')
                ->orderByDesc('total_visits')
                ->limit(5)
                ->get();
            
            if ($sources->isEmpty()) {
                // Fallback: agrupa por signup_source da tabela users
                $userSources = User::where('created_at', '>=', now()->subWeek())
                    ->select([
                        DB::raw('COALESCE(signup_source, "Direto") as source'),
                        DB::raw('COUNT(*) as count')
                    ])
                    ->groupBy('signup_source')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->get();

                if ($userSources->isEmpty()) {
                    return [
                        ['source' => 'Direto', 'count' => 0, 'type' => 'direct'],
                        ['source' => 'Google', 'count' => 0, 'type' => 'organic'],
                        ['source' => 'Redes Sociais', 'count' => 0, 'type' => 'social'],
                    ];
                }

                return $userSources->map(fn($s) => [
                    'source' => $s->source,
                    'count' => $s->count,
                    'type' => 'unknown'
                ])->toArray();
            }

            return $sources->map(fn($s) => [
                'source' => $s->source_name,
                'count' => $s->total_visits,
                'signups' => $s->total_signups,
                'type' => $s->source_type
            ])->toArray();
        });
    }

    #[Computed]
    public function recentActivity()
    {
        return User::with('wallet')
            ->whereNotNull('last_seen_at')
            ->latest('last_seen_at')
            ->limit(10)
            ->get();
    }

    public function render(): View
    {
        return view('pages.admin.users.live');
    }
};
?>

<div wire:poll.{{ $pollingInterval }}ms>
    <x-slot name="header">
        Usuários em Tempo Real
    </x-slot>

    <div class="space-y-6">
        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                    <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                    Monitoramento em Tempo Real
                </h2>
                <p class="text-slate-400 text-sm mt-1">Atualização automática a cada {{ $pollingInterval / 1000 }} segundos</p>
            </div>
            <a href="{{ route('admin.users.home') }}" wire:navigate
                class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-xl text-sm text-white transition-all">
                ← Voltar para Gestão
            </a>
        </div>

        <!-- STATS EM TEMPO REAL -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="bg-gradient-to-br from-green-500/10 to-emerald-500/5 border border-green-500/20 rounded-2xl p-5">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <p class="text-green-400 text-xs uppercase tracking-wider font-bold">Online Total</p>
                </div>
                <p class="text-4xl font-bold text-white">{{ number_format($this->stats['online_total'], 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400 mt-1">
                    {{ $this->stats['online_users'] }} usuários + {{ $this->stats['online_guests'] }} visitantes
                </p>
            </div>

            <div class="bg-gradient-to-br from-blue-500/10 to-cyan-500/5 border border-blue-500/20 rounded-2xl p-5">
                <p class="text-blue-400 text-xs uppercase tracking-wider font-bold mb-2">Usuários Online</p>
                <p class="text-4xl font-bold text-white">{{ number_format($this->stats['online_users'], 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400 mt-1">Autenticados</p>
            </div>

            <div class="bg-gradient-to-br from-purple-500/10 to-pink-500/5 border border-purple-500/20 rounded-2xl p-5">
                <p class="text-purple-400 text-xs uppercase tracking-wider font-bold mb-2">Visitantes</p>
                <p class="text-4xl font-bold text-white">{{ number_format($this->stats['online_guests'], 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400 mt-1">Não autenticados</p>
            </div>

            <div class="bg-gradient-to-br from-yellow-500/10 to-orange-500/5 border border-yellow-500/20 rounded-2xl p-5">
                <p class="text-yellow-400 text-xs uppercase tracking-wider font-bold mb-2">Novos Hoje</p>
                <p class="text-4xl font-bold text-white">{{ number_format($this->stats['new_today'], 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400 mt-1">Registros em {{ now()->format('d/m') }}</p>
            </div>

            <div class="bg-gradient-to-br from-indigo-500/10 to-blue-500/5 border border-indigo-500/20 rounded-2xl p-5">
                <p class="text-indigo-400 text-xs uppercase tracking-wider font-bold mb-2">Visitantes Hoje</p>
                <p class="text-4xl font-bold text-white">{{ number_format($this->stats['visitors_today'], 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400 mt-1">Total do dia</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- USUÁRIOS ONLINE -->
            <div class="lg:col-span-2 bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        Online Agora ({{ $this->onlineUsers->count() }})
                    </h3>
                    <span class="text-xs text-slate-500">Atualizado agora</span>
                </div>
                <div class="space-y-3 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                    @forelse($this->onlineUsers as $user)
                        <div class="flex items-center justify-between p-3 bg-black/20 border border-white/5 rounded-xl hover:bg-white/5 transition">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center text-green-400 font-semibold relative">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-[#0f172a]"></div>
                                </div>
                                <div>
                                    <div class="text-white font-medium text-sm">{{ $user->name }}</div>
                                    <div class="text-slate-400 text-xs">{{ $user->email }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-emerald-400 font-semibold text-sm">
                                    {{ number_format($user->wallet?->balance ?? 0, 0, ',', '.') }} CR
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $user->last_seen_at?->diffForHumans(null, true) ?? 'agora' }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-slate-400">
                            <p class="text-sm">Nenhum usuário online no momento</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- ORIGEM DO TRÁFEGO -->
            <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">📊 Origem do Tráfego</h3>
                <div class="space-y-4">
                    @foreach($this->trafficSources as $source)
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    @php
                                        $icon = match($source['type'] ?? 'unknown') {
                                            'social' => '📱',
                                            'organic' => '🔍',
                                            'campaign' => '📢',
                                            'referral' => '🔗',
                                            'direct' => '⚡',
                                            default => '🌐'
                                        };
                                        $color = match($source['type'] ?? 'unknown') {
                                            'social' => 'text-pink-400',
                                            'organic' => 'text-green-400',
                                            'campaign' => 'text-purple-400',
                                            'referral' => 'text-blue-400',
                                            'direct' => 'text-yellow-400',
                                            default => 'text-slate-400'
                                        };
                                    @endphp
                                    <span>{{ $icon }}</span>
                                    <span class="text-sm {{ $color }} font-medium">{{ $source['source'] }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-bold text-white">{{ $source['count'] }}</span>
                                    @if(isset($source['signups']) && $source['signups'] > 0)
                                        <span class="text-xs text-emerald-400 ml-2">({{ $source['signups'] }} 👤)</span>
                                    @endif
                                </div>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all {{ 
                                    match($source['type'] ?? 'unknown') {
                                        'social' => 'bg-gradient-to-r from-pink-500 to-rose-500',
                                        'organic' => 'bg-gradient-to-r from-green-500 to-emerald-500',
                                        'campaign' => 'bg-gradient-to-r from-purple-500 to-indigo-500',
                                        'referral' => 'bg-gradient-to-r from-blue-500 to-cyan-500',
                                        'direct' => 'bg-gradient-to-r from-yellow-500 to-orange-500',
                                        default => 'bg-gradient-to-r from-slate-500 to-gray-500'
                                    }
                                }}" 
                                    style="width: {{ $source['count'] > 0 ? min(($source['count'] / max(array_column($this->trafficSources, 'count'))) * 100, 100) : 0 }}%">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- NOVOS REGISTROS -->
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">✨ Novos Registros (Últimas 24h)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($this->newRegistrations as $user)
                    <div class="p-4 bg-black/20 border border-white/5 rounded-xl hover:bg-white/5 transition">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 bg-purple-500/20 rounded-full flex items-center justify-center text-purple-400 font-bold">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-white font-medium truncate">{{ $user->name }}</div>
                                <div class="text-slate-400 text-xs truncate">{{ $user->email }}</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-white/5">
                            <span class="text-xs text-slate-500">{{ $user->created_at->diffForHumans() }}</span>
                            <a href="{{ route('admin.users.profile', $user->uuid) }}" wire:navigate
                                class="text-xs text-indigo-400 hover:text-indigo-300 font-medium">
                                Ver →
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-8 text-slate-400">
                        Nenhum registro novo nas últimas 24 horas
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <x-toast position="top-right" />

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #111827;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }
    </style>
</div>