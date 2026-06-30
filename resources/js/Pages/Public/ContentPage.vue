<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSanitize } from '@/Composables/useSanitize';
import { useOgMeta } from '@/Composables/useOgMeta';

const { sanitize } = useSanitize();

const props = defineProps({
    page: Object,
});

const safeContent = computed(() => sanitize(props.page?.content));

const ogMeta = useOgMeta({
    title: props.page?.title ?? 'Pagina',
    description: props.page?.meta_description || 'Savino Del Bene Volley - Sito ufficiale.',
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
            </div>
        </section>
    </PublicLayout>
</template>

