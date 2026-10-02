<script setup>
import { computed } from 'vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { safeUrl } from '@/Composables/useSafeUrl.js';

const $t = useTranslations();

const props = defineProps({
    // Già in ordine di livello (SponsorDirectory), poi di ordinamento del pannello.
    sponsor: {
        type: Array,
        default: () => [],
    },
    // La pausa è la stessa del resto della home (WCAG 2.2.2): un pulsante solo
    // ferma slideshow, foto e loghi.
    fermo: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['alterna']);

// Sotto una fila scarsa lo scorrimento girerebbe su uno schermo mezzo vuoto:
// con pochi sponsor i loghi stanno fermi e centrati.
const MINIMO_PER_SCORRERE = 6;

const elenco = computed(() => props.sponsor
    .filter((s) => s?.name)
    .map((s) => ({ ...s, href: safeUrl(s.website_url) })));

const scorre = computed(() => elenco.value.length >= MINIMO_PER_SCORRERE && !props.fermo);

// Velocità costante qualunque sia il numero di loghi: tre secondi a logo.
const durata = computed(() => `${elenco.value.length * 3}s`);
</script>

<template>
    <section v-if="elenco.length" class="bg-white py-12 overflow-hidden" aria-labelledby="striscia-sponsor-titolo">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 mb-8">
            <div class="text-center sm:text-left">
                <span class="text-savino-fucsia text-xs font-bold uppercase tracking-[0.3em]">{{ $t('home.sponsor_subtitle') }}</span>
                <h2 id="striscia-sponsor-titolo" class="text-2xl md:text-3xl font-black text-savino-blue uppercase tracking-tighter mt-1">
                    {{ $t('home.sponsor_title') }}
                </h2>
            </div>
            <button
                v-if="elenco.length >= MINIMO_PER_SCORRERE"
                type="button"
                class="inline-flex items-center gap-2 rounded-full bg-savino-blue/10 px-3 py-2 text-xs font-semibold text-savino-blue hover:bg-savino-blue/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-savino-blue"
                @click="$emit('alterna')"
            >
                <svg v-if="!fermo" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z" /></svg>
                <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                {{ fermo ? $t('accessibilita.resume_motion') : $t('accessibilita.pause_motion') }}
            </button>
        </div>

        <!-- In pausa i loghi si dispongono tutti in vista: una striscia ferma a
             metà lascerebbe fuori dallo schermo metà degli sponsor. -->
        <div v-if="scorre" class="striscia">
            <ul class="striscia-binario" :aria-label="$t('home.sponsor_label')" :style="{ animationDuration: durata }">
                <li v-for="s in elenco" :key="'a-' + s.id" class="striscia-voce">
                    <component
                        :is="s.href ? 'a' : 'span'"
                        :href="s.href"
                        :target="s.href ? '_blank' : undefined"
                        :rel="s.href ? 'noopener sponsored' : undefined"
                        class="logo-sponsor"
                    >
                        <img v-if="s.logo_url" :src="s.logo_url" :alt="s.name" loading="lazy" class="max-h-16 max-w-[10rem] object-contain" />
                        <span v-else class="text-savino-blue font-bold text-sm">{{ s.name }}</span>
                    </component>
                </li>
                <!-- Seconda copia per il giro continuo: fuori dall'albero
                     dell'accessibilità e dal tasto Tab, o ogni sponsor si
                     leggerebbe e si attraverserebbe due volte. -->
                <li v-for="s in elenco" :key="'b-' + s.id" class="striscia-voce" aria-hidden="true">
                    <component
                        :is="s.href ? 'a' : 'span'"
                        :href="s.href"
                        :target="s.href ? '_blank' : undefined"
                        :rel="s.href ? 'noopener sponsored' : undefined"
                        tabindex="-1"
                        class="logo-sponsor"
                    >
                        <img v-if="s.logo_url" :src="s.logo_url" alt="" loading="lazy" class="max-h-16 max-w-[10rem] object-contain" />
                        <span v-else class="text-savino-blue font-bold text-sm">{{ s.name }}</span>
                    </component>
                </li>
            </ul>
        </div>
        <ul v-else class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
            <li v-for="s in elenco" :key="s.id">
                <component
                    :is="s.href ? 'a' : 'span'"
                    :href="s.href"
                    :target="s.href ? '_blank' : undefined"
                    :rel="s.href ? 'noopener sponsored' : undefined"
                    class="logo-sponsor"
                >
                    <img v-if="s.logo_url" :src="s.logo_url" :alt="s.name" loading="lazy" class="max-h-16 max-w-[10rem] object-contain" />
                    <span v-else class="text-savino-blue font-bold text-sm">{{ s.name }}</span>
                </component>
            </li>
        </ul>
    </section>
</template>

<style scoped>
.striscia {
    width: 100%;
    overflow: hidden;
    mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
    -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
}

.striscia-binario {
    display: flex;
    width: max-content;
    animation: scorriSponsor linear infinite;
}

/* Sotto il puntatore o con il fuoco da tastiera la striscia si ferma: un
   logo non si clicca mentre scappa. */
.striscia:hover .striscia-binario,
.striscia:focus-within .striscia-binario {
    animation-play-state: paused;
}

.striscia-voce {
    flex-shrink: 0;
    padding: 0 2rem;
}

.logo-sponsor {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 4rem;
    transition: transform 0.3s ease;
}

/* A colori, sempre: è visibilità venduta, e il marchio di uno sponsor in
   grigio non è il suo marchio. */
a.logo-sponsor:hover {
    transform: scale(1.05);
}

a.logo-sponsor:focus-visible {
    outline: 2px solid #003063;
    outline-offset: 4px;
    border-radius: 4px;
}

@keyframes scorriSponsor {
    from { transform: translateX(0); }
    to { transform: translateX(-50%); }
}

@media (prefers-reduced-motion: reduce) {
    .striscia-binario {
        animation: none;
    }
}
</style>
