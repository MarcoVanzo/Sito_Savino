<script setup>
/**
 * Talent Day e Recruiting.
 *
 * La pagina condivideva il modello del Summer Camp e, non avendo contenuti
 * propri, mostrava quelli: chi cercava le prove di selezione leggeva gli orari
 * del camp estivo. Qui il contenuto e' suo — le tappe con data, sede e
 * disponibilita', i turni per anno di nascita, e il modulo d'iscrizione.
 */
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSanitize } from '@/Composables/useSanitize';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useSafeUrl } from '@/Composables/useSafeUrl';
import PageMediaTail from '@/Components/PageMediaTail.vue';

const $t = useTranslations();
const { sanitize } = useSanitize();
const { safeUrl } = useSafeUrl();

defineOptions({ layout: PublicLayout });

const props = defineProps({
    page: {
        type: Object,
        default: () => ({}),
    },
});

const inertiaPage = usePage();
const contact = computed(() => inertiaPage.props.siteSettings?.contact ?? {});
const cd = computed(() => props.page?.content_data ?? {});
const safeContent = computed(() => sanitize(props.page?.content));

// Le tappe arrivano dal pannello. Una tappa senza data non e' una tappa.
const stages = computed(() => {
    const elenco = cd.value.stages;

    if (!Array.isArray(elenco)) return [];

    return elenco.filter(t => t?.date);
});

// Turni per anno di nascita.
const slots = computed(() => {
    const elenco = cd.value.slots;

    if (!Array.isArray(elenco)) return [];

    return elenco.filter(t => t?.time || t?.years);
});

const partners = computed(() => (typeof cd.value.partners === 'string' ? cd.value.partners.trim() : ''));
const signupUrl = computed(() => safeUrl(cd.value.signup_url) || null);
const signupEmail = computed(() => cd.value.signup_email || contact.value.email || null);

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('talent_day.og_title'),
    description: props.page?.meta_description || $t('talent_day.og_description'),
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

    <!-- Hero -->
    <section class="relative min-h-[45vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
        <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
            <span v-if="cd.hero_label" class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_label }}</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                {{ page?.title }}
            </h1>
            <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
            <p v-if="cd.hero_subtitle" class="text-white/70 text-lg max-w-2xl mx-auto">{{ cd.hero_subtitle }}</p>
        </div>
    </section>

    <!-- Testo introduttivo -->
    <section v-if="page?.content" class="py-16 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg max-w-none" v-html="safeContent"></div>
        </div>
    </section>

    <!-- Tappe -->
    <section v-if="stages.length" class="py-16 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight text-center mb-2">{{ cd.stages_title || $t('talent_day.stages_title') }}</h2>
            <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-10"></div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div
                    v-for="(stage, index) in stages"
                    :key="index"
                    class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-6 px-6 sm:px-8 py-5"
                    :class="index > 0 ? 'border-t border-gray-100' : ''"
                >
                    <span class="text-savino-blue font-black uppercase tracking-tight sm:w-32 sm:flex-shrink-0">{{ stage.date }}</span>
                    <span class="text-gray-700 flex-grow">{{ stage.place }}</span>
                    <span
                        v-if="stage.status"
                        class="text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full self-start sm:self-auto sm:flex-shrink-0"
                        :class="stage.sold_out ? 'bg-gray-100 text-gray-500' : 'bg-savino-fucsia/10 text-savino-fucsia'"
                    >
                        {{ stage.status }}
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- Turni -->
    <section v-if="slots.length" class="py-16 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight text-center mb-2">{{ cd.slots_title || $t('talent_day.slots_title') }}</h2>
            <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-10"></div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div
                    v-for="(slot, index) in slots"
                    :key="index"
                    class="bg-gray-50 rounded-2xl border border-gray-100 px-6 py-7 text-center"
                >
                    <span class="block text-3xl font-black text-savino-blue tabular-nums">{{ slot.time }}</span>
                    <span class="block text-gray-600 text-sm mt-2">{{ slot.years }}</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Iscrizione -->
    <section class="py-16 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-black text-white uppercase tracking-tight mb-4">{{ cd.signup_title || $t('talent_day.signup_title') }}</h2>
            <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-8"></div>
            <p v-if="cd.signup_description" class="text-white/70 text-lg leading-relaxed mb-8">{{ cd.signup_description }}</p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a
                    v-if="signupUrl"
                    :href="signupUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center px-8 py-3.5 bg-savino-fucsia text-white font-bold uppercase tracking-wider rounded-lg hover:bg-savino-fucsia/90 transition-all duration-300 shadow-lg shadow-savino-fucsia/30 text-sm"
                >
                    {{ cd.signup_cta || $t('talent_day.signup_cta') }}
                </a>
                <a
                    v-if="signupEmail"
                    :href="'mailto:' + signupEmail"
                    class="inline-flex items-center justify-center px-8 py-3.5 border-2 border-white/30 text-white font-bold uppercase tracking-wider rounded-lg hover:bg-white/10 transition-all duration-300 text-sm"
                >
                    {{ signupEmail }}
                </a>
            </div>

            <p v-if="partners" class="text-white/50 text-sm leading-relaxed mt-10">{{ partners }}</p>
        </div>
    </section>

    <PageMediaTail
        :video-embed-url="cd.video_embed_url"
        :video-url="cd.video_url"
        :images="page?.gallery_images ?? []"
    />
</template>
