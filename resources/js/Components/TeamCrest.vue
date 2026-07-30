<script setup>
import { useImageFallback } from '@/Composables/useImageFallback.js';

const { onImgError } = useImageFallback();

defineProps({
    // URL del logo, quando la squadra ne ha uno caricato.
    logoUrl: { type: String, default: null },
    name: { type: String, default: '' },
    // Classi di allineamento: cambia fra squadra di casa e ospite.
    alignClass: { type: String, default: 'mx-auto' },
});
</script>

<template>
    <img
        v-if="logoUrl"
        :src="logoUrl"
        :alt="name"
        class="w-36 h-28 rounded-xl object-contain bg-white mb-4 shadow-lg"
        :class="alignClass"
        @error="onImgError"
    />
    <!-- Senza logo caricato si mostra un segnaposto neutro: mai lo stemma
         di un'altra squadra. -->
    <div
        v-else
        class="w-24 h-24 rounded-xl bg-white/10 mb-4 flex items-center justify-center"
        :class="alignClass"
    >
        <svg class="w-12 h-12 text-white/30" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
        </svg>
    </div>
</template>
