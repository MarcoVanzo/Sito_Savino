<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSanitize } from '@/Composables/useSanitize';

const { sanitize } = useSanitize();

const props = defineProps({
    page: Object,
});

const safeContent = computed(() => sanitize(props.page?.content));
</script>

<template>
    <Head>
        <title>{{ page?.title ?? 'Pagina' }} — Savino Del Bene Volley</title>
        <meta v-if="page?.meta_description" name="description" :content="page.meta_description" />
    </Head>

    <PublicLayout>
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="mb-12 border-b border-gray-200 pb-8">
                    <h1 class="text-4xl font-black text-savino-blue uppercase tracking-tighter mb-2" style="font-family: 'Montserrat', sans-serif;">
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

