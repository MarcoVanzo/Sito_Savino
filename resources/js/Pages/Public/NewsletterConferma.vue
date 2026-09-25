<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * La conferma dell'iscrizione alla newsletter (doppio opt-in).
 *
 * Il link dell'email porta qui, e la conferma parte dal pulsante: se partisse
 * all'apertura della pagina, la darebbero anche i filtri dei client di posta
 * che aprono i link per controllarli. Stesso schema di NewsletterUnsubscribe.
 */
const $t = useTranslations();
const page = usePage();

const props = defineProps({
    email: String,
    giaConfermata: Boolean,
    confermaUrl: String,
});

const fatto = computed(() => props.giaConfermata || Boolean(page.props.flash?.success));

const form = useForm({});

function conferma() {
    form.post(props.confermaUrl, { preserveScroll: true });
}
</script>

<template>
    <Head>
        <title>{{ $t('newsletter.confirm_title') }}</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout>
        <section class="py-24 px-4 sm:px-6 lg:px-8 bg-white min-h-[60vh] flex flex-col justify-center items-center text-center">
            <div class="max-w-xl mx-auto">
                <h1 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mb-4">
                    {{ fatto ? $t('newsletter.confirm_done_title') : $t('newsletter.confirm_title') }}
                </h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-8"></div>

                <p class="text-lg text-gray-600 mb-2">
                    {{ fatto ? $t('newsletter.confirm_done_text') : $t('newsletter.confirm_intro') }}
                </p>
                <p class="text-lg font-bold text-savino-blue mb-10">{{ email }}</p>

                <button
                    v-if="!fatto"
                    type="button"
                    :disabled="form.processing"
                    class="px-8 py-3 bg-savino-blue text-white font-bold tracking-wider hover:bg-savino-red transition-colors duration-300 rounded-sm disabled:opacity-50"
                    @click="conferma"
                >
                    {{ $t('newsletter.confirm_button') }}
                </button>

                <Link
                    v-else
                    :href="route('home')"
                    class="inline-block px-8 py-3 bg-savino-fucsia text-white font-bold tracking-wider hover:bg-savino-blue transition-colors duration-300 rounded-sm"
                >
                    {{ $t('under_construction.back_home') }}
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
