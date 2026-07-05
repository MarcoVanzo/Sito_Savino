import { usePage } from '@inertiajs/vue3';

/**
 * Composable per formattare i prezzi in formato EUR.
 * Usa la locale della pagina corrente (Inertia), con fallback a 'it-IT'.
 *
 * Utilizzo:
 *   const { formatPrice } = useFormatPrice();
 *   formatPrice(29.99); // → "29,99 €" (it-IT) oppure "€29.99" (en-GB)
 */
export function useFormatPrice() {
    const page = usePage();

    const formatPrice = (price) => {
        if (price == null || isNaN(price)) return '—';
        const pageLocale = page?.props?.locale;
        const locale = pageLocale === 'en' ? 'en-GB' : 'it-IT';
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: 'EUR',
        }).format(price);
    };

    return { formatPrice };
}
