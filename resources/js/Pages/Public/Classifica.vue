<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SeasonNav from '@/Components/SeasonNav.vue';
import TeamLogo from '@/Components/TeamLogo.vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useLocale } from '@/Composables/useLocale.js';
import { useOgMeta } from '@/Composables/useOgMeta';

const $t = useTranslations();
const { formatDate } = useLocale();

const props = defineProps({
    standings: {
        type: Array,
        default: () => [],
    },
    seasonName: {
        type: String,
        default: '',
    },
    pageTitle: {
        type: String,
        default: null,
    },
    updatedAt: {
        type: String,
        default: null,
    },
});

// La classifica arriva già normalizzata dal backend. Le colonne facoltative
// (quozienti, ripartizione dei risultati) compaiono solo se la Lega le ha
// effettivamente sincronizzate: nessun valore inventato, nessuna colonna vuota.
const hasRatios = computed(() =>
    props.standings.some((row) => row.setRatio !== null || row.pointRatio !== null)
);

const hasBreakdown = computed(() =>
    props.standings.some((row) => [row.won30, row.won31, row.won32, row.lost23, row.lost13, row.lost03]
        .some((value) => value !== null && value !== undefined))
);

// Prima della prima giornata la Lega pubblica già l'elenco delle squadre, tutte
// a zero: mostrarlo come "classifica" fa credere a un dato sbagliato. Le
// posizioni sono solo l'ordine alfabetico, e va detto.
const seasonNotStarted = computed(() =>
    props.standings.length > 0 && props.standings.every((row) => !row.played)
);

function ratio(value) {
    return value === null || value === undefined ? '—' : Number(value).toFixed(2);
}

function count(value) {
    return value === null || value === undefined ? '—' : value;
}

const updatedLabel = computed(() => {
    if (!props.updatedAt) return null;
    return $t('classifica.updated_at', {
        date: formatDate(props.updatedAt, { day: '2-digit', month: 'long', year: 'numeric' }),
    });
});

const ogMeta = useOgMeta({
    title: $t('classifica.og_title'),
    description: $t('classifica.og_description'),
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
                <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ seasonName || $t('risultati.current_season') }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ $t('classifica.title') }}</h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">{{ pageTitle || $t('classifica.hero_subtitle') }}</p>
            </div>
        </section>

        <SeasonNav active="classifica" />

        <section class="py-16 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-4 mb-2">
                    <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight">{{ $t('classifica.full_table') }}</h2>
                    <Link
                        :href="route('stagione.risultati')"
                        class="text-sm font-bold text-savino-blue hover:text-savino-fucsia transition-colors"
                    >{{ $t('classifica.back_to_results') }} &rarr;</Link>
                </div>
                <div class="w-12 h-1 bg-savino-fucsia mb-4"></div>
                <p v-if="updatedLabel" class="text-xs text-gray-500 mb-8">{{ updatedLabel }}</p>
                <div v-else class="mb-8"></div>

                <p
                    v-if="standings.length === 0"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-10 text-center text-gray-500"
                >{{ $t('risultati.no_standings') }}</p>

                <p
                    v-else-if="seasonNotStarted"
                    class="bg-savino-blue/5 border border-savino-blue/20 rounded-xl px-6 py-4 text-sm text-savino-blue mb-6"
                >{{ $t('classifica.season_not_started') }}</p>

                <div v-if="standings.length" class="relative">
                    <div class="overflow-x-auto rounded-xl shadow-lg border border-gray-100 bg-white">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-savino-blue text-white">
                                    <th scope="col" class="px-4 py-3 text-xs font-bold uppercase tracking-wider">{{ $t('risultati.col_pos') }}</th>
                                    <th scope="col" class="px-4 py-3 text-xs font-bold uppercase tracking-wider">{{ $t('risultati.col_team') }}</th>
                                    <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_points') }}</th>
                                    <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_played_short') }}</th>
                                    <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_won_short') }}</th>
                                    <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_lost_short') }}</th>
                                    <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_sets_won_short') }}</th>
                                    <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('risultati.col_sets_lost_short') }}</th>
                                    <template v-if="hasRatios">
                                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('classifica.col_set_ratio_short') }}</th>
                                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('classifica.col_point_ratio_short') }}</th>
                                    </template>
                                    <template v-if="hasBreakdown">
                                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center whitespace-nowrap">3-0</th>
                                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center whitespace-nowrap">3-1</th>
                                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center whitespace-nowrap">3-2</th>
                                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center whitespace-nowrap">2-3</th>
                                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center whitespace-nowrap">1-3</th>
                                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center whitespace-nowrap">0-3</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in standings"
                                    :key="row.pos + '-' + row.team"
                                    class="border-b border-gray-100 transition-colors duration-200"
                                    :class="row.isOwn ? 'bg-savino-fucsia/10 font-bold' : 'hover:bg-gray-50'"
                                >
                                    <td class="px-4 py-3 text-sm">
                                        <span
                                            class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black"
                                            :class="row.pos <= 3 ? 'bg-savino-fucsia text-white' : 'bg-gray-200 text-gray-600'"
                                        >{{ row.pos }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <TeamLogo :src="row.logo" :name="row.team" size="md" />
                                            <span
                                                class="text-sm font-bold whitespace-nowrap"
                                                :class="row.isOwn ? 'text-savino-blue' : 'text-gray-900'"
                                            >{{ row.team }}</span>
                                            <span
                                                v-if="row.isOwn"
                                                class="px-2 py-0.5 rounded-full bg-savino-blue text-white text-[10px] font-bold uppercase tracking-wider"
                                            >{{ $t('classifica.our_team') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="text-lg font-black text-savino-blue">{{ row.pts }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-sm text-center text-gray-600">{{ count(row.played) }}</td>
                                    <td class="px-3 py-3 text-sm text-center text-green-600 font-semibold">{{ count(row.won) }}</td>
                                    <td class="px-3 py-3 text-sm text-center text-savino-red font-semibold">{{ count(row.lost) }}</td>
                                    <td class="px-3 py-3 text-sm text-center text-gray-600">{{ count(row.setWon) }}</td>
                                    <td class="px-3 py-3 text-sm text-center text-gray-600">{{ count(row.setLost) }}</td>
                                    <template v-if="hasRatios">
                                        <td class="px-3 py-3 text-sm text-center text-gray-600 tabular-nums">{{ ratio(row.setRatio) }}</td>
                                        <td class="px-3 py-3 text-sm text-center text-gray-600 tabular-nums">{{ ratio(row.pointRatio) }}</td>
                                    </template>
                                    <template v-if="hasBreakdown">
                                        <td class="px-3 py-3 text-sm text-center text-gray-500">{{ count(row.won30) }}</td>
                                        <td class="px-3 py-3 text-sm text-center text-gray-500">{{ count(row.won31) }}</td>
                                        <td class="px-3 py-3 text-sm text-center text-gray-500">{{ count(row.won32) }}</td>
                                        <td class="px-3 py-3 text-sm text-center text-gray-500">{{ count(row.lost23) }}</td>
                                        <td class="px-3 py-3 text-sm text-center text-gray-500">{{ count(row.lost13) }}</td>
                                        <td class="px-3 py-3 text-sm text-center text-gray-500">{{ count(row.lost03) }}</td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Suggerimento di scorrimento orizzontale su mobile -->
                    <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-gray-50 to-transparent pointer-events-none rounded-r-xl md:hidden"></div>
                </div>

                <p v-if="standings.length" class="text-xs text-gray-400 mt-4">{{ $t('risultati.legend') }}</p>
                <p v-if="standings.length && hasRatios" class="text-xs text-gray-400 mt-1">{{ $t('classifica.legend_ratios') }}</p>
                <p v-if="standings.length && hasBreakdown" class="text-xs text-gray-400 mt-1">{{ $t('classifica.legend_breakdown') }}</p>
            </div>
        </section>
    </PublicLayout>
</template>
