<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useLocale } from '@/Composables/useLocale.js';
import { safeUrl } from '@/Composables/useSafeUrl.js';
import { isExternalLink, externalLinkAttrs } from '@/Support/menuLinks.js';

const $t = useTranslations();
const { formatDate } = useLocale();

const props = defineProps({
    // Evento::perLaHome(), già filtrati (pubblicati, non finiti) e in ordine.
    eventi: {
        type: Array,
        default: () => [],
    },
});

const elenco = computed(() => props.eventi.map((evento) => ({
    ...evento,
    href: safeUrl(evento.link),
    // formatDate aggiunge giorno, mese e anno di suo: nel riquadro serve una
    // parte sola, le altre si spengono esplicitamente.
    giorno: formatDate(evento.inizia_il, { day: 'numeric', month: undefined, year: undefined }),
    mese: formatDate(evento.inizia_il, { day: undefined, month: 'short', year: undefined }),
    quando: formatDate(evento.inizia_il, { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }),
})));
</script>

<template>
    <section v-if="elenco.length" class="bg-white py-24" aria-labelledby="eventi-home-titolo">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14">
                <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ $t('home.events_subtitle') }}</span>
                <h2 id="eventi-home-titolo" class="text-3xl md:text-5xl font-black text-savino-blue uppercase tracking-tighter mt-3">
                    {{ $t('home.events_title') }}
                </h2>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-6"></div>
            </div>
            <ul class="grid gap-8" :class="elenco.length === 1 ? 'max-w-xl mx-auto' : elenco.length === 2 ? 'md:grid-cols-2 max-w-4xl mx-auto' : 'md:grid-cols-2 lg:grid-cols-3'">
                <li v-for="evento in elenco" :key="evento.id" class="flex flex-col rounded-2xl overflow-hidden bg-gray-50 shadow-sm ring-1 ring-gray-200">
                    <div class="relative aspect-video bg-gradient-to-br from-savino-blue to-gray-900">
                        <img v-if="evento.immagine" :src="evento.immagine" alt="" loading="lazy" class="w-full h-full object-cover" />
                        <div class="absolute top-4 left-4 rounded-lg bg-white px-3 py-2 text-center shadow-md" aria-hidden="true">
                            <div class="text-savino-blue text-2xl font-black leading-none">{{ evento.giorno }}</div>
                            <div class="text-savino-fucsia text-xs font-bold uppercase tracking-wider mt-1">{{ evento.mese }}</div>
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col p-6">
                        <p class="text-savino-fucsia text-xs font-bold uppercase tracking-wider">
                            <time :datetime="evento.inizia_il">{{ evento.quando }}</time>
                        </p>
                        <h3 class="text-lg font-bold text-gray-900 mt-2">{{ evento.titolo }}</h3>
                        <p v-if="evento.luogo" class="text-gray-600 text-sm mt-1">{{ evento.luogo }}</p>
                        <p v-if="evento.descrizione" class="text-gray-600 text-sm mt-3 leading-relaxed">{{ evento.descrizione }}</p>
                        <component
                            :is="isExternalLink(evento.href) ? 'a' : Link"
                            v-if="evento.href"
                            v-bind="externalLinkAttrs(evento.href)"
                            :href="evento.href"
                            class="mt-auto pt-5 inline-flex items-center gap-1 text-savino-blue text-xs font-bold uppercase tracking-wider hover:text-savino-fucsia transition-colors"
                        >
                            {{ $t('home.event_details') }}
                            <span class="sr-only">: {{ evento.titolo }}</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                        </component>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
