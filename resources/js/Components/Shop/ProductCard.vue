<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useCart } from '@/Composables/useCart.js';

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
const hasSalePrice = computed(() => props.product.sale_price && props.product.sale_price < props.product.price);

const isAdding = ref(false);
const cartError = ref('');
let cartErrorTimer = null;

const clearCartError = () => {
    if (cartErrorTimer) clearTimeout(cartErrorTimer);
    cartErrorTimer = setTimeout(() => { cartError.value = ''; }, 5000);
};

const handleAddToCart = () => {
    if (isAdding.value) return;
    if (props.product.type === 'variable') {
        router.visit(route('shop.product', props.product.slug));
        return;
    }
    isAdding.value = true;
    cartError.value = '';
    addToCart(props.product.id, {
        onFinish: () => { isAdding.value = false; },
        onError: (errors) => {
            cartError.value = errors?.message || errors?.product_id || Object.values(errors || {})[0] || 'Errore durante l\'aggiunta al carrello';
            clearCartError();
        },
    });
};
</script>

<template>
    <div
        class="group relative bg-gray-900 rounded-xl overflow-hidden shadow-md hover:shadow-2xl transition-all duration-500 transform hover:scale-[1.02] flex flex-col"
    >
        <!-- Product Link wraps image + info -->
        <Link
            :href="route('shop.product', product.slug)"
            class="flex flex-col flex-grow"
        >
            <!-- Product Image -->
            <div class="relative aspect-[3/4] bg-gray-800 overflow-hidden">
                <img
                    v-if="product.image_url"
                    :src="product.image_url"
                    :alt="product.name"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                    loading="lazy"
                    @error="onImgError"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                    <svg class="w-16 h-16 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>

                <!-- "Nuovo" Badge -->
                <div
                    v-if="product.is_new"
                    class="absolute top-3 left-3 bg-savino-gold text-gray-900 text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full shadow-lg"
                >
                    {{ $t('shop.badge_new') || 'Nuovo' }}
                </div>

                <!-- Sale Badge -->
                <div
                    v-if="hasSalePrice && !isOutOfStock"
                    class="absolute top-3 right-3 bg-savino-red text-white text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full shadow-lg"
                >
                    {{ $t('shop.sale') || 'Saldi' }}
                </div>

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
                    <span class="text-white text-xs font-bold uppercase tracking-wider border-2 border-savino-gold px-5 py-2.5 hover:bg-savino-gold hover:text-gray-900 transition-colors">
                        {{ $t('shop.product_details') || 'Dettagli' }}
                    </span>
                </div>
            </div>

            <!-- Product Info -->
            <div class="p-5 flex flex-col flex-grow">
                <!-- Category -->
                <span
                    v-if="product.category"
                    class="text-savino-gold/70 text-[10px] font-bold uppercase tracking-[0.2em] mb-2"
                >
                    {{ typeof product.category === 'string' ? product.category : product.category.name }}
                </span>

                <!-- Product Name -->
                <h3 class="text-white font-bold text-base mb-3 leading-tight group-hover:text-savino-gold transition-colors duration-300 line-clamp-2">
                    {{ product.name }}
                </h3>

                <!-- Price -->
                <div class="mt-auto flex items-baseline gap-2">
                    <span
                        v-if="hasSalePrice"
                        class="text-gray-500 text-sm line-through"
                    >
                        {{ formatPrice(product.price) }}
                    </span>
                    <span class="text-savino-red font-black text-xl">
                        {{ formatPrice(hasSalePrice ? product.sale_price : product.price) }}
                    </span>
                </div>
            </div>
        </Link>

        <!-- Add to Cart Button -->
        <div class="px-5 pb-5" v-if="!isOutOfStock">
            <button type="button"
                @click.prevent="handleAddToCart"
                :disabled="isAdding"
                class="w-full bg-savino-gold/10 border border-savino-gold/30 text-savino-gold text-xs font-bold uppercase tracking-wider py-3 rounded-lg hover:bg-savino-gold hover:text-gray-900 transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg v-if="isAdding" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                {{ $t('shop.add_to_cart') || 'Aggiungi al carrello' }}
            </button>
            <p v-if="cartError" class="text-red-500 text-xs mt-2 text-center">
                {{ cartError }}
            </p>
        </div>
    </div>
</template>
