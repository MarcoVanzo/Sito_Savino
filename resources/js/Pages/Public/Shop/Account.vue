<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useTranslations } from '@/Composables/useTranslations.js';

/**
 * Il mio account: i dati, il file con tutto quello che sappiamo del cliente
 * (art. 20 del GDPR) e la cancellazione (art. 17).
 *
 * Prima non esisteva: la cancellazione di Breeze stava sotto `/profile`, nel
 * layout del pannello, e nessun link del sito ci portava.
 */
const $t = useTranslations();

const props = defineProps({
    account: { type: Object, required: true },
    motivoPerNonCancellare: { type: String, default: null },
});

const ogMeta = useOgMeta({ title: $t('account.title') });

const confermaAperta = ref(false);
const form = useForm({ password: '' });

const dataIscrizione = () => {
    if (!props.account.created_at) return '';
    const locale = usePage().props.locale === 'en' ? 'en-GB' : 'it-IT';

    return new Date(props.account.created_at).toLocaleDateString(locale);
};

function cancella() {
    form.delete(route('shop.account.destroy'), {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout>
        <section class="relative min-h-[30vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter">
                    {{ $t('account.title') }}
                </h1>
            </div>
        </section>

        <section class="py-16 bg-gray-50 min-h-[60vh]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- I dati -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <h2 class="text-lg font-black text-savino-blue uppercase tracking-tight mb-4">{{ $t('account.data_title') }}</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500">{{ $t('account.name') }}</dt>
                            <dd class="font-semibold text-gray-900">{{ account.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ $t('account.email') }}</dt>
                            <dd class="font-semibold text-gray-900 break-all">{{ account.email }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ $t('account.member_since') }}</dt>
                            <dd class="font-semibold text-gray-900">{{ dataIscrizione() }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ $t('account.orders') }}</dt>
                            <dd class="font-semibold text-gray-900">
                                <Link :href="route('shop.orders')" class="text-savino-blue underline hover:text-savino-fucsia">{{ account.orders_count }}</Link>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Esportazione -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <h2 class="text-lg font-black text-savino-blue uppercase tracking-tight mb-2">{{ $t('account.export_title') }}</h2>
                    <p class="text-sm text-gray-600 mb-6">{{ $t('account.export_text') }}</p>
                    <!-- Un <a> e non un <Link>: è un file da scaricare, non una
                         pagina Inertia. -->
                    <a
                        :href="route('shop.account.export')"
                        class="inline-flex items-center gap-2 px-6 py-3 bg-savino-blue text-white text-sm font-bold uppercase tracking-wider rounded-lg hover:bg-savino-fucsia hover:text-savino-blue transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        {{ $t('account.export_button') }}
                    </a>
                </div>

                <!-- Cancellazione -->
                <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-8">
                    <h2 class="text-lg font-black text-red-700 uppercase tracking-tight mb-2">{{ $t('account.delete_title') }}</h2>
                    <p class="text-sm text-gray-600 mb-2">{{ $t('account.delete_text') }}</p>
                    <p class="text-sm text-gray-600 mb-6">{{ $t('account.delete_newsletter') }}</p>

                    <p v-if="motivoPerNonCancellare" class="text-sm font-medium text-amber-700 bg-amber-50 rounded-lg p-4">
                        {{ motivoPerNonCancellare }}
                    </p>

                    <template v-else>
                        <button
                            v-if="!confermaAperta"
                            type="button"
                            class="px-6 py-3 border-2 border-red-600 text-red-700 text-sm font-bold uppercase tracking-wider rounded-lg hover:bg-red-600 hover:text-white transition-colors"
                            @click="confermaAperta = true"
                        >
                            {{ $t('account.delete_button') }}
                        </button>

                        <form v-else class="space-y-4" @submit.prevent="cancella">
                            <label for="account-password" class="block text-sm font-semibold text-gray-700">{{ $t('account.delete_password') }}</label>
                            <input
                                id="account-password"
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="w-full sm:w-80 rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500"
                            />
                            <p v-if="form.errors.password" class="text-sm text-red-600">{{ form.errors.password }}</p>
                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="submit"
                                    :disabled="form.processing || !form.password"
                                    class="px-6 py-3 bg-red-600 text-white text-sm font-bold uppercase tracking-wider rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50"
                                >
                                    {{ $t('account.delete_confirm') }}
                                </button>
                                <button
                                    type="button"
                                    class="px-6 py-3 border border-gray-300 text-gray-700 text-sm font-bold uppercase tracking-wider rounded-lg hover:border-savino-blue hover:text-savino-blue transition-colors"
                                    @click="confermaAperta = false; form.reset(); form.clearErrors()"
                                >
                                    {{ $t('account.delete_cancel') }}
                                </button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
