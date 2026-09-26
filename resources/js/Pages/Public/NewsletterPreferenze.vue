<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/*
 * Le preferenze della newsletter, dal link in fondo a ogni invio.
 *
 * Le linee guida del Garante del 17/04/2026 sui pixel nelle email chiedono
 * una revoca granulare: fermare solo il tracciamento (aperture e clic)
 * continuando a ricevere, oppure disiscriversi. Entrambe partono in POST
 * verso indirizzi firmati passati dal server.
 */
const $t = useTranslations();
const page = usePage();

const props = defineProps({
    email: { type: String, default: '' },
    iscritto: { type: Boolean, default: true },
    tracciamentoAttivo: { type: Boolean, default: true },
    senzaTracciamentoUrl: { type: String, default: '' },
    disiscrivitiUrl: { type: String, default: '' },
});

const esito = computed(() => page.props.flash?.success ?? null);
const form = useForm({});

function senzaTracciamento() {
    form.post(props.senzaTracciamentoUrl, { preserveScroll: true });
}

function disiscriviti() {
    form.post(props.disiscrivitiUrl);
}
</script>

<template>
    <Head>
        <title>{{ $t('newsletter.preferences_title') }}</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout>
        <section class="py-24 px-4 sm:px-6 lg:px-8 bg-white min-h-[60vh] flex flex-col justify-center items-center text-center">
            <div class="max-w-xl mx-auto">
                <h1 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mb-4">
                    {{ $t('newsletter.preferences_title') }}
                </h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-8"></div>

                <p v-if="esito" role="status" class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-green-900">{{ esito }}</p>

                <template v-if="iscritto">
                    <p class="text-lg text-gray-600 mb-2">{{ $t('newsletter.preferences_intro') }}</p>
                    <p class="text-lg font-bold text-savino-blue mb-6">{{ email }}</p>
                    <p class="text-sm text-gray-700 mb-8">
                        {{ tracciamentoAttivo ? $t('newsletter.preferences_tracking_on') : $t('newsletter.preferences_tracking_off') }}
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <button
                            v-if="tracciamentoAttivo"
                            type="button"
                            :disabled="form.processing"
                            class="px-8 py-3 bg-savino-blue text-white font-bold tracking-wider hover:bg-savino-red transition-colors duration-300 rounded-sm disabled:opacity-50"
                            @click="senzaTracciamento"
                        >
                            {{ $t('newsletter.preferences_no_tracking') }}
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="px-8 py-3 border border-gray-300 text-gray-700 font-bold tracking-wider hover:border-savino-blue hover:text-savino-blue transition-colors duration-300 rounded-sm disabled:opacity-50"
                            @click="disiscriviti"
                        >
                            {{ $t('newsletter.preferences_unsubscribe') }}
                        </button>
                    </div>
                </template>

                <template v-else>
                    <p class="text-lg text-gray-600 mb-10">{{ $t('newsletter.preferences_not_subscribed') }}</p>
                    <Link
                        :href="route('home')"
                        class="inline-block px-8 py-3 bg-savino-blue text-white font-bold tracking-wider hover:bg-savino-red transition-colors duration-300 rounded-sm"
                    >
                        {{ $t('under_construction.back_home') }}
                    </Link>
                </template>
            </div>
        </section>
    </PublicLayout>
</template>
