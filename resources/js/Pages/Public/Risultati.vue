<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SeasonNav from '@/Components/SeasonNav.vue'
import TeamLogo from '@/Components/TeamLogo.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useOgMeta } from '@/Composables/useOgMeta'
import { useLocale } from '@/Composables/useLocale.js'

const $t = useTranslations();
const { formatDate, formatTime } = useLocale();

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    },
    games: {
        type: Array,
        default: () => []
    },
    standings: {
        type: Array,
        default: () => []
    },
    seasonName: {
        type: String,
        default: '',
    },
    pageTitle: {
        type: String,
        default: null,
    },
    showStandings: {
        type: Boolean,
        default: true,
    },
    competition: {
        type: String,
        default: 'championship',
    }
})

// Gare e classifica arrivano già normalizzate dal backend (PublicController),
// che conosce quali squadre sono del club e se una gara è stata giocata. Qui non
// si inventano dati: se la sincronizzazione con la Lega non ha ancora popolato
// nulla, si mostra uno stato vuoto esplicito.
const displayGames = computed(() => props.games)

// Estratto di classifica: la tabella completa (quozienti e ripartizione dei
// set) vive nella pagina dedicata, qui bastano le prime posizioni. Se la nostra
// squadra è fuori dal taglio viene aggiunta comunque, altrimenti la sezione
// perderebbe l'unica riga che interessa davvero al tifoso.
const PREVIEW_ROWS = 6

const displayStandings = computed(() => {
    const top = props.standings.slice(0, PREVIEW_ROWS)
    const own = props.standings.find((row) => row.isOwn)

    if (own && !top.includes(own)) {
        top.push(own)
    }

    return top
})

const hasHiddenStandings = computed(() => props.standings.length > displayStandings.value.length)

// Il tab attivo della barra di sezione: il componente è condiviso con la
// pagina Classifica e con le pagine di coppa.
const activeTab = computed(() => {
    switch (props.competition) {
        case 'Champions League':
        case 'champions_league':
            return 'cev'
        case 'Coppa Italia':
        case 'coppa_italia':
            return 'coppa-italia'
        default:
            return 'risultati'
    }
})

function gameDate(game) {
    return game.matchDate
        ? formatDate(game.matchDate, { day: '2-digit', month: 'short', year: 'numeric' })
        : ''
}

function gameTime(game) {
    // Solo l'ora: la data è già nella colonna a fianco, e formatDate la
    // ripeterebbe ("04/10/2026, 17:00" al posto di "17:00").
    return game.matchDate ? formatTime(game.matchDate) : ''
}

const theme = computed(() => {
    switch (props.competition) {
        case 'Champions League':
        case 'champions_league':
            return {
                bgGradient: 'from-green-900 via-yellow-600 to-green-900',
                dateBg: 'bg-green-700',
                headerBg: 'bg-green-700',
            };
        case 'Coppa Italia':
        case 'coppa_italia':
            return {
                bgGradient: 'from-savino-red via-red-900 to-savino-pink',
                dateBg: 'bg-savino-red',
                headerBg: 'bg-savino-red',
            };
        case 'Campionato':
        case 'championship':
        default:
            return {
                bgGradient: 'from-gray-900 via-savino-blue to-gray-900',
                dateBg: 'bg-savino-blue',
                headerBg: 'bg-savino-blue',
            };
    }
});

const ogMeta = useOgMeta({
    title: props.pageTitle || props.page?.title || $t('risultati.og_title'),
    description: $t('risultati.og_description'),
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
            <div class="absolute inset-0 bg-gradient-to-br" :class="theme.bgGradient"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ seasonName || $t('risultati.current_season') }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ pageTitle || page?.title || $t('risultati.og_title') }}</h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">{{ $t('risultati.hero_subtitle') }}</p>
            </div>
        </section>

        <SeasonNav :active="activeTab" />

        <!-- Ultime Partite -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight mb-2">{{ $t('risultati.latest_matches') }}</h2>
                <div class="w-12 h-1 bg-savino-gold mb-10"></div>

                <p
                    v-if="displayGames.length === 0"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-10 text-center text-gray-500"
                >{{ $t('risultati.no_matches') }}</p>

                <div v-else class="space-y-4">
                    <!-- La card diventa un link solo se la Lega ha già sincronizzato
                         il tabellino: la rotta della scheda risponde 404 altrimenti. -->
                    <component
                        :is="game.hasStats ? Link : 'div'"
                        v-for="game in displayGames"
                        :key="game.id"
                        :href="game.hasStats ? route('stagione.partita', { game: game.id }) : undefined"
                        class="block bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden border border-gray-100"
                    >
                        <!-- Desktop layout (sm+) -->
                        <div class="hidden sm:flex items-center">
                            <!-- Date -->
                            <div class="w-32 flex-shrink-0 text-white text-center py-5 px-3" :class="theme.dateBg">
                                <span class="text-xs font-bold uppercase tracking-wider block">{{ gameDate(game) }}</span>
                                <span class="text-[10px] text-white/60 mt-1 block">{{ game.matchdayLabel }}</span>
                            </div>
                            <!-- Match -->
                            <div class="flex-1 flex items-center justify-between px-8 py-4">
                                <div class="flex-1 flex items-center justify-end gap-3 pr-4">
                                    <span
                                        class="text-base font-bold whitespace-nowrap"
                                        :class="game.homeIsOwn ? 'text-savino-blue' : 'text-gray-700'"
                                    >{{ game.home }}</span>
                                    <TeamLogo :src="game.homeLogo" :name="game.home" size="md" />
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <!-- Le gare non ancora disputate mostrano l'orario, non un punteggio -->
                                    <template v-if="game.played">
                                        <span class="text-2xl font-black text-gray-900">{{ game.scoreHome }}</span>
                                        <span class="text-gray-400 text-lg">-</span>
                                        <span class="text-2xl font-black text-gray-900">{{ game.scoreAway }}</span>
                                    </template>
                                    <span v-else class="text-base font-bold text-gray-500 tabular-nums">{{ gameTime(game) }}</span>
                                </div>
                                <div class="flex-1 flex items-center gap-3 pl-4">
                                    <TeamLogo :src="game.awayLogo" :name="game.away" size="md" />
                                    <span
                                        class="text-base font-bold whitespace-nowrap"
                                        :class="game.awayIsOwn ? 'text-savino-blue' : 'text-gray-700'"
                                    >{{ game.away }}</span>
                                </div>
                            </div>
                            <!-- Result Badge: assente finché la gara non è stata giocata -->
                            <div class="w-32 flex-shrink-0 flex flex-col items-center gap-1 pr-4">
                                <span
                                    v-if="game.result"
                                    class="px-3 py-1 rounded-full text-xs font-bold uppercase"
                                    :class="game.result === 'win' ? 'bg-green-100 text-green-700' : 'bg-savino-red/10 text-savino-red'"
                                >{{ game.result === 'win' ? $t('risultati.win') : $t('risultati.loss') }}</span>
                                <span
                                    v-if="game.hasStats"
                                    class="text-[10px] font-bold uppercase tracking-wider text-savino-blue"
                                >{{ $t('risultati.view_box_score') }} &rarr;</span>
                            </div>
                        </div>

                        <!-- Mobile layout (<sm) -->
                        <div class="sm:hidden">
                            <!-- Header: date + status + badge -->
                            <div class="flex items-center justify-between text-white px-4 py-2.5" :class="theme.dateBg">
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wider">{{ gameDate(game) }}</span>
                                    <span class="text-[10px] text-white/50 ml-2">{{ game.matchdayLabel }}</span>
                                </div>
                                <span
                                    v-if="game.result"
                                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                    :class="game.result === 'win' ? 'bg-green-400/20 text-green-300' : 'bg-red-400/20 text-red-300'"
                                >{{ game.result === 'win' ? $t('risultati.win') : $t('risultati.loss') }}</span>
                                <span v-else class="text-[10px] font-bold uppercase text-white/70 tabular-nums">{{ gameTime(game) }}</span>
                            </div>
                            <!-- Teams -->
                            <div class="px-4 py-3 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-2 min-w-0 mr-3">
                                        <TeamLogo :src="game.homeLogo" :name="game.home" size="sm" />
                                        <span
                                            class="text-sm font-bold truncate"
                                            :class="game.homeIsOwn ? 'text-savino-blue' : 'text-gray-700'"
                                        >{{ game.home }}</span>
                                    </span>
                                    <span v-if="game.played" class="text-xl font-black text-gray-900 flex-shrink-0">{{ game.scoreHome }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-2 min-w-0 mr-3">
                                        <TeamLogo :src="game.awayLogo" :name="game.away" size="sm" />
                                        <span
                                            class="text-sm font-bold truncate"
                                            :class="game.awayIsOwn ? 'text-savino-blue' : 'text-gray-700'"
                                        >{{ game.away }}</span>
                                    </span>
                                    <span v-if="game.played" class="text-xl font-black text-gray-900 flex-shrink-0">{{ game.scoreAway }}</span>
                                </div>
                                <p
                                    v-if="game.hasStats"
                                    class="text-[10px] font-bold uppercase tracking-wider text-savino-blue pt-1"
                                >{{ $t('risultati.view_box_score') }} &rarr;</p>
                            </div>
                        </div>
                    </component>
                </div>
            </div>
        </section>

        <!-- Classifica -->
        <section v-if="showStandings" class="py-16 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-4 mb-2">
                    <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight">{{ $t('risultati.standings') }}</h2>
                    <Link
                        :href="route('stagione.classifica')"
                        class="text-sm font-bold text-savino-blue hover:text-savino-gold transition-colors"
                    >{{ $t('risultati.full_standings') }} &rarr;</Link>
                </div>
                <div class="w-12 h-1 bg-savino-gold mb-10"></div>

                <p
                    v-if="displayStandings.length === 0"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-10 text-center text-gray-500"
                >{{ $t('risultati.no_standings') }}</p>

                <div v-else class="relative">
                    <div class="overflow-x-auto rounded-xl shadow-lg border border-gray-100">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-white" :class="theme.headerBg">
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider">{{ $t('risultati.col_pos') }}</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider">{{ $t('risultati.col_team') }}</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_played_short') }}</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_won_short') }}</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_lost_short') }}</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_sets_won_short') }}</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_sets_lost_short') }}</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_points') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in displayStandings"
                                :key="row.pos"
                                class="border-b border-gray-100 transition-colors duration-200"
                                :class="row.isOwn ? 'bg-savino-gold/10 font-bold' : 'hover:bg-gray-50'"
                            >
                                <td class="px-4 py-3 text-sm">
                                    <span
                                        class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black"
                                        :class="row.pos <= 3 ? 'bg-savino-gold text-white' : 'bg-gray-200 text-gray-600'"
                                    >{{ row.pos }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <TeamLogo :src="row.logo" :name="row.team" size="md" />
                                        <span class="text-sm font-bold whitespace-nowrap" :class="row.isOwn ? 'text-savino-blue' : 'text-gray-900'">{{ row.team }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600">{{ row.played }}</td>
                                <td class="px-4 py-3 text-sm text-center text-green-600 font-semibold">{{ row.won }}</td>
                                <td class="px-4 py-3 text-sm text-center text-savino-red font-semibold">{{ row.lost }}</td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600">{{ row.setWon }}</td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600">{{ row.setLost }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-lg font-black text-savino-blue">{{ row.pts }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    <!-- Mobile scroll hint gradient -->
                    <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-white to-transparent pointer-events-none rounded-r-xl md:hidden"></div>
                </div>

                <p v-if="hasHiddenStandings" class="text-center mt-6">
                    <Link
                        :href="route('stagione.classifica')"
                        class="inline-block px-6 py-3 rounded-full bg-savino-blue text-white text-xs font-bold uppercase tracking-wider hover:bg-savino-gold transition-colors"
                    >{{ $t('risultati.full_standings') }}</Link>
                </p>

                <p class="text-xs text-gray-400 mt-4 text-center">{{ $t('risultati.legend') }}</p>
            </div>
        </section>
    </PublicLayout>
</template>

