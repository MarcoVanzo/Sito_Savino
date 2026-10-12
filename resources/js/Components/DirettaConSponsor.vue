<script setup>
/**
 * La diretta di una gara con gli sponsor intorno, mai sopra.
 *
 * 1. Pre-roll: uno spot nel nostro player, PRIMA di caricare la diretta
 *    (l'iframe non esiste ancora). «Salta» dopo i secondi decisi dalla
 *    redazione, audio spento di partenza.
 * 2. Diretta: `ContenutoIncorporato` con l'avvio automatico (il clic su
 *    «Guarda la diretta» è la richiesta del visitatore) e il consenso come
 *    sempre.
 * 3. Cornice: quando c'è uno sponsor in onda il player si riduce in alto a
 *    sinistra e lo sponsor compare a destra e sotto; sul telefono sotto il
 *    riquadro. Cambia solo la misura del contenitore: l'iframe non si
 *    smonta né si ricarica.
 *
 * Tutto schermo: quello del sito, sul contenitore intero (lo sponsor resta),
 * con il pulsante sotto il riquadro; quello di YouTube è spento (`fs=0`). Su
 * iPhone, dove gli elementi non vanno a tutto schermo, si simula.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ContenutoIncorporato from '@/Components/ContenutoIncorporato.vue';
import { useStatoDellaDiretta } from '@/Composables/useStatoDellaDiretta.js';
import { useTranslations } from '@/Composables/useTranslations.js';
import {
    disposizione,
    indirizzoSenzaTuttoSchermo,
    modoDellaDisposizione,
    prerollGiaVisto,
    sceltaDelPreroll,
    segnaPrerollVisto,
    stileDelRettangolo,
    tuttoSchermoDisponibile,
} from '@/Support/direttaConSponsor.js';

const $t = useTranslations();

const props = defineProps({
    src: { type: String, required: true },
    titolo: { type: String, default: '' },
    linkDiretto: { type: String, default: null },
    // DirettaPerIlSito: { gara, preroll: { salta_dopo, creativita: [...] }, stato, ora }
    sponsor: { type: Object, required: true },
    // Uno sponsor imposto da fuori (anteprime e test): altrimenti decide lo stato.
    contenuto: { type: Object, default: null },
});

const emit = defineEmits(['evento']);

// Letto subito: con l'autoplay disegnato acceso, il video partirebbe prima.
const movimentoRidotto = ref(typeof window !== 'undefined' && Boolean(window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches));

// ─── Pre-roll ────────────────────────────────────────────────────────────
const spot = ref(null);
const fase = ref('diretta');
const secondiTrascorsi = ref(0);
const audio = ref(false);
const videoSpot = ref(null);
let orologio = null;

const saltaDopo = computed(() => Number(props.sponsor?.preroll?.salta_dopo ?? 0));
const saltabile = computed(() => saltaDopo.value > 0);
const mancanoASaltare = computed(() => Math.max(0, saltaDopo.value - secondiTrascorsi.value));
// Con meno movimento il video non parte da solo: resta il primo fotogramma,
// e lo spot dura come un'immagine.
const spotFermo = computed(() => spot.value?.tipo === 'immagine' || movimentoRidotto.value);

const fermaOrologio = () => {
    if (orologio !== null) {
        clearInterval(orologio);
        orologio = null;
    }
};

const vaiAllaDiretta = (comeFinisce) => {
    if (fase.value !== 'preroll') {
        return;
    }

    fermaOrologio();
    if (comeFinisce && spot.value) {
        emit('evento', { evento: comeFinisce, creativita: spot.value.id });
    }
    fase.value = 'diretta';
};

const salta = () => {
    if (!saltabile.value || mancanoASaltare.value > 0) {
        return;
    }

    vaiAllaDiretta('preroll_saltato');
};

/**
 * Lo spot si sceglie subito, prima del primo disegno: con la diretta come
 * fase iniziale l'iframe (e la lettura dello stato) partirebbero per un
 * istante prima dello spot.
 */
const scelto = prerollGiaVisto(props.sponsor?.gara) ? null : sceltaDelPreroll(props.sponsor?.preroll);

if (scelto) {
    spot.value = scelto;
    fase.value = 'preroll';
}

const avviaPreroll = () => {
    if (fase.value !== 'preroll') {
        return;
    }

    segnaPrerollVisto(props.sponsor.gara);
    emit('evento', { evento: 'preroll_avviato', creativita: scelto.id });

    orologio = setInterval(() => {
        secondiTrascorsi.value++;

        // Le immagini (e i video fermi) durano quanto deciso nel pannello.
        if (spotFermo.value && secondiTrascorsi.value >= Number(scelto.durata || 10)) {
            vaiAllaDiretta('preroll_completato');
        }
    }, 1000);
};

const cambiaAudio = () => {
    audio.value = !audio.value;
    const video = videoSpot.value;

    if (video) {
        video.defaultMuted = !audio.value;
        video.toggleAttribute('muted', !audio.value);
        video.muted = !audio.value;
    }
};

// Un video che non si carica non deve fermare la diretta.
const spotNonDisponibile = () => vaiAllaDiretta(null);

const apriSponsor = (creativita) => {
    if (!creativita?.link) {
        return;
    }

    window.open(creativita.link, '_blank', 'noopener');
    emit('evento', { evento: 'clic', creativita: creativita.id });
};

// ─── Stato durante la gara ──────────────────────────────────────────────
const inDiretta = computed(() => fase.value === 'diretta');
const statoDellaDiretta = useStatoDellaDiretta({
    statoUrl: props.sponsor?.stato ?? null,
    oraUrl: props.sponsor?.ora ?? null,
    attivo: inDiretta,
});
const inOnda = computed(() => props.contenuto ?? statoDellaDiretta.contenuto.value);

// Ogni volta che compare uno sponsor, una visualizzazione (ciclo o regia).
watch(() => inOnda.value?.id, (id) => {
    if (id != null && fase.value === 'diretta') {
        emit('evento', { evento: statoDellaDiretta.inPausa.value ? 'pausa_mostrata' : 'ciclo_mostrato', creativita: id });
    }
});

// ─── Disposizione e cornice ─────────────────────────────────────────────
const contenitore = ref(null);
const riquadro = ref(null);
const larghezza = ref(1280);
let osservatore = null;

const modo = computed(() => modoDellaDisposizione({ larghezza: larghezza.value, conSponsor: fase.value === 'diretta' && Boolean(inOnda.value) }));
const rettangoli = computed(() => disposizione(modo.value));
const srcSenzaTuttoSchermo = computed(() => indirizzoSenzaTuttoSchermo(props.src));
const fasciaMobile = computed(() => inOnda.value?.fascia_mobile || null);

// ─── Tutto schermo ──────────────────────────────────────────────────────
const tuttoSchermo = ref(false);
const simulato = ref(false);

const entra = async () => {
    const el = contenitore.value;

    if (tuttoSchermoDisponibile(el)) {
        try {
            await el.requestFullscreen();

            return;
        } catch {
            // Rifiutato (es. dentro un iframe): si simula.
        }
    }

    simulato.value = true;
    tuttoSchermo.value = true;
};

const esci = async () => {
    if (simulato.value) {
        simulato.value = false;
        tuttoSchermo.value = false;

        return;
    }

    if (document.fullscreenElement) {
        await document.exitFullscreen().catch(() => {});
    }
};

const alternaTuttoSchermo = () => (tuttoSchermo.value ? esci() : entra());

const allaUscitaDalTuttoSchermo = () => {
    tuttoSchermo.value = document.fullscreenElement === contenitore.value;
};

const tastoEsc = (evento) => {
    if (evento.key === 'Escape' && simulato.value) {
        evento.stopPropagation();
        esci();
    }
};

// ─── Annunci per gli screen reader ──────────────────────────────────────
const annuncio = computed(() => {
    if (fase.value === 'preroll' && spot.value) {
        return $t('stream.preroll_annuncio', { sponsor: spot.value.sponsor });
    }

    return inOnda.value ? $t('stream.accanto', { sponsor: inOnda.value.sponsor }) : '';
});

onMounted(() => {
    avviaPreroll();
    faiPartireLoSpot();

    if (typeof ResizeObserver !== 'undefined' && contenitore.value) {
        osservatore = new ResizeObserver(([voce]) => {
            larghezza.value = voce.contentRect.width;
        });
        osservatore.observe(contenitore.value);
    } else if (contenitore.value) {
        larghezza.value = contenitore.value.clientWidth || larghezza.value;
    }

    document.addEventListener('fullscreenchange', allaUscitaDalTuttoSchermo);
});

onBeforeUnmount(() => {
    fermaOrologio();
    osservatore?.disconnect();
    document.removeEventListener('fullscreenchange', allaUscitaDalTuttoSchermo);
});

/**
 * Muto davvero: Vue imposta `muted` solo come proprietà, e Safari per iPhone
 * fa partire da solo un video soltanto se l'attributo c'è.
 */
const silenzia = (video) => {
    if (video) {
        video.defaultMuted = true;
        video.muted = true;
        video.setAttribute('muted', '');
    }
};

// Il video parte muto da sé (autoplay ammesso dai browser solo così).
const faiPartireLoSpot = async () => {
    if (fase.value === 'preroll' && !movimentoRidotto.value) {
        await nextTick();
        silenzia(videoSpot.value);
        videoSpot.value?.play?.()?.catch?.(() => {});
    }
};

defineExpose({ fase, modo });
</script>

<template>
    <div
        ref="contenitore"
        class="diretta-con-sponsor"
        :class="{ 'diretta-con-sponsor--simulato': simulato, 'diretta-con-sponsor--tutto-schermo': tuttoSchermo }"
        data-diretta-con-sponsor
        @keydown="tastoEsc"
    >
        <p class="sr-only" aria-live="polite">{{ annuncio }}</p>

        <div ref="riquadro" class="diretta-riquadro relative w-full aspect-video bg-black rounded-xl overflow-hidden shadow-2xl">
            <!-- Pre-roll: un player nostro, prima della diretta e non sopra. -->
            <section
                v-if="fase === 'preroll' && spot"
                class="absolute inset-0 flex flex-col"
                :aria-label="$t('stream.preroll_regione')"
                data-preroll
            >
                <div class="relative flex-1 min-h-0 bg-black">
                    <img
                        v-if="spot.tipo === 'immagine'"
                        :src="spot.url"
                        :alt="spot.alt"
                        class="w-full h-full object-contain"
                        :class="{ 'cursor-pointer': spot.link }"
                        @click="apriSponsor(spot)"
                    >
                    <video
                        v-else
                        ref="videoSpot"
                        :src="spot.url"
                        class="w-full h-full object-contain"
                        :class="{ 'cursor-pointer': spot.link }"
                        playsinline
                        :muted="!audio"
                        :autoplay="!movimentoRidotto"
                        preload="auto"
                        :aria-label="spot.alt || spot.sponsor"
                        @ended="vaiAllaDiretta('preroll_completato')"
                        @error="spotNonDisponibile"
                        @click="apriSponsor(spot)"
                    ></video>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 bg-savino-blue/95 px-3 py-2 text-xs font-bold text-white">
                    <div class="flex items-center gap-2">
                        <button
                            v-if="spot.tipo === 'video' && !movimentoRidotto"
                            type="button"
                            class="rounded-lg bg-white/10 px-3 py-2 hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                            :aria-pressed="audio ? 'true' : 'false'"
                            @click="cambiaAudio"
                        >{{ audio ? $t('stream.audio_disattiva') : $t('stream.audio_attiva') }}</button>
                        <a
                            v-if="spot.link"
                            :href="spot.link"
                            target="_blank"
                            rel="sponsored noopener"
                            class="rounded-lg bg-white/10 px-3 py-2 hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                            :aria-label="$t('stream.visita_nome', { sponsor: spot.sponsor })"
                            @click="emit('evento', { evento: 'clic', creativita: spot.id })"
                        >{{ $t('stream.visita') }}</a>
                    </div>
                    <button
                        v-if="saltabile"
                        type="button"
                        class="rounded-lg bg-white px-4 py-2 text-savino-blue hover:bg-white/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white aria-disabled:opacity-70"
                        :aria-disabled="mancanoASaltare > 0 ? 'true' : 'false'"
                        data-salta
                        @click="salta"
                    >{{ mancanoASaltare > 0 ? $t('stream.salta_tra', { secondi: mancanoASaltare }) : $t('stream.salta') }}</button>
                </div>
            </section>

            <template v-else>
                <!-- Lo sponsor accanto al player: due ritagli della stessa
                     creatività 1920×1080, colonna a destra e fascia sotto.
                     Nessuno dei due tocca il rettangolo del player. -->
                <template v-if="modo === 'cornice' && inOnda">
                    <a
                        v-for="pezzo in ['colonna', 'fascia']"
                        :key="pezzo"
                        :href="inOnda.link || undefined"
                        target="_blank"
                        rel="sponsored noopener"
                        class="diretta-cornice absolute overflow-hidden"
                        :class="`diretta-cornice--${pezzo}`"
                        :style="stileDelRettangolo(rettangoli[pezzo])"
                        :aria-label="pezzo === 'colonna' && inOnda.link ? $t('stream.visita_nome', { sponsor: inOnda.sponsor }) : undefined"
                        :aria-hidden="pezzo === 'fascia' ? 'true' : undefined"
                        :tabindex="pezzo === 'fascia' || !inOnda.link ? -1 : undefined"
                        :data-sponsor="pezzo"
                        @click="inOnda.link ? emit('evento', { evento: 'clic', creativita: inOnda.id }) : $event.preventDefault()"
                    >
                        <img
                            v-if="inOnda.tipo === 'immagine'"
                            :src="inOnda.url"
                            :alt="pezzo === 'colonna' ? inOnda.alt : ''"
                            class="diretta-cornice__file"
                        >
                        <video
                            v-else
                            :ref="silenzia"
                            :src="inOnda.url"
                            class="diretta-cornice__file"
                            muted
                            preload="metadata"
                            :autoplay="!movimentoRidotto"
                            :loop="!movimentoRidotto"
                            playsinline
                            aria-hidden="true"
                        ></video>
                    </a>
                </template>

                <div class="diretta-player absolute z-10" :style="stileDelRettangolo(rettangoli.player)" data-player>
                    <ContenutoIncorporato
                        :src="srcSenzaTuttoSchermo"
                        :titolo="titolo"
                        :link-diretto="linkDiretto"
                        avvio-automatico
                        allow="autoplay; encrypted-media; picture-in-picture"
                    />
                </div>
            </template>
        </div>

        <!-- Telefono: la fascia sotto il riquadro, mai sopra il player. -->
        <a
            v-if="fase === 'diretta' && modo === 'telefono' && inOnda"
            :href="inOnda.link || undefined"
            target="_blank"
            rel="sponsored noopener"
            class="diretta-fascia-mobile mt-2 block overflow-hidden rounded-xl"
            :class="{ 'diretta-fascia-mobile--ritaglio': !fasciaMobile }"
            :aria-label="inOnda.link ? $t('stream.visita_nome', { sponsor: inOnda.sponsor }) : undefined"
            :tabindex="inOnda.link ? undefined : -1"
            data-sponsor="fascia-mobile"
            @click="inOnda.link ? emit('evento', { evento: 'clic', creativita: inOnda.id }) : $event.preventDefault()"
        >
            <img v-if="fasciaMobile" :src="fasciaMobile" :alt="inOnda.alt" class="block w-full h-auto">
            <img v-else-if="inOnda.tipo === 'immagine'" :src="inOnda.url" :alt="inOnda.alt" class="diretta-cornice__file">
            <video v-else :ref="silenzia" :src="inOnda.url" class="diretta-cornice__file" muted preload="metadata" :autoplay="!movimentoRidotto" :loop="!movimentoRidotto" playsinline aria-hidden="true"></video>
        </a>

        <div class="diretta-comandi mt-3 flex flex-wrap items-center justify-end gap-3 text-xs font-bold uppercase tracking-wider">
            <p v-if="simulato" class="diretta-ruota mr-auto normal-case tracking-normal text-white/70">{{ $t('stream.ruota') }}</p>
            <a
                v-if="linkDiretto"
                :href="linkDiretto"
                target="_blank"
                rel="noopener noreferrer"
                class="text-white/70 hover:text-white"
            >{{ $t('stream.open_external') }}</a>
            <button
                type="button"
                class="rounded-lg bg-white/10 px-3 py-2 text-white hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                :aria-pressed="tuttoSchermo ? 'true' : 'false'"
                data-tutto-schermo
                @click="alternaTuttoSchermo"
            >{{ tuttoSchermo ? $t('stream.esci_tutto_schermo') : $t('stream.tutto_schermo') }}</button>
        </div>
    </div>
</template>

<style scoped>
.diretta-player,
.diretta-cornice {
    transition: left 300ms ease, top 300ms ease, width 300ms ease, height 300ms ease, opacity 300ms ease;
}

/* I due ritagli mostrano la parte giusta della creatività 1920×1080: la
   colonna è l'ultimo quarto in alto (da 1440 a 1920 px), la fascia l'ultimo
   quarto in basso. */
.diretta-cornice__file {
    position: absolute;
    max-width: none;
    object-fit: fill;
}

.diretta-cornice--colonna .diretta-cornice__file {
    width: 400%;
    height: 133.3333%;
    left: -300%;
    top: 0;
}

.diretta-cornice--fascia .diretta-cornice__file {
    width: 100%;
    height: 400%;
    left: 0;
    top: -300%;
}

/* Sul telefono, senza fascia propria: il quarto basso della cornice. */
.diretta-fascia-mobile--ritaglio {
    position: relative;
    aspect-ratio: 1920 / 270;
}

.diretta-fascia-mobile--ritaglio .diretta-cornice__file {
    width: 100%;
    height: 400%;
    left: 0;
    top: -300%;
}

/* Tutto schermo: riquadro 16:9 al centro, bande nere, comandi sotto. */
.diretta-con-sponsor:fullscreen,
.diretta-con-sponsor--simulato {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #000;
    padding: 0.5rem;
}

.diretta-con-sponsor--simulato {
    position: fixed;
    inset: 0;
    z-index: 100;
}

.diretta-con-sponsor:fullscreen .diretta-riquadro,
.diretta-con-sponsor--simulato .diretta-riquadro {
    width: min(100%, calc((100vh - 4.5rem) * 16 / 9));
    border-radius: 0;
}

.diretta-con-sponsor:fullscreen .diretta-comandi,
.diretta-con-sponsor--simulato .diretta-comandi {
    width: min(100%, calc((100vh - 4.5rem) * 16 / 9));
}

@media (prefers-reduced-motion: reduce) {
    .diretta-player,
    .diretta-cornice {
        transition: none;
    }
}
</style>
