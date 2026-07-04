<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { ref, watch } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';
import ProductCard from '@/Components/Shop/ProductCard.vue';
import CartDrawer from '@/Components/Shop/CartDrawer.vue';

const $t = useTranslations();

const { onImgError } = useImageFallback();

const props = defineProps({
    category: Object,
    products: Object,
    sort: {
        type: String,
        default: 'newest',
    },
});

const ogMeta = useOgMeta({
    title: props.category?.name ?? $t('shop.category'),
    description: props.category?.description || $t('shop.og_description'),
});

// --- Sorting ---
const currentSort = ref(props.sort);

const sortOptions = [
    { value: 'newest', label: $t('shop.sort_newest') || 'Più recenti' },
    { value: 'price_asc', label: $t('shop.sort_price_asc') || 'Prezzo crescente' },
    { value: 'price_desc', label: $t('shop.sort_price_desc') || 'Prezzo decrescente' },
];

watch(currentSort, (newSort) => {
    router.get(route('shop.category', props.category.slug), { sort: newSort }, {
        preserveState: true,
        preserveScroll: true,
    });
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
        <meta property="og:type" :content="ogMeta.type" />
        <component :is="'script'" type="application/ld+json">
            {{ JSON.stringify({
                '@context': 'https://schema.org',
                '@type': 'BreadcrumbList',
                'itemListElement': [
                    { '@type': 'ListItem', 'position': 1, 'name': 'Home', 'item': usePage().props.ziggy?.url },
                    { '@type': 'ListItem', 'position': 2, 'name': 'Shop', 'item': `${usePage().props.ziggy?.url}/shop` },
                    { '@type': 'ListItem', 'position': 3, 'name': category?.name },
                ],
            }) }}
        </component>
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[35vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.05]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <!-- Breadcrumb -->
                <nav class="flex items-center justify-center gap-2 text-sm text-white/60 mb-6">
                    <Link :href="route('home')" class="hover:text-savino-gold transition-colors">Home</Link>
                    <span>/</span>
                    <Link :href="route('shop.index')" class="hover:text-savino-gold transition-colors">Shop</Link>
                    <span>/</span>
                    <span class="text-savino-gold">{{ category?.name }}</span>
                </nav>
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ $t('shop.category_label') || 'Categoria' }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ category?.name }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p v-if="category?.description" class="text-white/70 text-lg max-w-2xl mx-auto">
                    {{ category.description }}
                </p>
            </div>
        </section>

        <!-- PRODUCTS SECTION -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <!-- Sort Bar -->
                <div class="flex items-center justify-between mb-10">
                    <p class="text-gray-500 text-sm">
                        <span class="font-semibold text-savino-blue">{{ products?.meta?.total ?? products?.data?.length ?? 0 }}</span>
                        {{ $t('shop.products_found') || 'prodotti' }}
                    </p>
                    <div class="flex items-center gap-3">
                        <label for="sort-select" class="text-sm text-gray-500 hidden sm:block">{{ $t('shop.sort_by') || 'Ordina per' }}:</label>
                        <select
                            id="sort-select"
                            v-model="currentSort"
                            class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-savino-blue font-semibold bg-white focus:ring-2 focus:ring-savino-gold/50 focus:border-savino-gold outline-none transition-all"
                        >
                            <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                </div>

                <!-- Products Grid -->
                <div v-if="products?.data?.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <ProductCard v-for="product in products.data" :key="product.id" :product="product" />
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-20">
                    <div class="max-w-lg mx-auto">
                        <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-savino-blue to-gray-900 flex items-center justify-center shadow-xl">
                            <svg class="w-10 h-10 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-2.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                        </div>
                        <h2 class="text-2xl font-black text-savino-blue uppercase tracking-tight mb-3">
                            {{ $t('shop.no_products_title') || 'Nessun prodotto' }}
                        </h2>
                        <div class="w-12 h-1 bg-savino-gold mx-auto mb-4"></div>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            {{ $t('shop.no_products_category') || 'Non ci sono ancora prodotti in questa categoria. Torna a trovarci presto!' }}
                        </p>
                        <Link :href="route('shop.index')" class="inline-flex items-center gap-2 bg-savino-blue text-white font-bold uppercase tracking-wider text-sm px-8 py-3 rounded-xl hover:bg-savino-gold hover:text-savino-blue transition-all duration-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            {{ $t('shop.back_to_shop') || 'Torna allo shop' }}
                        </Link>
                    </div>
                </div>

                <!-- Pagination -->
                <nav v-if="products?.links?.length > 3" class="flex items-center justify-center gap-1 mt-12">
                    <template v-for="link in products.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-4 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200"
                            :class="link.active
                                ? 'bg-savino-blue text-white shadow-md'
                                : 'text-gray-600 hover:bg-savino-blue/5 hover:text-savino-blue'"
                            v-html="link.label"
                            preserve-state
                        />
                        <span
                            v-else
                            class="px-4 py-2.5 text-sm text-gray-300"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </div>
        </section>

        <!-- Cart Drawer -->
        <CartDrawer />
    </PublicLayout>
</template>
