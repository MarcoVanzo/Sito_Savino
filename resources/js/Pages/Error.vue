<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTranslations } from '@/Composables/useTranslations.js';

const $t = useTranslations();
import PublicLayout from '@/Layouts/PublicLayout.vue';

// Nei template Vue `window` non è un globale: `window.location` lì dentro era
// `undefined.location`, e "Riprova" su un 500/503 lanciava un TypeError.
const retry = () => router.reload();

// Una pagina scaduta (419) ha un token CSRF vecchio: serve una pagina nuova,
// non una visita Inertia che ripartirebbe con lo stesso token.
const ricarica = () => window.location.reload();

const props = defineProps({
    status: Number,
    // Testo gia' tradotto dal server per i casi che lo meritano (link firmato
    // scaduto: conferma della newsletter, disiscrizione, ricevuta di recesso).
    // Quando c'e' vince sul testo generico dello stato.
    messaggio: { type: String, default: null },
});

const title = computed(() => {
    const titles = {
        403: $t('error.403_title'),
        404: $t('error.404_title'),
        419: $t('error.419_title'),
        429: $t('error.429_title'),
        500: $t('error.500_title'),
        503: $t('error.503_title'),
    };
    return titles[props.status] || $t('error.generic_title') + ' ' + props.status;
});

const description = computed(() => {
    const descriptions = {
        403: $t('error.403_desc'),
        404: $t('error.404_desc'),
        419: $t('error.419_desc'),
        429: $t('error.429_desc'),
        500: $t('error.500_desc'),
        503: $t('error.503_desc'),
    };
    return props.messaggio || descriptions[props.status] || $t('error.generic_desc');
});
</script>

<template>
    <Head>
        <title>{{ title }} — Savino Del Bene Volley</title>
    </Head>
    <PublicLayout>
        <div class="min-h-[80vh] flex items-center justify-center bg-savino-blue relative overflow-hidden">
            <!-- Background decorativo -->
            <div class="absolute inset-0 bg-[url('/images/logo.png')] opacity-[0.03] bg-no-repeat bg-center bg-contain scale-150"></div>

            <div class="relative z-10 text-center px-4 max-w-xl mx-auto py-20">
                <!-- Status Code grande -->
                <p class="text-[120px] sm:text-[160px] font-black text-white/10 leading-none select-none" aria-hidden="true">
                    {{ status }}
                </p>

                <!-- Titolo -->
                <h1 class="text-3xl sm:text-4xl font-black text-white uppercase tracking-tighter -mt-8 mb-4">
                    {{ title }}
                </h1>

                <!-- Linea decorativa -->
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-6"></div>

                <!-- Descrizione -->
                <p class="text-gray-300 text-lg mb-10">
                    {{ description }}
                </p>

                <!-- CTA -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link
                        :href="route('home')"
                        class="inline-flex items-center justify-center px-8 py-4 border-2 border-savino-fucsia bg-savino-fucsia text-white text-sm font-bold uppercase tracking-widest transition-all duration-300 hover:bg-transparent hover:text-white"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4" /></svg>
                        {{ $t('error.back_home') }}
                    </Link>
                    <button type="button"
                        v-if="status === 419"
                        @click="ricarica"
                        class="inline-flex items-center justify-center px-8 py-4 border-2 border-white/20 text-white text-sm font-bold uppercase tracking-widest transition-all duration-300 hover:border-white/50 hover:bg-white/5"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        {{ $t('error.reload') }}
                    </button>
                    <button type="button"
                        v-if="status >= 500"
                        @click="retry"
                        class="inline-flex items-center justify-center px-8 py-4 border-2 border-white/20 text-white text-sm font-bold uppercase tracking-widest transition-all duration-300 hover:border-white/50 hover:bg-white/5"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        {{ $t('error.retry') }}
                    </button>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
