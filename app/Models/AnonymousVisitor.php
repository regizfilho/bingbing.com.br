<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Jenssegers\Agent\Agent;

class AnonymousVisitor extends Model
{
    protected $fillable = [
        'session_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'country',
        'city',
        'referrer_url',
        'landing_page',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'first_seen_at',
        'last_seen_at',
        'page_views',
        'duration_seconds',
        'converted_to_user',
        'user_id',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'page_views' => 'integer',
        'duration_seconds' => 'integer',
        'converted_to_user' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rastreia ou atualiza visitante anônimo
     */
    public static function track(string $sessionId, string $ipAddress, string $userAgent, array $data = []): self
    {
        $visitor = self::firstOrNew(['session_id' => $sessionId]);

        if (!$visitor->exists) {
            // Novo visitante
            $agent = new Agent();
            $agent->setUserAgent($userAgent);

            $visitor->fill([
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_type' => $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop'),
                'browser' => $agent->browser(),
                'os' => $agent->platform(),
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'page_views' => 1,
            ]);

            // Dados opcionais
            if (isset($data['referrer_url'])) $visitor->referrer_url = $data['referrer_url'];
            if (isset($data['landing_page'])) $visitor->landing_page = $data['landing_page'];
            if (isset($data['utm_source'])) $visitor->utm_source = $data['utm_source'];
            if (isset($data['utm_medium'])) $visitor->utm_medium = $data['utm_medium'];
            if (isset($data['utm_campaign'])) $visitor->utm_campaign = $data['utm_campaign'];

            $visitor->save();
        } else {
            // Atualiza visitante existente
            $visitor->increment('page_views');
            $visitor->update([
                'last_seen_at' => now(),
                'duration_seconds' => now()->diffInSeconds($visitor->first_seen_at),
            ]);
        }

        return $visitor;
    }

    /**
     * Marca como convertido quando se registra
     */
    public function markAsConverted(int $userId): void
    {
        $this->update([
            'converted_to_user' => true,
            'user_id' => $userId,
        ]);
    }

    /**
     * Obtém visitantes online (últimos 5 minutos)
     */
    public static function getOnline(): int
    {
        return self::where('last_seen_at', '>=', now()->subMinutes(5))
            ->where('converted_to_user', false)
            ->count();
    }

    /**
     * Limpa visitantes antigos (mais de 30 dias)
     */
    public static function cleanup(): int
    {
        return self::where('last_seen_at', '<', now()->subDays(30))
            ->delete();
    }

    /**
     * Obtém estatísticas
     */
    public static function getStats(int $days = 30): array
    {
        $visitors = self::where('first_seen_at', '>=', now()->subDays($days))->get();

        return [
            'total_visitors' => $visitors->count(),
            'unique_ips' => $visitors->pluck('ip_address')->unique()->count(),
            'converted' => $visitors->where('converted_to_user', true)->count(),
            'conversion_rate' => $visitors->count() > 0 
                ? round(($visitors->where('converted_to_user', true)->count() / $visitors->count()) * 100, 2)
                : 0,
            'avg_page_views' => round($visitors->avg('page_views'), 1),
            'avg_duration' => round($visitors->avg('duration_seconds'), 0),
            'by_device' => [
                'mobile' => $visitors->where('device_type', 'mobile')->count(),
                'desktop' => $visitors->where('device_type', 'desktop')->count(),
                'tablet' => $visitors->where('device_type', 'tablet')->count(),
            ],
        ];
    }
}