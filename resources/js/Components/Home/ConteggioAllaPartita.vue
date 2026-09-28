<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { tempoMancante } from '@/Support/conteggioAllaPartita.js';

const $t = useTranslations();

const props = defineProps({
    // Inizio della gara, ISO 8601 con fuso (Game::match_date).
    data: {
        type: String,
        default: null,
    },
});

// Si aggiorna al minuto, non al secondo: i secondi che corrono sono movimento
// continuo accanto al resto della pagina (WCAG 2.2.2), e a chi guarda la home
// non dicono niente in più.
const adesso = ref(Date.now());
let timer = null;

onMounted(() => {
    adesso.value = Date.now();
    timer = setInterval(() => {
        adesso.value = Date.now();
    }, 30 * 1000);
});

onUnmounted(() => clearInterval(timer));

const tempo = computed(() => tempoMancante(props.data, adesso.value));

const riassunto = computed(() => (tempo.value.stato === 'prima'
    ? $t('home.countdown_aria', { giorni: tempo.value.giorni, ore: tempo.value.ore, minuti: tempo.value.minuti })
    : ''));

const caselle = computed(() => (tempo.value.stato === 'prima'
    ? [
        { valore: tempo.value.giorni, etichetta: $t('home.countdown_days') },
        { valore: tempo.value.ore, etichetta: $t('home.countdown_hours') },
        { valore: tempo.value.minuti, etichetta: $t('home.countdown_minutes') },
    ]
    : []));
</script>

<template>
    <!-- role="timer" ha aria-live spento: lo screen reader legge il riassunto
         quando ci arriva, senza essere interrotto a ogni minuto. -->
    <div v-if="tempo.stato === 'prima'" role="timer" :aria-label="riassunto" class="text-center">
        <p aria-hidden="true" class="text-white/70 text-xs font-bold uppercase tracking-[0.25em] mb-3">
            {{ $t('home.countdown_label') }}
        </p>
        <div aria-hidden="true" class="inline-flex items-stretch gap-3">
            <div
                v-for="casella in caselle"
                :key="casella.etichetta"
                class="min-w-[4.5rem] rounded-lg bg-white/10 px-3 py-2"
            >
                <div class="text-white text-3xl md:text-4xl font-black tabular-nums leading-none">
                    {{ String(casella.valore).padStart(2, '0') }}
                </div>
                <div class="text-savino-fucsia-chiaro text-[0.65rem] font-bold uppercase tracking-widest mt-1">
                    {{ casella.etichetta }}
                </div>
            </div>
        </div>
    </div>
    <p v-else-if="tempo.stato === 'in_corso'" class="text-center">
        <span class="inline-flex items-center gap-2 rounded-full bg-savino-red px-4 py-2 text-white text-xs font-bold uppercase tracking-widest">
            <span class="w-2 h-2 rounded-full bg-white" aria-hidden="true"></span>
            {{ $t('home.countdown_live') }}
        </span>
    </p>
</template>
