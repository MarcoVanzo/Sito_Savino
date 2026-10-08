<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useTranslations } from '@/Composables/useTranslations.js';

/**
 * Video muto in loop dietro la testata dello shop.
 *
 * Parte da solo anche sul telefono (muted + playsinline è ciò che iOS chiede
 * per l'autoplay), tranne quando il sistema chiede meno movimento: lì resta
 * la copertina finché il visitatore non preme «Riprendi». Il pulsante di
 * pausa c'è sempre perché il video dura più di 5 secondi (WCAG 2.2.2).
 */
defineProps({
    src: { type: String, required: true },
    poster: { type: String, default: null },
});

const $t = useTranslations();

const video = ref(null);
const fermo = ref(false);

const riprendi = () => {
    if (!fermo.value && !document.hidden) {
        video.value?.play()?.catch(() => {});
    }
};

onMounted(() => {
    fermo.value = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    riprendi();
    // Chrome ferma i video muti delle schede in secondo piano: aperta la
    // pagina dietro un'altra scheda, il video resterebbe sulla copertina.
    document.addEventListener('visibilitychange', riprendi);
});

onUnmounted(() => document.removeEventListener('visibilitychange', riprendi));

const alterna = () => {
    fermo.value = !fermo.value;

    if (fermo.value) {
        video.value?.pause();
    } else {
        video.value?.play()?.catch(() => {});
    }
};
</script>

<template>
    <video
        ref="video"
        data-video-di-sfondo
        muted
        loop
        playsinline
        preload="metadata"
        :poster="poster || undefined"
        aria-hidden="true"
        class="absolute inset-0 h-full w-full object-cover object-center"
    >
        <source :src="src" type="video/mp4" />
    </video>
    <!-- L'etichetta cambia con lo stato: niente aria-pressed (vedi Home.vue). -->
    <button
        type="button"
        class="absolute bottom-4 right-4 z-20 inline-flex items-center gap-2 rounded-full bg-black/60 px-3 py-2 text-xs font-semibold text-white hover:bg-black/80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
        @click="alterna"
    >
        <svg v-if="!fermo" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z" /></svg>
        <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
        {{ fermo ? $t('accessibilita.resume_motion') : $t('accessibilita.pause_motion') }}
    </button>
</template>
