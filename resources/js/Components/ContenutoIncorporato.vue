<script setup>
/**
 * Un iframe di terzi (video, diretta, mappa) che si carica solo col consenso.
 *
 * YouTube, Google Maps e le altre piattaforme scrivono cookie di marketing
 * appena il riquadro si apre (così li classifica `catalogo_cookie.json`), e
 * gli iframe partivano prima di qualsiasi scelta sul banner: la Cookie Policy
 * lo dichiarava, ma dichiararlo non lo rendeva lecito. Finché il consenso di
 * marketing non c'è, al posto dell'iframe resta un segnaposto con due strade:
 * caricare solo questo contenuto (il clic è la richiesta esplicita) o aprirlo
 * sulla piattaforma. Il consenso si legge da `consenso.js`, l'unica fonte.
 *
 * L'avvio automatico si aggiunge solo quando è il visitatore ad averlo
 * chiesto: il clic sul segnaposto, o `avvioAutomatico` per chi apre il
 * riquadro da un pulsante (la finestra della diretta).
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';
import { EVENTO_CAMBIO, marketingConsentito } from '@/consenso.js';

const $t = useTranslations();
const page = usePage();

const props = defineProps({
    src: { type: String, required: true },
    titolo: { type: String, default: '' },
    // L'indirizzo da aprire sulla piattaforma, in alternativa al caricamento.
    linkDiretto: { type: String, default: null },
    tipo: { type: String, default: 'video', validator: (v) => ['video', 'mappa'].includes(v) },
    avvioAutomatico: { type: Boolean, default: false },
    allow: { type: String, default: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen' },
    referrerpolicy: { type: String, default: 'strict-origin-when-cross-origin' },
    classeIframe: { type: String, default: 'w-full h-full border-0' },
});

/** Quello che ha deciso il banner. */
const consensoDato = ref(false);
/** Il clic sul segnaposto: vale per questo contenuto e basta. */
const chiestoDalVisitatore = ref(false);

const versione = () => page?.props?.consensoCookie?.versione ?? null;

const aggiorna = () => {
    consensoDato.value = marketingConsentito(versione());
};

// Il consenso si legge al montaggio e non durante il setup: localStorage non
// esiste fuori dal browser.
onMounted(() => {
    aggiorna();
    window.addEventListener(EVENTO_CAMBIO, aggiorna);
});

onBeforeUnmount(() => {
    window.removeEventListener(EVENTO_CAMBIO, aggiorna);
});

const caricato = computed(() => consensoDato.value || chiestoDalVisitatore.value);

const piattaforma = computed(() => {
    let host;

    try {
        host = new URL(props.src).hostname;
    } catch {
        return '';
    }

    if (/(^|\.)youtube(-nocookie)?\.com$/.test(host)) return 'YouTube';
    if (/(^|\.)vimeo\.com$/.test(host)) return 'Vimeo';
    if (/(^|\.)twitch\.tv$/.test(host)) return 'Twitch';
    if (/(^|\.)dailymotion\.com$/.test(host)) return 'Dailymotion';
    if (/(^|\.)google\.com$/.test(host)) return 'Google';

    return host;
});

const indirizzo = computed(() => {
    if (props.tipo !== 'video' || !(chiestoDalVisitatore.value || props.avvioAutomatico)) {
        return props.src;
    }

    try {
        const url = new URL(props.src);
        url.searchParams.set('autoplay', '1');

        return url.toString();
    } catch {
        return props.src;
    }
});

const carica = () => {
    chiestoDalVisitatore.value = true;
};

const apriPreferenze = () => {
    // Lo stesso evento con cui footer e Cookie Policy riaprono il banner.
    window.dispatchEvent(new CustomEvent('preferenze-cookie:apri'));
};
</script>

<template>
    <iframe
        v-if="caricato"
        :src="indirizzo"
        :title="titolo"
        :class="classeIframe"
        :allow="allow"
        :referrerpolicy="referrerpolicy"
        allowfullscreen
    ></iframe>

    <div
        v-else
        role="group"
        :aria-label="titolo || undefined"
        class="w-full h-full flex flex-col items-center justify-center gap-4 p-6 text-center bg-savino-blue text-white"
        data-segnaposto-incorporato
    >
        <p class="max-w-md text-sm leading-relaxed text-white/80">
            {{ $t('incorporato.avviso', { piattaforma }) }}
        </p>

        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-savino-blue hover:bg-savino-fucsia hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white transition-colors"
            @click="carica"
        >
            <svg v-if="tipo === 'video'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
            {{ tipo === 'mappa' ? $t('incorporato.carica_mappa', { piattaforma }) : $t('incorporato.carica_video', { piattaforma }) }}
        </button>

        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs font-bold">
            <a
                v-if="linkDiretto"
                :href="linkDiretto"
                target="_blank"
                rel="noopener noreferrer"
                class="underline underline-offset-2 text-white/80 hover:text-white"
            >{{ tipo === 'mappa' ? $t('incorporato.apri_mappa', { piattaforma }) : $t('incorporato.apri_video', { piattaforma }) }}</a>
            <button
                type="button"
                class="underline underline-offset-2 text-white/60 hover:text-white"
                @click="apriPreferenze"
            >{{ $t('incorporato.preferenze') }}</button>
        </div>
    </div>
</template>
