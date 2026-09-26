<script setup>
/**
 * Lo stato di un articolo indossato o autografato (maglia da gara, pallone
 * firmato), scritto dalla redazione nel pannello.
 *
 * Le condizioni di vendita vendono questi articoli «nello stato descritto
 * nella scheda»: senza questo riquadro la clausola non avrebbe niente a cui
 * rimandare. Sta accanto al prezzo, prima dell'acquisto, e non in fondo alla
 * descrizione. `scuro` e' per la pagina dell'asta, su fondo grigio scuro.
 */
defineProps({
    testo: { type: String, default: null },
    scuro: { type: Boolean, default: false },
});
</script>

<template>
    <section
        v-if="testo"
        :aria-label="$t('shop.item_condition')"
        class="rounded-xl border-l-4 px-4 py-3"
        :class="scuro
            ? 'border-savino-fucsia-chiaro bg-gray-800/50'
            : 'border-savino-blue bg-savino-blue/5'"
    >
        <h3
            class="text-sm font-bold uppercase tracking-wider mb-1"
            :class="scuro ? 'text-savino-fucsia-chiaro' : 'text-savino-blue'"
        >
            {{ $t('shop.item_condition') }}
        </h3>
        <p
            class="text-sm leading-relaxed whitespace-pre-line"
            :class="scuro ? 'text-gray-100' : 'text-gray-800'"
        >{{ testo }}</p>
    </section>
</template>
