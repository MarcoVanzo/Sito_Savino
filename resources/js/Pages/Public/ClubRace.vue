<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useSanitize } from '@/Composables/useSanitize'
import { useOgMeta } from '@/Composables/useOgMeta'
import { classificaClubRace } from '@/Support/clubRaceStandings.js'

const $t = useTranslations();

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    }
})

const { sanitize } = useSanitize()

// Il regolamento (assegnazione punti e premi) e' il contenuto della pagina,
// scritto nell'editor del pannello.
const safeContent = computed(() => sanitize(props.page?.content))
const cd = computed(() => props.page?.content_data ?? {})

// La classifica si compila a mano dal pannello (societa' e punti): qui si
// ordina per punteggio, cosi' la redazione non deve riordinare l'elenco a
// ogni aggiornamento.
const standings = computed(() => classificaClubRace(cd.value.standings))

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('club_race.og_title'),
    description: props.page?.meta_description || $t('club_race.og_description'),
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
        <!-- Hero: stessa testata delle altre pagine del Ticketing -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span v-if="cd.hero_label" class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_label }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title ?? $t('club_race.og_title') }}</h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
                <p v-if="cd.hero_subtitle" class="text-white/70 text-lg max-w-2xl mx-auto">{{ cd.hero_subtitle }}</p>
            </div>
        </section>

        <!-- Regolamento -->
        <section v-if="safeContent" class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-4xl mx-auto">
                <div
                    class="prose prose-lg max-w-none prose-headings:font-bold prose-headings:text-savino-blue prose-a:text-savino-fucsia prose-a:no-underline hover:prose-a:underline"
                    v-html="safeContent"
                ></div>
            </div>
        </section>

        <!-- Classifica -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-4xl mx-auto">
                <h2 v-if="cd.standings_title" class="text-3xl font-black text-gray-900 uppercase tracking-tight text-center mb-2">
                    {{ cd.standings_title }}
                </h2>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-4"></div>
                <p v-if="cd.standings_updated" class="text-center text-sm text-gray-500 mb-10">
                    {{ $t('club_race.standings_updated') }} {{ cd.standings_updated }}
                </p>
                <div v-else class="mb-10"></div>

                <div v-if="standings.length === 0" class="max-w-2xl mx-auto text-center bg-white rounded-2xl border border-gray-100 shadow-sm px-8 py-12">
                    <p class="text-gray-600 text-lg">{{ $t('club_race.standings_empty') }}</p>
                </div>

                <div v-else class="overflow-x-auto bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-gray-200 text-xs font-bold uppercase tracking-widest text-gray-500">
                                <th scope="col" class="px-6 py-4 w-16">{{ $t('club_race.position') }}</th>
                                <th scope="col" class="px-6 py-4">{{ $t('club_race.club') }}</th>
                                <th scope="col" class="px-6 py-4 text-right">{{ $t('club_race.points') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="riga in standings"
                                :key="riga.club"
                                class="border-b border-gray-100 last:border-0"
                                :class="riga.position <= 3 ? 'bg-savino-fucsia/5' : ''"
                            >
                                <td class="px-6 py-4 font-black" :class="riga.position <= 3 ? 'text-savino-fucsia' : 'text-gray-400'">{{ riga.position }}</td>
                                <td class="px-6 py-4 font-bold text-gray-900">{{ riga.club }}</td>
                                <td class="px-6 py-4 text-right font-black text-savino-blue tabular-nums">{{ riga.points }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="cd.standings_note" class="text-center text-sm text-gray-500 mt-6">{{ cd.standings_note }}</p>
            </div>
        </section>
    </PublicLayout>
</template>
