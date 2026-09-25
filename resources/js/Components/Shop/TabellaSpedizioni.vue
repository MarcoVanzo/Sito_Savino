<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';

/*
 * Costi e tempi di spedizione per zona, letti dalle zone del pannello: sono
 * gli stessi numeri con cui il checkout calcola la spedizione.
 */
const $t = useTranslations();

defineProps({
    zone: { type: Array, required: true },
});

// I numeri seguono la lingua della pagina: in inglese "€7.90", non "7,90 €".
const page = usePage();
const localeNumeri = computed(() => (page.props.locale === 'en' ? 'en-GB' : 'it-IT'));

const euro = (valore) => new Intl.NumberFormat(localeNumeri.value, { style: 'currency', currency: 'EUR' }).format(Number(valore));

const paese = (codice) => {
    if (codice === '*') return $t('spedizioni.other_countries');
    const tradotto = $t(`countries.${codice}`);
    return tradotto && tradotto !== `countries.${codice}` ? tradotto : codice;
};

const peso = (kg) => `${new Intl.NumberFormat(localeNumeri.value, { maximumFractionDigits: 3 }).format(Number(kg))} kg`;

// L'ultima fascia puo' restare senza limite: "oltre" il limite della
// precedente. Se e' l'unica, vale per qualsiasi peso ("oltre 0 kg" non
// dice niente).
const etichettaFascia = (fasce, j) => {
    const fascia = fasce[j];
    if (fascia.max_weight !== null && fascia.max_weight !== undefined) {
        return $t('spedizioni.up_to', { kg: peso(fascia.max_weight) });
    }
    const precedente = fasce[j - 1]?.max_weight;
    return precedente ? $t('spedizioni.over', { kg: peso(precedente) }) : $t('spedizioni.any_weight');
};

const haGiorni = (z) => z.giorni_min !== null && z.giorni_min !== undefined
    && z.giorni_max !== null && z.giorni_max !== undefined;
</script>

<template>
    <div class="mt-12">
        <h2 class="text-2xl font-bold text-savino-blue mb-6">{{ $t('spedizioni.table_title') }}</h2>
        <div class="overflow-x-auto rounded-2xl border border-gray-200">
            <table class="min-w-full text-sm text-left text-gray-800">
                <caption class="sr-only">{{ $t('spedizioni.table_title') }}</caption>
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ $t('spedizioni.col_zone') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ $t('spedizioni.col_cost') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ $t('spedizioni.col_free') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ $t('spedizioni.col_days') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="(z, i) in zone" :key="i" class="align-top">
                        <th scope="row" class="px-4 py-3 font-semibold">
                            {{ z.nome }}
                            <span class="block text-xs font-normal text-gray-600 mt-1">{{ (z.paesi ?? []).map(paese).join(', ') }}</span>
                        </th>
                        <td class="px-4 py-3">
                            <ul v-if="z.fasce?.length" class="space-y-0.5">
                                <li v-for="(f, j) in z.fasce" :key="j">
                                    {{ etichettaFascia(z.fasce, j) }}:
                                    <strong>{{ euro(f.rate) }}</strong>
                                </li>
                            </ul>
                            <strong v-else>{{ euro(z.tariffa) }}</strong>
                        </td>
                        <td class="px-4 py-3">{{ z.soglia_gratuita !== null && z.soglia_gratuita !== undefined ? $t('spedizioni.free_from', { amount: euro(z.soglia_gratuita) }) : '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <template v-if="haGiorni(z)">{{ $t('spedizioni.days', { min: z.giorni_min, max: z.giorni_max }) }}</template>
                            <template v-else>—</template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-600">{{ $t('spedizioni.table_note') }}</p>
    </div>
</template>
