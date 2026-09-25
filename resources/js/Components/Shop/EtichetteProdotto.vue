<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';

const $t = useTranslations();

/**
 * Le etichette sulla foto di un prodotto (NUOVO, HOT SALES, IN OFFERTA,
 * ULTIMO RIMASTO). Quali mostrare lo decide il backend
 * (App\Support\EtichetteDelProdotto), al massimo due: la prima nell'angolo in
 * alto a sinistra, la seconda in quello a destra.
 */
defineProps({
    etichette: { type: Array, default: () => [] },
    grandi: { type: Boolean, default: false },
});

const COLORI = {
    nuovo: 'bg-savino-fucsia text-white',
    hot_sales: 'bg-savino-blue text-white ring-1 ring-white/70',
    in_offerta: 'bg-savino-red text-white',
    ultimo_rimasto: 'bg-white text-savino-red ring-1 ring-savino-red/40',
};

const ANGOLI = ['left-3', 'right-3'];
</script>

<template>
    <div
        v-for="(etichetta, indice) in etichette.slice(0, 2)"
        :key="etichetta"
        :data-etichetta="etichetta"
        class="absolute font-black uppercase tracking-wider rounded-full shadow-lg"
        :class="[
            COLORI[etichetta] ?? 'bg-savino-blue text-white',
            ANGOLI[indice],
            grandi ? 'top-4 text-xs px-3 py-1.5' : 'top-3 text-[10px] px-3 py-1',
        ]"
    >
        {{ $t(`shop.badge_${etichetta}`) }}
    </div>
</template>
