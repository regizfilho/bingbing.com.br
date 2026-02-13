<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Jenssegers\Agent\Agent;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'started_at',
        'last_activity_at',
        'ended_at',
        'duration_seconds',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cria ou atualiza sessão
     */
    public static function startOrUpdate(int $userId, string $ipAddress, string $userAgent): self
    {
        $session = self::where('user_id', $userId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if (!$session) {
            $agent = new Agent();
            $agent->setUserAgent($userAgent);

            $session = self::create([
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_type' => $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop'),
                'browser' => $agent->browser(),
                'os' => $agent->platform(),
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
        } else {
            $session->update(['last_activity_at' => now()]);
        }

        return $session;
    }

    /**
     * Finaliza sessão
     */
    public function end(): void
    {
        $this->update([
            'ended_at' => now(),
            'duration_seconds' => now()->diffInSeconds($this->started_at),
        ]);
    }

    /**
     * Finaliza sessões inativas (mais de 30 minutos)
     */
    public static function endInactiveSessions(): void
    {
        self::whereNull('ended_at')
            ->where('last_activity_at', '<', now()->subMinutes(30))
            ->get()
            ->each(fn($session) => $session->end());
    }
}