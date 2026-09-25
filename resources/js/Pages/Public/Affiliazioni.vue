<script setup>
import { useTranslations } from '@/Composables/useTranslations.js'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useSanitize } from '@/Composables/useSanitize'
import { useOgMeta } from '@/Composables/useOgMeta'
import { useSafeUrl } from '@/Composables/useSafeUrl'
import { useImageFallback } from '@/Composables/useImageFallback.js'
import { societaAffiliate } from '@/Support/societaAffiliate.js'

const $t = useTranslations()
const { safeUrl } = useSafeUrl()
const { onImgError } = useImageFallback()

const props = defineProps({
    page: {
        type: Object,
        default: () => ({}),
    },
})

const { sanitize } = useSanitize()
const safeContent = computed(() => sanitize(props.page?.content))
const cd = computed(() => props.page?.content_data ?? {})

// I tre livelli del progetto, nello stesso ordine di App\Enums\AffiliateTier:
// la pagina esiste anche in inglese, quindi le intestazioni sono tradotte qui
// e non salvate nel contenuto.
const ORDINE_LIVELLI = ['main', 'official', 'affiliated']

const gruppi = computed(() => societaAffiliate(cd.value.affiliates, {
    ordine: ORDINE_LIVELLI,
    etichetta: (livello) => $t(`affiliazioni.tier_${livello}`),
    safeUrl,
}))

// Stessa misura dei riquadri della pagina Sponsor: i loghi delle societa'
// sono disegnati per il fondo bianco e vanno tutti trattati allo stesso modo.
const boxClass = 'h-24 md:h-28 p-5 w-[calc((100%-1.25rem)/2)] sm:w-[calc((100%-2.5rem)/3)] lg:w-[calc((100%-3.75rem)/4)]'

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('affiliazioni.og_title'),
    description: props.page?.meta_description || $t('affiliazioni.og_description'),
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
                <span v-if="cd.hero_label" class="text-savino-fucsia-chiaro text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_label }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ page?.title ?? $t('affiliazioni.og_title') }}
                </h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
                <p v-if="cd.hero_description" class="text-white/70 text-lg max-w-2xl mx-auto">{{ cd.hero_description }}</p>
            </div>
        </section>

        <!-- Racconto del progetto -->
        <section v-if="page?.content" class="py-16 bg-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    class="prose prose-lg max-w-none prose-headings:font-black prose-headings:uppercase prose-headings:tracking-tight prose-a:text-savino-blue"
                    v-html="safeContent"
                ></div>
            </div>
        </section>

        <!-- Le societa', divise per livello -->
        <section v-if="gruppi.length" class="py-16 bg-gray-50" data-test="affiliazioni-clubs">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="cd.clubs_heading" class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight">{{ cd.clubs_heading }}</h2>
                    <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4"></div>
                </div>

                <div v-for="gruppo in gruppi" :key="gruppo.key" class="mb-16 last:mb-0">
                    <div class="text-center mb-8">
                        <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.2em]">{{ gruppo.label }}</span>
                        <div class="w-12 h-0.5 bg-savino-fucsia mx-auto mt-3"></div>
                    </div>

                    <div class="flex flex-wrap justify-center gap-5">
                        <component
                            :is="club.url ? 'a' : 'div'"
                            v-for="(club, index) in gruppo.clubs"
                            :key="index"
                            :href="club.url || undefined"
                            :target="club.url ? '_blank' : undefined"
                            :rel="club.url ? 'noopener noreferrer' : undefined"
                            :title="club.name"
                            class="rounded-xl bg-white border border-gray-200 flex items-center justify-center transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5"
                            :class="boxClass"
                        >
                            <img
                                v-if="club.logo"
                                :src="club.logo"
                                :alt="club.name"
                                class="w-full h-full object-contain"
                                loading="lazy"
                                @error="onImgError"
                            />
                            <span v-else class="text-savino-blue font-bold text-center uppercase tracking-wide text-sm px-2">
                                {{ club.name }}
                            </span>
                        </component>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
