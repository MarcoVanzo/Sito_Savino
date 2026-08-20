<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useSanitize } from '@/Composables/useSanitize';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useLocale } from '@/Composables/useLocale.js';
import { collapseCategories } from '@/Support/collapseCategories.js';

const { onImgError } = useImageFallback();
const { formatDate, locale } = useLocale();

const $t = useTranslations();

const props = defineProps({
    posts: Object,
    categories: {
        type: Array,
        default: () => [],
    },
    activeCategory: {
        type: String,
        default: null,
    },
});

// Lo slug del filtro sta nell'URL, non nello stato del componente: così il
// link è condivisibile e la paginazione lo conserva. Il path si ricava da
// quello corrente per non perdere il prefisso di lingua.
const basePath = computed(() => usePage().url.split('?')[0]);

// Le categorie sono più di venti (una per stagione, più le coppe): si
// mostrano le più usate, il resto dietro un "mostra tutte". La logica vive
// in collapseCategories, coperta dai test.
const showAllCategories = ref(false);

const collapsed = computed(() => collapseCategories(props.categories, {
    activeSlug: props.activeCategory,
    showAll: showAllCategories.value,
}));

const visibleCategories = computed(() => collapsed.value.visible);
const hiddenCategoriesCount = computed(() => collapsed.value.hiddenCount);

function categoryUrl(slug) {
    if (!slug) return basePath.value;

    const param = locale.value === 'en' ? 'category' : 'categoria';

    return `${basePath.value}?${param}=${encodeURIComponent(slug)}`;
}
const { sanitize } = useSanitize();

const ogMeta = useOgMeta({
    title: $t('news.og_title'),
    description: $t('news.og_description'),
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
    </Head>

    <PublicLayout>
        <!-- HERO -->
        <section class="relative min-h-[40vh] flex items-center bg-gradient-to-br from-gray-900 via-[#0B1521] to-gray-800">
            <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.4&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 w-full">
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter">
                    {{ $t('news.hero_title') }}
                </h1>
                <div class="w-16 h-1 bg-savino-fucsia mt-4"></div>
                <p class="text-gray-300 mt-4 text-lg max-w-2xl">
                    {{ $t('news.hero_subtitle') }}
                </p>
            </div>
        </section>

        <!-- NEWS GRID -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <!-- Filtro per categoria -->
                <nav v-if="categories.length" class="mb-12 flex flex-wrap gap-2.5" :aria-label="$t('news.filter_label')">
                    <Link
                        :href="categoryUrl(null)"
                        class="px-4 py-2.5 min-h-[44px] flex items-center rounded-full text-xs font-black uppercase tracking-widest transition-colors"
                        :class="!activeCategory
                            ? 'bg-savino-blue text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-savino-blue'"
                        :aria-current="!activeCategory ? 'page' : undefined"
                    >
                        {{ $t('news.filter_all') }}
                    </Link>
                    <Link
                        v-for="category in visibleCategories"
                        :key="category.slug"
                        :href="categoryUrl(category.slug)"
                        class="px-4 py-2.5 min-h-[44px] flex items-center rounded-full text-xs font-black uppercase tracking-widest transition-colors"
                        :class="activeCategory === category.slug
                            ? 'bg-savino-blue text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-savino-blue'"
                        :aria-current="activeCategory === category.slug ? 'page' : undefined"
                    >
                        {{ category.name }}
                        <span class="ml-2 text-[10px] opacity-60">{{ category.count }}</span>
                    </Link>
                    <button
                        v-if="hiddenCategoriesCount > 0"
                        type="button"
                        class="px-4 py-2.5 min-h-[44px] flex items-center rounded-full text-xs font-black uppercase tracking-widest text-savino-blue border border-savino-blue/30 hover:bg-savino-blue/10 transition-colors"
                        @click="showAllCategories = true"
                    >
                        {{ $t('news.filter_show_all') }}
                        <span class="ml-2 text-[10px] opacity-60">+{{ hiddenCategoriesCount }}</span>
                    </button>
                </nav>

                <div v-if="posts?.data?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <article
                        v-for="post in posts.data"
                        :key="post.id"
                        class="group bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100"
                    >
                        <div class="aspect-video bg-gray-200 overflow-hidden">
                            <img
                                v-if="post.media?.length"
                                :src="post.media[0]?.original_url"
                                :alt="post.title"
                                loading="lazy"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                @error="onImgError"
                            />
                            <div v-else class="w-full h-full bg-gradient-to-br from-savino-blue to-gray-700 flex items-center justify-center">
                                <span class="text-savino-fucsia text-5xl font-black opacity-30">SDB</span>
                            </div>
                        </div>
                        <div class="p-6">
                            <time v-if="post.published_at" class="text-xs font-semibold text-savino-fucsia uppercase tracking-wider">
                                {{ formatDate(post.published_at) }}
                            </time>
                            <h2 class="mt-2 text-lg font-bold text-savino-blue group-hover:text-savino-red transition-colors line-clamp-2">
                                <Link :href="`/news/${post.slug}`">
                                    {{ post.title }}
                                </Link>
                            </h2>
                            <p v-if="post.excerpt" class="mt-2 text-gray-600 text-sm line-clamp-3">
                                {{ post.excerpt }}
                            </p>
                            <Link
                                :href="`/news/${post.slug}`"
                                class="inline-flex items-center mt-4 text-sm font-bold text-savino-fucsia hover:text-savino-red transition-colors uppercase tracking-wider"
                            >
                                {{ $t('common.read_more') }} →
                            </Link>
                        </div>
                    </article>
                </div>

                <!-- Empty state -->
                <div v-else class="text-center py-20">
                    <div class="text-6xl mb-4">📰</div>
                    <h2 class="text-2xl font-bold text-savino-blue mb-2">{{ $t('news.empty_title') }}</h2>
                    <p class="text-gray-500">{{ activeCategory ? $t('news.empty_category_text') : $t('news.empty_text') }}</p>
                    <Link v-if="activeCategory" :href="categoryUrl(null)" class="inline-block mt-6 font-bold text-savino-blue hover:underline">
                        {{ $t('news.filter_reset') }}
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="posts?.links?.length > 3" class="mt-12 flex justify-center gap-2">
                    <template v-for="link in posts.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-4 py-2.5 min-h-[44px] flex items-center text-sm font-medium border rounded-md transition-colors"
                            :class="link.active ? 'bg-savino-blue text-white border-savino-blue' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                        >
                            <span v-html="sanitize(link.label)" />
                        </Link>
                        <span
                            v-else
                            class="px-4 py-2.5 min-h-[44px] flex items-center text-sm text-gray-400 border border-gray-200 rounded-md"
                            v-html="sanitize(link.label)"
                        />
                    </template>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

