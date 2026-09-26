<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCart } from '@/Composables/useCart.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useTrappolaDelFocus } from '@/Composables/useTrappolaDelFocus.js';

const $t = useTranslations();

const {
    isCartOpen,
    closeCart,
    updateQuantity,
    removeItem,
    cartVersion,
} = useCart();
const { formatPrice } = useFormatPrice();
const { onImgError } = useImageFallback();

const cart = ref({ items: [], total: 0 });
const hasFetched = ref(false);
const loadingItems = ref(new Set());

const fetchCart = async () => {
    try {
        const response = await fetch(route('shop.cart.data'));
        const data = await response.json();
        cart.value = data;
        hasFetched.value = true;
    } catch (e) {
        console.error('[CartDrawer] Failed to fetch cart data:', e);
    }
};

const items = computed(() => cart.value?.items ?? []);
const total = computed(() => cart.value?.total ?? 0);
const itemCount = computed(() => items.value.reduce((sum, item) => sum + (item.quantity || 1), 0));
const isEmpty = computed(() => items.value.length === 0);
const hasStockWarnings = computed(() => cart.value?.items?.some(item => item.stock_warning) ?? false);

// Il carrello e' una finestra modale: il focus entra, non esce col Tab e
// torna al pulsante che l'ha aperto (WCAG 2.4.3). L'Esc c'era gia'.
const pannello = ref(null);
useTrappolaDelFocus(pannello, isCartOpen);

// Alla chiusura si rimette lo scroll com'era all'apertura, non vuoto: aperto
// sopra il menu mobile, che blocca a sua volta la pagina, rimetterlo a ''
// faceva tornare a scorrere la pagina sotto il menu.
let overflowPrecedente = '';

watch(isCartOpen, (open) => {
    if (typeof document !== 'undefined') {
        if (open) {
            overflowPrecedente = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = overflowPrecedente;
        }
    }
    if (open) {
        fetchCart();
    }
});

// Re-fetch cart data when cart mutations occur (add/update/remove)
watch(cartVersion, () => {
    if (isCartOpen.value) {
        fetchCart();
    }
});

// Chiude il drawer con ESC. Anche il menu mobile ascolta l'Esc sul document:
// col carrello aperto sopra il menu un Esc chiudeva entrambi. Il carrello sta
// sopra, quindi ascolta in cattura (prima di chiunque ascolti in bolla, a
// prescindere dall'ordine di registrazione) e ferma l'evento quando chiude.
const handleKeydown = (e) => {
    if (e.key === 'Escape' && isCartOpen.value) {
        e.stopImmediatePropagation();
        closeCart();
    }
};

// Durante un aggiornamento i pulsanti restano focalizzabili (aria-disabled):
// con `disabled` il focus cadeva sul body a ogni click su "+" o "−". Il
// doppio click lo ferma la guardia, non l'attributo.
const inAggiornamento = (id) => loadingItems.value.has(id);
const limiteDellaRiga = (item) => item.disponibili ?? item.stock ?? item.product?.stock ?? 99;

const conAttesa = (id, azione) => {
    if (inAggiornamento(id)) return;
    loadingItems.value.add(id);
    azione({ onFinish: () => { loadingItems.value.delete(id); } });
};

const diminuisci = (item) => {
    if ((item.quantity || 1) <= 1) return;
    conAttesa(item.id, (callbacks) => updateQuantity(item.id, (item.quantity || 1) - 1, callbacks));
};

const aumenta = (item) => {
    if ((item.quantity || 1) >= limiteDellaRiga(item)) return;
    conAttesa(item.id, (callbacks) => updateQuantity(item.id, (item.quantity || 1) + 1, callbacks));
};

const togli = (item) => {
    conAttesa(item.id, (callbacks) => removeItem(item.id, callbacks));
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown, true);
    if (!hasFetched.value) {
        fetchCart();
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown, true);
    if (typeof document !== 'undefined' && isCartOpen.value) {
        document.body.style.overflow = overflowPrecedente;
    }
});
</script>

<template>
    <Teleport to="body">
        <!-- Overlay -->
        <Transition
            enter-active-class="ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isCartOpen"
                class="fixed inset-0 z-[80] bg-black/60 backdrop-blur-sm"
                @click="closeCart"
            />
        </Transition>

        <!-- Drawer Panel -->
        <Transition
            enter-active-class="ease-out duration-300"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="ease-in duration-200"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div
                v-if="isCartOpen"
                ref="pannello"
                role="dialog"
                aria-modal="true"
                aria-labelledby="carrello-titolo"
                class="fixed inset-y-0 right-0 z-[90] w-full max-w-md bg-gray-900 shadow-2xl flex flex-col transform transition-transform"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-5 border-b border-gray-800">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 text-savino-fucsia-chiaro" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <h2 id="carrello-titolo" class="text-white font-bold text-lg uppercase tracking-wider">
                            {{ $t('shop.cart_title') || 'Carrello' }}
                        </h2>
                        <span
                            v-if="itemCount > 0"
                            class="bg-savino-fucsia/20 text-savino-fucsia-chiaro text-xs font-bold px-2.5 py-0.5 rounded-full"
                        >
                            {{ itemCount }}
                        </span>
                    </div>
                    <button type="button"
                        @click="closeCart"
                        class="text-gray-400 hover:text-white transition-colors p-1"
                        :aria-label="$t('common.close') || 'Chiudi'"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Cart Items -->
                <div class="flex-grow overflow-y-auto px-6 py-4 space-y-4" v-if="!isEmpty">
                    <div
                        v-for="item in items"
                        :key="item.id"
                        class="flex gap-4 bg-gray-800/50 rounded-lg p-3 border border-gray-800 transition-opacity duration-200"
                        :class="{ 'opacity-50': loadingItems.has(item.id) }"
                        :aria-busy="loadingItems.has(item.id) ? 'true' : undefined"
                    >
                        <!-- Product Image -->
                        <div class="w-20 h-20 rounded-lg overflow-hidden bg-gray-800 flex-shrink-0">
                            <img
                                v-if="item.product?.image_url || item.image_url"
                                :src="item.product?.image_url || item.image_url"
                                :alt="item.product?.name || item.name"
                                class="w-full h-full object-cover"
                                loading="lazy"
                                @error="onImgError"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Item Details -->
                        <div class="flex-grow min-w-0">
                            <Link
                                v-if="item.slug || item.product?.slug"
                                :href="route('shop.product', item.slug || item.product?.slug)"
                                @click="closeCart"
                                class="text-white text-sm font-bold leading-tight truncate block hover:text-savino-fucsia-chiaro transition-colors"
                            >
                                {{ item.product?.name || item.name }}
                            </Link>
                            <h4 v-else class="text-white text-sm font-bold leading-tight truncate">
                                {{ item.product?.name || item.name }}
                            </h4>
                            <p v-if="item.variant_name || item.variant" class="text-gray-400 text-xs mt-0.5">
                                {{ item.variant_name || item.variant }}
                            </p>
                            <p v-if="item.personalizzazione" class="text-xs text-savino-fucsia-chiaro font-semibold mt-0.5">+ {{ item.personalizzazione }}</p>
                            <p class="text-savino-fucsia-chiaro font-bold text-sm mt-1">
                                {{ formatPrice(item.price) }}
                            </p>

                            <!-- Quantity Controls -->
                            <div class="flex items-center gap-2 mt-2">
                                <button type="button"
                                    @click="diminuisci(item)"
                                    class="w-7 h-7 rounded-md bg-gray-700 text-gray-300 hover:bg-savino-fucsia hover:text-white transition-colors flex items-center justify-center text-sm font-bold"
                                    :aria-label="$t('shop.decrease_quantity_of', { name: item.product?.name || item.name })"
                                    :disabled="item.quantity <= 1"
                                    :aria-disabled="loadingItems.has(item.id) ? 'true' : undefined"
                                >
                                    −
                                </button>
                                <span class="text-white text-sm font-bold w-8 text-center" aria-live="polite">
                                    <span class="sr-only">{{ $t('shop.quantity') }}: </span>{{ item.quantity || 1 }}
                                </span>
                                <button type="button"
                                    @click="aumenta(item)"
                                    class="w-7 h-7 rounded-md bg-gray-700 text-gray-300 hover:bg-savino-fucsia hover:text-white transition-colors flex items-center justify-center text-sm font-bold disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-gray-700 disabled:hover:text-gray-300"
                                    :aria-label="$t('shop.increase_quantity_of', { name: item.product?.name || item.name })"
                                    :disabled="item.quantity >= limiteDellaRiga(item)"
                                    :aria-disabled="loadingItems.has(item.id) ? 'true' : undefined"
                                >
                                    +
                                </button>

                                <!-- Remove Button -->
                                <button type="button"
                                    @click="togli(item)"
                                    class="ml-auto text-gray-400 hover:text-red-400 transition-colors p-1"
                                    :aria-label="$t('shop.remove_item_named', { name: item.product?.name || item.name })"
                                    :aria-disabled="loadingItems.has(item.id) ? 'true' : undefined"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex-grow flex flex-col items-center justify-center px-6 text-center">
                    <div class="w-24 h-24 rounded-full bg-gray-800 flex items-center justify-center mb-6">
                        <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <p class="text-gray-400 text-lg font-bold mb-2">
                        {{ $t('shop.cart_empty_title') || 'Il tuo carrello è vuoto' }}
                    </p>
                    <p class="text-gray-400 text-sm mb-6">
                        {{ $t('shop.cart_empty_description') || 'Scopri i prodotti ufficiali della squadra' }}
                    </p>
                    <Link
                        :href="route('shop')"
                        class="inline-flex items-center gap-2 bg-savino-fucsia text-white text-xs font-bold uppercase tracking-wider px-6 py-3 rounded-lg hover:bg-savino-fucsia/90 transition-colors"
                        @click="closeCart"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        {{ $t('shop.browse_products') || 'Vai allo shop' }}
                    </Link>
                </div>

                <!-- Footer -->
                <div v-if="!isEmpty" class="border-t border-gray-800 px-6 py-5 space-y-4">
                    <!-- Subtotal -->
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400 text-sm font-bold uppercase tracking-wider">
                            {{ $t('shop.subtotal') || 'Subtotale' }}
                        </span>
                        <span class="text-white font-black text-xl">
                            {{ formatPrice(total) }}
                        </span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col gap-3">
                        <Link
                            v-if="!hasStockWarnings"
                            :href="route('shop.checkout')"
                            class="w-full bg-savino-fucsia text-white text-sm font-black uppercase tracking-wider py-3.5 rounded-lg hover:bg-savino-fucsia/90 transition-colors text-center"
                            @click="closeCart"
                        >
                            {{ $t('shop.proceed_checkout') || 'Procedi al checkout' }}
                        </Link>
                        <button type="button"
                            v-else
                            disabled
                            aria-disabled="true"
                            class="w-full bg-gray-600 text-gray-400 text-sm font-black uppercase tracking-wider py-3.5 rounded-lg cursor-not-allowed text-center"
                        >
                            {{ $t('shop.fix_cart_issues') || 'Correggi i problemi nel carrello' }}
                        </button>
                        <Link
                            :href="route('shop.cart')"
                            class="w-full bg-gray-800 text-gray-300 text-sm font-bold uppercase tracking-wider py-3 rounded-lg hover:bg-gray-700 hover:text-white transition-colors text-center"
                            @click="closeCart"
                        >
                            {{ $t('shop.view_cart') || 'Vedi carrello' }}
                        </Link>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
