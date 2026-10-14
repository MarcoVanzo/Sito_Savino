<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSanitize } from '@/Composables/useSanitize';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useLocale } from '@/Composables/useLocale.js';
import { testoAlternativo } from '@/Support/testoAlternativo.js';

const { onImgError } = useImageFallback();
const { formatDate } = useLocale();

const $t = useTranslations();

const { sanitize } = useSanitize();

const props = defineProps({
    post: Object,
    relatedPosts: Array,
});

const safeContent = computed(() => sanitize(props.post?.content));

const formattedDate = computed(() => {
    if (!props.post?.published_at) return '';
    return formatDate(props.post.published_at);
});

const ogMeta = useOgMeta({
    title: props.post?.title,
    description: props.post?.meta_description || props.post?.excerpt,
    image: props.post?.media?.[0]?.original_url,
    type: 'article',
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
        <!-- ARTICLE HEADER -->
        <section class="relative min-h-[35vh] flex items-end bg-gradient-to-br from-gray-900 via-[#0B1521] to-gray-800">
            <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
                <div class="mb-4">
                    <Link :href="route('news.index')" class="inline-flex items-center min-h-[44px] text-savino-fucsia-chiaro hover:text-white text-sm font-bold uppercase tracking-wider transition-colors">
                        ← {{ $t('news_detail.back_to_news') }}
                    </Link>
                </div>
                <time v-if="formattedDate" class="text-savino-fucsia-chiaro text-sm font-semibold uppercase tracking-wider">
                    {{ formattedDate }}
                </time>
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-black text-white uppercase tracking-tighter mt-2">
                    {{ post?.title }}
                </h1>
                <div class="w-16 h-1 bg-savino-fucsia mt-4"></div>
                <p v-if="post?.author" class="text-gray-400 mt-4 text-sm">
                    {{ $t('news_detail.by_author') }} <span class="text-white font-semibold">{{ post.author.name }}</span>
                </p>
            </div>
        </section>

        <!-- COVER IMAGE -->
        <div v-if="post?.media?.length" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">
            <img
                :src="post.media[0]?.original_url"
                :alt="testoAlternativo(post.image_alt, post.title)"
                class="w-full rounded-xl shadow-2xl object-cover max-h-[500px]"
                @error="onImgError"
            />
        </div>

        <!-- ARTICLE CONTENT -->
        <article class="py-12 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-4xl mx-auto">
                <div
                    class="prose prose-lg max-w-none prose-headings:font-bold prose-headings:text-savino-blue prose-a:text-savino-fucsia prose-a:no-underline hover:prose-a:underline"
                    v-html="safeContent"
                ></div>

                <!-- Tags -->
                <div v-if="post?.tags?.length" class="mt-12 pt-8 border-t border-gray-200">
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="tag in post.tags"
                            :key="tag.id"
                            class="px-3 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full uppercase tracking-wider"
                        >
                            {{ tag.name }}
                        </span>
                    </div>
                </div>
            </div>
        </article>

        <!-- RELATED POSTS -->
        <section v-if="relatedPosts?.length" class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-7xl mx-auto">
                <h2 class="text-2xl font-black text-savino-blue uppercase tracking-tighter mb-8">
                    {{ $t('news_detail.related_posts') }}
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
                    <article
                        v-for="related in relatedPosts"
                        :key="related.id"
                        class="group bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden"
                    >
                        <div class="p-6">
                            <time v-if="related.published_at" class="text-xs font-semibold text-savino-fucsia uppercase tracking-wider">
                                {{ formatDate(related.published_at) }}
                            </time>
                            <h3 class="mt-2 text-lg font-bold text-savino-blue group-hover:text-savino-red transition-colors line-clamp-2">
                                <Link :href="route('news.show', related.slug)">{{ related.title }}</Link>
                            </h3>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

