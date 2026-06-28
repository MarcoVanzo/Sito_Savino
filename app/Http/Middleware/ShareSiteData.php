<?php

namespace App\Http\Middleware;

use App\Models\MenuItem;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShareSiteData
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/*', 'filament/*', 'livewire/*', 'api/*')) {
            return $next($request);
        }

        $settings = SiteSetting::getAllGrouped();
        $navigation = MenuItem::getTree('main');
        $footerMenu = MenuItem::getTree('footer');

        Inertia::share([
            'siteSettings' => fn () => $settings,
            'navigation' => fn () => $navigation,
            'footerMenu' => fn () => $footerMenu,
        ]);

        return $next($request);
    }
}
