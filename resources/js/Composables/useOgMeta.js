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
    const baseUrl = page.props.ziggy?.url || '';
    const currentUrl = page.props.ziggy?.location || '';
    const siteName = 'Savino Del Bene Volley';
    const defaultImage = `${baseUrl}${LOGOS.VOLLEY}`;
    const defaultDescription = 'Savino Del Bene Volley - Sito ufficiale della squadra di pallavolo femminile di Scandicci. Serie A1, roster, calendario, risultati e shop.';

    const fullTitle = title
        ? (suffix ? `${title} — ${siteName}` : title)
        : siteName;

    return {
        title: fullTitle,
        description: description || defaultDescription,
        image: image || defaultImage,
        url: currentUrl || baseUrl,
        type,
        siteName,
    };
}
