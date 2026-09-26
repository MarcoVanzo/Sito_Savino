<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
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
const mostraPassword = ref(false);
const campoPassword = ref(null);
const pulsanteCancella = ref(null);

// Aperta la conferma il pulsante sparisce: il focus va sul campo da compilare,
// e torna sul pulsante se si annulla (WCAG 2.4.3).
const apriConferma = () => {
    confermaAperta.value = true;
    nextTick(() => campoPassword.value?.focus());
};

const annulla = () => {
    confermaAperta.value = false;
    mostraPassword.value = false;
    form.reset();
    form.clearErrors();
    nextTick(() => pulsanteCancella.value?.focus());
};

const dataIscrizione = () => {
    if (!props.account.created_at) return '';
    const locale = usePage().props.locale === 'en' ? 'en-GB' : 'it-IT';

    return new Date(props.account.created_at).toLocaleDateString(locale);
};

function cancella() {
    form.delete(route('shop.account.destroy'), {
        preserveScroll: true,
        onError: () => nextTick(() => campoPassword.value?.focus()),
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
                                <Link :href="route('shop.orders')" class="text-savino-blue underline hover:text-savino-fucsia">{{ account.orders_count }}<span class="sr-only"> {{ $t('account.orders_link') }}</span></Link>
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
                        class="inline-flex items-center gap-2 px-6 py-3 bg-savino-blue text-white text-sm font-bold uppercase tracking-wider rounded-lg hover:bg-savino-fucsia hover:text-white transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        {{ $t('account.export_button') }}
                    </a>
                    <p class="mt-3 text-sm text-gray-600">{{ $t('account.export_note') }}</p>
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
                            ref="pulsanteCancella"
                            type="button"
                            class="px-6 py-3 border-2 border-red-600 text-red-700 text-sm font-bold uppercase tracking-wider rounded-lg hover:bg-red-600 hover:text-white transition-colors"
                            @click="apriConferma"
                        >
                            {{ $t('account.delete_button') }}
                        </button>

                        <form v-else class="space-y-4" @submit.prevent="cancella">
                            <label for="account-password" class="block text-sm font-semibold text-gray-700">{{ $t('account.delete_password') }}</label>
                            <div class="relative w-full sm:w-80">
                                <input
                                    id="account-password"
                                    ref="campoPassword"
                                    v-model="form.password"
                                    :type="mostraPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    :aria-invalid="form.errors.password ? 'true' : undefined"
                                    :aria-describedby="form.errors.password ? 'errore-account-password' : undefined"
                                    class="w-full rounded-lg border-gray-300 pr-12 text-sm focus:border-red-500 focus:ring-red-500"
                                />
                                <!-- Etichetta che cambia con lo stato, quindi niente
                                     aria-pressed: come nella registrazione. -->
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-600 hover:text-gray-900"
                                    :aria-label="mostraPassword ? $t('shop.hide_password') : $t('shop.show_password')"
                                    @click="mostraPassword = !mostraPassword"
                                >
                                    <svg v-if="!mostraPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.password" id="errore-account-password" role="alert" class="text-sm text-red-700">{{ form.errors.password }}</p>
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
                                    @click="annulla"
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
