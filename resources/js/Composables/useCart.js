import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

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

export function useCart() {
    /**
     * Recupera il conteggio e il totale del carrello dal backend.
     * Fail silente — il badge del carrello non è critico.
     */
    const fetchCartCount = async () => {
        try {
            const response = await fetch(route('shop.cart.count'));
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
    const addToCart = (productId, quantity = 1, variantId = null) => {
        router.post(route('shop.cart.store'), {
            product_id: productId,
            quantity,
            variant_id: variantId,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                fetchCartCount();
                isCartOpen.value = true;
            },
        });
    };

    /**
     * Aggiorna la quantità di un item già nel carrello.
     */
    const updateQuantity = (cartItemId, quantity) => {
        router.patch(route('shop.cart.update', cartItemId), {
            quantity,
        }, {
            preserveScroll: true,
            onSuccess: () => fetchCartCount(),
        });
    };

    /**
     * Rimuove un item dal carrello.
     */
    const removeItem = (cartItemId) => {
        router.delete(route('shop.cart.destroy', cartItemId), {
            preserveScroll: true,
            onSuccess: () => fetchCartCount(),
        });
    };

    const openCart = () => { isCartOpen.value = true; };
    const closeCart = () => { isCartOpen.value = false; };
    const toggleCart = () => { isCartOpen.value = !isCartOpen.value; };

    /**
     * Totale formattato in EUR con locale italiano.
     */
    const formattedTotal = computed(() => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
        }).format(cartTotal.value);
    });

    return {
        cartCount,
        cartTotal,
        isCartOpen,
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
