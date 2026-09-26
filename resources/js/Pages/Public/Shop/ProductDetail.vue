<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { useSanitize } from '@/Composables/useSanitize.js';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AvvisoGaranziaLegale from '@/Components/Shop/AvvisoGaranziaLegale.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useCart } from '@/Composables/useCart.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';
import ProductCard from '@/Components/Shop/ProductCard.vue';
import EtichetteProdotto from '@/Components/Shop/EtichetteProdotto.vue';
import { trackViewContent, trackAddToCart } from '@/meta-pixel.js';


const $t = useTranslations();

const { sanitize } = useSanitize();

const { addToCart } = useCart();
const { formatPrice } = useFormatPrice();
const { onImgError } = useImageFallback();

const props = defineProps({
    product: Object,
    relatedProducts: {
        type: Array,
        default: () => [],
    },
});

const ogMeta = useOgMeta({
    title: props.product?.name ?? $t('shop.product_detail'),
    description: props.product?.description || $t('shop.og_description'),
    image: props.product?.images?.[0] || null,
});

// --- Image Gallery ---
const selectedImageIndex = ref(0);
const mainImage = computed(() => {
    if (props.product?.images?.length) {
        return props.product.images[selectedImageIndex.value];
    }
    return null;
});

const selectImage = (index) => {
    selectedImageIndex.value = index;
};

// --- Image Zoom on Hover ---
const isZoomed = ref(false);
const zoomStyle = ref({});

const handleMouseMove = (e) => {
    if (!isZoomed.value) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    zoomStyle.value = {
        transformOrigin: `${x}% ${y}%`,
        transform: 'scale(2)',
    };
};

const handleMouseEnter = () => {
    isZoomed.value = true;
};

const handleMouseLeave = () => {
    isZoomed.value = false;
    zoomStyle.value = {};
};

// --- Touch Device Detection ---
const isTouchDevice = ref(false);
onMounted(() => {
    isTouchDevice.value = window.matchMedia('(hover: none)').matches;

    trackViewContent({
        id: props.product.id,
        name: props.product.name,
        // Il prezzo che si paga, non il barrato (`price` con uno sconto
        // annunciabile e' il riferimento dei 30 giorni).
        value: displayPrice.value,
    });
});

// --- Variant Selector ---
// Con una sola taglia disponibile chiedere di sceglierla è solo un ostacolo:
// si preseleziona, e il messaggio di validazione resta per i casi veri.
const availableVariants = (props.product?.variants ?? []).filter(v => v.stock > 0);
const selectedVariant = ref(availableVariants.length === 1 ? availableVariants[0].id : null);

const activeVariant = computed(() => {
    if (selectedVariant.value && props.product?.variants?.length) {
        return props.product.variants.find((v) => v.id === selectedVariant.value) || null;
    }
    return null;
});

// --- Price ---
// Il barrato lo decide il backend (ShopController::prezzi): con il flag acceso
// `price` e' il prezzo piu' basso dei 30 giorni prima dello sconto e
// `sale_price` quello che si paga; spento, `price` e' gia' il prezzo da pagare.
const scontoAnnunciabile = computed(() => props.product?.prezzo_piu_basso_30_giorni === true
    && props.product?.sale_price != null);

const modificatoreVariante = computed(() => Number.parseFloat(activeVariant.value?.price_modifier || 0) || 0);

// --- Personalizzazione (di solito la firma della giocatrice) ---
// Facoltativa, con un supplemento: non e' una taglia e non ha giacenza propria.
const conPersonalizzazione = ref(false);
const supplemento = computed(() => (
    conPersonalizzazione.value && props.product?.personalizzazione
        ? Number.parseFloat(props.product.personalizzazione.prezzo || 0)
        : 0
));

const displayPrice = computed(() => {
    const basePrice = scontoAnnunciabile.value ? props.product.sale_price : (props.product?.price ?? 0);
    return Number.parseFloat(basePrice) + modificatoreVariante.value + supplemento.value;
});

// La firma e' un'aggiunta con il suo prezzo fisso, non scontata: il barrato
// resta quello del prodotto con lo stesso supplemento sopra, cosi' spuntarla
// non fa sparire l'annuncio dello sconto.
const originalPrice = computed(() => (scontoAnnunciabile.value
    ? Number.parseFloat(props.product.price) + supplemento.value
    : null));

// Il riferimento dei 30 giorni vale per il prezzo del prodotto: resta anche
// dopo la scelta di una taglia che non cambia il prezzo. Una variante con un
// sovrapprezzo ha un prezzo suo, per cui quel riferimento non e' calcolato.
const hasSale = computed(() => scontoAnnunciabile.value && modificatoreVariante.value === 0);

// --- Stock ---
const currentStock = computed(() => {
    if (activeVariant.value) return activeVariant.value.stock ?? 0;
    return props.product?.stock ?? 0;
});

const isOutOfStock = computed(() => currentStock.value <= 0);


// --- Quantity ---
const quantity = ref(1);

const decrementQty = () => {
    if (quantity.value > 1) quantity.value--;
};

const incrementQty = () => {
    if (quantity.value < currentStock.value) quantity.value++;
};

// Prima di scegliere la taglia la giacenza è la somma di tutte le varianti:
// otto pezzi scelti e poi una taglia che ne ha due davano errore solo dal server.
watch(currentStock, (stock) => {
    if (quantity.value > stock) quantity.value = Math.max(1, stock);
});

// --- Add to Cart ---
const isAdding = ref(false);
const cartError = ref('');
const variantError = ref(false);
let cartErrorTimer = null;
let variantErrorTimer = null;

const clearCartError = () => {
    if (cartErrorTimer) clearTimeout(cartErrorTimer);
    cartErrorTimer = setTimeout(() => { cartError.value = ''; }, 5000);
};

// Cleanup on unmount
onUnmounted(() => {
    if (cartErrorTimer) clearTimeout(cartErrorTimer);
    if (variantErrorTimer) clearTimeout(variantErrorTimer);
});

const handleAddToCart = () => {
    if (isOutOfStock.value || isAdding.value) return;
    if (props.product?.variants?.length > 0 && !selectedVariant.value) {
        variantError.value = true;
        variantErrorTimer = setTimeout(() => { variantError.value = false; }, 3000);
        return;
    }
    variantError.value = false;
    isAdding.value = true;
    cartError.value = '';
    // Prima della chiamata e non nella callback: `addToCart` naviga via Inertia
    // e il componente può essere già smontato quando la risposta arriva.
    trackAddToCart({
        id: props.product.id,
        name: props.product.name,
        // Taglia e firma comprese: e' il valore che il carrello fa pagare.
        value: displayPrice.value * quantity.value,
        quantity: quantity.value,
    });
    addToCart({
        product_id: props.product.id,
        variant_id: selectedVariant.value,
        quantity: quantity.value,
        personalizzazione: conPersonalizzazione.value,
    }, {
        onFinish: () => { isAdding.value = false; },
        onError: (errors) => {
            cartError.value = errors?.message || errors?.product_id || Object.values(errors || {})[0] || $t('shop.cart_error_generic');
            clearCartError();
        },
    });
};

// --- JSON-LD ---
const structuredData = computed(() => {
    const p = props.product;
    if (!p) return {};
    const baseUrl = usePage().props.ziggy?.url ?? '';
    return {
        '@context': 'https://schema.org',
        '@type': 'Product',
        'name': p.name,
        'description': p.description,
        'image': p.images ?? [],
        'sku': p.slug,
        'offers': {
            '@type': 'Offer',
            'url': `${baseUrl}/shop/${p.slug}`,
            'priceCurrency': 'EUR',
            'price': displayPrice.value,
            'availability': isOutOfStock.value
                ? 'https://schema.org/OutOfStock'
                : 'https://schema.org/InStock',
        },
    };
});
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
        <meta property="og:title" :content="ogMeta.title" />
        <meta property="og:description" :content="ogMeta.description" />
        <meta property="og:image" :content="ogMeta.image" />
        <meta property="og:url" :content="ogMeta.url" />
        <meta property="og:type" content="product" />
        <component :is="'script'" type="application/ld+json">
            {{ JSON.stringify(structuredData) }}
        </component>
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[25vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.05]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <!-- Breadcrumb -->
                <nav class="flex items-center justify-center gap-2 text-sm text-white/60 mb-6">
                    <Link :href="route('home')" class="hover:text-savino-fucsia-chiaro transition-colors">{{ $t('common.home') }}</Link>
                    <span>/</span>
                    <Link :href="route('shop')" class="hover:text-savino-fucsia-chiaro transition-colors">{{ $t('common.shop') }}</Link>
                    <template v-if="product?.category">
                        <span>/</span>
                        <Link :href="route('shop.category', product.category.slug)" class="hover:text-savino-fucsia-chiaro transition-colors">{{ product.category.name }}</Link>
                    </template>
                    <span>/</span>
                    <span class="text-savino-fucsia-chiaro">{{ product?.name }}</span>
                </nav>
                <span class="text-savino-fucsia-chiaro text-sm font-bold uppercase tracking-[0.3em]">{{ product?.category?.name ?? $t('shop.hero_label') }}</span>
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ product?.name }}
                </h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4"></div>
            </div>
        </section>

        <!-- PRODUCT DETAIL -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16">
                    <!-- LEFT: Image Gallery -->
                    <div>
                        <!-- Main Image -->
                        <div
                            class="relative aspect-square bg-gray-50 rounded-2xl overflow-hidden shadow-lg mb-4"
                            :class="{ 'cursor-zoom-in': !isTouchDevice }"
                            @mouseenter="!isTouchDevice && handleMouseEnter()"
                            @mouseleave="!isTouchDevice && handleMouseLeave()"
                            @mousemove="!isTouchDevice && handleMouseMove($event)"
                        >
                            <img
                                v-if="mainImage"
                                :src="mainImage"
                                :alt="product?.name"
                                class="w-full h-full object-cover transition-all duration-300"
                                :style="zoomStyle"
                                @error="onImgError"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <svg class="w-20 h-20 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                            </div>
                            <!-- Etichette scelte dalla redazione (EtichetteDelProdotto) -->
                            <EtichetteProdotto :etichette="product?.etichette" grandi />
                            <!-- Out of Stock Overlay -->
                            <div v-if="isOutOfStock" class="absolute inset-0 bg-black/40 flex items-center justify-center">
                                <span class="bg-white text-gray-900 font-bold text-lg px-6 py-3 rounded-full">{{ $t('shop.out_of_stock') }}</span>
                            </div>
                        </div>
                        <!-- Thumbnail Strip -->
                        <div v-if="product?.images?.length > 1" class="flex gap-3 overflow-x-auto pb-2">
                            <button type="button"
                                v-for="(img, index) in product.images"
                                :key="index"
                                @click="selectImage(index)"
                                class="flex-shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 transition-all duration-200 hover:opacity-100"
                                :class="selectedImageIndex === index ? 'border-savino-fucsia shadow-md opacity-100' : 'border-gray-200 opacity-60'"
                            >
                                <img :src="img" :alt="`${product.name} - ${index + 1}`" class="w-full h-full object-cover" @error="onImgError" />
                            </button>
                        </div>
                    </div>

                    <!-- RIGHT: Product Info -->
                    <div class="flex flex-col">
                        <!-- Category Badge -->
                        <div v-if="product?.category" class="mb-4">
                            <Link
                                :href="route('shop.category', product.category.slug)"
                                class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-savino-blue bg-savino-blue/5 border border-savino-blue/10 px-3 py-1.5 rounded-full hover:bg-savino-blue/10 transition-colors"
                            >
                                {{ product.category.name }}
                            </Link>
                        </div>

                        <!-- Product Name -->
                        <h2 class="text-2xl md:text-3xl font-black text-savino-blue uppercase tracking-tight mb-4">
                            {{ product?.name }}
                        </h2>

                        <!-- Price: il barrato è il prezzo più basso dei 30 giorni prima
                             dello sconto (art. 17-bis Codice del consumo), e va detto
                             accanto: senza la didascalia il cliente lo leggerebbe come
                             il listino. -->
                        <div class="mb-6">
                            <div class="flex items-baseline gap-3">
                                <span class="text-3xl font-black text-savino-red">{{ formatPrice(displayPrice) }}</span>
                                <span v-if="hasSale" class="text-lg text-gray-600 line-through">{{ formatPrice(originalPrice) }}</span>
                            </div>
                            <p v-if="hasSale" class="text-sm text-gray-600 mt-1">
                                {{ $t('shop.lowest_price_30_days', { price: formatPrice(originalPrice) }) }}
                            </p>
                        </div>

                        <!-- Short Description -->
                        <div v-if="product?.description" class="text-gray-600 leading-relaxed mb-8 prose prose-sm max-w-none" v-html="sanitize(product.description)"></div>

                        <!-- Variant Selector -->
                        <div v-if="product?.variants?.length" :class="['mb-6 transition-all duration-300', variantError ? 'ring-2 ring-red-400 rounded-xl p-3 bg-red-50/50' : '']">
                            <span class="block text-sm font-bold text-savino-blue uppercase tracking-wider mb-3">{{ $t('shop.select_variant') }}</span>
                            <div class="flex flex-wrap gap-2">
                                <button type="button"
                                    v-for="variant in product.variants"
                                    :key="variant.id"
                                    @click="selectedVariant = variant.id"
                                    class="px-5 py-2.5 rounded-lg border-2 text-sm font-semibold transition-all duration-200"
                                    :class="selectedVariant === variant.id
                                        ? 'border-savino-fucsia bg-savino-fucsia/10 text-savino-blue'
                                        : 'border-gray-200 text-gray-600 hover:border-savino-blue/30'"
                                    :disabled="variant.stock <= 0"
                                >
                                    {{ variant.size }}{{ variant.color ? ` — ${variant.color}` : '' }}
                                    <span v-if="variant.stock <= 0" class="ml-1 text-xs text-gray-400">({{ $t('shop.out_of_stock') }})</span>
                                </button>
                            </div>
                            <p v-if="variantError" class="text-sm text-red-500 font-medium mt-2 flex items-center gap-1.5 animate-pulse">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                {{ $t('shop.select_variant_required') }}
                            </p>
                        </div>

                        <!-- Guida alle taglie: il documento scelto in redazione,
                             o la pagina generale. Senza, la voce non compare. -->
                        <a v-if="product?.size_guide_url && product?.variants?.some(v => v.size)" :href="product.size_guide_url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm text-savino-blue hover:text-savino-fucsia transition-colors mt-3">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                            {{ $t('shop.size_guide') }}
                        </a>

                        <!-- Personalizzazione facoltativa. L'avviso sul recesso sta
                             accanto alla casella e le è legato: chi la spunta deve
                             sapere prima che l'articolo non si restituisce
                             (art. 59 c. 1 lett. c del Codice del consumo). -->
                        <label
                            v-if="product?.personalizzazione"
                            class="mt-6 flex items-center gap-3 cursor-pointer rounded-xl border-2 px-4 py-3 transition-colors"
                            :class="conPersonalizzazione ? 'border-savino-fucsia bg-savino-fucsia/10' : 'border-gray-200 hover:border-savino-blue/30'"
                        >
                            <input
                                v-model="conPersonalizzazione"
                                type="checkbox"
                                aria-describedby="personalizzazione-recesso"
                                class="h-5 w-5 rounded border-gray-300 text-savino-fucsia focus:ring-savino-fucsia"
                            />
                            <span class="text-sm font-semibold text-savino-blue">
                                {{ $t('shop.personalization_add', { name: product.personalizzazione.nome }) }}
                            </span>
                            <!-- Sul fondo fucsia chiaro della casella spuntata il
                                 rosso del token arriva a 4,4:1: serve #B8066A (§11). -->
                            <span class="ml-auto text-sm font-bold whitespace-nowrap" :class="conPersonalizzazione ? 'text-[#B8066A]' : 'text-savino-red'">
                                {{ Number(product.personalizzazione.prezzo) > 0
                                    ? $t('shop.personalization_surcharge', { price: formatPrice(product.personalizzazione.prezzo) })
                                    : $t('shop.personalization_included') }}
                            </span>
                        </label>
                        <p v-if="product?.personalizzazione" id="personalizzazione-recesso" class="mt-2 mb-6 text-xs text-gray-600">
                            {{ $t('shop.personalization_no_withdrawal') }}
                        </p>

                        <!-- Quantity Selector -->
                        <div class="mb-8">
                            <span class="block text-sm font-bold text-savino-blue uppercase tracking-wider mb-3">{{ $t('shop.quantity') }}</span>
                            <div class="inline-flex items-center border-2 border-gray-200 rounded-lg overflow-hidden">
                                <button type="button"
                                    @click="decrementQty"
                                    :disabled="quantity <= 1"
                                    :aria-label="$t('shop.decrease_quantity')"
                                    class="w-12 h-12 flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                                </button>
                                <span class="w-16 h-12 flex items-center justify-center text-lg font-bold text-savino-blue border-x-2 border-gray-200">
                                    {{ quantity }}
                                </span>
                                <button type="button"
                                    @click="incrementQty"
                                    :disabled="quantity >= currentStock"
                                    :aria-label="$t('shop.increase_quantity')"
                                    class="w-12 h-12 flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                </button>
                            </div>
                            <span v-if="currentStock > 0 && currentStock <= 5" class="ml-3 text-sm text-amber-600 font-medium">
                                {{ $t('shop.low_stock', { count: currentStock }) }}
                            </span>
                        </div>

                        <!-- Add to Cart Button -->
                        <button type="button"
                            @click="handleAddToCart"
                            :disabled="isOutOfStock || isAdding"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-10 py-4 rounded-xl text-white font-bold uppercase tracking-wider text-sm transition-all duration-300 shadow-lg"
                            :class="isOutOfStock
                                ? 'bg-gray-300 cursor-not-allowed shadow-none'
                                : 'bg-savino-blue hover:bg-savino-fucsia hover:text-white hover:shadow-xl transform hover:-translate-y-0.5'"
                        >
                            <svg v-if="!isAdding" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                            <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            {{ isOutOfStock ? $t('shop.out_of_stock') : $t('shop.add_to_cart') }}
                        </button>
                        <p v-if="cartError" class="text-red-500 text-sm mt-3 font-medium">
                            {{ cartError }}
                        </p>

                        <!-- Trust Badges -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div class="flex flex-col items-center gap-1.5">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                    <span class="text-xs font-medium text-gray-600">{{ $t('shop.trust_secure') }}</span>
                                </div>
                                <div class="flex flex-col items-center gap-1.5">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                    <Link :href="route('pages.show', 'spedizioni')" class="text-xs font-medium text-gray-600 underline decoration-dotted underline-offset-2 hover:text-savino-blue">{{ $t('shop.trust_shipping') }}</Link>
                                </div>
                                <!-- Il reso ha una regola scritta dietro: 14 giorni dalla
                                     consegna, spese di restituzione a carico del cliente,
                                     esclusi i prodotti personalizzati. Prima diceva
                                     "Reso Facile" e non portava da nessuna parte. -->
                                <a :href="route('pages.show', 'diritto-di-recesso')" target="_blank" rel="noopener noreferrer" class="flex flex-col items-center gap-1.5 group">
                                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                                    <span class="text-xs font-medium text-gray-600 underline decoration-dotted underline-offset-2 group-hover:text-savino-blue">{{ $t('shop.trust_returns') }}</span>
                                </a>
                            </div>
                            <!-- Avviso UE sulla garanzia legale (Reg. 2025/1960): in scheda
                                 prodotto, raggiungibile con un click prima dell'acquisto. -->
                            <div class="mt-4 text-center text-xs text-gray-600"><AvvisoGaranziaLegale /></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- LONG DESCRIPTION -->
        <section v-if="product?.long_description" class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-4xl mx-auto">
                <h3 class="text-2xl font-black text-savino-blue uppercase tracking-tight mb-8">{{ $t('shop.description') }}</h3>
                <div class="w-12 h-1 bg-savino-fucsia mb-8"></div>
                <div class="prose prose-lg max-w-none text-gray-700 prose-headings:text-savino-blue prose-a:text-savino-blue" v-html="sanitize(product.long_description)"></div>
            </div>
        </section>

        <!-- RELATED PRODUCTS -->
        <section v-if="relatedProducts.length > 0" class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-12">
                    <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ $t('shop.discover') }}</span>
                    <h3 class="text-3xl font-black text-savino-blue uppercase tracking-tighter mt-2">{{ $t('shop.related_products') }}</h3>
                    <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    <ProductCard v-for="rp in relatedProducts" :key="rp.id" :product="rp" />
                </div>
            </div>
        </section>


    </PublicLayout>
</template>
