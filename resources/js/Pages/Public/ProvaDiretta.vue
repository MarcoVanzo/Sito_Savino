<script setup>
/**
 * La prova della diretta con sponsor, riservata allo staff
 * (DirettaController::prova). Stessa finestra che vede il tifoso, con spot e
 * cornici della gara; la cornice torna ogni minuto per vederla subito.
 */
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import LiveStreamModal from '@/Components/LiveStreamModal.vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useLocale } from '@/Composables/useLocale.js';

const $t = useTranslations();
const { formatDate, formatTime } = useLocale();

const props = defineProps({
    gara: { type: Object, required: true },
    sponsor: { type: Object, default: null },
    interruttoreAcceso: { type: Boolean, default: false },
    urlImposta: { type: String, default: null },
});

const diretta = ref(null);

const apri = () => {
    diretta.value = {
        title: props.gara.titolo,
        embedUrl: props.gara.embedUrl,
        url: null,
        sponsor: props.sponsor,
    };
};
</script>

<template>
    <Head>
        <title>{{ $t('stream.prova_titolo') }}</title>
        <meta name="robots" content="noindex, nofollow">
    </Head>

    <PublicLayout>
        <section class="mx-auto max-w-3xl px-4 py-16 sm:py-24">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#B8066A]">{{ $t('stream.prova_occhiello') }}</p>
            <h1 class="mt-2 text-3xl font-extrabold text-savino-blue">{{ gara.titolo }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ formatDate(gara.data) }} · {{ formatTime(gara.data) }}</p>

            <div class="mt-6 rounded-xl border border-savino-blue/20 bg-savino-blue/5 p-4 text-sm leading-relaxed text-gray-800">
                <p>{{ $t('stream.prova_spiegazione') }}</p>
                <p v-if="!interruttoreAcceso" class="mt-2 font-semibold">{{ $t('stream.prova_interruttore_spento') }}</p>
            </div>

            <p v-if="!gara.embedUrl" class="mt-6 font-semibold text-[#B8066A]" data-prova-avviso>{{ $t('stream.prova_senza_link') }}</p>
            <p v-else-if="!sponsor" class="mt-6 font-semibold text-[#B8066A]" data-prova-avviso>{{ $t('stream.prova_senza_sponsor') }}</p>

            <div class="mt-8 flex flex-wrap gap-3">
                <button
                    v-if="gara.embedUrl"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-savino-fucsia px-6 py-3 text-sm font-bold uppercase tracking-wider text-white hover:bg-savino-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-savino-blue"
                    data-prova-apri
                    @click="apri"
                >{{ $t('stream.watch') }}</button>
                <a
                    v-if="urlImposta"
                    :href="urlImposta"
                    class="inline-flex items-center rounded-xl border border-savino-blue px-6 py-3 text-sm font-bold uppercase tracking-wider text-savino-blue hover:bg-savino-blue hover:text-white"
                >{{ $t('stream.prova_torna') }}</a>
            </div>
        </section>

        <LiveStreamModal :stream="diretta" @close="diretta = null" />
    </PublicLayout>
</template>
