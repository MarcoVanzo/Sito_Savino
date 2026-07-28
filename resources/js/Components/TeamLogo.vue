<script setup>
import { computed, ref, watch } from 'vue';

/**
 * Logo di una squadra con fallback sulle iniziali.
 *
 * L'URL arriva sempre dal backend tramite Team::logoUrl() (logo del CMS →
 * logo importato dalla Lega → URL remoto). Quando manca, o quando l'immagine
 * remota non si carica, si mostrano le iniziali: il posto occupato resta lo
 * stesso, quindi la riga della tabella non si sposta.
 */
const props = defineProps({
    src: {
        type: String,
        default: null,
    },
    name: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md', // 'sm' | 'md' | 'lg'
    },
});

const failed = ref(false);

// Nelle liste virtualizzate/riordinate Vue può riusare lo stesso nodo con un
// logo diverso: senza questo reset l'errore precedente resterebbe appiccicato.
watch(() => props.src, () => {
    failed.value = false;
});

const initials = computed(() => {
    const words = (props.name || '').split(/\s+/).filter(Boolean);
    return words.slice(0, 2).map((word) => word.charAt(0)).join('').toUpperCase();
});

const showImage = computed(() => Boolean(props.src) && !failed.value);

const boxClass = computed(() => ({
    sm: 'w-6 h-6',
    md: 'w-10 h-10',
    lg: 'w-20 h-20 md:w-24 md:h-24',
}[props.size] ?? 'w-10 h-10'));

const textClass = computed(() => ({
    sm: 'text-[9px]',
    md: 'text-xs',
    lg: 'text-2xl',
}[props.size] ?? 'text-xs'));
</script>

<template>
    <span
        class="inline-flex items-center justify-center flex-shrink-0 rounded-full bg-white/80 ring-1 ring-gray-200 overflow-hidden"
        :class="boxClass"
    >
        <img
            v-if="showImage"
            :src="src"
            :alt="name"
            class="w-full h-full object-contain p-0.5"
            loading="lazy"
            decoding="async"
            @error="failed = true"
        />
        <span
            v-else-if="initials"
            class="font-black text-savino-blue leading-none"
            :class="textClass"
            aria-hidden="true"
        >{{ initials }}</span>
    </span>
</template>
