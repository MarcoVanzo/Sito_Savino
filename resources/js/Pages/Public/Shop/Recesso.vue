<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

/*
 * La funzione di recesso online (art. 54-bis del Codice del Consumo).
 *
 * Due passaggi, come chiede la norma: prima si inseriscono nome, ordine e
 * recapito ("Continua"), poi si rilegge la dichiarazione e la si conferma con
 * un comando separato ("Conferma recesso"). Solo il secondo invia. La
 * ricevuta con data e ora arriva nella risposta e per email.
 */
const $t = useTranslations();

const props = defineProps({
    precompilato: { type: Object, default: () => ({}) },
    ricevuta: { type: Object, default: null },
});

const form = useForm({
    nome: props.precompilato?.nome ?? '',
    email: props.precompilato?.email ?? '',
    numero_ordine: props.precompilato?.numero_ordine ?? '',
    articoli: '',
    conferma: false,
});

const passaggio = ref('dati');
const titoloRiepilogo = ref(null);

const erroriLocali = ref({});

const errori = computed(() => ({ ...erroriLocali.value, ...form.errors }));

const idErrore = (campo) => `recesso-errore-${campo}`;

function continua() {
    const mancanti = {};
    for (const campo of ['nome', 'email', 'numero_ordine']) {
        if (!String(form[campo] ?? '').trim()) {
            mancanti[campo] = $t('recesso.required');
        }
    }
    if (form.email && !/^\S+@\S+\.\S+$/.test(form.email)) {
        mancanti.email = $t('recesso.invalid_email');
    }
    erroriLocali.value = mancanti;

    if (Object.keys(mancanti).length) {
        nextTick(() => document.getElementById(`recesso-${Object.keys(mancanti)[0]}`)?.focus());
        return;
    }

    passaggio.value = 'riepilogo';
    nextTick(() => titoloRiepilogo.value?.focus());
}

function modifica() {
    passaggio.value = 'dati';
}

function conferma() {
    form.conferma = true;
    form.post(route('recesso.store'), {
        preserveScroll: false,
        onError: () => {
            form.conferma = false;
            passaggio.value = 'dati';
        },
    });
}
</script>

<template>
    <Head>
        <title>{{ $t('recesso.title') }}</title>
        <meta name="description" :content="$t('recesso.meta_description')" />
        <meta name="robots" content="noindex" />
    </Head>

    <PublicLayout>
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-2xl mx-auto">
                <div class="mb-10 border-b border-gray-200 pb-8">
                    <h1 class="text-4xl font-black text-savino-blue uppercase tracking-tighter mb-2">
                        {{ $t('recesso.title') }}
                    </h1>
                    <div class="w-16 h-1 bg-savino-fucsia"></div>
                </div>

                <!-- Ricevuta -->
                <div v-if="ricevuta" role="status" class="rounded-2xl border border-green-200 bg-green-50 p-6">
                    <h2 class="text-xl font-bold text-green-900 mb-2">{{ $t('recesso.received_title') }}</h2>
                    <p class="text-green-900 mb-4">{{ $t('recesso.received_intro', { email: ricevuta.email }) }}</p>
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-2 text-sm text-gray-800">
                        <dt class="font-semibold">{{ $t('recesso.order_number') }}</dt>
                        <dd class="sm:col-span-2">{{ ricevuta.numero_ordine }}</dd>
                        <dt class="font-semibold">{{ $t('recesso.name') }}</dt>
                        <dd class="sm:col-span-2">{{ ricevuta.nome }}</dd>
                        <dt class="font-semibold">{{ $t('recesso.items') }}</dt>
                        <dd class="sm:col-span-2">{{ ricevuta.articoli || $t('recesso.all_items') }}</dd>
                        <dt class="font-semibold">{{ $t('recesso.sent_at') }}</dt>
                        <dd class="sm:col-span-2">{{ ricevuta.inviata_il }}</dd>
                    </dl>
                    <p class="text-sm text-gray-700 mt-4">{{ $t('recesso.next_steps') }}</p>
                    <Link :href="route('pages.show', 'resi-e-rimborsi')" class="inline-block mt-4 text-savino-blue font-semibold underline">
                        {{ $t('recesso.returns_link') }}
                    </Link>
                </div>

                <template v-else>
                    <p class="text-gray-700 mb-2">{{ $t('recesso.intro') }}</p>
                    <p class="text-gray-700 mb-8">
                        {{ $t('recesso.intro_terms') }}
                        <Link :href="route('pages.show', 'condizioni-di-vendita')" class="text-savino-blue underline">{{ $t('recesso.terms_link') }}</Link>.
                    </p>

                    <!-- Passaggio 1: i dati -->
                    <form v-if="passaggio === 'dati'" novalidate class="space-y-6" @submit.prevent="continua">
                        <div>
                            <label for="recesso-nome" class="block text-sm font-semibold text-gray-800 mb-1">{{ $t('recesso.name') }} *</label>
                            <input id="recesso-nome" v-model="form.nome" type="text" autocomplete="name" required
                                :aria-invalid="!!errori.nome" :aria-describedby="errori.nome ? idErrore('nome') : undefined"
                                class="w-full rounded-lg border-gray-300 focus:border-savino-blue focus:ring-savino-blue" />
                            <p v-if="errori.nome" :id="idErrore('nome')" class="mt-1 text-sm text-red-700">{{ errori.nome }}</p>
                        </div>
                        <div>
                            <label for="recesso-email" class="block text-sm font-semibold text-gray-800 mb-1">{{ $t('recesso.email') }} *</label>
                            <input id="recesso-email" v-model="form.email" type="email" autocomplete="email" required
                                :aria-invalid="!!errori.email" :aria-describedby="errori.email ? idErrore('email') : 'recesso-email-aiuto'"
                                class="w-full rounded-lg border-gray-300 focus:border-savino-blue focus:ring-savino-blue" />
                            <p id="recesso-email-aiuto" class="mt-1 text-xs text-gray-600">{{ $t('recesso.email_help') }}</p>
                            <p v-if="errori.email" :id="idErrore('email')" class="mt-1 text-sm text-red-700">{{ errori.email }}</p>
                        </div>
                        <div>
                            <label for="recesso-numero_ordine" class="block text-sm font-semibold text-gray-800 mb-1">{{ $t('recesso.order_number') }} *</label>
                            <input id="recesso-numero_ordine" v-model="form.numero_ordine" type="text" required
                                :aria-invalid="!!errori.numero_ordine" :aria-describedby="errori.numero_ordine ? idErrore('numero_ordine') : 'recesso-ordine-aiuto'"
                                class="w-full rounded-lg border-gray-300 focus:border-savino-blue focus:ring-savino-blue" />
                            <p id="recesso-ordine-aiuto" class="mt-1 text-xs text-gray-600">{{ $t('recesso.order_help') }}</p>
                            <p v-if="errori.numero_ordine" :id="idErrore('numero_ordine')" class="mt-1 text-sm text-red-700">{{ errori.numero_ordine }}</p>
                        </div>
                        <div>
                            <label for="recesso-articoli" class="block text-sm font-semibold text-gray-800 mb-1">{{ $t('recesso.items_label') }}</label>
                            <textarea id="recesso-articoli" v-model="form.articoli" rows="3" maxlength="2000"
                                aria-describedby="recesso-articoli-aiuto"
                                class="w-full rounded-lg border-gray-300 focus:border-savino-blue focus:ring-savino-blue"></textarea>
                            <p id="recesso-articoli-aiuto" class="mt-1 text-xs text-gray-600">{{ $t('recesso.items_help') }}</p>
                        </div>
                        <button type="submit" class="px-8 py-3 bg-savino-blue text-white font-bold rounded-lg hover:bg-savino-blue/90 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-savino-blue">
                            {{ $t('recesso.continue') }}
                        </button>
                    </form>

                    <!-- Passaggio 2: riepilogo e conferma -->
                    <div v-else class="space-y-6">
                        <h2 ref="titoloRiepilogo" tabindex="-1" class="text-xl font-bold text-savino-blue">{{ $t('recesso.summary_title') }}</h2>
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 text-gray-800">
                            <p class="mb-4">{{ $t('recesso.statement', { number: form.numero_ordine }) }}</p>
                            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                                <dt class="font-semibold">{{ $t('recesso.name') }}</dt>
                                <dd class="sm:col-span-2">{{ form.nome }}</dd>
                                <dt class="font-semibold">{{ $t('recesso.email') }}</dt>
                                <dd class="sm:col-span-2">{{ form.email }}</dd>
                                <dt class="font-semibold">{{ $t('recesso.items') }}</dt>
                                <dd class="sm:col-span-2">{{ form.articoli || $t('recesso.all_items') }}</dd>
                            </dl>
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <button type="button" :disabled="form.processing" class="px-8 py-3 bg-savino-blue text-white font-bold rounded-lg hover:bg-savino-blue/90 disabled:opacity-60 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-savino-blue" @click="conferma">
                                {{ $t('recesso.confirm') }}
                            </button>
                            <button type="button" class="px-6 py-3 border border-gray-300 text-gray-800 font-semibold rounded-lg hover:bg-gray-50" @click="modifica">
                                {{ $t('recesso.edit') }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </PublicLayout>
</template>
