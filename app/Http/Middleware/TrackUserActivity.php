<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TrafficSource;
use App\Models\AnonymousVisitor;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            // Usuário autenticado
            $this->trackSession($request);
            
            // Se tinha sessão anônima, marca como convertido
            $this->convertAnonymousVisitor($request);
        } else {
            // Visitante anônimo
            $this->trackAnonymousVisitor($request);
        }

        // Registra origem de tráfego para novos visitantes
        if (!$request->session()->has('traffic_tracked')) {
            $this->trackTrafficSource($request);
            $request->session()->put('traffic_tracked', true);
        }

        return $next($request);
    }

    protected function trackSession(Request $request): void
    {
        UserSession::startOrUpdate(
            Auth::id(),
            $request->ip(),
            $request->userAgent() ?? 'Unknown'
        );
    }

    protected function trackAnonymousVisitor(Request $request): void
    {
        $sessionId = $request->session()->getId();
        
        AnonymousVisitor::track(
            $sessionId,
            $request->ip(),
            $request->userAgent() ?? 'Unknown',
            [
                'referrer_url' => $request->headers->get('referer'),
                'landing_page' => $request->session()->get('landing_page') ?? $request->fullUrl(),
                'utm_source' => $request->get('utm_source'),
                'utm_medium' => $request->get('utm_medium'),
                'utm_campaign' => $request->get('utm_campaign'),
            ]
        );

        // Salva landing page na primeira visita
        if (!$request->session()->has('landing_page')) {
            $request->session()->put('landing_page', $request->fullUrl());
        }
    }

    protected function convertAnonymousVisitor(Request $request): void
    {
        $sessionId = $request->session()->getId();
        
        $visitor = AnonymousVisitor::where('session_id', $sessionId)
            ->where('converted_to_user', false)
            ->first();

        if ($visitor) {
            $visitor->markAsConverted(Auth::id());
        }
    }

    protected function trackTrafficSource(Request $request): void
    {
        $referrer = $request->headers->get('referer', '');
        $utmSource = $request->get('utm_source');
        $utmMedium = $request->get('utm_medium');
        $utmCampaign = $request->get('utm_campaign');

        // Determina tipo e nome da fonte
        $sourceType = $utmSource 
            ? 'campaign' 
            : TrafficSource::identifySourceType($referrer);
        
        $sourceName = $utmSource 
            ?? TrafficSource::extractSourceName($referrer, $sourceType);

        $referrerDomain = $referrer 
            ? parse_url($referrer, PHP_URL_HOST) 
            : null;

        // Cria ou atualiza fonte de tráfego
        $trafficSource = TrafficSource::firstOrCreate(
            [
                'source_type' => $sourceType,
                'source_name' => $sourceName,
                'referrer_domain' => $referrerDomain,
            ],
            [
                'utm_params' => [
                    'utm_source' => $utmSource,
                    'utm_medium' => $utmMedium,
                    'utm_campaign' => $utmCampaign,
                    'utm_content' => $request->get('utm_content'),
                    'utm_term' => $request->get('utm_term'),
                ],
                'first_seen_at' => now(),
                'visits_count' => 0,
            ]
        );

        $trafficSource->incrementVisit();

        // Armazena dados na sessão para uso futuro (ex: no registro)
        $request->session()->put('traffic_source', [
            'id' => $trafficSource->id,
            'signup_source' => $sourceName,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $request->get('utm_content'),
            'utm_term' => $request->get('utm_term'),
            'referrer_url' => $referrer,
            'landing_page' => $request->fullUrl(),
        ]);
    }
}