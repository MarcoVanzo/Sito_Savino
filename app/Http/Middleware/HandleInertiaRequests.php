<?php

namespace App\Http\Middleware;

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

                if ($locale === 'it') {
                    $enPath = $path === '/' ? '/en' : '/en'.$path;

                    return url($enPath.$query);
                }

                $itPath = preg_replace('#^/en(/|$)#', '/', $path);

                return url($itPath.$query);
            },
            'locales' => config('app.supported_locales', ['it', 'en']),
            // Le pagine usano `ziggy.url`/`ziggy.location` per i dati strutturati
            // e i meta Open Graph, che richiedono URL assoluti. La direttiva
            // @routes espone solo l'helper JS, non questa prop.
            'ziggy' => fn () => [
                'url' => $request->getSchemeAndHttpHost(),
                'location' => $request->url(),
            ],
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email', 'role'),
                // Preavviso di scadenza password: il banner sul sito è l'unico
                // canale che raggiunge anche i clienti dello shop, che il
                // pannello CMS non lo vedono mai.
                'passwordExpiry' => function () use ($request) {
                    $user = $request->user();

                    if (! $user instanceof User || ! $user->passwordIsExpiringSoon()) {
                        return null;
                    }

                    return [
                        'days' => $user->daysUntilPasswordExpires(),
                        'expiresOn' => $user->passwordExpiresAt()?->toDateString(),
                    ];
                },
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'newsletter_info' => fn () => $request->session()->get('newsletter_info'),
            ],
            'navigation' => fn () => $isPublic ? MenuItem::getTree('main') : [],
            'footerMenu' => fn () => $isPublic ? MenuItem::getTree('footer') : [],
            'siteSettings' => function () use ($isPublic) {
                if (! $isPublic) {
                    return [];
                }
                $settings = SiteSetting::getPublicGrouped();

                try {
                    $contactPage = Page::where('slug', 'contatti')->first();
                    if ($contactPage) {
                        $contentData = $contactPage->getTranslation('content_data', app()->getLocale());
                        if (is_string($contentData)) {
                            $contentData = json_decode($contentData, true);
                        }
                        if (is_array($contentData)) {
                            $settings['contact'] = array_merge($settings['contact'] ?? [], $contentData);
                        }
                    }
                } catch (\Throwable $e) {
                    // Safe fallback in case table/page doesn't exist
                }

                if (isset($settings['legal'])) {
                    foreach ($settings['legal'] as $key => $path) {
                        if ($path) {
                            $settings['legal'][$key] = Storage::url($path);
                        }
                    }
                }

                return $settings;
            },
        ];
    }
}
