<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { ref, computed, onUnmounted, watch } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import ProductCard from '@/Components/Shop/ProductCard.vue';
import ProductCardSkeleton from '@/Components/Shop/ProductCardSkeleton.vue';

const $t = useTranslations();

// --- Skeleton loading state ---
const isNavigating = ref(false);
const removeStartListener = router.on('start', () => { isNavigating.value = true; });
const removeFinishListener = router.on('finish', () => { isNavigating.value = false; });
onUnmounted(() => {
    removeStartListener();
    removeFinishListener();
});

const props = defineProps({
    allProducts: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    announcementBanner: {
        type: String,
        default: null,
    },
});

const ogMeta = useOgMeta({
    title: $t('shop.og_title'),
    description: $t('shop.og_description'),
});

// --- Search & Filter ---
const searchQuery = ref('');
const selectedCategory = ref(null);
const selectedSort = ref('');

const filteredProducts = computed(() => {
    let products = props.allProducts;

    // Filter by category
    if (selectedCategory.value) {
        products = products.filter(p => p.category?.id === selectedCategory.value);
    }

    // Filter by search query (name only — descriptions are not in the card payload)
    const q = searchQuery.value.trim().toLowerCase();
    if (q.length >= 2) {
        products = products.filter(p =>
            p.name?.toLowerCase().includes(q)
        );
    }

    // Sort
    if (selectedSort.value) {
        products = [...products];
        switch (selectedSort.value) {
            case 'price_asc':
                products.sort((a, b) => (a.sale_price || a.price) - (b.sale_price || b.price));
                break;
            case 'price_desc':
                products.sort((a, b) => (b.sale_price || b.price) - (a.sale_price || a.price));
                break;
            case 'newest':
                products.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                break;
        }
    }

    return products;
});

const clearFilters = () => {
    searchQuery.value = '';
    selectedCategory.value = null;
    selectedSort.value = '';
    visibleCount.value = itemsPerPage;
};

const hasActiveFilters = computed(() => searchQuery.value.trim().length >= 2 || selectedCategory.value !== null || selectedSort.value !== '');

// --- Client-side pagination ---
const itemsPerPage = 12;
const visibleCount = ref(itemsPerPage);

// Reset visible count when filters change
watch([searchQuery, selectedCategory, selectedSort], () => {
    visibleCount.value = itemsPerPage;
});

const visibleProducts = computed(() => filteredProducts.value.slice(0, visibleCount.value));
const hasMoreProducts = computed(() => visibleCount.value < filteredProducts.value.length);

const loadMore = () => {
    visibleCount.value += itemsPerPage;
};
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
        <meta property="og:title" :content="ogMeta.title" />
        <meta property="og:description" :content="ogMeta.description" />
        <meta property="og:image" :content="ogMeta.image" />
        <meta property="og:url" :content="ogMeta.url" />
        <meta property="og:type" :content="ogMeta.type" />
        <component :is="'script'" type="application/ld+json">
            {{ JSON.stringify({
                '@context': 'https://schema.org',
                '@type': 'BreadcrumbList',
                'itemListElement': [
                    { '@type': 'ListItem', 'position': 1, 'name': 'Home', 'item': usePage().props.ziggy.url },
                    { '@type': 'ListItem', 'position': 2, 'name': 'Shop' },
                ],
            }) }}
        </component>
    </Head>

    <PublicLayout>
        <!-- ANNOUNCEMENT BANNER -->
        <div v-if="announcementBanner" class="bg-savino-gold text-gray-900 text-center py-3 px-4 font-bold text-sm">
            {{ announcementBanner }}
        </div>

        <!-- HERO SECTION -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.05]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ $t('shop.hero_label') }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ $t('shop.og_title') }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">
                    {{ $t('shop.hero_description') }}
                </p>
            </div>
        </section>

        <!-- SEARCH & FILTERS + PRODUCTS GRID -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">

                <!-- Search Bar -->
                <div class="max-w-2xl mx-auto mb-10">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            :aria-label="$t('shop.search_placeholder') || 'Search products'"
                            :placeholder="$t('shop.search_placeholder') || 'Cerca prodotti...'"
                            class="w-full border-2 border-gray-200 rounded-xl pl-12 pr-12 py-4 text-lg text-savino-blue placeholder-gray-400 focus:ring-2 focus:ring-savino-gold/50 focus:border-savino-gold outline-none transition-all"
                        />
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        <!-- Clear button -->
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            :aria-label="$t('shop.clear_search') || 'Clear search'"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-savino-blue transition-colors"
                        >
                            <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Category Filter Pills -->
                <div v-if="categories.length > 1" role="group" :aria-label="$t('shop.category_filter') || 'Category filter'" class="flex flex-wrap items-center justify-center gap-2 mb-10">
                    <button
                        @click="selectedCategory = null"
                        :aria-pressed="selectedCategory === null"
                        class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all duration-200 border"
                        :class="selectedCategory === null
                            ? 'bg-savino-blue text-white border-savino-blue shadow-md'
                            : 'bg-white text-gray-600 border-gray-200 hover:border-savino-blue/30 hover:text-savino-blue'"
                    >
                        {{ $t('shop.all_categories') || 'Tutti' }}
                        <span class="ml-1.5 text-xs opacity-70">({{ allProducts.length }})</span>
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        @click="selectedCategory = selectedCategory === cat.id ? null : cat.id"
                        :aria-pressed="selectedCategory === cat.id"
                        class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all duration-200 border"
                        :class="selectedCategory === cat.id
                            ? 'bg-savino-blue text-white border-savino-blue shadow-md'
                            : 'bg-white text-gray-600 border-gray-200 hover:border-savino-blue/30 hover:text-savino-blue'"
                    >
                        {{ cat.name }}
                        <span v-if="cat.products_count" class="ml-1.5 text-xs opacity-70">({{ cat.products_count }})</span>
                    </button>
                </div>

                <!-- Result count + Sort + Clear filters -->
                <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
                    <p class="text-gray-500 text-sm">
                        <span class="font-semibold text-savino-blue">{{ filteredProducts.length }}</span>
                        {{ filteredProducts.length === 1 ? ($t('shop.product_singular') || 'prodotto') : ($t('shop.products_found') || 'prodotti') }}
                    </p>
                    <div class="flex items-center gap-3">
                        <select
                            v-model="selectedSort"
                            :aria-label="$t('shop.sort_by') || 'Ordina per'"
                            class="text-sm border border-gray-200 rounded-lg px-3 py-2 text-gray-700 bg-white focus:ring-2 focus:ring-savino-gold/50 focus:border-savino-gold outline-none transition-all cursor-pointer"
                        >
                            <option value="">{{ $t('shop.sort_default') || 'Ordina per' }}</option>
                            <option value="price_asc">{{ $t('shop.sort_price_asc') || 'Prezzo: basso → alto' }}</option>
                            <option value="price_desc">{{ $t('shop.sort_price_desc') || 'Prezzo: alto → basso' }}</option>
                            <option value="newest">{{ $t('shop.sort_newest') || 'Più recenti' }}</option>
                        </select>
                        <button
                            v-if="hasActiveFilters"
                            @click="clearFilters"
                            class="text-sm text-savino-blue/70 hover:text-savino-blue font-medium flex items-center gap-1.5 transition-colors"
                        >
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            {{ $t('shop.clear_filters') || 'Rimuovi filtri' }}
                        </button>
                    </div>
                </div>

                <!-- Skeleton Loading -->
                <div v-if="isNavigating" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8" role="list" aria-label="Caricamento prodotti">
                    <ProductCardSkeleton v-for="n in 8" :key="'skeleton-' + n" role="listitem" />
                </div>

                <!-- Products Grid -->
                <transition-group
                    v-else-if="filteredProducts.length > 0"
                    name="product-fade"
                    tag="div"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8"
                    role="list"
                    :aria-label="$t('shop.products_list') || 'Elenco prodotti'"
                >
                    <ProductCard v-for="product in visibleProducts" :key="product.id" :product="product" role="listitem" />
                </transition-group>

                <!-- Load More Button -->
                <div v-if="hasMoreProducts && !isNavigating" class="text-center mt-12">
                    <button
                        @click="loadMore"
                        class="inline-flex items-center gap-2 bg-white text-savino-blue font-bold uppercase tracking-wider text-sm px-10 py-4 rounded-xl border-2 border-savino-blue hover:bg-savino-blue hover:text-white transition-all duration-300 shadow-md hover:shadow-xl"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        {{ $t('shop.load_more') }}
                        <span class="text-xs opacity-70">({{ filteredProducts.length - visibleCount }} {{ $t('shop.remaining') }})</span>
                    </button>
                </div>

                <!-- Empty State: no results from filter/search -->
                <div v-else-if="hasActiveFilters" class="text-center py-20">
                    <div class="max-w-lg mx-auto">
                        <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-savino-blue to-gray-900 flex items-center justify-center shadow-xl">
                            <svg class="w-10 h-10 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <h2 class="text-2xl font-black text-savino-blue uppercase tracking-tight mb-3">
                            {{ $t('shop.no_results_title') || 'Nessun risultato' }}
                        </h2>
                        <div class="w-12 h-1 bg-savino-gold mx-auto mb-4"></div>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            {{ $t('shop.try_different_search') || 'Prova con un altro termine di ricerca o categoria.' }}
                        </p>
                        <button
                            @click="clearFilters"
                            class="inline-flex items-center gap-2 bg-savino-blue text-white font-bold uppercase tracking-wider text-sm px-8 py-3 rounded-xl hover:bg-savino-gold hover:text-savino-blue transition-all duration-300"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            {{ $t('shop.clear_filters') || 'Rimuovi filtri' }}
                        </button>
                    </div>
                </div>

                <!-- Coming Soon State (no products at all in the DB) -->
                <div v-else class="text-center py-20">
                    <div class="max-w-lg mx-auto">
                        <!-- Animated Shopping Icon -->
                        <div class="w-32 h-32 mx-auto mb-8 rounded-full bg-gradient-to-br from-savino-blue to-gray-900 flex items-center justify-center shadow-2xl">
                            <svg class="w-14 h-14 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        </div>
                        <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mb-4">
                            {{ $t('shop.coming_soon_title') }}
                        </h2>
                        <div class="w-16 h-1 bg-savino-gold mx-auto mb-6"></div>
                        <p class="text-gray-600 text-lg leading-relaxed mb-8">
                            {{ $t('shop.coming_soon_description') }}
                        </p>
                        <div class="inline-flex items-center gap-3 bg-savino-blue/5 border border-savino-blue/10 rounded-full px-8 py-4">
                            <div class="w-2 h-2 rounded-full bg-savino-gold animate-pulse"></div>
                            <span class="text-savino-blue text-sm font-bold uppercase tracking-wider">{{ $t('common.coming_soon') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Cross-link: Auctions -->
        <section class="bg-gradient-to-r from-savino-blue to-savino-blue/80 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <h3 class="text-2xl font-black text-white uppercase tracking-tight">🔥 {{ $t('shop.auctions_cta_title') }}</h3>
                    <p class="text-white/80 mt-1">{{ $t('shop.auctions_cta_description') }}</p>
                </div>
                <Link :href="route('shop.auctions.index')" class="inline-flex items-center px-8 py-3 bg-savino-gold text-savino-blue font-bold uppercase tracking-wider text-sm rounded-xl hover:bg-white transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 whitespace-nowrap">
                    {{ $t('shop.auctions_cta_button') }}
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
.product-fade-enter-active,
.product-fade-leave-active {
    transition: all 0.3s ease;
}
.product-fade-enter-from {
    opacity: 0;
    transform: translateY(12px);
}
.product-fade-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>
