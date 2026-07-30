<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSanitize } from '@/Composables/useSanitize';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useSafeUrl } from '@/Composables/useSafeUrl';

const { sanitize } = useSanitize();
const { safeUrl } = useSafeUrl();

const $t = useTranslations();

const props = defineProps({
    page: Object,
});

const safeContent = computed(() => sanitize(props.page?.content));

// URL del bottone/banner: arriva dal CMS, va validato prima di finire in href.
const buttonUrl = computed(() => safeUrl(props.page?.content_data?.button_url));

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('content_page.og_title'),
    description: props.page?.meta_description || $t('content_page.og_description'),
});

const getEmbedUrl = (url) => {
    if (!url) return '';
    let videoId = '';
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
    const match = url.match(regExp);
    if (match && match[2].length === 11) {
        videoId = match[2];
    } else {
        videoId = url;
    }
    return `https://www.youtube.com/embed/${videoId}`;
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
    </Head>

    <PublicLayout>
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="mb-12 border-b border-gray-200 pb-8">
                    <h1 class="text-4xl font-black text-savino-blue uppercase tracking-tighter mb-2">
                        {{ page?.title }}
                    </h1>
                    <div class="w-16 h-1 bg-savino-gold"></div>
                </div>

                <!-- Content -->
                <div 
                    class="prose prose-lg max-w-none prose-headings:font-bold prose-headings:text-savino-blue prose-a:text-savino-gold prose-a:no-underline hover:prose-a:underline"
                    v-html="safeContent"
                ></div>

                <!-- Custom Segment: Iscrizione Experience -->
                <div v-if="page?.slug === 'iscrizione-experience'" class="mt-12 pt-8 border-t border-gray-100 flex flex-col items-center">
                    <div class="w-full max-w-2xl text-center">
                        <template v-if="page?.content_data?.button_image">
                            <!-- Clickable Premium Banner -->
                            <a 
                                :href="buttonUrl"
                                target="_blank"
                                rel="noopener noreferrer" 
                                class="group relative block overflow-hidden rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1"
                            >
                                <img 
                                    :src="`/storage/${page.content_data.button_image}`" 
                                    :alt="page.content_data.button_text || 'Accedi'" 
                                    class="w-full h-auto object-cover transition-transform duration-500 group-hover:scale-105"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-center p-6">
                                    <span class="px-6 py-3 bg-savino-gold text-white font-semibold rounded-lg shadow-lg uppercase tracking-wider text-sm flex items-center gap-2">
                                        {{ page.content_data.button_text }}
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                    </span>
                                </div>
                            </a>
                        </template>
                        <template v-else>
                            <!-- Premium CTA Button -->
                            <a 
                                :href="buttonUrl"
                                target="_blank"
                                rel="noopener noreferrer" 
                                class="inline-flex items-center gap-3 px-10 py-5 bg-gradient-to-r from-savino-blue to-savino-pink text-white font-bold text-lg rounded-full shadow-lg hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 uppercase tracking-wider hover:brightness-110 group"
                            >
                                <span>{{ page?.content_data?.button_text || 'Accedi al portale iscrizioni' }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </a>
                        </template>
                    </div>
                </div>

                <!-- Custom Segment: Magazine Archives -->
                <div v-if="page?.slug === 'magazine'" class="mt-16 pt-12 border-t border-gray-100">
                    <h3 class="text-2xl font-bold text-savino-blue mb-8 uppercase tracking-tight flex items-center gap-3">
                        <span class="w-8 h-8 rounded-full bg-savino-pink/10 flex items-center justify-center text-savino-pink">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                        </span>
                        Le Nostre Edizioni
                    </h3>

                    <div v-if="page.content_data?.magazines?.length" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div 
                            v-for="(mag, idx) in page.content_data.magazines" 
                            :key="idx"
                            class="bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-gray-100 overflow-hidden flex shadow-sm hover:shadow-lg transition-all duration-300 group"
                        >
                            <!-- Cover Thumbnail -->
                            <div class="w-1/3 relative bg-savino-blue flex-shrink-0 flex items-center justify-center overflow-hidden">
                                <img 
                                    v-if="mag.cover_image_url"
                                    :src="`/storage/${mag.cover_image_url}`" 
                                    :alt="mag.title" 
                                    class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                />
                                <div v-else class="w-full h-full bg-gradient-to-br from-savino-blue to-indigo-900 p-4 flex flex-col justify-between text-white select-none">
                                    <span class="text-xs uppercase font-semibold text-savino-gold tracking-widest">SDB Volley</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-white/20 self-center" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                    <span class="text-[10px] opacity-60">Magazine</span>
                                </div>
                            </div>

                            <!-- Details -->
                            <div class="p-6 flex flex-col justify-between flex-grow">
                                <div>
                                    <span v-if="mag.publish_date" class="text-xs font-semibold text-savino-pink uppercase tracking-wider block mb-1">
                                        {{ mag.publish_date }}
                                    </span>
                                    <h4 class="text-lg font-bold text-savino-blue line-clamp-2 leading-snug mb-2 group-hover:text-savino-pink transition-colors">
                                        {{ mag.title }}
                                    </h4>
                                </div>

                                <a 
                                    :href="`/storage/${mag.file_url}`" 
                                    target="_blank" 
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-savino-blue hover:bg-savino-blue/90 text-white rounded-lg font-semibold text-sm transition-all shadow hover:shadow-md transform hover:-translate-y-0.5"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                    {{ $t('content_page.download_pdf') }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 00-2-2H6a2 2 0 00-2 2v4h16z" /></svg>
                        <p class="text-gray-500 font-medium">{{ $t('content_page.no_magazines') }}</p>
                    </div>
                </div>

                <!-- Custom Segment: Double Face (YouTube Videos) -->
                <div v-if="page?.slug === 'double-face'" class="mt-16 pt-12 border-t border-gray-100">
                    <h3 class="text-2xl font-bold text-savino-blue mb-8 uppercase tracking-tight flex items-center gap-3">
                        <span class="w-8 h-8 rounded-full bg-savino-gold/10 flex items-center justify-center text-savino-gold">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                        </span>
                        Video & Approfondimenti
                    </h3>

                    <div v-if="page.content_data?.youtube_videos?.length" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div 
                            v-for="(vid, idx) in page.content_data.youtube_videos" 
                            :key="idx"
                            class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300"
                        >
                            <div class="relative w-full aspect-video bg-black">
                                <iframe 
                                    v-if="vid.youtube_url"
                                    :src="getEmbedUrl(vid.youtube_url)" 
                                    title="YouTube video player" 
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                    allowfullscreen
                                    class="absolute top-0 left-0 w-full h-full border-0"
                                ></iframe>
                            </div>

                            <div class="p-6">
                                <h4 class="text-lg font-bold text-savino-blue mb-2 leading-snug">
                                    {{ vid.title }}
                                </h4>
                                <p v-if="vid.description" class="text-sm text-gray-500 leading-relaxed">
                                    {{ vid.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                        <p class="text-gray-500 font-medium">{{ $t('content_page.no_videos') }}</p>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

