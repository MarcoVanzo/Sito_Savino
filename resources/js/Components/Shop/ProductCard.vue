<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useCart } from '@/Composables/useCart.js';
import { trackAddToCart } from '@/meta-pixel.js';
import EtichetteProdotto from '@/Components/Shop/EtichetteProdotto.vue';

const $t = useTranslations();

const { onImgError } = useImageFallback();
const { formatPrice } = useFormatPrice();
const { addToCart } = useCart();

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const isOutOfStock = computed(() => props.product.stock !== undefined && props.product.stock !== null && props.product.stock <= 0);
// Se barrare lo decide il backend (ShopController::prezzi): `price` e' il prezzo
// piu' basso dei 30 giorni prima dello sconto solo quando il flag e' acceso.
// Confrontare qui sale_price con price barrerebbe anche uno sconto senza un
// prezzo precedente praticato, che l'art. 17-bis non consente di annunciare.
const hasSalePrice = computed(() => props.product.prezzo_piu_basso_30_giorni === true && props.product.sale_price != null);

const isAdding = ref(false);
const cartError = ref('');
// L'errore resta finché non si riprova: sparire da solo dopo pochi secondi
// lo toglieva a chi legge più lentamente (WCAG 2.2.1), come in ProductDetail.

const handleAddToCart = () => {
    if (isAdding.value) return;
    if (props.product.type === 'variable') {
        router.visit(route('shop.product', props.product.slug));
        return;
    }
    isAdding.value = true;
    cartError.value = '';
    trackAddToCart({
        id: props.product.id,
        name: props.product.name,
        // Il prezzo che si paga: con uno sconto annunciabile `price` e' il
        // barrato, non l'incasso.
        value: Number(hasSalePrice.value ? props.product.sale_price : props.product.price),
    });
    addToCart({ product_id: props.product.id, quantity: 1 }, {
        onFinish: () => { isAdding.value = false; },
        onError: (errors) => {
            cartError.value = errors?.message || errors?.product_id || Object.values(errors || {})[0] || $t('shop.cart_error_generic');
        },
    });
};
</script>

<template>
    <div
        class="group relative bg-savino-blue rounded-xl overflow-hidden shadow-md hover:shadow-2xl transition-all duration-500 transform hover:scale-[1.02] flex flex-col"
    >
        <!-- Product Link wraps image + info -->
        <Link
            :href="route('shop.product', product.slug)"
            class="flex flex-col flex-grow"
        >
            <!-- Product Image -->
            <div class="relative aspect-[3/4] bg-savino-blue/60 overflow-hidden">
                <img
                    v-if="product.image_url"
                    :src="product.image_url"
                    :alt="product.name"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                    loading="lazy"
                    @error="onImgError"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                    <svg class="w-16 h-16 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>

                <!-- Etichette scelte dalla redazione (EtichetteDelProdotto) -->
                <EtichetteProdotto :etichette="product.etichette" />

                <!-- "Esaurito" Overlay -->
                <div
                    v-if="isOutOfStock"
                    class="absolute inset-0 bg-gray-900/70 flex items-center justify-center backdrop-blur-sm"
                >
                    <span class="text-white text-sm font-black uppercase tracking-wider border-2 border-white/30 px-5 py-2 rounded-full">
                        {{ $t('shop.out_of_stock') || 'Esaurito' }}
                    </span>
                </div>

                <!-- Hover Overlay (desktop) -->
                <div
                    v-if="!isOutOfStock"
                    class="absolute inset-0 bg-savino-blue/70 opacity-0 group-hover:opacity-100 transition-opacity duration-300 hidden sm:flex items-center justify-center"
                >
                    <span class="text-white text-xs font-bold uppercase tracking-wider border-2 border-savino-fucsia px-5 py-2.5 hover:bg-savino-fucsia hover:text-white transition-colors">
                        {{ $t('shop.product_details') || 'Dettagli' }}
                    </span>
                </div>
            </div>

            <!-- Product Info -->
            <div class="p-5 flex flex-col flex-grow">
                <!-- Category -->
                <span
                    v-if="product.category"
                    class="text-savino-fucsia-chiaro text-xs font-bold uppercase tracking-[0.2em] mb-2"
                >
                    {{ typeof product.category === 'string' ? product.category : product.category.name }}
                </span>

                <!-- Product Name -->
                <h3 class="text-white font-bold text-base mb-3 leading-tight group-hover:text-savino-fucsia-chiaro transition-colors duration-300 line-clamp-2">
                    {{ product.name }}
                </h3>

                <!-- Price: il barrato è il prezzo più basso dei 30 giorni prima
                     dello sconto (art. 17-bis Codice del consumo), non il listino:
                     lo decide il backend (ShopController::prezzi). -->
                <div class="mt-auto">
                    <div class="flex items-baseline gap-2">
                        <span
                            v-if="hasSalePrice"
                            class="text-gray-300 text-sm line-through"
                        >
                            {{ formatPrice(product.price) }}
                        </span>
                        <span class="text-savino-fucsia-chiaro font-black text-xl">
                            {{ formatPrice(hasSalePrice ? product.sale_price : product.price) }}
                        </span>
                    </div>
                    <p v-if="hasSalePrice" class="text-xs text-gray-300 mt-0.5">
                        {{ $t('shop.lowest_price_30_days_short') }}
                    </p>
                </div>
            </div>
        </Link>

        <!-- Add to Cart Button -->
        <div class="px-5 pb-5" v-if="!isOutOfStock">
            <button type="button"
                @click.prevent="handleAddToCart"
                :disabled="isAdding"
                class="w-full bg-savino-fucsia text-white text-xs font-bold uppercase tracking-wider py-3 rounded-lg hover:bg-white hover:text-savino-blue transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg v-if="isAdding" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                {{ product.type === 'variable' ? $t('shop.choose_options') : $t('shop.add_to_cart') }}
            </button>
            <p v-if="cartError" role="alert" class="text-red-700 text-xs mt-2 text-center">
                {{ cartError }}
            </p>
        </div>
    </div>
</template>
