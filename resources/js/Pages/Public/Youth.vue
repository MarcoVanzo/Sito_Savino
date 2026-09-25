<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useSanitize } from '@/Composables/useSanitize'
import { useOgMeta } from '@/Composables/useOgMeta'

const $t = useTranslations();

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    }
})

const { sanitize } = useSanitize()
const safeContent = computed(() => sanitize(props.page?.content))

const inertiaPage = usePage()
const settings = computed(() => inertiaPage.props.siteSettings ?? {})
const contact = computed(() => settings.value.contact ?? {})
const cd = computed(() => props.page?.content_data ?? {})

// Indirizzo del settore giovanile: quello scritto nella pagina, altrimenti
// quello in Impostazioni -> Contatti.
const scoutingEmail = computed(() => cd.value.scouting_email || contact.value.youth_email || null)

// Le foto di squadra, dall'Under 19 alla Promozionale: una voce senza foto non
// ha niente da mostrare e resta fuori.
const teamPhotos = computed(() => (Array.isArray(cd.value.team_photos) ? cd.value.team_photos : [])
    .filter(squadra => squadra && typeof squadra === 'object' && typeof squadra.photo === 'string' && squadra.photo !== ''))


const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('youth.og_title'),
    description: props.page?.meta_description || $t('youth.og_description'),
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
                <span v-if="cd.hero_subtitle" class="text-savino-fucsia-chiaro text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_subtitle }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title ?? $t('youth.og_title') }}</h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
                <p v-if="cd.hero_description" class="text-white/70 text-lg max-w-2xl mx-auto">{{ cd.hero_description }}</p>
            </div>
        </section>

        <!-- Introduction -->
        <section class="py-16 bg-white">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <span v-if="cd.intro_label" class="text-savino-fucsia text-sm font-bold uppercase tracking-wider">{{ cd.intro_label }}</span>
                        <h2 v-if="cd.intro_title" class="text-3xl font-black text-gray-900 uppercase tracking-tight mt-3 mb-4">{{ cd.intro_title }}</h2>
                        <div class="w-12 h-1 bg-savino-fucsia mb-6"></div>
                        <p v-if="cd.intro_paragraph_1" class="text-gray-600 leading-relaxed mb-4">
                            {{ cd.intro_paragraph_1 }}
                        </p>
                        <p v-if="cd.intro_paragraph_2" class="text-gray-600 leading-relaxed">
                            {{ cd.intro_paragraph_2 }}
                        </p>
                    </div>
                    <div class="bg-gradient-to-br from-savino-blue/5 to-savino-fucsia/5 rounded-2xl p-8 border border-gray-100">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="text-center">
                                <span class="text-4xl font-black text-savino-blue block">{{ cd.stat_athletes }}</span>
                                <span v-if="cd.stat_athletes_label" class="text-sm text-gray-500 font-semibold mt-1 block">{{ cd.stat_athletes_label }}</span>
                            </div>
                            <div class="text-center">
                                <span class="text-4xl font-black text-savino-fucsia block">{{ cd.stat_categories }}</span>
                                <span v-if="cd.stat_categories_label" class="text-sm text-gray-500 font-semibold mt-1 block">{{ cd.stat_categories_label }}</span>
                            </div>
                            <div class="text-center">
                                <span class="text-4xl font-black text-savino-red block">{{ cd.stat_coaches }}</span>
                                <span v-if="cd.stat_coaches_label" class="text-sm text-gray-500 font-semibold mt-1 block">{{ cd.stat_coaches_label }}</span>
                            </div>
                            <div class="text-center">
                                <span class="text-4xl font-black text-savino-blue block">{{ cd.stat_years }}</span>
                                <span v-if="cd.stat_years_label" class="text-sm text-gray-500 font-semibold mt-1 block">{{ cd.stat_years_label }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Foto delle squadre: grandi, una per squadra -->
        <section v-if="teamPhotos.length" class="py-16 bg-gray-50" data-test="youth-team-photos">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="cd.team_photos_heading" class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight">{{ cd.team_photos_heading }}</h2>
                    <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4"></div>
                </div>

                <div class="space-y-14">
                    <figure v-for="(squadra, index) in teamPhotos" :key="index">
                        <figcaption class="text-center mb-5">
                            <h3 class="text-2xl font-black text-savino-blue uppercase tracking-tight">{{ squadra.title }}</h3>
                            <p v-if="squadra.caption" class="text-gray-500 text-sm mt-1">{{ squadra.caption }}</p>
                        </figcaption>
                        <img
                            :src="squadra.photo"
                            :alt="squadra.title || ''"
                            class="w-full h-auto rounded-2xl shadow-xl"
                            loading="lazy"
                        />
                    </figure>
                </div>
            </div>
        </section>

        <!-- Talent Scouting -->
        <section class="py-16 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <span v-if="cd.scouting_label" class="text-savino-fucsia-chiaro text-sm font-bold uppercase tracking-[0.3em]">{{ cd.scouting_label }}</span>
                <h2 v-if="cd.scouting_title" class="text-3xl md:text-4xl font-black text-white uppercase tracking-tight mt-4 mb-4">{{ cd.scouting_title }}</h2>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-8"></div>
                <p v-if="cd.scouting_description" class="text-white/70 text-lg leading-relaxed max-w-2xl mx-auto mb-6">
                    {{ cd.scouting_description }}
                </p>
                <p v-if="cd.scouting_info" class="text-white/50 text-sm mb-8">
                    {{ cd.scouting_info }}
                </p>
                <!-- Un solo pulsante, e apre la posta verso il settore giovanile. Erano
                     due: "Contattaci" rimandava alla pagina Contatti generale, dove chi
                     cerca il vivaio deve ricominciare da capo a scegliere un destinatario. -->
                <div class="flex justify-center">
                    <a
                        v-if="scoutingEmail && cd.scouting_cta_primary"
                        :href="'mailto:' + scoutingEmail"
                        class="inline-flex items-center justify-center px-8 py-3.5 bg-savino-fucsia text-white font-bold uppercase tracking-wider rounded-lg hover:bg-savino-fucsia/90 transition-all duration-300 shadow-lg shadow-savino-fucsia/30 text-sm"
                    >
                        {{ cd.scouting_cta_primary }}
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- Page Content (CMS) -->
        <section v-if="page?.content" class="py-16 bg-gray-50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    class="prose prose-lg max-w-none prose-headings:font-black prose-headings:uppercase prose-headings:tracking-tight prose-a:text-savino-blue"
                   
                    v-html="safeContent"
                ></div>
            </div>
        </section>
    </PublicLayout>
</template>
