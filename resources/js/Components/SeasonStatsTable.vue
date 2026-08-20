<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useLocale } from '@/Composables/useLocale.js';
import { displayRole } from '@/data/playerRoles';

/**
 * Totali di stagione della rosa, confrontabili fra atlete.
 *
 * Il senso della tabella è il confronto, quindi l'ordinamento è client-side su
 * ogni colonna numerica: il backend serve una pagina cachata uguale per tutti e
 * riordinarla non deve costare una richiesta.
 *
 * Le righe arrivano già filtrate dal controller: se una squadra non ha
 * tabellini della Lega (le giovanili) l'array è vuoto e il chiamante mostra lo
 * stato esplicito, non una griglia di zeri.
 */
const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
});

const $t = useTranslations();
const { locale } = useLocale();

// Ogni colonna dichiara come si formatta e come si ordina: il template resta
// una sola cella generica invece di dieci varianti copiate.
const COLUMNS = [
    { key: 'matchesPlayed', label: 'stagione.stats_col_matches', format: 'int' },
    { key: 'setsPlayed', label: 'stagione.stats_col_sets', format: 'int' },
    { key: 'points', label: 'stagione.stats_col_points', format: 'int', highlight: true },
    { key: 'pointsPerSet', label: 'stagione.stats_col_points_per_set', format: 'decimal' },
    { key: 'attackPoints', label: 'stagione.stats_col_attack_points', format: 'int' },
    { key: 'attackPct', label: 'stagione.stats_col_attack_pct', format: 'pct' },
    { key: 'blocks', label: 'stagione.stats_col_blocks', format: 'int' },
    { key: 'aces', label: 'stagione.stats_col_aces', format: 'int' },
    { key: 'receptionPositivePct', label: 'stagione.stats_col_reception_positive', format: 'pct' },
    { key: 'receptionPerfectPct', label: 'stagione.stats_col_reception_perfect', format: 'pct' },
];

// Una colonna compare solo se almeno un'atleta ha quel dato: le percentuali
// mancano dove il fondamentale non è mai stato eseguito, e una colonna di
// trattini non aggiunge nulla.
const visibleColumns = computed(() => COLUMNS.filter(
    (column) => props.rows.some((row) => row[column.key] !== null && row[column.key] !== undefined)
));

const sortKey = ref('points');
const sortDir = ref('desc');

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc';

        return;
    }

    sortKey.value = key;
    // I nomi si leggono dall'inizio, i numeri dal migliore: default diversi.
    sortDir.value = key === 'name' ? 'asc' : 'desc';
}

function ariaSort(key) {
    if (sortKey.value !== key) return 'none';

    return sortDir.value === 'asc' ? 'ascending' : 'descending';
}

// Freccia piena sulla colonna attiva, sbiadita sulle altre: si vede quali sono
// riordinabili senza aggiungere un'icona per ogni intestazione.
function sortIcon(key) {
    return sortKey.value === key && sortDir.value === 'asc' ? '▲' : '▼';
}

// A parità di valore il numero di maglia dà un ordine stabile: senza,
// due atlete con gli stessi punti si scambierebbero a ogni riordino.
function byJersey(a, b) {
    return (a.jersey ?? Number.MAX_SAFE_INTEGER) - (b.jersey ?? Number.MAX_SAFE_INTEGER);
}

const sortedRows = computed(() => {
    const key = sortKey.value;
    const direction = sortDir.value === 'asc' ? 1 : -1;

    return [...props.rows].sort((a, b) => {
        const left = a[key];
        const right = b[key];
        const leftMissing = left === null || left === undefined;
        const rightMissing = right === null || right === undefined;

        // I dati mancanti restano in coda in entrambe le direzioni: sono
        // assenze, non valori bassi.
        if (leftMissing || rightMissing) {
            if (leftMissing && rightMissing) return byJersey(a, b);

            return leftMissing ? 1 : -1;
        }

        const comparison = typeof left === 'string'
            ? left.localeCompare(right, locale.value)
            : left - right;

        return comparison !== 0 ? comparison * direction : byJersey(a, b);
    });
});

function formatCell(value, format) {
    if (value === null || value === undefined) return '—';
    if (format === 'pct') return `${value}%`;
    // Due decimali come i quozienti della classifica: stessa lettura.
    if (format === 'decimal') return Number(value).toFixed(2);

    return value;
}
</script>

<template>
    <div class="relative">
        <div class="overflow-x-auto rounded-xl shadow-lg border border-gray-100 bg-white">
            <table class="w-full text-left">
                <caption class="sr-only">{{ $t('stagione.stats_caption') }}</caption>
                <thead>
                    <tr class="bg-savino-blue text-white">
                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">{{ $t('stagione.stats_col_jersey') }}</th>
                        <th scope="col" class="px-4 py-3 text-xs font-bold uppercase tracking-wider" :aria-sort="ariaSort('name')">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-savino-fucsia transition-colors"
                                @click="toggleSort('name')"
                            >
                                {{ $t('stagione.stats_col_player') }}
                                <span aria-hidden="true" class="text-[9px]" :class="sortKey === 'name' ? 'opacity-100' : 'opacity-40'">{{ sortIcon('name') }}</span>
                            </button>
                        </th>
                        <th
                            v-for="column in visibleColumns"
                            :key="column.key"
                            scope="col"
                            class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center whitespace-nowrap"
                            :aria-sort="ariaSort(column.key)"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-savino-fucsia transition-colors"
                                @click="toggleSort(column.key)"
                            >
                                {{ $t(column.label) }}
                                <span aria-hidden="true" class="text-[9px]" :class="sortKey === column.key ? 'opacity-100' : 'opacity-40'">{{ sortIcon(column.key) }}</span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <tr
                        v-for="row in sortedRows"
                        :key="row.id"
                        class="border-b border-gray-100 last:border-0 hover:bg-gray-50"
                    >
                        <td class="px-3 py-3 text-sm text-center font-black text-gray-500 tabular-nums">{{ row.jersey ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm whitespace-nowrap">
                            <Link
                                :href="route('gallery.atleta', { slug: row.playerSlug })"
                                class="font-bold text-savino-blue hover:text-savino-fucsia transition-colors"
                            >{{ row.name }}</Link>
                            <span v-if="row.role" class="block text-[10px] font-bold uppercase tracking-wider text-savino-fucsia">
                                {{ displayRole(row.role, $t) }}
                            </span>
                        </td>
                        <td
                            v-for="column in visibleColumns"
                            :key="column.key"
                            class="px-3 py-3 text-center tabular-nums"
                            :class="column.highlight ? 'text-base font-black text-savino-blue' : 'text-sm text-gray-600'"
                        >{{ formatCell(row[column.key], column.format) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Suggerimento di scorrimento orizzontale su mobile -->
        <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-white to-transparent pointer-events-none rounded-r-xl md:hidden"></div>
    </div>
</template>
