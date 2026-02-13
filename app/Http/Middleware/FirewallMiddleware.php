<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\WhitelistIp;
use App\Models\FirewallLog;
use Illuminate\Http\Request;

class FirewallMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();

        $isAllowed = WhitelistIp::where('ip', $ip)
            ->where('is_active', true)
            ->exists();

        if (!$isAllowed) {
            FirewallLog::create([
                'ip' => $ip,
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, "Acesso Negado. IP não autorizado: $ip");
        }

        return $next($request);
    }
}