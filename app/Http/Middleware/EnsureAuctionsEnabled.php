<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuctionsEnabled
{
    /**
     * Verifica che le aste siano abilitate globalmente.
     * Se disabilitate tramite SiteSettings, restituisce 404.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! SiteSetting::get('auctions.enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}
