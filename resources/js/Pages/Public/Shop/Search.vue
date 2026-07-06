<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { ref, onUnmounted } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import ProductCard from '@/Components/Shop/ProductCard.vue';
import ProductCardSkeleton from '@/Components/Shop/ProductCardSkeleton.vue';


const $t = useTranslations();

const props = defineProps({
    query: {
        type: String,
        default: '',
    },
    products: Object,
});

const ogMeta = useOgMeta({
    title: props.query ? `${$t('shop.search_results_for')} "${props.query}"` : $t('shop.search'),
    description: $t('shop.og_description'),
});

// --- Search Form ---
const searchQuery = ref(props.query);

const submitSearch = () => {
    if (!searchQuery.value?.trim()) return;
    router.get(route('shop.search'), { q: searchQuery.value.trim() }, {
        preserveState: true,
    });
};

// --- Skeleton loading state ---
const isNavigating = ref(false);
const removeStartListener = router.on('start', () => { isNavigating.value = true; });
const removeFinishListener = router.on('finish', () => { isNavigating.value = false; });
onUnmounted(() => {
    removeStartListener();
    removeFinishListener();
});
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
        <meta property="og:title" :content="ogMeta.title" />
        <meta property="og:description" :content="ogMeta.description" />
        <meta property="og:url" :content="ogMeta.url" />
        <meta property="og:type" :content="ogMeta.type" />
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[35vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.05]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <!-- Search Icon -->
                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-white/10 backdrop-blur-sm flex items-center justify-center">
                    <svg class="w-9 h-9 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ $t('shop.search_label') }}</span>
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-black text-white uppercase tracking-tighter mt-4">
                    <template v-if="query">{{ $t('shop.results_for') }} "{{ query }}"</template>
                    <template v-else>{{ $t('shop.search_products') }}</template>
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4"></div>
            </div>
        </section>

        <!-- SEARCH & RESULTS -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <!-- Search Input -->
                <div class="max-w-2xl mx-auto mb-12">
                    <form @submit.prevent="submitSearch" class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            :aria-label="$t('shop.search_placeholder') || 'Cerca prodotti'"
                            :placeholder="$t('shop.search_placeholder')"
                            class="w-full border-2 border-gray-200 rounded-xl pl-12 pr-32 py-4 text-lg text-savino-blue placeholder-gray-400 focus:ring-2 focus:ring-savino-gold/50 focus:border-savino-gold outline-none transition-all"
                        />
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        <button
                            type="submit"
                            class="absolute right-2 top-1/2 -translate-y-1/2 bg-savino-blue text-white font-bold uppercase tracking-wider text-sm px-6 py-2.5 rounded-lg hover:bg-savino-gold hover:text-savino-blue transition-all duration-300"
                        >
                            {{ $t('shop.search_btn') }}
                        </button>
                    </form>
                </div>

                <!-- Result Count -->
                <div v-if="query" class="mb-8">
                    <p class="text-gray-500 text-sm">
                        <span class="font-semibold text-savino-blue">{{ products?.meta?.total ?? products?.data?.length ?? 0 }}</span>
                        {{ $t('shop.results_count') }}
                    </p>
                </div>

                <!-- Skeleton Loading -->
                <div v-if="isNavigating" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8" role="list" aria-label="Caricamento prodotti">
                    <ProductCardSkeleton v-for="n in 8" :key="'skeleton-' + n" role="listitem" />
                </div>

                <!-- Products Grid -->
                <div v-else-if="products?.data?.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8" role="list" :aria-label="$t('shop.products_list') || 'Elenco prodotti'">
                    <ProductCard v-for="product in products.data" :key="product.id" :product="product" role="listitem" />
                </div>

                <!-- Empty State -->
                <div v-else-if="query" class="text-center py-20">
                    <div class="max-w-lg mx-auto">
                        <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-savino-blue to-gray-900 flex items-center justify-center shadow-xl">
                            <svg class="w-10 h-10 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <h2 class="text-2xl font-black text-savino-blue uppercase tracking-tight mb-3">
                            {{ $t('shop.no_results_title') }}
                        </h2>
                        <div class="w-12 h-1 bg-savino-gold mx-auto mb-4"></div>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            {{ $t('shop.no_results_for') }} "<span class="font-semibold text-savino-blue">{{ query }}</span>".
                            {{ $t('shop.try_different_search') }}
                        </p>
                        <Link :href="route('shop')" class="inline-flex items-center gap-2 bg-savino-blue text-white font-bold uppercase tracking-wider text-sm px-8 py-3 rounded-xl hover:bg-savino-gold hover:text-savino-blue transition-all duration-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            {{ $t('shop.back_to_shop') }}
                        </Link>
                    </div>
                </div>

                <!-- Pagination -->
                <nav v-if="products?.links?.length > 3" role="navigation" :aria-label="$t('shop.pagination') || 'Paginazione risultati'" class="flex items-center justify-center gap-1 mt-12">
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

    </PublicLayout>
</template>
