<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Le immagini che l'HTML servito precarica (`<link rel="preload">` in
 * app.blade.php), perche' il browser le chieda subito invece di aspettare
 * che il JavaScript disegni la pagina.
 *
 * Sono quelle che Lighthouse ha trovato come elemento LCP (audit del
 * 20/10/2026): il logo volley della testata, la prima foto dell'hero della
 * home e la foto principale della scheda prodotto. Le ultime due le passa il
 * controller con `withViewData(['immaginiDaPrecaricare' => ...])`.
 */
class ImmaginiDaPrecaricare
{
    /**
     * La stessa serie di `LOGO_VOLLEY_SRCSET` in resources/js/Constants/logos.js:
     * se divergono, il browser scarica il logo due volte (un test le confronta).
     */
    public const LOGO_VOLLEY_SRCSET = '/images/logo-volley-128.webp 128w, /images/logo-volley-256.webp 256w, /images/logo-volley-384.webp 384w';

    /** Lo stesso `sizes` del logo in PublicLayout.vue. */
    public const LOGO_VOLLEY_SIZES = '(min-width: 640px) 125px, 64px';

    /**
     * @param  list<array{href: string, srcset?: string|null, sizes?: string|null}>  $dellaPagina
     * @return list<array{href: string, srcset: string|null, sizes: string|null}>
     */
    public static function perLaPagina(array $dellaPagina = []): array
    {
        $immagini = [];

        // Un logo caricato dalla redazione non ha varianti: non si precarica
        // la serie del logo volley, che la testata non userebbe.
        if (blank(SiteSetting::get('site_logo'))) {
            $immagini[] = [
                'href' => '/images/logo-volley-256.webp',
                'srcset' => self::LOGO_VOLLEY_SRCSET,
                'sizes' => self::LOGO_VOLLEY_SIZES,
            ];
        }

        foreach ($dellaPagina as $immagine) {
            if ($immagine['href'] === '') {
                continue;
            }

            $immagini[] = [
                'href' => $immagine['href'],
                'srcset' => $immagine['srcset'] ?? null,
                'sizes' => $immagine['sizes'] ?? null,
            ];
        }

        return $immagini;
    }

    /**
     * La prima foto dell'hero, la stessa serie che Home.vue mette nello
     * `srcset`: senza video in testa e' l'immagine piu'
     * grande della home (PublicController::home).
     *
     * @param  array<int, array<string, mixed>>  $slide
     * @return list<array{href: string, srcset: string|null, sizes: string}>
     */
    public static function primaSlideDellHero(array $slide): array
    {
        $prima = $slide[0] ?? null;

        if (! is_array($prima) || filled(SiteSetting::get('home.hero_video_url')) || ! is_string($prima['image'] ?? null)) {
            return [];
        }

        $mobile = $prima['image_mobile'] ?? null;

        return [[
            'href' => $mobile ?: $prima['image'],
            'srcset' => $mobile ? $mobile.' 1200w, '.$prima['image'].' 1920w' : null,
            'sizes' => '100vw',
        ]];
    }
}
