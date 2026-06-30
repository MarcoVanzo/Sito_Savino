<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSanitize } from '@/Composables/useSanitize';
import { useImageFallback } from '@/Composables/useImageFallback.js';

const { onImgError } = useImageFallback();

const { sanitize } = useSanitize();

const props = defineProps({
    post: Object,
    relatedPosts: Array,
});

const safeContent = computed(() => sanitize(props.post?.content));

const formattedDate = computed(() => {
    if (!props.post?.published_at) return '';
    return new Date(props.post.published_at).toLocaleDateString('it-IT', {
        day: 'numeric', month: 'long', year: 'numeric'
    });
});
</script>

<template>
    <Head>
        <title>{{ post?.title }} — Savino Del Bene Volley</title>
        <meta v-if="post?.meta_description || post?.excerpt" name="description" :content="post.meta_description || post.excerpt" />
        <meta property="og:type" content="article" />
        <meta property="og:title" :content="post?.title" />
        <meta v-if="post?.excerpt" property="og:description" :content="post.excerpt" />
        <meta property="og:image" :content="post?.media?.[0]?.original_url ?? '/images/logo.png'" />
        <meta property="og:url" :content="$page.props.ziggy?.location || ''" />
        <component :is="'script'" type="application/ld+json" v-if="post">
            {{ JSON.stringify({
                '@context': 'https://schema.org',
                '@type': 'NewsArticle',
                'headline': post.title,
                'datePublished': post.published_at,
                'dateModified': post.updated_at || post.published_at,
                'author': {
                    '@type': 'Organization',
                    'name': 'Savino Del Bene Volley',
                },
                'publisher': {
                    '@type': 'Organization',
                    'name': 'Savino Del Bene Volley',
                },
                'description': post.meta_description || post.excerpt || '',
                'image': post.media?.length ? post.media[0].original_url : undefined,
                'mainEntityOfPage': {
                    '@type': 'WebPage',
                    '@id': `${usePage().props.ziggy.url}/news/${post.slug}`,
                },
            }) }}
        </component>
    </Head>

    <PublicLayout>
        <!-- ARTICLE HEADER -->
        <section class="relative min-h-[35vh] flex items-end bg-gradient-to-br from-gray-900 via-[#0B1521] to-gray-800">
            <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
                <div class="mb-4">
                    <Link href="/news" class="text-savino-gold hover:text-white text-sm font-bold uppercase tracking-wider transition-colors">
                        ← Torna alle News
                    </Link>
                </div>
                <time v-if="formattedDate" class="text-savino-gold text-sm font-semibold uppercase tracking-wider">
                    {{ formattedDate }}
                </time>
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-black text-white uppercase tracking-tighter mt-2">
                    {{ post?.title }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mt-4"></div>
                <p v-if="post?.author" class="text-gray-400 mt-4 text-sm">
                    di <span class="text-white font-semibold">{{ post.author.name }}</span>
                </p>
            </div>
        </section>

        <!-- COVER IMAGE -->
        <div v-if="post?.media?.length" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">
            <img
                :src="post.media[0]?.original_url"
                :alt="post.title"
                class="w-full rounded-xl shadow-2xl object-cover max-h-[500px]"
                @error="onImgError"
            />
        </div>

        <!-- ARTICLE CONTENT -->
        <article class="py-12 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-4xl mx-auto">
                <div
                    class="prose prose-lg max-w-none prose-headings:font-bold prose-headings:text-savino-blue prose-a:text-savino-gold prose-a:no-underline hover:prose-a:underline"
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
                    Articoli Correlati
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <article
                        v-for="related in relatedPosts"
                        :key="related.id"
                        class="group bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden"
                    >
                        <div class="p-6">
                            <time v-if="related.published_at" class="text-xs font-semibold text-savino-gold uppercase tracking-wider">
                                {{ new Date(related.published_at).toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                            </time>
                            <h3 class="mt-2 text-lg font-bold text-savino-blue group-hover:text-savino-red transition-colors line-clamp-2">
                                <Link :href="`/news/${related.slug}`">{{ related.title }}</Link>
                            </h3>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
