<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';
import { safeUrl } from '@/Composables/useSafeUrl.js';
import { EVENTO_CAMBIO, leggiIlConsenso } from '@/consenso.js';
import { externalLinkAttrs, isExternalLink } from '@/Support/menuLinks.js';

const $t = useTranslations();
const page = usePage();

const props = defineProps({
    // MatchDay::stato()['popup']: titolo, testo, pulsante, url (vuoto = Biglietteria).
    popup: {
        type: Object,
        default: null,
    },
});

// Una volta per visita e per giorno: chi chiude il pop-up e torna in home
// dalla stessa scheda non lo rivede, chi torna il giorno della gara dopo sì.
const CHIAVE_VISTO = 'match-day-popup-visto';

const dialogo = ref(null);
const aperto = ref(false);

const indirizzo = computed(() => safeUrl(props.popup?.url) ?? route('ticketing.page', 'biglietteria'));

// Il giorno del visitatore, non quello di Greenwich: `toISOString()` a
// mezzanotte e mezza direbbe ancora ieri.
const oggi = () => new Date().toLocaleDateString('sv');

const giaVisto = () => {
    try {
        return sessionStorage.getItem(CHIAVE_VISTO) === oggi();
    } catch {
        // Senza sessionStorage non si può ricordare: meglio non mostrarlo a
        // ogni pagina che mostrarlo sempre.
        return true;
    }
};

const ricordaVisto = () => {
    try {
        sessionStorage.setItem(CHIAVE_VISTO, oggi());
    } catch {
        // Niente da fare: al prossimo caricamento giaVisto() dirà di no.
    }
};

const apri = async () => {
    if (aperto.value || giaVisto()) {
        return;
    }

    aperto.value = true;
    ricordaVisto();
    await nextTick();
    // showModal(): il fuoco resta dentro, Esc chiude, il resto della pagina
    // diventa inerte. È la stessa scelta di LiveStreamModal.
    dialogo.value?.showModal?.();
};

const chiudi = () => {
    dialogo.value?.close?.();
    aperto.value = false;
};

// Due finestre sovrapposte al primo ingresso sarebbero troppe: il pop-up
// aspetta che il visitatore abbia risposto al banner dei cookie.
let attesa = null;
const dopoIlConsenso = () => {
    window.removeEventListener(EVENTO_CAMBIO, dopoIlConsenso);
    attesa = setTimeout(apri, 800);
};

onMounted(() => {
    if (!props.popup || giaVisto()) {
        return;
    }

    if (leggiIlConsenso(page.props.consensoCookie?.versione ?? null).scelto) {
        attesa = setTimeout(apri, 1500);
    } else {
        window.addEventListener(EVENTO_CAMBIO, dopoIlConsenso);
    }
});

onUnmounted(() => {
    clearTimeout(attesa);
    window.removeEventListener(EVENTO_CAMBIO, dopoIlConsenso);
});
</script>

<template>
    <dialog
        v-if="popup"
        ref="dialogo"
        aria-labelledby="popup-match-day-titolo"
        class="popup-match-day m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl p-0 shadow-2xl backdrop:bg-gray-950/80"
        @close="aperto = false"
    >
        <div class="relative bg-gradient-to-br from-savino-blue to-gray-950 px-8 py-10 text-center text-white">
            <button
                type="button"
                class="absolute top-3 right-3 rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                :aria-label="$t('common.close')"
                @click="chiudi"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            <h2 id="popup-match-day-titolo" class="mt-2 text-3xl font-black uppercase tracking-tight">
                {{ popup.titolo || $t('home.match_day_badge') }}
            </h2>
            <p v-if="popup.testo" class="mt-4 text-white/80 leading-relaxed">{{ popup.testo }}</p>
            <a
                :href="indirizzo"
                v-bind="isExternalLink(indirizzo) ? externalLinkAttrs(indirizzo) : {}"
                class="mt-8 inline-flex items-center gap-3 rounded-lg bg-savino-fucsia px-8 py-4 text-sm font-bold uppercase tracking-wider text-white hover:bg-white hover:text-savino-blue transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                @click="chiudi"
            >
                {{ popup.pulsante || $t('common.buy_tickets') }}
            </a>
        </div>
    </dialog>
</template>
