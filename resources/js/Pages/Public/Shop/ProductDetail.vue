<script setup>\nimport { useTranslations } from '@/Composables/useTranslations.js';
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useCart } from '@/Composables/useCart.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';
import ProductCard from '@/Components/Shop/ProductCard.vue';
import CartDrawer from '@/Components/Shop/CartDrawer.vue';

const $t = useTranslations();

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

// --- Variant Selector ---
const selectedVariant = ref(null);

const activeVariant = computed(() => {
    if (selectedVariant.value && props.product?.variants?.length) {
        return props.product.variants.find((v) => v.id === selectedVariant.value) || null;
    }
    return null;
});

// --- Price ---
const displayPrice = computed(() => {
    if (activeVariant.value?.price) return activeVariant.value.price;
    return props.product?.sale_price ?? props.product?.price ?? 0;
});

const originalPrice = computed(() => {
    if (props.product?.sale_price && props.product?.price > props.product?.sale_price) {
        return props.product.price;
    }
    return null;
});

const hasSale = computed(() => {
    return !activeVariant.value && originalPrice.value !== null;
});

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

// --- Add to Cart ---
const isAdding = ref(false);
const handleAddToCart = async () => {
    if (isOutOfStock.value || isAdding.value) return;
    isAdding.value = true;
    try {
        await addToCart({
            product_id: props.product.id,
            variant_id: selectedVariant.value,
            quantity: quantity.value,
        });
    } finally {
        isAdding.value = false;
    }
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
                    <Link :href="route('home')" class="hover:text-savino-gold transition-colors">Home</Link>
                    <span>/</span>
                    <Link :href="route('shop.index')" class="hover:text-savino-gold transition-colors">Shop</Link>
                    <template v-if="product?.category">
                        <span>/</span>
                        <Link :href="route('shop.category', product.category.slug)" class="hover:text-savino-gold transition-colors">{{ product.category.name }}</Link>
                    </template>
                    <span>/</span>
                    <span class="text-savino-gold">{{ product?.name }}</span>
                </nav>
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ product?.category?.name ?? $t('shop.hero_label') }}</span>
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ product?.name }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4"></div>
            </div>
        </section>

        <!-- PRODUCT DETAIL -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16">
                    <!-- LEFT: Image Gallery -->
                    <div>
                        <!-- Main Image -->
                        <div class="relative aspect-square bg-gray-50 rounded-2xl overflow-hidden shadow-lg mb-4">
                            <img
                                v-if="mainImage"
                                :src="mainImage"
                                :alt="product?.name"
                                class="w-full h-full object-cover transition-opacity duration-300"
                                @error="onImgError"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <svg class="w-20 h-20 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                            </div>
                            <!-- Sale Badge -->
                            <div v-if="hasSale" class="absolute top-4 left-4 bg-savino-red text-white text-xs font-bold uppercase px-3 py-1.5 rounded-full shadow-lg">
                                {{ $t('shop.sale') }}
                            </div>
                            <!-- Out of Stock Overlay -->
                            <div v-if="isOutOfStock" class="absolute inset-0 bg-black/40 flex items-center justify-center">
                                <span class="bg-white text-gray-900 font-bold text-lg px-6 py-3 rounded-full">{{ $t('shop.out_of_stock') }}</span>
                            </div>
                        </div>
                        <!-- Thumbnail Strip -->
                        <div v-if="product?.images?.length > 1" class="flex gap-3 overflow-x-auto pb-2">
                            <button
                                v-for="(img, index) in product.images"
                                :key="index"
                                @click="selectImage(index)"
                                class="flex-shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 transition-all duration-200 hover:opacity-100"
                                :class="selectedImageIndex === index ? 'border-savino-gold shadow-md opacity-100' : 'border-gray-200 opacity-60'"
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

                        <!-- Price -->
                        <div class="flex items-baseline gap-3 mb-6">
                            <span class="text-3xl font-black text-savino-red">{{ formatPrice(displayPrice) }}</span>
                            <span v-if="hasSale" class="text-lg text-gray-400 line-through">{{ formatPrice(originalPrice) }}</span>
                        </div>

                        <!-- Short Description -->
                        <p v-if="product?.description" class="text-gray-600 leading-relaxed mb-8">
                            {{ product.description }}
                        </p>

                        <!-- Variant Selector -->
                        <div v-if="product?.variants?.length" class="mb-6">
                            <label class="block text-sm font-bold text-savino-blue uppercase tracking-wider mb-3">{{ $t('shop.select_variant') }}</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="variant in product.variants"
                                    :key="variant.id"
                                    @click="selectedVariant = variant.id"
                                    class="px-5 py-2.5 rounded-lg border-2 text-sm font-semibold transition-all duration-200"
                                    :class="selectedVariant === variant.id
                                        ? 'border-savino-gold bg-savino-gold/10 text-savino-blue'
                                        : 'border-gray-200 text-gray-600 hover:border-savino-blue/30'"
                                    :disabled="variant.stock <= 0"
                                >
                                    {{ variant.name }}
                                    <span v-if="variant.stock <= 0" class="ml-1 text-xs text-gray-400">({{ $t('shop.out_of_stock') }})</span>
                                </button>
                            </div>
                        </div>

                        <!-- Quantity Selector -->
                        <div class="mb-8">
                            <label class="block text-sm font-bold text-savino-blue uppercase tracking-wider mb-3">{{ $t('shop.quantity') }}</label>
                            <div class="inline-flex items-center border-2 border-gray-200 rounded-lg overflow-hidden">
                                <button
                                    @click="decrementQty"
                                    :disabled="quantity <= 1"
                                    class="w-12 h-12 flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                                </button>
                                <span class="w-16 h-12 flex items-center justify-center text-lg font-bold text-savino-blue border-x-2 border-gray-200">
                                    {{ quantity }}
                                </span>
                                <button
                                    @click="incrementQty"
                                    :disabled="quantity >= currentStock"
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
                        <button
                            @click="handleAddToCart"
                            :disabled="isOutOfStock || isAdding"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-10 py-4 rounded-xl text-white font-bold uppercase tracking-wider text-sm transition-all duration-300 shadow-lg"
                            :class="isOutOfStock
                                ? 'bg-gray-300 cursor-not-allowed shadow-none'
                                : 'bg-savino-blue hover:bg-savino-gold hover:text-savino-blue hover:shadow-xl transform hover:-translate-y-0.5'"
                        >
                            <svg v-if="!isAdding" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                            <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            {{ isOutOfStock ? $t('shop.out_of_stock') : $t('shop.add_to_cart') }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- LONG DESCRIPTION -->
        <section v-if="product?.long_description" class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-4xl mx-auto">
                <h3 class="text-2xl font-black text-savino-blue uppercase tracking-tight mb-8">{{ $t('shop.description') }}</h3>
                <div class="w-12 h-1 bg-savino-gold mb-8"></div>
                <div class="prose prose-lg max-w-none text-gray-700 prose-headings:text-savino-blue prose-a:text-savino-blue" v-html="product.long_description"></div>
            </div>
        </section>

        <!-- RELATED PRODUCTS -->
        <section v-if="relatedProducts.length > 0" class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-12">
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ $t('shop.discover') }}</span>
                    <h3 class="text-3xl font-black text-savino-blue uppercase tracking-tighter mt-2">{{ $t('shop.related_products') }}</h3>
                    <div class="w-16 h-1 bg-savino-gold mx-auto mt-4"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    <ProductCard v-for="rp in relatedProducts" :key="rp.id" :product="rp" />
                </div>
            </div>
        </section>

        <!-- Cart Drawer -->
        <CartDrawer />
    </PublicLayout>
</template>
