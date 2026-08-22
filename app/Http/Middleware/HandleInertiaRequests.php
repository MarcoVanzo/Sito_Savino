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
            'alternateUrl' => fn () => $this->alternateUrl($request),
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
                'passwordExpiry' => fn () => $this->passwordExpiryNotice($request),
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
            'siteSettings' => fn () => $isPublic ? $this->publicSiteSettings() : [],
        ];
    }

    /**
     * Indirizzo della stessa pagina nell'altra lingua, per il selettore.
     */
    private function alternateUrl(Request $request): string
    {
        $path = $request->getPathInfo();
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        if (app()->getLocale() === 'it') {
            return url(($path === '/' ? '/en' : '/en'.$path).$query);
        }

        return url(preg_replace('#^/en(/|$)#', '/', $path).$query);
    }

    /**
     * Preavviso di scadenza password: il banner sul sito e' l'unico canale che
     * raggiunge anche i clienti dello shop, che il pannello CMS non lo vedono
     * mai.
     *
     * @return array{days: int|null, expiresOn: string|null}|null
     */
    private function passwordExpiryNotice(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->passwordIsExpiringSoon()) {
            return null;
        }

        return [
            'days' => $user->daysUntilPasswordExpires(),
            'expiresOn' => $user->passwordExpiresAt()?->toDateString(),
        ];
    }

    /**
     * Impostazioni pubbliche del sito, gia' pronte per il front-end.
     *
     * @return array<string, mixed>
     */
    private function publicSiteSettings(): array
    {
        $settings = SiteSetting::getPublicGrouped();

        // Il pixel legge una configurazione d'ambiente, non un'impostazione del
        // pannello: viaggia insieme alle altre voci di analytics perche' il
        // front-end abbia un posto solo da guardare invece di una prop di primo
        // livello in piu'.
        $settings['analytics']['meta_pixel_requires_consent'] =
            (bool) config('services.meta.pixel_requires_consent');

        $contactOverrides = $this->contactPageOverrides();

        if ($contactOverrides !== []) {
            $settings['contact'] = array_merge($settings['contact'] ?? [], $contactOverrides);
        }

        foreach ($settings['legal'] ?? [] as $key => $path) {
            if ($path) {
                $settings['legal'][$key] = Storage::url($path);
            }
        }

        return $settings;
    }

    /**
     * Campi della pagina CMS "Contatti" che integrano il gruppo di
     * impostazioni omonimo.
     *
     * @return array<string, mixed>
     */
    private function contactPageOverrides(): array
    {
        try {
            $contactPage = Page::where('slug', 'contatti')->first();

            if (! $contactPage) {
                return [];
            }

            $contentData = $contactPage->getTranslation('content_data', app()->getLocale());

            if (is_string($contentData)) {
                $contentData = json_decode($contentData, true);
            }

            return is_array($contentData) ? $contentData : [];
        } catch (\Throwable) {
            // La tabella o la pagina possono non esistere ancora: il sito deve
            // reggere lo stesso.
            return [];
        }
    }
}
