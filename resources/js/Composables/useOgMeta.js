import { usePage } from '@inertiajs/vue3';
import LOGOS from '@/Constants/logos.js';

/**
 * Composable per generare meta tag OG dinamici per ogni pagina.
 * 
 * Utilizzo nel template:
 *   <Head>
 *     <title>{{ ogMeta.title }}</title>
 *     <meta name="description" :content="ogMeta.description" />
 *     <meta property="og:title" :content="ogMeta.title" />
 *     <meta property="og:description" :content="ogMeta.description" />
 *     <meta property="og:image" :content="ogMeta.image" />
 *     <meta property="og:url" :content="ogMeta.url" />
 *     <meta property="og:type" :content="ogMeta.type" />
 *   </Head>
 */
export function useOgMeta({
    title = '',
    description = '',
    image = null,
    type = 'website',
    suffix = true,
} = {}) {
    const page = usePage();
    // `ziggy` è fra le props condivise da HandleInertiaRequests, ma un reload
    // parziale (`router.reload({ only: [...] })`) non lo rispedisce: il fallback
    // su window evita che og:image diventi un path relativo (non valido per
    // Open Graph) e og:url resti vuoto.
    const hasWindow = typeof window !== 'undefined';
    const baseUrl = page.props.ziggy?.url || (hasWindow ? window.location.origin : '');
    const currentUrl = page.props.ziggy?.location || (hasWindow ? window.location.href : '');
    const siteName = 'Savino Del Bene Volley';
    const defaultImage = `${baseUrl}${LOGOS.VOLLEY}`;

    // Locale-aware default description via $t (with Italian fallback for SSR)
    const $t = (typeof window !== 'undefined' && window.$t) ? window.$t : null;
    const defaultDescription = $t
        ? $t('home.og_description')
        : 'Savino Del Bene Volley - Sito ufficiale della squadra di pallavolo femminile di Scandicci. Serie A1, roster, calendario, risultati e shop.';

    let fullTitle = siteName;
    if (title) {
        fullTitle = suffix ? `${title} — ${siteName}` : title;
    }

    return {
        title: fullTitle,
        description: description || defaultDescription,
        image: image || defaultImage,
        url: currentUrl || baseUrl,
        type,
        siteName,
    };
}
