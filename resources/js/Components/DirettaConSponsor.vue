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
 * Nessun collegamento verso la piattaforma (scelta della società: dalla
 * diretta del sito non si esce verso YouTube); restano solo i link degli
 * sponsor. Tutto schermo: quello del sito, sul contenitore intero (lo sponsor resta),
 * con il pulsante sotto il riquadro; quello di YouTube è spento (`fs=0`). Su
 * iPhone, dove gli elementi non vanno a tutto schermo, si simula.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ContenutoIncorporato from '@/Components/ContenutoIncorporato.vue';
import { useStatoDellaDiretta } from '@/Composables/useStatoDellaDiretta.js';
import { useTranslations } from '@/Composables/useTranslations.js';
import { registraEvento } from '@/Support/eventiDellaDiretta.js';
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
const pagina = usePage();

/**
 * Il JSON dello stato è uno per tutte le lingue e porta il testo alternativo
 * come `{it, en}`; il pre-roll arriva con la pagina, già nella sua lingua.
 */
const testoAlternativo = (creativita) => {
    const alt = creativita?.alt;

    if (alt && typeof alt === 'object') {
        return alt[pagina.props.locale] || alt.it || Object.values(alt).find(Boolean) || '';
    }

    return alt || '';
};

const props = defineProps({
    src: { type: String, required: true },
    titolo: { type: String, default: '' },
    // DirettaPerIlSito: { gara, preroll: { salta_dopo, creativita: [...] }, stato, ora }
    sponsor: { type: Object, required: true },
    // Uno sponsor imposto da fuori (anteprime e test): altrimenti decide lo stato.
    contenuto: { type: Object, default: null },
});

const emitDelComponente = defineEmits(['evento']);

/** Ogni evento va al report dello sponsor (contatore anonimo) e a chi ascolta. */
const emit = (nome, dati) => {
    registraEvento(props.sponsor?.eventi, dati);
    emitDelComponente(nome, dati);
};

// Letto subito: con l'autoplay disegnato acceso, il video partirebbe prima.
const movimentoRidotto = ref(typeof window !== 'undefined' && Boolean(window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches));

// ─── Pre-roll ────────────────────────────────────────────────────────────
const spot = ref(null);
const fase = ref('diretta');
const secondiTrascorsi = ref(0);
const audio = ref(false);
const videoSpot = ref(null);
// Il browser ha rifiutato di far partire il video (iPhone in risparmio
// energetico, autoplay bloccato): lo spot dura come un'immagine, o la diretta
// non partirebbe mai con uno spot che non si può saltare.
const spotBloccato = ref(false);
// Spot in pausa dal visitatore (WCAG 2.2.2): l'orologio si ferma con lui.
const spotInPausa = ref(false);
let orologio = null;

// Margine oltre la durata dichiarata: un video che resta in caricamento senza
// errori non deve tenere ferma la diretta.
const MARGINE_DELLO_SPOT = 10;

const saltaDopo = computed(() => Number(props.sponsor?.preroll?.salta_dopo ?? 0));
const saltabile = computed(() => saltaDopo.value > 0);
const mancanoASaltare = computed(() => Math.max(0, saltaDopo.value - secondiTrascorsi.value));
// Con meno movimento il video non parte da solo: resta il primo fotogramma,
// e lo spot dura come un'immagine.
const spotFermo = computed(() => spot.value?.tipo === 'immagine' || movimentoRidotto.value || spotBloccato.value);

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

    // Il pulsante che aveva il fuoco («Salta») sparisce con lo spot: senza
    // spostarlo, chi usa la tastiera ricomincerebbe dall'inizio della finestra.
    const fuocoNeiComandi = Boolean(contenitore.value?.querySelector('[data-comandi-spot]')?.contains(document.activeElement));
    fase.value = 'diretta';

    if (fuocoNeiComandi) {
        nextTick(() => pulsanteTuttoSchermo.value?.focus());
    }
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
// In prova (staff) lo spot si rivede a ogni apertura.
const inProva = Boolean(props.sponsor?.prova);
const scelto = !inProva && prerollGiaVisto(props.sponsor?.gara) ? null : sceltaDelPreroll(props.sponsor?.preroll);

if (scelto) {
    spot.value = scelto;
    fase.value = 'preroll';
}

const avviaPreroll = () => {
    if (fase.value !== 'preroll') {
        return;
    }

    if (!inProva) {
        segnaPrerollVisto(props.sponsor.gara);
    }

    // Il video si conta quando parte davvero (`playing`), non quando si apre.
    if (scelto.tipo === 'immagine') {
        emit('evento', { evento: 'preroll_avviato', creativita: scelto.id });
    }

    orologio = setInterval(() => {
        if (spotInPausa.value) {
            return;
        }

        secondiTrascorsi.value++;
        const durata = Number(scelto.durata || 10);

        // Le immagini (e i video fermi) durano quanto deciso nel pannello.
        // Un video mai partito non è «completato»: senza «avviato» il report
        // andava oltre il 100% (autoplay negato, meno movimento).
        if (spotFermo.value && secondiTrascorsi.value >= durata) {
            vaiAllaDiretta(scelto.tipo === 'immagine' || avvioContato ? 'preroll_completato' : null);
        } else if (secondiTrascorsi.value >= durata + MARGINE_DELLO_SPOT) {
            vaiAllaDiretta(null);
        }
    }, 1000);
};

let avvioContato = false;
const spotPartito = () => {
    if (!avvioContato && spot.value) {
        avvioContato = true;
        emit('evento', { evento: 'preroll_avviato', creativita: spot.value.id });
    }
};

const alternaPausaDelloSpot = () => {
    const video = videoSpot.value;

    if (!video) {
        return;
    }

    if (video.paused) {
        spotInPausa.value = false;
        video.play?.()?.catch?.(() => {});
    } else {
        spotInPausa.value = true;
        video.pause();
    }
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
    statoDiProva: props.sponsor?.stato_prova ?? null,
});
const inOnda = computed(() => props.contenuto ?? statoDellaDiretta.contenuto.value);

// Ogni volta che compare uno sponsor, una visualizzazione (ciclo o regia).
// Con la scheda nascosta nessuno la vede: non si conta.
watch(() => inOnda.value?.id, (id) => {
    if (id != null && fase.value === 'diretta' && document.visibilityState !== 'hidden') {
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

// Le cornici video si possono fermare (WCAG 2.2.2): restano ferme anche per
// gli sponsor successivi, finché il visitatore non le fa ripartire.
const cornicePausa = ref(false);
// Senza avvio automatico (meno movimento) Safari su iPhone non disegna il
// primo fotogramma e il riquadro dello sponsor restava nero: il frammento
// `#t=0.1` glielo fa caricare.
const videoFermo = (url) => (movimentoRidotto.value && url ? `${url}#t=0.1` : url);

const corniceVideo = computed(() => fase.value === 'diretta' && inOnda.value?.tipo === 'video' && !movimentoRidotto.value);

const applicaPausaDellaCornice = () => {
    contenitore.value?.querySelectorAll('video[data-cornice]').forEach((video) => {
        if (cornicePausa.value) {
            video.pause();
        } else {
            video.play?.()?.catch?.(() => {});
        }
    });
};

watch(cornicePausa, applicaPausaDellaCornice);
watch(() => inOnda.value?.id, () => nextTick(applicaPausaDellaCornice));

// ─── Tutto schermo ──────────────────────────────────────────────────────
const tuttoSchermo = ref(false);
const simulato = ref(false);
const pulsanteTuttoSchermo = ref(null);

// Quanto si aspetta il tutto schermo del browser prima di simularlo: quello
// vero risponde in meno di 200 ms, e se arriva dopo sostituisce il simulato.
const ATTESA_DEL_TUTTO_SCHERMO = 500;

/**
 * Il tutto schermo del browser, e se non arriva quello simulato. Alcuni
 * browser incorporati (app, anteprime) lasciano la richiesta in sospeso per
 * sempre, senza accettarla né rifiutarla: aspettandola e basta, il pulsante
 * non faceva niente.
 */
const entra = async () => {
    const el = contenitore.value;

    if (tuttoSchermoDisponibile(el)) {
        const riuscito = await Promise.race([
            Promise.resolve(el.requestFullscreen()).then(() => true, () => false),
            new Promise((risolvi) => setTimeout(() => risolvi(false), ATTESA_DEL_TUTTO_SCHERMO)),
        ]);

        if (riuscito || document.fullscreenElement === el) {
            return;
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
    const vero = document.fullscreenElement === contenitore.value;

    // Il tutto schermo vero arrivato tardi, a simulato già acceso, lo sostituisce.
    if (vero) {
        simulato.value = false;
    }

    tuttoSchermo.value = vero || simulato.value;
};

// Esc chiude lo schermo intero simulato e basta: senza `preventDefault` il
// `<dialog>` della diretta lo prende come richiesta di chiusura e sparisce tutto.
const tastoEsc = (evento) => {
    if (evento.key === 'Escape' && simulato.value) {
        evento.preventDefault();
        evento.stopPropagation();
        esci();
    }
};

// ─── Annunci per gli screen reader ──────────────────────────────────────
// La regione nasce vuota: un testo già presente al montaggio non si legge.
const annunciPronti = ref(false);
const annuncio = computed(() => {
    if (!annunciPronti.value) {
        return '';
    }

    if (fase.value === 'preroll' && spot.value) {
        return $t('stream.preroll_annuncio', { sponsor: spot.value.sponsor });
    }

    return inOnda.value ? $t('stream.accanto', { sponsor: inOnda.value.sponsor }) : '';
});

let attesaDegliAnnunci = null;

onMounted(() => {
    avviaPreroll();
    faiPartireLoSpot();
    attesaDegliAnnunci = setTimeout(() => {
        annunciPronti.value = true;
    }, 300);

    // Si misura il riquadro, non il contenitore: a tutto schermo il riquadro
    // è limitato dall'altezza e il player scenderebbe sotto i 480 px.
    if (typeof ResizeObserver !== 'undefined' && riquadro.value) {
        osservatore = new ResizeObserver(([voce]) => {
            larghezza.value = voce.contentRect.width;
        });
        osservatore.observe(riquadro.value);
    } else if (riquadro.value) {
        larghezza.value = riquadro.value.clientWidth || larghezza.value;
    }

    document.addEventListener('fullscreenchange', allaUscitaDalTuttoSchermo);
});

onBeforeUnmount(() => {
    fermaOrologio();
    clearTimeout(attesaDegliAnnunci);
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
        videoSpot.value?.play?.()?.catch?.(() => {
            spotBloccato.value = true;
        });
    }
};

// Con meno movimento lo spot video non parte da sé: lo fa partire il visitatore.
const guardaLoSpot = () => {
    spotBloccato.value = false;
    videoSpot.value?.play?.()?.catch?.(() => {});
};

defineExpose({ fase, modo, simulato, esci });
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
                class="absolute inset-0"
                :aria-label="$t('stream.preroll_regione')"
                data-preroll
            >
                <div class="relative h-full bg-black">
                    <img
                        v-if="spot.tipo === 'immagine'"
                        :src="spot.url"
                        :alt="spot.alt || ''"
                        class="w-full h-full object-contain"
                        :class="{ 'cursor-pointer': spot.link }"
                        @click="apriSponsor(spot)"
                    >
                    <video
                        v-else
                        ref="videoSpot"
                        :src="videoFermo(spot.url)"
                        class="w-full h-full object-contain"
                        :class="{ 'cursor-pointer': spot.link }"
                        playsinline
                        :muted="!audio"
                        :autoplay="!movimentoRidotto"
                        preload="auto"
                        :aria-label="spot.alt || spot.sponsor"
                        @playing="spotPartito"
                        @ended="vaiAllaDiretta('preroll_completato')"
                        @error="spotNonDisponibile"
                    ></video>
                </div>
            </section>

            <template v-else>
                <!-- Lo sponsor accanto al player. La creatività 1920×1080 è
                     disegnata sul riquadro: il quarto in alto a sinistra
                     (1440×810) è la zona del player, colonna e fascia sono il
                     resto. Si mostra quindi un file solo, a riquadro intero,
                     DIETRO il player (che sta sopra, z-10): niente copre la
                     diretta e il video si scarica una volta, non una per
                     ritaglio. Colonna e fascia sono solo le zone del link. -->
                <template v-if="modo === 'cornice' && inOnda">
                    <div class="diretta-cornice absolute inset-0" data-cornice-file>
                        <img
                            v-if="inOnda.tipo === 'immagine'"
                            :src="inOnda.url"
                            :alt="testoAlternativo(inOnda)"
                            class="diretta-cornice__file"
                        >
                        <video
                            v-else
                            :ref="silenzia"
                            :src="videoFermo(inOnda.url)"
                            class="diretta-cornice__file"
                            :aria-label="testoAlternativo(inOnda) || undefined"
                            data-cornice
                            muted
                            :preload="movimentoRidotto ? 'auto' : 'metadata'"
                            :autoplay="!movimentoRidotto"
                            :loop="!movimentoRidotto"
                            playsinline
                        ></video>
                    </div>
                    <template v-if="inOnda.link">
                        <a
                            v-for="pezzo in ['colonna', 'fascia']"
                            :key="pezzo"
                            :href="inOnda.link"
                            target="_blank"
                            rel="sponsored noopener"
                            class="diretta-cornice absolute"
                            :style="stileDelRettangolo(rettangoli[pezzo])"
                            :aria-label="pezzo === 'colonna' ? $t('stream.visita_nome', { sponsor: inOnda.sponsor }) : undefined"
                            :aria-hidden="pezzo === 'fascia' ? 'true' : undefined"
                            :tabindex="pezzo === 'fascia' ? -1 : undefined"
                            :data-sponsor="pezzo"
                            @click="emit('evento', { evento: 'clic', creativita: inOnda.id })"
                        ></a>
                    </template>
                </template>

                <div class="diretta-player absolute z-10" :style="stileDelRettangolo(rettangoli.player)" data-player>
                    <ContenutoIncorporato
                        :src="srcSenzaTuttoSchermo"
                        :titolo="titolo"
                        avvio-automatico
                        allow="autoplay; encrypted-media; picture-in-picture"
                    />
                </div>
            </template>
        </div>

        <!-- I comandi dello spot stanno sotto il riquadro: dentro, sul telefono,
             andavano su due righe e coprivano metà del video. -->
        <div
            v-if="fase === 'preroll' && spot"
            role="group"
            :aria-label="$t('stream.preroll_regione')"
            class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-savino-blue px-3 py-2 text-xs font-bold text-white"
            data-comandi-spot
        >
            <div class="flex items-center gap-2">
                <button
                    v-if="spot.tipo === 'video' && !movimentoRidotto"
                    type="button"
                    class="rounded-lg bg-white/10 px-3 py-2 hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                    :aria-pressed="audio ? 'true' : 'false'"
                    @click="cambiaAudio"
                >{{ audio ? $t('stream.audio_disattiva') : $t('stream.audio_attiva') }}</button>
                <button
                    v-if="spot.tipo === 'video' && !spotFermo"
                    type="button"
                    class="rounded-lg bg-white/10 px-3 py-2 hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                    :aria-pressed="spotInPausa ? 'true' : 'false'"
                    data-pausa-spot
                    @click="alternaPausaDelloSpot"
                >{{ spotInPausa ? $t('stream.riprendi') : $t('stream.pausa') }}</button>
                <button
                    v-if="spot.tipo === 'video' && spotFermo"
                    type="button"
                    class="rounded-lg bg-white/10 px-3 py-2 hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                    data-guarda-spot
                    @click="guardaLoSpot"
                >{{ $t('stream.guarda_spot') }}</button>
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
            <img v-if="fasciaMobile" :src="fasciaMobile" :alt="testoAlternativo(inOnda)" class="block w-full h-auto">
            <img v-else-if="inOnda.tipo === 'immagine'" :src="inOnda.url" :alt="testoAlternativo(inOnda)" class="diretta-cornice__file">
            <video v-else :ref="silenzia" :src="videoFermo(inOnda.url)" class="diretta-cornice__file" data-cornice muted :preload="movimentoRidotto ? 'auto' : 'metadata'" :autoplay="!movimentoRidotto" :loop="!movimentoRidotto" playsinline></video>
        </a>

        <div class="diretta-comandi mt-3 flex flex-wrap items-center justify-end gap-3 text-xs font-bold uppercase tracking-wider">
            <p v-if="simulato" class="diretta-ruota mr-auto normal-case tracking-normal text-white/70">{{ $t('stream.ruota') }}</p>
            <button
                v-if="corniceVideo"
                type="button"
                class="rounded-lg bg-white/10 px-3 py-2 text-white hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                :aria-pressed="cornicePausa ? 'true' : 'false'"
                data-pausa-cornice
                @click="cornicePausa = !cornicePausa"
            >{{ cornicePausa ? $t('stream.riprendi_sponsor') : $t('stream.pausa_sponsor') }}</button>
            <button
                ref="pulsanteTuttoSchermo"
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

/* La creatività copre il riquadro intero, dietro il player. */
.diretta-cornice__file {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    max-width: none;
    object-fit: fill;
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
    width: min(100%, calc((100dvh - 4.5rem) * 16 / 9));
    border-radius: 0;
}

.diretta-con-sponsor:fullscreen .diretta-comandi,
.diretta-con-sponsor--simulato .diretta-comandi,
.diretta-con-sponsor:fullscreen .diretta-fascia-mobile,
.diretta-con-sponsor--simulato .diretta-fascia-mobile,
.diretta-con-sponsor:fullscreen [data-comandi-spot],
.diretta-con-sponsor--simulato [data-comandi-spot] {
    /* Nel contenitore centrato la fascia (che ha solo un rapporto, senza
       larghezza propria) si restringeva a zero e spariva. */
    width: min(100%, calc((100dvh - 4.5rem) * 16 / 9));
}

/* L'invito a girare il telefono serve solo finché è in verticale. */
@media (orientation: landscape) {
    .diretta-ruota {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .diretta-player,
    .diretta-cornice {
        transition: none;
    }
}
</style>
