<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';

/*
 * Costi e tempi di spedizione per zona, letti dalle zone del pannello: sono
 * gli stessi numeri con cui il checkout calcola la spedizione.
 */
const $t = useTranslations();

defineProps({
    zone: { type: Array, required: true },
});

const euro = (valore) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(valore);

const paese = (codice) => {
    if (codice === '*') return $t('spedizioni.other_countries');
    const tradotto = $t(`countries.${codice}`);
    return tradotto && tradotto !== `countries.${codice}` ? tradotto : codice;
};

const peso = (kg) => `${String(kg).replace('.', ',')} kg`;
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
                            <span class="block text-xs font-normal text-gray-600 mt-1">{{ z.paesi.map(paese).join(', ') }}</span>
                        </th>
                        <td class="px-4 py-3">
                            <ul v-if="z.fasce.length" class="space-y-0.5">
                                <li v-for="(f, j) in z.fasce" :key="j">
                                    {{ f.max_weight === null ? $t('spedizioni.over', { kg: peso(z.fasce[j - 1]?.max_weight ?? 0) }) : $t('spedizioni.up_to', { kg: peso(f.max_weight) }) }}:
                                    <strong>{{ euro(f.rate) }}</strong>
                                </li>
                            </ul>
                            <strong v-else>{{ euro(z.tariffa) }}</strong>
                        </td>
                        <td class="px-4 py-3">{{ z.soglia_gratuita !== null ? $t('spedizioni.free_from', { amount: euro(z.soglia_gratuita) }) : '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <template v-if="z.giorni_min">{{ $t('spedizioni.days', { min: z.giorni_min, max: z.giorni_max }) }}</template>
                            <template v-else>—</template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-600">{{ $t('spedizioni.table_note') }}</p>
    </div>
</template>
