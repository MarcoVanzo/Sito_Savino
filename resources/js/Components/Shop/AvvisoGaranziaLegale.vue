<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/*
 * L'avviso armonizzato UE sulla garanzia legale di conformità, obbligatorio
 * dal 27 settembre 2026 (Dir. UE 2024/825, d.lgs. 30/2026, Reg. di esecuzione
 * UE 2025/1960).
 *
 * Le linee guida della Commissione per i negozi online chiedono l'avviso
 * intero, a colori e leggibile a dimensione normale, raggiungibile con un
 * click da una frase del tipo "I tuoi diritti di garanzia legale" in scheda
 * prodotto o al checkout, più un link cliccabile alla stessa pagina del
 * codice QR. L'immagine è la pagina a colori del PDF ufficiale
 * (public/images/garanzia/), non un rifacimento: il contenuto non si
 * parafrasa. Sotto c'è la trascrizione per chi usa uno screen reader.
 */
const $t = useTranslations();
const page = usePage();

const lingua = computed(() => (page?.props?.locale === 'en' ? 'en' : 'it'));
const immagine = computed(() => `/images/garanzia/avviso-garanzia-legale-${lingua.value}.png`);

const finestra = ref(null);
const apri = () => finestra.value?.showModal();
const chiudi = () => finestra.value?.close();
</script>

<template>
    <span>
        <button type="button" class="underline decoration-dotted underline-offset-2 hover:text-savino-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-savino-blue" @click="apri">
            {{ $t('garanzia_legale.link') }}
        </button>

        <dialog
            ref="finestra"
            class="w-full max-w-xl rounded-2xl p-0 backdrop:bg-black/60"
            aria-labelledby="avviso-garanzia-titolo"
            @click.self="chiudi"
        >
            <div class="p-4 sm:p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <h2 id="avviso-garanzia-titolo" class="text-lg font-bold text-savino-blue">{{ $t('garanzia_legale.title') }}</h2>
                    <button type="button" class="text-gray-600 hover:text-gray-900 text-2xl leading-none" :aria-label="$t('garanzia_legale.close')" @click="chiudi">&times;</button>
                </div>
                <img :src="immagine" :alt="$t('garanzia_legale.alt')" aria-describedby="avviso-garanzia-trascrizione" width="910" height="1287" class="w-full h-auto" />
                <p id="avviso-garanzia-trascrizione" class="sr-only">{{ $t('garanzia_legale.transcript') }}</p>
                <p class="mt-4 text-sm text-center">
                    <a :href="$t('garanzia_legale.url')" target="_blank" rel="noopener noreferrer" class="text-savino-blue underline font-semibold">
                        {{ $t('garanzia_legale.url_label') }}
                    </a>
                </p>
            </div>
        </dialog>
    </span>
</template>
