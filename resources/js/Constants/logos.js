/**
 * Percorsi centralizzati per tutti i loghi del sito.
 * Usare queste costanti come fallback quando il backend non fornisce i loghi.
 */
export const LOGOS = {
    /** Logo volley ufficiale a colori (sfondo trasparente) */
    VOLLEY: '/images/logo.png',
    /** Logo volley bianco per sfondi scuri (sfondo trasparente) */
    VOLLEY_WHITE: '/images/logo-volley-white.png',
    /** Logo corporate Savino Del Bene con payoff (sfondo trasparente) */
    CORPORATE: '/images/logo-corporate.png',
    /** Marchio corporate con payoff, cubo a sinistra — vettoriale (>= 40 mm / 151 px) */
    CORPORATE_LEFT: '/images/logo-corporate-left.svg',
    /** Marchio corporate con payoff, bianco per sfondi scuri — vettoriale */
    CORPORATE_LEFT_WHITE: '/images/logo-corporate-left-white.svg',
    /** Marchio corporate senza payoff — da usare sotto i 40 mm (151 px) */
    CORPORATE_NAME: '/images/logo-corporate-name.svg',
    /** Marchio corporate senza payoff, bianco per sfondi scuri */
    CORPORATE_NAME_WHITE: '/images/logo-corporate-name-white.svg',
    /** Icona corporate Savino Del Bene — solo simbolo cubo, senza testo */
    CORPORATE_ICON: '/images/logo-corporate-icon.png',
    /** Logo Lega Volley Femminile (LVF) ufficiale (>= 25mm) */
    LVF: '/images/logo-lvf.png',
    /** Logo Lega Volley Femminile (LVF) ridotto (10-25mm, senza scritta SERIE A) */
    LVF_SMALL: '/images/logo-lvf-small.png',
};

/**
 * Il logo volley in WebP alle misure in cui si mostra (64-125 px in testata,
 * 56-80 nel footer): l'originale PNG e' 1600x1600, 100 KB, ed era l'elemento
 * LCP della home (audit del 20/10/2026). `LOGOS.VOLLEY` resta l'originale per
 * og:image e i ripieghi. La stessa serie e' precaricata in app.blade.php
 * (`App\Support\LogoDelSito`): un test le confronta.
 */
export const LOGO_VOLLEY_SRCSET = '/images/logo-volley-128.webp 128w, /images/logo-volley-256.webp 256w, /images/logo-volley-384.webp 384w';

/**
 * La serie di misure per un logo, solo se e' il logo volley del sito: un logo
 * caricato dalla redazione (`site_logo`) non ha varianti.
 */
export function srcsetDelLogo(src) {
    return src === LOGOS.VOLLEY ? LOGO_VOLLEY_SRCSET : undefined;
}

export default LOGOS;

