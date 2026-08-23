import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Helper condivisi dalle pagine del checkout asta
 * (Public/Shop/Auctions/Checkout, CheckoutSuccess, CheckoutCancel, CheckoutExpired).
 *
 * Le props di quelle pagine arrivano da AuctionCheckoutController, che può
 * cambiare: qui tutto è difensivo (optional chaining + fallback) in modo che il
 * componente si monti comunque anche se una prop manca o cambia forma.
 *
 * @param {Object} props Le props del componente chiamante.
 */
export function useAuctionCheckout(props = {}) {
    let page = null;
    try {
        page = usePage();
    } catch {
        page = null;
    }

    const locale = computed(() => page?.props?.locale ?? 'it');

    /**
     * Gli attributi tradotti (spatie/laravel-translatable) di norma arrivano già
     * risolti nella lingua corrente, ma in alcuni contesti serializzano l'intera
     * mappa `{ it: '…', en: '…' }`: gestiamo entrambi i casi.
     */
    const localized = (value) => {
        if (value == null) return '';
        if (typeof value === 'string') return value;
        if (typeof value === 'object') {
            return value[locale.value] ?? value.it ?? Object.values(value)[0] ?? '';
        }
        return String(value);
    };

    /**
     * Token del checkout. Il controller non lo passa come prop: si ricava
     * dall'asta e, come ultima risorsa, dal path corrente (/checkout/asta/{token}).
     */
    const checkoutToken = computed(() => {
        if (props.token) return props.token;
        if (props.auction?.winner_checkout_token) return props.auction.winner_checkout_token;
        if (props.order?.order_token) return props.order.order_token;

        if (typeof window !== 'undefined') {
            const match = /\/checkout\/asta\/([^/?#]+)/.exec(window.location.pathname);
            if (match) return decodeURIComponent(match[1]);
        }

        return null;
    });

    /**
     * Prima immagine disponibile fra prodotto e asta, provando le varie forme
     * di serializzazione (accessor, media library, oggetto già trasformato).
     */
    const auctionImage = (product = null) => {
        const candidates = [
            product?.image_url,
            product?.image,
            product?.media?.[0]?.original_url,
            props.auction?.image,
            props.auction?.images?.[0]?.original,
            props.auction?.product?.image_url,
            props.auction?.product?.media?.[0]?.original_url,
        ];
        return candidates.find(url => typeof url === 'string' && url !== '') ?? null;
    };

    return { locale, localized, checkoutToken, auctionImage };
}

export default useAuctionCheckout;
