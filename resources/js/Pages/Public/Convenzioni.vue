<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSanitize } from '@/Composables/useSanitize';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useSafeUrl } from '@/Composables/useSafeUrl';
import { partnerConvenzionati } from '@/Support/convenzioni.js';

const { sanitize } = useSanitize();
const { safeUrl } = useSafeUrl();
const $t = useTranslations();

const props = defineProps({
    page: {
        type: Object,
        default: () => ({}),
    },
});

const safeContent = computed(() => sanitize(props.page?.content));
const cd = computed(() => props.page?.content_data ?? {});

// I partner arrivano dal pannello: nome, sito, sconto e come usarlo. Senza
// nome la voce non compare, senza link valido il nome non e' cliccabile.
const partners = computed(() => partnerConvenzionati(cd.value.partners, { safeUrl }));

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('convenzioni.og_title'),
    description: props.page?.meta_description || $t('convenzioni.og_description'),
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
        <!-- Hero -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span v-if="cd.hero_label" class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_label }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title ?? $t('convenzioni.og_title') }}</h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
                <p v-if="cd.hero_subtitle" class="text-white/70 text-lg max-w-2xl mx-auto">{{ cd.hero_subtitle }}</p>
            </div>
        </section>

        <!-- Testo introduttivo dall'editor della pagina -->
        <section v-if="page?.content" class="py-16 bg-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    class="prose prose-lg max-w-none prose-headings:font-black prose-headings:uppercase prose-headings:tracking-tight prose-a:text-savino-blue"
                    v-html="safeContent"
                ></div>
            </div>
        </section>

        <!-- Partner convenzionati -->
        <section class="py-16 bg-gray-50" data-test="convenzioni-partners">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 v-if="cd.partners_heading" class="text-3xl font-black text-gray-900 uppercase tracking-tight text-center mb-2">{{ cd.partners_heading }}</h2>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-12"></div>

                <div v-if="partners.length === 0 && cd.partners_empty" class="max-w-2xl mx-auto text-center bg-white rounded-2xl border border-gray-100 shadow-sm px-8 py-12">
                    <p class="text-gray-600 text-lg">{{ cd.partners_empty }}</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <article
                        v-for="(partner, idx) in partners"
                        :key="idx"
                        class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-savino-blue/30 transition-all p-6 flex flex-col"
                    >
                        <div class="flex items-start gap-4 mb-4">
                            <div class="flex-shrink-0 w-16 h-16 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center overflow-hidden">
                                <img v-if="partner.logo" :src="partner.logo" :alt="partner.name" class="w-full h-full object-contain p-1" loading="lazy" />
                                <span v-else class="text-2xl font-black text-savino-blue">{{ partner.initial }}</span>
                            </div>
                            <div class="min-w-0 flex-grow">
                                <h3 class="text-lg font-bold text-savino-blue leading-tight">
                                    <a v-if="partner.url" :href="partner.url" target="_blank" rel="noopener noreferrer" class="hover:text-savino-fucsia transition-colors">{{ partner.name }}</a>
                                    <template v-else>{{ partner.name }}</template>
                                </h3>
                                <span v-if="partner.discount" class="inline-block mt-2 bg-savino-fucsia/10 text-savino-fucsia text-xs font-black uppercase tracking-wider px-3 py-1 rounded-full">{{ partner.discount }}</span>
                            </div>
                        </div>

                        <p v-if="partner.description" class="text-gray-600 text-sm leading-relaxed whitespace-pre-line">{{ partner.description }}</p>

                        <p v-if="partner.howToUse" class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500 leading-relaxed">
                            <span class="font-bold text-gray-700 uppercase tracking-wider">{{ $t('convenzioni.how_to_use') }}</span>
                            <span class="block mt-1 whitespace-pre-line">{{ partner.howToUse }}</span>
                        </p>

                        <a
                            v-if="partner.url"
                            :href="partner.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 mt-auto pt-5 text-savino-blue font-bold text-sm uppercase tracking-wider hover:underline"
                        >
                            {{ $t('convenzioni.visit_site') }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </a>
                    </article>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
