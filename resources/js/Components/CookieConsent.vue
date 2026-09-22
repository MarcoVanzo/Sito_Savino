<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { updateAnalyticsConsent } from '../analytics.js';
import { updateMarketingConsent } from '../meta-pixel.js';
import { leggiIlConsenso, salvaIlConsenso, registraIlConsenso, consensoInAttesa } from '../consenso.js';

/**
 * L'evento con cui il footer, la cookie policy o qualunque altro punto del sito
 * riaprono le preferenze: un `ref` sul componente non basterebbe, perché il
 * banner è caricato in differita dal layout.
 */
const EVENTO_APERTURA = 'preferenze-cookie:apri';

const page = usePage();

const showBanner = ref(false);
const showSettings = ref(false);

/** Con la scelta fatta il banner sparisce e resta l'icona per tornarci. */
const sceltaFatta = ref(false);

const riferimento = ref(null);
const registratoIl = ref(null);

const consent = reactive({
    necessary: true, // Sempre attivo, non disattivabile
    analytics: false,
    marketing: false,
});

const versione = computed(() => page.props.consensoCookie?.versione ?? null);

const dataLeggibile = computed(() => {
    if (! registratoIl.value) {
        return null;
    }

    const quando = new Date(registratoIl.value);

    return Number.isNaN(quando.getTime())
        ? null
        : quando.toLocaleString(page.props.locale === 'en' ? 'en-GB' : 'it-IT');
});

const apriIlBanner = () => {
    showBanner.value = true;
    showSettings.value = true;
};

onMounted(() => {
    const salvato = leggiIlConsenso(versione.value);

    consent.analytics = salvato.statistiche;
    consent.marketing = salvato.marketing;
    riferimento.value = salvato.riferimento ?? null;
    registratoIl.value = salvato.data ?? null;

    sceltaFatta.value = salvato.scelto;
    showBanner.value = ! salvato.scelto;

    // Il consenso raccolto su un'informativa precedente non vale più: finché
    // non si risponde al banner nuovo, la misurazione si ferma. Senza questo,
    // aggiungere un tracker basterebbe a coprirlo con un sì di mesi prima.
    if (salvato.versioneSuperata) {
        updateAnalyticsConsent(false);
        updateMarketingConsent(false);
    }

    // Una registrazione rimasta indietro (rete assente nel momento della
    // scelta) si completa adesso, senza disturbare il visitatore.
    const inAttesa = consensoInAttesa();

    if (inAttesa) {
        registra(inAttesa.statistiche, inAttesa.marketing);
    }

    window.addEventListener(EVENTO_APERTURA, apriIlBanner);
});

onBeforeUnmount(() => window.removeEventListener(EVENTO_APERTURA, apriIlBanner));

async function registra(statistiche, marketing) {
    const esito = await registraIlConsenso(
        { statistiche, marketing, riferimento: riferimento.value },
        route('consenso-cookie.registra'),
    );

    if (! esito) {
        return;
    }

    riferimento.value = esito.riferimento;
    registratoIl.value = esito.registrato_il;

    // Il riferimento arriva dal server: va riscritto nel browser, o alla
    // prossima modifica il visitatore aprirebbe una seconda storia invece di
    // aggiungere una riga alla propria.
    salvaIlConsenso({
        statistiche,
        marketing,
        versione: versione.value,
        riferimento: esito.riferimento,
        data: esito.registrato_il,
    });
}

const saveConsent = () => {
    salvaIlConsenso({
        statistiche: consent.analytics,
        marketing: consent.marketing,
        versione: versione.value,
        riferimento: riferimento.value,
    });

    // Fino a qui la scelta veniva solo memorizzata e non pilotava nulla: il
    // banner chiedeva un consenso che poi non cambiava il comportamento del
    // sito. Da qui la misurazione parte, o si ferma, davvero.
    updateAnalyticsConsent(consent.analytics);
    updateMarketingConsent(consent.marketing);

    // La prova della scelta, sul server. Non si aspetta: il sito si comporta
    // già come il visitatore ha chiesto.
    registra(consent.analytics, consent.marketing);

    sceltaFatta.value = true;
    showBanner.value = false;
    showSettings.value = false;
};

const acceptAll = () => {
    consent.analytics = true;
    consent.marketing = true;
    saveConsent();
};

const rejectAll = () => {
    consent.analytics = false;
    consent.marketing = false;
    saveConsent();
};

const openSettings = () => {
    showSettings.value = !showSettings.value;
};

// Esponi per revoca esterna (footer link)
defineExpose({ show: apriIlBanner });
</script>

<template>
    <transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div v-if="showBanner" class="fixed bottom-0 left-0 right-0 z-[100] p-4">
            <div class="max-w-4xl mx-auto bg-gray-900/95 backdrop-blur-lg text-white rounded-2xl shadow-[0_-10px_40px_rgba(0,0,0,0.3)] border border-white/10 overflow-hidden">
                <!-- Banner principale -->
                <div class="p-6 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="flex-1">
                        <h4 class="text-sm font-bold mb-1">{{ $t('cookie.title') }}</h4>
                        <p class="text-xs text-gray-400 leading-relaxed">
                            {{ $t('cookie.description') }}
                            <Link :href="route('pages.show', 'privacy-policy')" class="text-savino-fucsia hover:underline">{{ $t('footer.privacy_policy') }}</Link>
                            {{ $t('cookie.and_the') }}
                            <Link :href="route('pages.show', 'cookie-policy')" class="text-savino-fucsia hover:underline">{{ $t('footer.cookie_policy') }}</Link>.
                        </p>
                    </div>
                    <div class="flex gap-2 flex-shrink-0 flex-wrap">
                        <button
type="button"
                            class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white border border-gray-600 hover:border-white/30 rounded-lg transition-all duration-200"
                            @click="openSettings"
                        >
                            {{ $t('cookie.customize') }}
                        </button>
                        <button
type="button"
                            class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white border border-gray-600 hover:border-white/30 rounded-lg transition-all duration-200"
                            @click="rejectAll"
                        >
                            {{ $t('cookie.reject_all') }}
                        </button>
                        <button
type="button"
                            class="px-4 py-2 text-xs font-bold uppercase tracking-wider bg-savino-fucsia text-savino-blue hover:bg-yellow-400 rounded-lg transition-all duration-200 shadow-lg"
                            @click="acceptAll"
                        >
                            {{ $t('cookie.accept_all') }}
                        </button>
                    </div>
                </div>

                <!-- Pannello impostazioni granulari -->
                <transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-[500px] opacity-100"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="max-h-[500px] opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <div v-show="showSettings" class="border-t border-white/10 overflow-hidden">
                        <div class="p-6 space-y-4">
                            <!-- Cookie necessari -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-bold">{{ $t('cookie.necessary_title') }}</p>
                                    <p class="text-xs text-gray-500">{{ $t('cookie.necessary_desc') }}</p>
                                </div>
                                <div class="relative">
                                    <input
id="cookie-necessary" type="checkbox" checked disabled
                                        :aria-label="$t('cookie.necessary_title')"
                                        class="w-10 h-5 rounded-full appearance-none bg-savino-fucsia/50 cursor-not-allowed checked:bg-savino-fucsia" />
                                </div>
                            </div>

                            <!-- Cookie analitici -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-bold">{{ $t('cookie.analytics_title') }}</p>
                                    <p class="text-xs text-gray-500">{{ $t('cookie.analytics_desc') }}</p>
                                </div>
                                <label for="cookie-analytics" class="relative inline-flex items-center cursor-pointer">
                                    <input id="cookie-analytics" v-model="consent.analytics" type="checkbox" class="sr-only peer" :aria-label="$t('cookie.analytics_title')" />
                                    <div class="w-10 h-5 bg-gray-600 rounded-full peer peer-checked:bg-savino-fucsia transition-colors"></div>
                                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                                </label>
                            </div>

                            <!-- Cookie marketing -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-bold">{{ $t('cookie.marketing_title') }}</p>
                                    <p class="text-xs text-gray-500">{{ $t('cookie.marketing_desc') }}</p>
                                </div>
                                <label for="cookie-marketing" class="relative inline-flex items-center cursor-pointer">
                                    <input id="cookie-marketing" v-model="consent.marketing" type="checkbox" class="sr-only peer" :aria-label="$t('cookie.marketing_title')" />
                                    <div class="w-10 h-5 bg-gray-600 rounded-full peer peer-checked:bg-savino-fucsia transition-colors"></div>
                                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                                </label>
                            </div>

                            <!-- Il riferimento della scelta gia' registrata: e' il
                                 numero che il visitatore cita se ci scrive per
                                 chiedere conto del proprio consenso. -->
                            <p v-if="riferimento" class="text-[11px] text-gray-500 leading-relaxed border-t border-white/10 pt-3">
                                {{ $t('cookie.reference_label') }}
                                <span class="font-mono text-gray-400 break-all">{{ riferimento }}</span>
                                <template v-if="dataLeggibile"> &middot; {{ dataLeggibile }}</template>
                            </p>

                            <div class="pt-2 flex justify-end">
                                <button
type="button"
                                    class="px-6 py-2 text-xs font-bold uppercase tracking-wider bg-savino-fucsia text-savino-blue hover:bg-yellow-400 rounded-lg transition-all duration-200"
                                    @click="saveConsent"
                                >
                                    {{ $t('cookie.save_preferences') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </transition>

    <!-- Una volta risposto al banner resta questa: e' l'unico modo che il
         visitatore ha di tornare sulle proprie scelte in qualsiasi pagina.
         Sta in basso a sinistra per non finire sotto il pulsante del carrello
         e sotto i riquadri di aiuto, che stanno a destra. -->
    <transition
        enter-active-class="transition duration-300 ease-out delay-500"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
    >
        <button
            v-if="sceltaFatta && !showBanner"
            type="button"
            :aria-label="$t('cookie.manage_aria')"
            :title="$t('cookie.manage_aria')"
            class="fixed bottom-4 left-4 z-[90] w-11 h-11 rounded-full bg-savino-blue/90 text-white shadow-lg backdrop-blur ring-1 ring-white/20 flex items-center justify-center transition-all duration-200 hover:bg-savino-blue hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-savino-fucsia print:hidden"
            @click="apriIlBanner"
        >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 1 0 9 9 3.6 3.6 0 0 1-4.2-2.1A3.6 3.6 0 0 1 12 3Z" />
                <circle cx="9" cy="10" r="1" fill="currentColor" stroke="none" />
                <circle cx="13.5" cy="14.5" r="1" fill="currentColor" stroke="none" />
                <circle cx="9.5" cy="15.5" r="0.75" fill="currentColor" stroke="none" />
            </svg>
        </button>
    </transition>
</template>
