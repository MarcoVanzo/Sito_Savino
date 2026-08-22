<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { useOgMeta } from '@/Composables/useOgMeta'
import { useSanitize } from '@/Composables/useSanitize';
import { computed } from 'vue';

const { sanitize } = useSanitize();

const $t = useTranslations();

const props = defineProps({
    dirigenza: {
        type: Array,
        default: () => []
    },
    page: {
        type: Object,
        default: null
    }
})

const safeContent = computed(() => sanitize(props.page?.content));
const cd = computed(() => props.page?.content_data ?? {});

const ogMeta = useOgMeta({
    title: props.page?.title || $t('organigramma.og_title'),
    description: props.page?.meta_description || $t('organigramma.og_description'),
})
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
        <!-- Hero -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span v-if="cd.hero_subheading" class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_subheading }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title || $t('organigramma.og_title') }}</h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>

                <div v-if="page?.content" class="prose prose-lg prose-invert max-w-3xl mx-auto" v-html="safeContent"></div>
                <p v-else-if="cd.hero_description" class="text-white/70 text-lg max-w-2xl mx-auto">{{ cd.hero_description }}</p>
            </div>
        </section>

        <!-- Dirigenza Grid -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Elenco scritto, senza ritratti: la dirigenza non ha una fototeca e
                     le schede restavano riquadri grigi con le iniziali, con l'unica foto
                     disponibile presa da un'immagine di gara. Il nome e il ruolo sono
                     l'informazione che serve, e si leggono meglio in colonna. -->
                <div v-if="dirigenza.length > 0" class="max-w-3xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div
                        v-for="(member, index) in dirigenza"
                        :key="member.id"
                        class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1 sm:gap-6 px-6 sm:px-8 py-5"
                        :class="index > 0 ? 'border-t border-gray-100' : ''"
                    >
                        <h3 class="text-base sm:text-lg font-black text-gray-900 uppercase tracking-tight">{{ member.name }}</h3>
                        <p class="text-savino-fucsia text-sm font-bold sm:text-right sm:flex-shrink-0">{{ member.role }}</p>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="dirigenza.length === 0" class="text-center py-20">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-savino-blue/10 flex items-center justify-center">
                        <svg class="w-12 h-12 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 uppercase mb-3">{{ $t('organigramma.empty_title') }}</h3>
                    <p class="text-gray-500 max-w-md mx-auto">{{ $t('organigramma.empty_description') }}</p>
                </div>

            </div>
        </section>
    </PublicLayout>
</template>
