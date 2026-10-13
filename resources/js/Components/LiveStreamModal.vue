<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import ContenutoIncorporato from '@/Components/ContenutoIncorporato.vue';
import DirettaConSponsor from '@/Components/DirettaConSponsor.vue';
import { useTranslations } from '@/Composables/useTranslations.js';

const $t = useTranslations();

const props = defineProps({
    // { title, embedUrl, url, sponsor } — embedUrl è valorizzato solo per le
    // piattaforme incorporabili (vedi App\Support\LiveStream); `sponsor`
    // solo con la diretta con sponsor accesa (DirettaPerIlSito).
    stream: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const isOpen = computed(() => Boolean(props.stream?.embedUrl));

const close = () => emit('close');

// Spento l'interruttore la finestra resta quella di sempre.
const conSponsor = computed(() => Boolean(props.stream?.sponsor));

const dialogEl = ref(null);
const diretta = ref(null);

// Esc col fuoco fuori dal contenitore (dentro l'iframe o sullo sfondo) arriva
// alla finestra come `cancel`: a tutto schermo simulato deve chiudere quello,
// non la diretta.
const allAnnullamento = (evento) => {
    if (diretta.value?.simulato) {
        evento.preventDefault();
        diretta.value.esci();
    }
};

// `showModal()` mette la finestra nel top layer: il fuoco resta dentro, l'Esc
// la chiude e lo sfondo lo disegna ::backdrop. Prima erano tre cose scritte a
// mano, e l'Esc funzionava solo grazie a un ascoltatore su tutta la pagina.
watch(isOpen, async (open) => {
    if (typeof document === 'undefined') {
        return;
    }

    await nextTick();

    const el = dialogEl.value;

    if (el && open && !el.open) {
        el.showModal();
    }

    if (el && !open && el.open) {
        el.close();
    }

    // La pagina sotto non deve scorrere: showModal() blocca il clic, non la rotella.
    document.body.style.overflow = open ? 'hidden' : '';
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <dialog
        ref="dialogEl"
        class="live-stream-dialog"
        :aria-label="stream?.title || $t('stream.modal_title')"
        @close="close"
        @cancel="allAnnullamento"
        @click.self="close"
    >
        <div v-if="isOpen" class="live-stream-finestra w-full max-w-5xl">
            <div class="flex items-center justify-between gap-4 mb-3">
                <p class="text-white font-bold uppercase tracking-wider text-sm truncate">
                    <span class="inline-flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-savino-red animate-pulse"></span>
                        {{ stream?.title || $t('stream.modal_title') }}
                    </span>
                </p>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <button
                        type="button"
                        class="w-10 h-10 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20"
                        :aria-label="$t('stream.close')"
                        @click="close"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>

            <DirettaConSponsor
                v-if="conSponsor"
                ref="diretta"
                :src="stream.embedUrl"
                :titolo="stream?.title || $t('stream.modal_title')"
                :sponsor="stream.sponsor"
            />

            <div v-else class="relative w-full aspect-video bg-black rounded-xl overflow-hidden shadow-2xl">
                <!-- La finestra si apre dal pulsante "Guarda la diretta": l'avvio
                     automatico è chiesto dal visitatore. Il riquadro, invece,
                     aspetta comunque il consenso di marketing o il clic. -->
                <div class="absolute inset-0">
                    <ContenutoIncorporato
                        :src="stream.embedUrl"
                        :titolo="stream?.title || $t('stream.modal_title')"
                        avvio-automatico
                        allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                    />
                </div>
            </div>
        </div>
    </dialog>
</template>

<style scoped>
/* Il browser dà a <dialog> bordo, sfondo e dimensioni proprie: qui serve una
   finestra a tutto schermo con il video al centro. */
.live-stream-dialog {
    width: 100%;
    max-width: none;
    height: 100%;
    max-height: none;
    padding: 1rem;
    border: 0;
    background: transparent;
    overflow-y: auto;
}

.live-stream-dialog[open] {
    display: flex;
    align-items: safe center;
    justify-content: center;
}

/* Su un portatile basso (1366×768) il 16:9 a tutta larghezza, con testata e
   comandi, non ci stava: tagliava la X in alto e «Salta» in basso. La
   larghezza si ricava dall'altezza disponibile (11,5rem = margini, testata,
   comandi dello spot e del tutto schermo). */
.live-stream-finestra {
    max-width: min(64rem, calc((100dvh - 11.5rem) * 16 / 9));
    min-width: min(100%, 30rem);
}

.live-stream-dialog::backdrop {
    background: rgb(0 0 0 / 0.9);
}
</style>
