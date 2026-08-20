<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const $t = useTranslations();
const page = usePage();

const props = defineProps({
    email: String,
    alreadyUnsubscribed: Boolean,
    confirmUrl: String,
});

// Il flash arriva dopo la POST: da lì in poi la pagina mostra l'esito, non il form.
const done = computed(() => props.alreadyUnsubscribed || Boolean(page.props.flash?.success));

const form = useForm({});

function confirm() {
    form.post(props.confirmUrl, { preserveScroll: true });
}
</script>

<template>
    <Head>
        <title>{{ $t('newsletter.unsubscribe_title') }}</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout>
        <section class="py-24 px-4 sm:px-6 lg:px-8 bg-white min-h-[60vh] flex flex-col justify-center items-center text-center">
            <div class="max-w-xl mx-auto">
                <h1 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mb-4">
                    {{ done ? $t('newsletter.unsubscribe_done_title') : $t('newsletter.unsubscribe_title') }}
                </h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-8"></div>

                <p class="text-lg text-gray-600 mb-2">
                    {{ done ? $t('newsletter.unsubscribe_done_text') : $t('newsletter.unsubscribe_intro') }}
                </p>
                <p class="text-lg font-bold text-savino-blue mb-10">{{ email }}</p>

                <div v-if="!done" class="flex flex-col sm:flex-row gap-4 justify-center">
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="px-8 py-3 bg-savino-blue text-white font-bold tracking-wider hover:bg-savino-red transition-colors duration-300 rounded-sm disabled:opacity-50"
                        @click="confirm"
                    >
                        {{ $t('newsletter.unsubscribe_confirm') }}
                    </button>
                    <Link
                        :href="route('home')"
                        class="px-8 py-3 border border-gray-300 text-gray-700 font-bold tracking-wider hover:border-savino-blue hover:text-savino-blue transition-colors duration-300 rounded-sm"
                    >
                        {{ $t('newsletter.unsubscribe_cancel') }}
                    </Link>
                </div>

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
