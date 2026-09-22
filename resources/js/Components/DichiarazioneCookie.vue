<script setup>
import { computed } from 'vue';
import { useTranslations } from '@/Composables/useTranslations.js';

/**
 * L'elenco dei cookie che il sito usa davvero.
 *
 * Non è scritto in redazione: arriva dalla scansione settimanale, quindi
 * quando si aggiunge o si toglie un tracker questa pagina cambia da sola. Le
 * righe che la scansione non ha saputo nominare compaiono lo stesso, sotto
 * "non classificati": un cookie che non sappiamo spiegare è un fatto da
 * dichiarare, non da nascondere.
 */
const $t = useTranslations();

const props = defineProps({
    dichiarazione: { type: Object, default: () => ({}) },
});

const categorie = computed(() => props.dichiarazione?.categorie ?? []);
const aggiornataIl = computed(() => props.dichiarazione?.aggiornata_il ?? null);

const dataLeggibile = computed(() => {
    if (! aggiornataIl.value) return null;

    const quando = new Date(aggiornataIl.value);

    return Number.isNaN(quando.getTime()) ? aggiornataIl.value : quando.toLocaleDateString();
});

const titoloCategoria = (chiave) => $t(`cookie_declaration.categories.${chiave}`);

const apriLePreferenze = () => window.dispatchEvent(new CustomEvent('preferenze-cookie:apri'));
</script>

<template>
    <section v-if="categorie.length" class="mt-12 pt-8 border-t border-gray-100">
        <h2 class="text-2xl font-bold text-savino-blue mb-1">{{ $t('cookie_declaration.title') }}</h2>

        <p class="text-sm text-gray-500 mb-6">
            <template v-if="dataLeggibile">{{ $t('cookie_declaration.updated_on') }} {{ dataLeggibile }}. </template>
            {{ $t('cookie_declaration.intro') }}
            <button type="button" class="text-savino-fucsia hover:underline" @click="apriLePreferenze">
                {{ $t('cookie_declaration.manage') }}
            </button>
        </p>

        <div v-for="categoria in categorie" :key="categoria.chiave" class="mb-8">
            <h3 class="text-lg font-bold text-savino-blue mb-3">{{ titoloCategoria(categoria.chiave) }}</h3>

            <div v-if="categoria.cookie.length" class="overflow-x-auto">
                <table class="w-full text-sm text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 uppercase text-xs tracking-wider">
                            <th scope="col" class="py-2 pr-4 font-semibold">{{ $t('cookie_declaration.name') }}</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">{{ $t('cookie_declaration.provider') }}</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">{{ $t('cookie_declaration.purpose') }}</th>
                            <th scope="col" class="py-2 font-semibold">{{ $t('cookie_declaration.duration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="cookie in categoria.cookie" :key="cookie.nome" class="border-b border-gray-100 align-top">
                            <td class="py-3 pr-4 font-mono text-xs text-savino-blue whitespace-nowrap">{{ cookie.nome }}</td>
                            <td class="py-3 pr-4 text-gray-600">{{ cookie.fornitore || '—' }}</td>
                            <td class="py-3 pr-4 text-gray-600">{{ cookie.scopo || $t('cookie_declaration.unknown_purpose') }}</td>
                            <td class="py-3 text-gray-600 whitespace-nowrap">{{ cookie.durata || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="categoria.host.length" class="mt-3 text-xs text-gray-500 leading-relaxed">
                {{ $t('cookie_declaration.third_parties') }}
                <span class="text-gray-600">{{ categoria.host.map(h => h.host).join(', ') }}</span>
            </p>
        </div>
    </section>
</template>
