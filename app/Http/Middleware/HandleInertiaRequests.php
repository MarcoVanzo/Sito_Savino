<?php

namespace App\Http\Middleware;

use App\Models\MenuItem;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Skip heavy queries for admin/filament routes
        $isPublic = ! $request->is('admin*', 'filament*', 'livewire*');

        return [
            ...parent::share($request),
            'locale' => fn () => app()->getLocale(),
            'alternateUrl' => function () use ($request) {
                $locale = app()->getLocale();
                $path = $request->getPathInfo();
                $query = $request->getQueryString() ? '?' . $request->getQueryString() : '';

                if ($locale === 'it') {
                    $enPath = $path === '/' ? '/en' : '/en' . $path;
                    return url($enPath . $query);
                }
                
                $itPath = preg_replace('#^/en(/|$)#', '/', $path);
                return url($itPath . $query);
            },
            'locales' => ['it', 'en'],
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email', 'role'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'newsletter_info' => fn () => $request->session()->get('newsletter_info'),
            ],
            'navigation' => fn () => $isPublic ? MenuItem::getTree('main') : [],
            'footerMenu' => fn () => $isPublic ? MenuItem::getTree('footer') : [],
            'siteSettings' => fn () => $isPublic ? SiteSetting::getAllGrouped() : [],
        ];
    }
}
