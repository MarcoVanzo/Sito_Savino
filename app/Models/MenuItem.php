<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use App\Support\CmsFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class MenuItem extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity;

    public $translatable = ['label', 'description', 'motto_title', 'motto_subtitle'];

    protected $fillable = [
        'label',
        'url',
        'description',
        'motto_title',
        'motto_subtitle',
        'menu_image_position',
        'parent_id',
        'location',
        'sort_order',
        'is_active',
        'is_highlight',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_highlight' => 'boolean',
        'sort_order' => 'integer',
    ];

    private const CACHE_KEY = 'menu_items';

    private const CACHE_TTL = 86400;

    private const PREFISSO_DOCUMENTO = 'documento:';

    /**
     * Parent menu item.
     */
    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * Children menu items.
     */
    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /**
     * Register media collections for menu images.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('menu-images')->singleFile();
    }

    /**
     * Mappa label → file immagine statica in public/images/menu/.
     * Usata come fonte primaria; fallback alla media library Spatie.
     *
     * Sono WebP a 720px: il riquadro nel mega-menu è largo ~290px e con lo
     * zoom in hover non supera i 720 nemmeno su schermi retina. In JPEG a
     * piena risoluzione le nove immagini pesavano 1,1 MB.
     */
    public static array $staticMenuImages = [
        'stagione' => 'stagione.webp',
        'società' => 'societa.webp',
        'ticketing' => 'ticketing.webp',
        'sponsor' => 'sponsor.webp',
        'sdb youth' => 'youth.webp',
        'summer camp' => 'camp.webp',
        'sociale' => 'sociale.webp',
        'comunicazione' => 'media.webp',
        'shop ufficiale' => 'shop.webp',
    ];

    /**
     * Slug delle pagine che in questo momento non sono pubblicate.
     *
     * Mettere una pagina in bozza deve toglierla anche dal menu: la voce
     * restava e portava a un "pagina non trovata", che per chi legge è peggio
     * della voce assente. È successo con Summer Camp.
     *
     * @return array<int, string>
     */
    private static function slugNonPubblicati(): array
    {
        return Page::query()
            ->where('status', '!=', PostStatus::Published->value)
            ->pluck('slug')
            ->all();
    }

    /**
     * La voce porta a una pagina che oggi non è pubblicata?
     *
     * Si guarda l'ultimo segmento dell'indirizzo, che è lo slug della pagina:
     * `/sociale/progetti-sociali` e `/progetti-sociali` sono la stessa cosa.
     * Gli indirizzi esterni non hanno una pagina dietro e restano.
     */
    private static function portaAUnaPaginaInBozza(?string $url, array $nonPubblicati): bool
    {
        if ($nonPubblicati === [] || ! is_string($url) || ! str_starts_with($url, '/')) {
            return false;
        }

        $percorso = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($percorso === '') {
            return false;
        }

        $segmenti = explode('/', $percorso);

        return in_array(end($segmenti), $nonPubblicati, true);
    }

    /**
     * Albero di navigazione completo di una posizione, tenuto in cache.
     */
    public static function getTree(string $location = 'main'): array
    {
        $locale = app()->getLocale();

        return Cache::remember(self::CACHE_KEY.'_'.$location.'_'.$locale, self::CACHE_TTL, function () use ($location, $locale) {
            $nonPubblicati = self::slugNonPubblicati();

            return static::where('location', $location)
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->with(['children'])
                ->orderBy('sort_order')
                ->get()
                ->reject(fn ($item) => self::vaNascosta($item->url, $nonPubblicati))
                ->map(fn ($item) => self::nodo($item, $locale, $nonPubblicati))
                // `values()` non e' un vezzo: `reject()` conserva le chiavi, e
                // una numerazione con un buco (la voce Summer Camp scartata
                // perche' in bozza) diventa in JSON un oggetto invece di un
                // elenco. Nel browser `navigation.map(...)` andava in errore e
                // la pagina restava bianca — tutta, non solo il menu.
                ->values()
                ->toArray();
        });
    }

    /**
     * Una voce sparisce dal menu se porta a una pagina in bozza o a un
     * documento legale che non e' stato caricato.
     *
     * @param  array<int, string>  $nonPubblicati
     */
    private static function vaNascosta(?string $url, array $nonPubblicati): bool
    {
        return self::portaAUnaPaginaInBozza($url, $nonPubblicati) || self::documentoMancante($url);
    }

    /**
     * Voce di primo livello, con le sue figlie gia' filtrate.
     *
     * @param  array<int, string>  $nonPubblicati
     * @return array<string, mixed>
     */
    private static function nodo(self $item, string $locale, array $nonPubblicati): array
    {
        return [
            'id' => $item->id,
            'label' => $item->label,
            'href' => static::href($item->url, $locale),
            'description' => $item->description,
            'mottoTitle' => $item->motto_title,
            'mottoSubtitle' => $item->motto_subtitle,
            'menuImagePosition' => $item->menu_image_position ?: 'center',
            'menuImage' => self::immagineDelMenu($item),
            'isHighlight' => $item->is_highlight,
            'children' => $item->children
                ->reject(fn ($child) => self::vaNascosta($child->url, $nonPubblicati))
                ->map(fn ($child) => self::nodoFiglio($child, $locale))
                ->values()
                ->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function nodoFiglio(self $child, string $locale): array
    {
        return [
            'id' => $child->id,
            'label' => $child->label,
            'href' => static::href($child->url, $locale),
            'description' => $child->description,
            'isHighlight' => $child->is_highlight,
        ];
    }

    /**
     * Immagine del mega-menu: quella caricata dal pannello se c'e', altrimenti
     * quella statica abbinata all'etichetta italiana della voce.
     */
    private static function immagineDelMenu(self $item): ?string
    {
        $caricata = $item->getFirstMediaUrl('menu-images') ?: null;

        if ($caricata) {
            return $caricata;
        }

        $etichettaIt = is_array($item->getTranslations('label'))
            ? ($item->getTranslation('label', 'it', false) ?: $item->label)
            : $item->label;

        $normalizzata = mb_strtolower(trim((string) $etichettaIt));

        return isset(self::$staticMenuImages[$normalizzata])
            ? '/images/menu/'.self::$staticMenuImages[$normalizzata]
            : null;
    }

    /**
     * Indirizzo finale di una voce di menu.
     *
     * Gli indirizzi interni si normalizzano (via la barra finale) e prendono il
     * prefisso della lingua. Quelli esterni si lasciano esattamente come li ha
     * scritti la redazione: togliere la barra finale a un indirizzo altrui
     * significa cambiarlo, e su alcuni siti cambia anche la pagina che apre.
     */
    /**
     * Chiave del documento legale richiesto da una voce di menu.
     *
     * I documenti di Corporate Governance non sono pagine: sono i PDF caricati
     * in Impostazioni → Documenti Legali. La voce di menu li indica con
     * `documento:modello_organizzativo`, e l'indirizzo vero si legge al momento:
     * cosi' sostituire il file dal pannello aggiorna il link, e rinominare la
     * voce non lo rompe.
     *
     * Prima l'abbinamento lo faceva il footer confrontando l'etichetta italiana
     * ("Protocollo Bullismo"): sul sito inglese nessuna etichetta corrispondeva
     * e tutti e quattro i link portavano alla pagina Safeguarding.
     */
    private static function chiaveDelDocumento(?string $url): ?string
    {
        $url = trim((string) $url);

        if (! str_starts_with($url, self::PREFISSO_DOCUMENTO)) {
            return null;
        }

        $chiave = substr($url, strlen(self::PREFISSO_DOCUMENTO));

        return $chiave === '' ? null : $chiave;
    }

    /**
     * Indirizzo pubblico del documento, o null se non e' stato caricato.
     */
    private static function indirizzoDelDocumento(?string $url): ?string
    {
        $chiave = self::chiaveDelDocumento($url);

        if ($chiave === null) {
            return null;
        }

        $documenti = SiteSetting::getAllGrouped()['legal'] ?? [];

        // In archivio c'e' il percorso sul disco, non l'indirizzo: in
        // produzione i file stanno su Spaces e "legal/Protocollo.pdf" nel
        // footer diventerebbe un percorso relativo alla pagina aperta.
        return CmsFile::url($documenti[$chiave] ?? null);
    }

    /**
     * La voce chiede un documento che nessuno ha ancora caricato.
     *
     * In quel caso sparisce: un link che non apre nulla e' peggio della voce
     * assente, ed e' esattamente cio' che la redazione ha segnalato.
     */
    private static function documentoMancante(?string $url): bool
    {
        return self::chiaveDelDocumento($url) !== null
            && self::indirizzoDelDocumento($url) === null;
    }

    public static function href(?string $url, string $locale): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return static::localizeUrl('/in-costruzione', $locale);
        }

        if (self::chiaveDelDocumento($url) !== null) {
            return self::indirizzoDelDocumento($url) ?? static::localizeUrl('/in-costruzione', $locale);
        }

        // Non comincia con "/": porta fuori dal sito (o e' un mailto:).
        if (! str_starts_with($url, '/')) {
            return $url;
        }

        $pulito = rtrim($url, '/');

        return static::localizeUrl($pulito === '' ? '/' : $pulito, $locale);
    }

    public static function localizeUrl(string $url, string $locale): string
    {
        if (! str_starts_with($url, '/')) {
            return $url;
        }

        // Parse the URL to preserve any query string (?...) and fragment (#...)
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        $queryString = isset($parsed['query']) ? '?'.$parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

        $cleanUrl = rtrim($path, '/');
        if ($cleanUrl === '') {
            $cleanUrl = '/';
        }

        // Remove language prefix if present (e.g. /en/) to normalize before matching
        $cleanUrl = preg_replace('/^\/(en)(\/|$)/', '/', $cleanUrl) ?? '/';

        $localizedPath = null;

        try {
            // Match the request against Laravel's routing system to find the matching route
            $request = Request::create($cleanUrl, 'GET');
            $route = app('router')->getRoutes()->match($request);
            $routeName = $route->getName();

            if ($routeName) {
                // Strip existing locale prefix from route name (e.g. "en.shop.category" -> "shop.category")
                $baseRouteName = preg_replace('/^(en)\./', '', $routeName);

                // Get target localized route name
                $targetRouteName = $locale === 'it' ? $baseRouteName : $locale.'.'.$baseRouteName;

                if (app('router')->getRoutes()->hasNamedRoute($targetRouteName)) {
                    $parameters = $route->parameters();

                    // Generate relative URL using Laravel's route generator (this automatically handles prefixes and parameters)
                    $localizedPath = route($targetRouteName, $parameters, false);
                }
            }
        } catch (\Exception $e) {
            // Fallback for non-matching URLs (e.g. pages still in development or raw custom paths)
        }

        if ($localizedPath === null) {
            // Manually prepend the language prefix for fallbacks on non-default languages
            $prefix = $locale === 'it' ? '' : '/'.$locale;
            $localizedPath = $prefix.$cleanUrl;
        }

        // Re-append the preserved query string and fragment
        return $localizedPath.$queryString.$fragment;
    }

    /**
     * Clear menu cache.
     */
    public static function clearCache(): void
    {
        foreach (config('app.supported_locales', ['it', 'en']) as $locale) {
            Cache::forget(self::CACHE_KEY.'_main_'.$locale);
            Cache::forget(self::CACHE_KEY.'_footer_'.$locale);
        }
    }

    /**
     * Boot — auto clear cache on changes.
     */
    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions();
    }
}
