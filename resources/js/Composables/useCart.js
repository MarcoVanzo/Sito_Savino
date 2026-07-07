import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Composable per gestire lo stato del carrello in modo reattivo
 * attraverso tutta l'applicazione.
 *
 * Lo stato (cartCount, cartTotal, isCartOpen) è definito fuori dalla funzione
 * per essere condiviso come singleton tra tutti i componenti che lo usano.
 *
 * Utilizzo:
 *   const { cartCount, addToCart, toggleCart } = useCart();
 */

const cartCount = ref(0);
const cartTotal = ref(0);
const isCartOpen = ref(false);
const cartVersion = ref(0);

export function useCart() {
    /**
     * Recupera il conteggio e il totale del carrello dal backend.
     * Fail silente — il badge del carrello non è critico.
     */
    const fetchCartCount = async () => {
        try {
            const response = await fetch(route('shop.cart.count'));
            if (!response.ok) return;
            const data = await response.json();
            cartCount.value = data.count;
            cartTotal.value = data.total;
        } catch (e) {
            // Silent fail — cart badge is non-critical
        }
    };

    /**
     * Aggiunge un prodotto al carrello via Inertia.
     * Dopo il successo, aggiorna il conteggio e apre il drawer.
     */
    const addToCart = (productIdOrOptions, quantity = 1, variantId = null, callbacks = {}) => {
        let productId, qty, variant;
        if (typeof productIdOrOptions === 'object' && productIdOrOptions !== null) {
            productId = productIdOrOptions.product_id;
            qty = productIdOrOptions.quantity || 1;
            variant = productIdOrOptions.variant_id || null;
            // When called with a single options object, the second arg is callbacks
            if (typeof quantity === 'object' && quantity !== null) {
                callbacks = quantity;
            }
        } else {
            productId = productIdOrOptions;
            qty = quantity;
            variant = variantId;
        }

        const { onStart, onFinish, onSuccess, onError } = callbacks;

        router.post(route('shop.cart.store'), {
            product_id: productId,
            quantity: qty,
            variant_id: variant,
        }, {
            preserveScroll: true,
            preserveState: true,
            onStart: (visit) => {
                onStart?.(visit);
            },
            onSuccess: (page) => {
                fetchCartCount();
                cartVersion.value++;
                isCartOpen.value = true;
                onSuccess?.(page);
            },
            onError: (errors) => {
                onError?.(errors);
            },
            onFinish: (visit) => {
                onFinish?.(visit);
            },
        });
    };

    /**
     * Aggiorna la quantità di un item già nel carrello.
     */
    const updateQuantity = (cartItemId, quantity, callbacks = {}) => {
        router.patch(route('shop.cart.update', cartItemId), {
            quantity,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                fetchCartCount();
                cartVersion.value++;
                callbacks.onSuccess?.();
            },
            onError: (errors) => {
                callbacks.onError?.(errors);
            },
            onFinish: () => {
                callbacks.onFinish?.();
            },
        });
    };

    /**
     * Rimuove un item dal carrello.
     */
    const removeItem = (cartItemId, callbacks = {}) => {
        router.delete(route('shop.cart.destroy', cartItemId), {
            preserveScroll: true,
            onSuccess: () => {
                fetchCartCount();
                cartVersion.value++;
                callbacks.onSuccess?.();
            },
            onError: (errors) => {
                callbacks.onError?.(errors);
            },
            onFinish: () => {
                callbacks.onFinish?.();
            },
        });
    };

    const openCart = () => { isCartOpen.value = true; };
    const closeCart = () => { isCartOpen.value = false; };
    const toggleCart = () => { isCartOpen.value = !isCartOpen.value; };

    /**
     * Totale formattato in EUR con locale italiano.
     */
    const formattedTotal = computed(() => {
        const pageLocale = usePage()?.props?.locale;
        const locale = pageLocale === 'en' ? 'en-GB' : 'it-IT';
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: 'EUR',
        }).format(cartTotal.value);
    });

    return {
        cartCount,
        cartTotal,
        isCartOpen,
        cartVersion,
        formattedTotal,
        fetchCartCount,
        addToCart,
        updateQuantity,
        removeItem,
        openCart,
        closeCart,
        toggleCart,
    };
}
