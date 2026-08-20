<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import TeamLogo from '@/Components/TeamLogo.vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useLocale } from '@/Composables/useLocale.js';
import { useOgMeta } from '@/Composables/useOgMeta';

const $t = useTranslations();
const { formatDate, formatTime, formatNumber } = useLocale();

const props = defineProps({
    game: {
        type: Object,
        required: true,
    },
    homeStats: {
        type: Array,
        default: () => [],
    },
    awayStats: {
        type: Array,
        default: () => [],
    },
});

// La rotta risponde 404 se la gara non è stata giocata o non ha tabellino:
// qui i dati ci sono per costruzione, resta solo da presentarli.
const sides = computed(() => [
    { key: 'home', team: props.game.home, stats: props.homeStats },
    { key: 'away', team: props.game.away, stats: props.awayStats },
]);

const allStats = computed(() => [...props.homeStats, ...props.awayStats]);

const hasValue = (field) => allStats.value.some((row) => row[field] !== null && row[field] !== undefined);

// Le percentuali non sono nel tabellino di tutte le gare: le colonne compaiono
// solo quando c'è almeno un dato reale, invece di riempirle di trattini.
const showAttackPct = computed(() => hasValue('attackPct'));
const showReceptionPct = computed(() => hasValue('receptionPositivePct') || hasValue('receptionPerfectPct'));

const matchDateLabel = computed(() => props.game.matchDate
    ? formatDate(props.game.matchDate, { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })
    : null);

// Solo l'orario: la data per esteso è già nella stessa riga.
const matchTimeLabel = computed(() => props.game.matchDate
    ? formatTime(props.game.matchDate)
    : null);

const spectatorsLabel = computed(() => (props.game.spectators === null || props.game.spectators === undefined)
    ? null
    : formatNumber(props.game.spectators));

const totalDuration = computed(() => {
    const durations = (props.game.sets ?? []).map((set) => set.duration).filter((d) => typeof d === 'number');
    return durations.length === (props.game.sets ?? []).length && durations.length > 0
        ? durations.reduce((sum, d) => sum + d, 0)
        : null;
});

function pct(value) {
    return (value === null || value === undefined) ? '—' : `${value}%`;
}

function num(value) {
    return (value === null || value === undefined) ? '—' : value;
}

const ogMeta = useOgMeta({
    title: `${props.game.home.name} - ${props.game.away.name}`,
    description: $t('partita.og_description', {
        home: props.game.home.name,
        away: props.game.away.name,
    }),
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
        <!-- Intestazione con le due squadre e il punteggio in set -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-20">
                <p class="text-center text-savino-fucsia text-xs font-bold uppercase tracking-[0.3em]">
                    {{ game.competitionLabel }}<span v-if="game.matchdayLabel"> · {{ game.matchdayLabel }}</span>
                </p>

                <div class="mt-8 grid grid-cols-[1fr_auto_1fr] items-center gap-3 md:gap-8">
                    <div class="text-center">
                        <TeamLogo :src="game.home.logo" :name="game.home.name" size="lg" class="mx-auto" />
                        <p
                            class="mt-3 text-sm md:text-lg font-black uppercase tracking-tight"
                            :class="game.home.isOwn ? 'text-savino-fucsia' : 'text-white'"
                        >{{ game.home.name }}</p>
                    </div>
                    <div class="flex items-center gap-2 md:gap-4">
                        <span class="text-4xl md:text-6xl font-black text-white tabular-nums">{{ game.home.score }}</span>
                        <span class="text-2xl md:text-4xl text-white/40">-</span>
                        <span class="text-4xl md:text-6xl font-black text-white tabular-nums">{{ game.away.score }}</span>
                    </div>
                    <div class="text-center">
                        <TeamLogo :src="game.away.logo" :name="game.away.name" size="lg" class="mx-auto" />
                        <p
                            class="mt-3 text-sm md:text-lg font-black uppercase tracking-tight"
                            :class="game.away.isOwn ? 'text-savino-fucsia' : 'text-white'"
                        >{{ game.away.name }}</p>
                    </div>
                </div>

                <p class="mt-8 text-center text-white/70 text-sm">
                    <span v-if="matchDateLabel">{{ matchDateLabel }}</span>
                    <span v-if="matchTimeLabel"> · {{ matchTimeLabel }}</span>
                    <span v-if="game.location"> · {{ game.location }}</span>
                </p>

                <div class="mt-6 text-center">
                    <Link
                        :href="route('stagione.risultati')"
                        class="inline-block text-xs font-bold uppercase tracking-wider text-savino-fucsia hover:text-white transition-colors"
                    >&larr; {{ $t('partita.back_to_results') }}</Link>
                </div>
            </div>
        </section>

        <!-- Set: punteggio, durata e parziali -->
        <section class="py-14 bg-gray-50">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-black text-gray-900 uppercase tracking-tight mb-2">{{ $t('partita.sets') }}</h2>
                <div class="w-12 h-1 bg-savino-fucsia mb-8"></div>

                <p
                    v-if="!game.sets || game.sets.length === 0"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-8 text-center text-gray-500"
                >{{ $t('partita.no_sets') }}</p>

                <div v-else class="overflow-x-auto rounded-xl shadow-lg border border-gray-100 bg-white">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-savino-blue text-white">
                                <th scope="col" class="px-4 py-3 text-xs font-bold uppercase tracking-wider">{{ $t('partita.col_set') }}</th>
                                <th scope="col" class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_score') }}</th>
                                <th scope="col" class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_duration') }}</th>
                                <th scope="col" class="px-4 py-3 text-xs font-bold uppercase tracking-wider">{{ $t('partita.col_partials') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="set in game.sets" :key="set.set" class="border-b border-gray-100 last:border-0">
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 whitespace-nowrap">{{ $t('partita.set_n', { number: set.set }) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-base font-black tabular-nums" :class="set.home > set.away ? 'text-savino-blue' : 'text-gray-900'">{{ set.home }}</span>
                                    <span class="text-gray-400 mx-1">-</span>
                                    <span class="text-base font-black tabular-nums" :class="set.away > set.home ? 'text-savino-blue' : 'text-gray-900'">{{ set.away }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600 whitespace-nowrap">
                                    {{ set.duration === null ? '—' : $t('partita.minutes', { minutes: set.duration }) }}
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    <span v-if="set.partials && set.partials.length">{{ set.partials.join(' · ') }}</span>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="totalDuration !== null">
                            <tr class="bg-gray-50">
                                <td class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-600" colspan="2">{{ $t('partita.total_duration') }}</td>
                                <td class="px-4 py-3 text-sm text-center font-bold text-gray-900 whitespace-nowrap">{{ $t('partita.minutes', { minutes: totalDuration }) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Spettatori e arbitri: mostrati solo se la Lega li ha comunicati -->
                <dl v-if="spectatorsLabel || game.referees" class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div v-if="spectatorsLabel" class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $t('partita.spectators') }}</dt>
                        <dd class="mt-1 text-lg font-black text-savino-blue tabular-nums">{{ spectatorsLabel }}</dd>
                    </div>
                    <div v-if="game.referees" class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $t('partita.referees') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-800">{{ game.referees }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- Tabellino: entrambe le squadre -->
        <section class="py-14 bg-white">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-black text-gray-900 uppercase tracking-tight mb-2">{{ $t('partita.box_score') }}</h2>
                <div class="w-12 h-1 bg-savino-fucsia mb-8"></div>

                <div class="space-y-10">
                    <div v-for="side in sides" :key="side.key">
                        <div class="flex items-center gap-3 mb-4">
                            <TeamLogo :src="side.team.logo" :name="side.team.name" size="md" />
                            <h3
                                class="text-lg font-black uppercase tracking-tight"
                                :class="side.team.isOwn ? 'text-savino-blue' : 'text-gray-900'"
                            >{{ side.team.name }}</h3>
                        </div>

                        <p
                            v-if="side.stats.length === 0"
                            class="bg-gray-50 rounded-xl border border-gray-100 px-6 py-8 text-center text-gray-500"
                        >{{ $t('partita.no_box_score') }}</p>

                        <div v-else class="relative">
                            <div class="overflow-x-auto rounded-xl shadow-lg border border-gray-100">
                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="bg-savino-blue text-white">
                                            <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_jersey') }}</th>
                                            <th scope="col" class="px-4 py-3 text-xs font-bold uppercase tracking-wider">{{ $t('partita.col_player') }}</th>
                                            <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_sets_played') }}</th>
                                            <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_points') }}</th>
                                            <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_attack_points') }}</th>
                                            <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_blocks') }}</th>
                                            <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_aces') }}</th>
                                            <th v-if="showAttackPct" scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_attack_pct') }}</th>
                                            <template v-if="showReceptionPct">
                                                <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_reception_positive') }}</th>
                                                <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('partita.col_reception_perfect') }}</th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        <tr v-for="row in side.stats" :key="row.id" class="border-b border-gray-100 last:border-0 hover:bg-gray-50">
                                            <td class="px-3 py-3 text-sm text-center font-black text-gray-500 tabular-nums">{{ num(row.jersey) }}</td>
                                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                                <!-- Le avversarie non hanno una scheda sul nostro sito: solo il nome -->
                                                <Link
                                                    v-if="row.playerSlug"
                                                    :href="route('gallery.atleta', { slug: row.playerSlug })"
                                                    class="font-bold text-savino-blue hover:text-savino-fucsia transition-colors"
                                                >{{ row.name }}</Link>
                                                <span v-else class="font-semibold text-gray-800">{{ row.name }}</span>
                                                <span v-if="row.isCaptain" class="ml-2 text-[10px] font-bold uppercase text-savino-fucsia" :title="$t('partita.captain')">(C)</span>
                                                <span v-if="row.isLibero" class="ml-1 text-[10px] font-bold uppercase text-gray-400" :title="$t('partita.libero')">(L)</span>
                                            </td>
                                            <td class="px-3 py-3 text-sm text-center text-gray-600 tabular-nums">{{ num(row.setsPlayed) }}</td>
                                            <td class="px-3 py-3 text-center">
                                                <span class="text-base font-black text-savino-blue tabular-nums">{{ num(row.points) }}</span>
                                            </td>
                                            <td class="px-3 py-3 text-sm text-center text-gray-600 tabular-nums">{{ num(row.attackPoints) }}</td>
                                            <td class="px-3 py-3 text-sm text-center text-gray-600 tabular-nums">{{ num(row.blockPoints) }}</td>
                                            <td class="px-3 py-3 text-sm text-center text-gray-600 tabular-nums">{{ num(row.servePoints) }}</td>
                                            <td v-if="showAttackPct" class="px-3 py-3 text-sm text-center text-gray-600 tabular-nums">{{ pct(row.attackPct) }}</td>
                                            <template v-if="showReceptionPct">
                                                <td class="px-3 py-3 text-sm text-center text-gray-600 tabular-nums">{{ pct(row.receptionPositivePct) }}</td>
                                                <td class="px-3 py-3 text-sm text-center text-gray-600 tabular-nums">{{ pct(row.receptionPerfectPct) }}</td>
                                            </template>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-white to-transparent pointer-events-none rounded-r-xl md:hidden"></div>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-400 mt-6">{{ $t('partita.legend') }}</p>
            </div>
        </section>
    </PublicLayout>
</template>
