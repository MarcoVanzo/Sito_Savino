<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { updateAnalyticsConsent } from '../analytics.js';
import { updateMarketingConsent } from '../meta-pixel.js';

const showBanner = ref(false);
const showSettings = ref(false);

const consent = reactive({
    necessary: true, // Sempre attivo, non disattivabile
    analytics: false,
    marketing: false,
});

const CONSENT_KEY = 'cookie-consent-v2';

onMounted(() => {
    const stored = localStorage.getItem(CONSENT_KEY);
    if (!stored) {
        showBanner.value = true;
    } else {
        try {
            const parsed = JSON.parse(stored);
            consent.analytics = parsed.analytics ?? false;
            consent.marketing = parsed.marketing ?? false;
        } catch {
            showBanner.value = true;
        }
    }
});

const saveConsent = () => {
    localStorage.setItem(CONSENT_KEY, JSON.stringify({
        necessary: true,
        analytics: consent.analytics,
        marketing: consent.marketing,
        timestamp: new Date().toISOString(),
    }));
    // Fino a qui la scelta veniva solo memorizzata e non pilotava nulla: il
    // banner chiedeva un consenso che poi non cambiava il comportamento del
    // sito. Da qui la misurazione parte, o si ferma, davvero.
    updateAnalyticsConsent(consent.analytics);
    // Il pixel oggi non è subordinato al consenso (scelta di configurazione):
    // la chiamata resta perché il giorno in cui lo diventa non serva ricordarsi
    // di aggiungerla qui.
    updateMarketingConsent(consent.marketing);
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
defineExpose({ show: () => { showBanner.value = true; showSettings.value = true; } });
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
                            <Link href="/privacy-policy" class="text-savino-fucsia hover:underline">{{ $t('footer.privacy_policy') }}</Link>
                            {{ $t('cookie.and_the') }}
                            <Link href="/cookie-policy" class="text-savino-fucsia hover:underline">{{ $t('footer.cookie_policy') }}</Link>.
                        </p>
                    </div>
                    <div class="flex gap-2 flex-shrink-0 flex-wrap">
                        <button type="button"
                            @click="openSettings"
                            class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white border border-gray-600 hover:border-white/30 rounded-lg transition-all duration-200"
                        >
                            {{ $t('cookie.customize') }}
                        </button>
                        <button type="button"
                            @click="rejectAll"
                            class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white border border-gray-600 hover:border-white/30 rounded-lg transition-all duration-200"
                        >
                            {{ $t('cookie.reject_all') }}
                        </button>
                        <button type="button"
                            @click="acceptAll"
                            class="px-4 py-2 text-xs font-bold uppercase tracking-wider bg-savino-fucsia text-savino-blue hover:bg-yellow-400 rounded-lg transition-all duration-200 shadow-lg"
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
                                    <input id="cookie-necessary" type="checkbox" checked disabled
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
                                    <input id="cookie-analytics" type="checkbox" v-model="consent.analytics" class="sr-only peer" :aria-label="$t('cookie.analytics_title')" />
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
                                    <input id="cookie-marketing" type="checkbox" v-model="consent.marketing" class="sr-only peer" :aria-label="$t('cookie.marketing_title')" />
                                    <div class="w-10 h-5 bg-gray-600 rounded-full peer peer-checked:bg-savino-fucsia transition-colors"></div>
                                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                                </label>
                            </div>

                            <div class="pt-2 flex justify-end">
                                <button type="button"
                                    @click="saveConsent"
                                    class="px-6 py-2 text-xs font-bold uppercase tracking-wider bg-savino-fucsia text-savino-blue hover:bg-yellow-400 rounded-lg transition-all duration-200"
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
</template>
