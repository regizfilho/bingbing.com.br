<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TrafficSource extends Model
{
    protected $fillable = [
        'source_type',
        'source_name',
        'referrer_domain',
        'landing_page',
        'utm_params',
        'visits_count',
        'signups_count',
        'conversions_count',
        'revenue',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'utm_params' => 'array',
        'visits_count' => 'integer',
        'signups_count' => 'integer',
        'conversions_count' => 'integer',
        'revenue' => 'decimal:2',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_traffic_sources')
            ->withPivot(['landing_page', 'ip_address', 'visited_at', 'converted'])
            ->withTimestamps();
    }

    /**
     * Incrementa visita
     */
    public function incrementVisit(): void
    {
        $this->increment('visits_count');
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * Incrementa signup
     */
    public function incrementSignup(): void
    {
        $this->increment('signups_count');
    }

    /**
     * Incrementa conversão e receita
     */
    public function incrementConversion(float $revenue = 0): void
    {
        $this->increment('conversions_count');
        $this->increment('revenue', $revenue);
    }

    /**
     * Identifica tipo de fonte baseado no referrer
     */
    public static function identifySourceType(string $referrer): string
    {
        if (empty($referrer)) {
            return 'direct';
        }

        $domain = parse_url($referrer, PHP_URL_HOST);
        
        // Redes sociais
        $social = ['facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'linkedin.com', 'tiktok.com', 'youtube.com'];
        foreach ($social as $site) {
            if (str_contains($domain, $site)) {
                return 'social';
            }
        }

        // Mecanismos de busca
        $search = ['google.com', 'bing.com', 'yahoo.com', 'duckduckgo.com', 'baidu.com'];
        foreach ($search as $site) {
            if (str_contains($domain, $site)) {
                return 'organic';
            }
        }

        return 'referral';
    }

    /**
     * Extrai nome da fonte baseado no referrer
     */
    public static function extractSourceName(string $referrer, string $type): string
    {
        if ($type === 'direct') {
            return 'direct';
        }

        $domain = parse_url($referrer, PHP_URL_HOST);
        $domain = str_replace('www.', '', $domain);

        // Simplifica nomes comuns
        $names = [
            'facebook.com' => 'Facebook',
            'instagram.com' => 'Instagram',
            'twitter.com' => 'Twitter',
            'x.com' => 'X (Twitter)',
            'linkedin.com' => 'LinkedIn',
            'tiktok.com' => 'TikTok',
            'youtube.com' => 'YouTube',
            'google.com' => 'Google',
            'bing.com' => 'Bing',
            'yahoo.com' => 'Yahoo',
        ];

        foreach ($names as $key => $name) {
            if (str_contains($domain, $key)) {
                return $name;
            }
        }

        return ucfirst(str_replace('.com', '', $domain));
    }
}