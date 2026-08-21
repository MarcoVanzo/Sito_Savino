<script setup>
import { Link } from '@inertiajs/vue3';
import { isExternalLink, externalLinkAttrs } from '@/Support/menuLinks.js';

// Il negozio non è una voce di menu come le altre: in mezzo alle sezioni
// editoriali si perdeva. Qui diventa il solo elemento pieno della testata, così
// si trova a colpo d'occhio. Etichetta e indirizzo restano quelli della voce
// marcata "Evidenziata" nel pannello: nel componente non c'è nessun testo.
defineProps({
    href: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    // Corpo fisso (SHOP_BUTTON_FONT_SIZE): il pulsante non segue il corpo delle voci,
    // che cambia con lo spazio disponibile. È la stessa misura con cui la testata
    // calcola quanto spazio gli serve.
    fontSize: {
        type: Number,
        default: 14,
    },
});
</script>

<template>
    <component
        :is="isExternalLink(href) ? 'a' : Link"
        :href="href"
        v-bind="externalLinkAttrs(href)"
        class="group inline-flex items-center gap-2 whitespace-nowrap rounded-full bg-gradient-to-r from-savino-fucsia to-savino-pink px-4 py-2 font-black uppercase tracking-wider text-white ring-1 ring-white/25 shadow-[0_6px_20px_rgba(248,38,156,0.45)] transition-all duration-300 hover:shadow-[0_8px_26px_rgba(248,38,156,0.65)] hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
        :style="{ fontSize: `${fontSize}px` }"
    >
        <svg class="h-[1.15em] w-[1.15em] transition-transform duration-300 group-hover:-translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A1.125 1.125 0 0119.75 21.75H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
        </svg>
        {{ label }}
    </component>
</template>
