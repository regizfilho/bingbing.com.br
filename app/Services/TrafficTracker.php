<?php

namespace App\Services;

use App\Models\User;
use App\Models\TrafficSource;
use Illuminate\Http\Request;

class TrafficTracker
{
    /**
     * Salva informações de tráfego quando o usuário se registra
     */
    public static function saveOnSignup(User $user, Request $request): void
    {
        $trafficData = $request->session()->get('traffic_source', []);

        // Atualiza campos do usuário
        $user->update([
            'signup_source' => $trafficData['signup_source'] ?? 'direct',
            'utm_source' => $trafficData['utm_source'] ?? null,
            'utm_medium' => $trafficData['utm_medium'] ?? null,
            'utm_campaign' => $trafficData['utm_campaign'] ?? null,
            'utm_content' => $trafficData['utm_content'] ?? null,
            'utm_term' => $trafficData['utm_term'] ?? null,
            'referrer_url' => $trafficData['referrer_url'] ?? null,
            'signup_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Vincula usuário à fonte de tráfego se existir
        if (isset($trafficData['id'])) {
            $trafficSource = TrafficSource::find($trafficData['id']);
            
            if ($trafficSource) {
                $trafficSource->users()->attach($user->id, [
                    'landing_page' => $trafficData['landing_page'] ?? null,
                    'ip_address' => $request->ip(),
                    'visited_at' => now(),
                    'converted' => false,
                ]);

                $trafficSource->incrementSignup();
            }
        }

        // Limpa dados da sessão
        $request->session()->forget('traffic_source');
    }

    /**
     * Registra conversão (compra) do usuário
     */
    public static function trackConversion(User $user, float $revenue): void
    {
        // Atualiza todas as fontes de tráfego do usuário
        $user->trafficSources()->each(function($source) use ($revenue) {
            $source->incrementConversion($revenue);
            
            // Marca como convertido na tabela pivot
            $source->users()->updateExistingPivot($source->id, [
                'converted' => true,
            ]);
        });
    }

    /**
     * Obtém estatísticas de origem de tráfego
     */
    public static function getStats(int $days = 30): array
    {
        $sources = TrafficSource::where('last_seen_at', '>=', now()->subDays($days))
            ->orderByDesc('visits_count')
            ->get();

        return [
            'total_visits' => $sources->sum('visits_count'),
            'total_signups' => $sources->sum('signups_count'),
            'total_conversions' => $sources->sum('conversions_count'),
            'total_revenue' => $sources->sum('revenue'),
            'conversion_rate' => $sources->sum('visits_count') > 0 
                ? round(($sources->sum('conversions_count') / $sources->sum('visits_count')) * 100, 2)
                : 0,
            'top_sources' => $sources->take(10)->map(fn($s) => [
                'name' => $s->source_name,
                'type' => $s->source_type,
                'visits' => $s->visits_count,
                'signups' => $s->signups_count,
                'conversions' => $s->conversions_count,
                'revenue' => $s->revenue,
            ]),
        ];
    }
}