<?php

namespace App\Http\Middleware;

use App\Models\ConsensoCookie;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\IndirizziPerLingua;
use App\Support\SentryDsn;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;
use Throwable;
use Tighten\Ziggy\Ziggy;

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
            //
            // Il server SSR non ha la direttiva @routes: al primo caricamento
            // (non nelle visite Inertia) riceve qui l'elenco chiuso delle rotte
            // (config/ziggy.php), per i `route()` dei componenti (paginaSsr.js).
            'ziggy' => fn () => [
                ...($this->serveIlServerSsr($request)
                    ? Arr::only((new Ziggy)->toArray(), ['routes', 'port', 'defaults'])
                    : []),
                'url' => $request->getSchemeAndHttpHost(),
                'location' => $request->url(),
            ],
            // Titolo, descrizione e immagine calcolati sul server
            // (ServeSocialCrawlerMeta): il client li usa così come sono, così
            // il titolo della scheda è lo stesso dell'HTML servito. Null sulle
            // pagine che il server non sa descrivere (errori, pannello).
            'seo' => fn () => $this->metaDellaPagina($request),
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
            'footerMenu' => fn () => $isPublic ? $this->footerMenu() : [],
            'siteSettings' => fn () => $isPublic ? $this->publicSiteSettings($request) : [],
            // La versione dei testi dell'informativa: il banner la confronta con
            // quella salvata nel browser e, se è cambiata, torna a chiedere
            // invece di dare per buono un sì raccolto su un'altra informativa.
            'consensoCookie' => ['versione' => ConsensoCookie::VERSIONE],
            // Il DSN di Sentry non è un segreto: dice dove spedire, non
            // autorizza a leggere. Gli eventi passano comunque dal nostro
            // server (SentryTunnelController), che accetta solo i DSN del sito.
            'diagnostica' => [
                'dsn' => SentryDsn::perIlBrowser(),
                'environment' => config('sentry.environment') ?? app()->environment(),
            ],
        ];
    }

    /**
     * Se questa risposta passa dal server SSR: solo i caricamenti interi, con
     * l'SSR acceso (INERTIA_SSR_ENABLED).
     */
    private function serveIlServerSsr(Request $request): bool
    {
        return (bool) config('inertia.ssr.enabled') && ! $request->header('X-Inertia');
    }

    /**
     * @return array{title: string, description: string, image: ?string, type: string}|null
     */
    private function metaDellaPagina(Request $request): ?array
    {
        $meta = $request->attributes->get(ServeSocialCrawlerMeta::ATTRIBUTO_META);

        if (! is_array($meta)) {
            return null;
        }

        return [
            'title' => (string) $meta['title'],
            'description' => (string) $meta['description'],
            'image' => $meta['image'] ?? null,
            'type' => (string) $meta['type'],
        ];
    }

    /**
     * Indirizzo della stessa pagina nell'altra lingua, per il selettore.
     *
     * Dal nome della rotta, non dal percorso: `/contatti` diventava
     * `/en/contatti`, che non esiste (è `/en/contacts`). Se nell'altra lingua
     * la pagina non c'è, il selettore porta alla sua home invece che a un 404.
     */
    private function alternateUrl(Request $request): string
    {
        $corrente = app()->getLocale();
        $altra = collect(IndirizziPerLingua::lingue())->first(fn (string $lingua) => $lingua !== $corrente)
            ?? IndirizziPerLingua::linguaPredefinita();

        return IndirizziPerLingua::perRichiesta($request, $altra) ?? IndirizziPerLingua::home($altra);
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
     * Gruppi di impostazioni che il layout e le pagine leggono da
     * `siteSettings`. Viaggiano su ogni pagina: `shop`, `auctions`, `legal`,
     * `brand` e `hero` non li legge nessun componente (lo shop riceve i suoi
     * valori dai controller) e portavano a ogni visita anche testi della
     * ricevuta e indirizzi interni (audit SEO del 14/10/2026). `home` serve
     * solo alla homepage.
     */
    private const GRUPPI_CONDIVISI = ['general', 'footer', 'social', 'contact', 'analytics'];

    /**
     * Il menu del footer come lo legge SiteFooter.vue: colonne con etichetta
     * e link. Immagini, motti e descrizioni sono del menu principale.
     *
     * @return list<array{label: mixed, children: list<array{label: mixed, href: mixed}>}>
     */
    private function footerMenu(): array
    {
        return array_map(fn (array $colonna): array => [
            'label' => $colonna['label'] ?? null,
            'children' => array_map(fn (array $voce): array => [
                'label' => $voce['label'] ?? null,
                'href' => $voce['href'] ?? null,
            ], array_values($colonna['children'] ?? [])),
        ], MenuItem::getTree('footer'));
    }

    /**
     * Impostazioni pubbliche del sito, gia' pronte per il front-end.
     *
     * @return array<string, mixed>
     */
    private function publicSiteSettings(Request $request): array
    {
        $gruppi = $request->routeIs('home', 'en.home')
            ? [...self::GRUPPI_CONDIVISI, 'home']
            : self::GRUPPI_CONDIVISI;

        $settings = array_intersect_key(SiteSetting::getPublicGrouped(), array_flip($gruppi));

        // Il pixel legge una configurazione d'ambiente, non un'impostazione del
        // pannello: viaggia insieme alle altre voci di analytics perche' il
        // front-end abbia un posto solo da guardare invece di una prop di primo
        // livello in piu'.
        $settings['analytics']['meta_pixel_requires_consent'] =
            (bool) config('services.meta.pixel_requires_consent');

        // Google Ads, come il pixel, da variabile d'ambiente: vuoto = spento.
        $settings['analytics']['google_ads_id'] = ((string) config('services.google_ads.id')) ?: null;
        $settings['analytics']['google_ads_purchase_label'] = ((string) config('services.google_ads.purchase_label')) ?: null;

        $contactOverrides = $this->contactPageOverrides();

        if ($contactOverrides !== []) {
            $settings['contact'] = array_merge($settings['contact'] ?? [], $contactOverrides);
        }

        // Le caselle dei singoli uffici le riceve solo la pagina che le mostra
        // (Page::datiPerIlFrontend).
        foreach (Page::CASELLE_PER_MODELLO as $caselle) {
            foreach ($caselle as $chiave) {
                unset($settings['contact'][$chiave]);
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

            if (! is_array($contentData)) {
                return [];
            }

            // Restano solo i recapiti: i testi della pagina (intestazione,
            // modulo, argomenti, elenco degli uffici) Contatti.vue li legge da
            // `page.content_data`, e in `siteSettings` viaggiavano su ogni
            // pagina del sito.
            return array_filter(
                $contentData,
                fn (mixed $valore, string|int $chiave): bool => ! is_array($valore)
                    && ! str_starts_with((string) $chiave, 'form_')
                    && ! str_starts_with((string) $chiave, 'hero_'),
                ARRAY_FILTER_USE_BOTH,
            );
        } catch (Throwable) {
            // La tabella o la pagina possono non esistere ancora: il sito deve
            // reggere lo stesso.
            return [];
        }
    }
}
