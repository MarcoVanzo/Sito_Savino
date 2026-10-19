<script setup>
/**
 * L'indirizzo fisso della diretta (`/diretta`, DirettaController::pagina), da
 * promuovere sui social: la gara della società in corso o la prossima, con
 * lo stesso pulsante e la stessa finestra della home (spot e cornici
 * compresi). Senza una gara con il link, lo dice e rimanda al calendario.
 */
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import LiveStreamModal from '@/Components/LiveStreamModal.vue';
import TeamLogo from '@/Components/TeamLogo.vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useLocale } from '@/Composables/useLocale.js';
import { useOgMeta } from '@/Composables/useOgMeta';

const $t = useTranslations();
const { formatDate, formatTime } = useLocale();

const props = defineProps({
    gara: { type: Object, default: null },
});

const ogMeta = useOgMeta({
    title: $t('stream.pagina_titolo'),
    description: $t('stream.pagina_descrizione'),
});

const diretta = ref(null);

// Le piattaforme non incorporabili restano un link normale, in una nuova scheda.
const apri = (event) => {
    if (!props.gara?.embedUrl) {
        return;
    }

    event.preventDefault();
    diretta.value = {
        title: `${props.gara.casa.nome} — ${props.gara.ospite.nome}`,
        embedUrl: props.gara.embedUrl,
        url: props.gara.url,
        sponsor: props.gara.sponsor ?? null,
    };
};
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
        <meta property="og:title" :content="ogMeta.title" />
        <meta property="og:description" :content="ogMeta.description" />
    </Head>

    <PublicLayout>
        <section class="bg-savino-blue text-white">
            <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:py-24">
                <h1 class="text-3xl font-black uppercase tracking-tight sm:text-5xl">{{ $t('stream.pagina_titolo') }}</h1>

                <template v-if="gara">
                    <div class="mt-10 flex items-center justify-center gap-4 sm:gap-10" data-diretta-gara>
                        <div class="flex w-28 flex-col items-center gap-3 sm:w-48">
                            <TeamLogo :src="gara.casa.logo" :name="gara.casa.nome" size="lg" />
                            <p class="text-sm font-bold uppercase sm:text-lg">{{ gara.casa.nome }}</p>
                        </div>
                        <span class="text-2xl font-black text-savino-fucsia-chiaro" aria-hidden="true">VS</span>
                        <div class="flex w-28 flex-col items-center gap-3 sm:w-48">
                            <TeamLogo :src="gara.ospite.logo" :name="gara.ospite.nome" size="lg" />
                            <p class="text-sm font-bold uppercase sm:text-lg">{{ gara.ospite.nome }}</p>
                        </div>
                    </div>

                    <p class="mt-8 text-base text-white/85">
                        {{ formatDate(gara.data) }} · {{ formatTime(gara.data) }}<template v-if="gara.luogo"> · {{ gara.luogo }}</template>
                    </p>

                    <component
                        :is="gara.embedUrl ? 'button' : 'a'"
                        v-if="gara.embedUrl || gara.url"
                        :type="gara.embedUrl ? 'button' : undefined"
                        :href="gara.embedUrl ? undefined : gara.url"
                        :target="gara.embedUrl ? undefined : '_blank'"
                        :rel="gara.embedUrl ? undefined : 'noopener noreferrer'"
                        class="mt-10 inline-flex items-center gap-3 rounded-xl bg-savino-fucsia px-8 py-4 text-base font-bold uppercase tracking-wider text-white hover:bg-white hover:text-savino-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                        data-diretta-apri
                        @click="apri"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                        {{ $t('stream.watch') }}
                    </component>
                </template>

                <template v-else>
                    <p class="mx-auto mt-8 max-w-xl text-base text-white/85" data-diretta-nessuna>{{ $t('stream.pagina_nessuna') }}</p>
                    <Link
                        :href="route('stagione.risultati', { squadra: 'savino' })"
                        class="mt-8 inline-flex items-center rounded-xl border border-white px-6 py-3 text-sm font-bold uppercase tracking-wider text-white hover:bg-white hover:text-savino-blue"
                    >{{ $t('stream.pagina_calendario') }}</Link>
                </template>
            </div>
        </section>

        <LiveStreamModal :stream="diretta" @close="diretta = null" />
    </PublicLayout>
</template>
