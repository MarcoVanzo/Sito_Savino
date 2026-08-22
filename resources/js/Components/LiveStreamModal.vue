<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useTranslations } from '@/Composables/useTranslations.js';

const $t = useTranslations();

const props = defineProps({
    // { title, embedUrl, url } — embedUrl è valorizzato solo per le
    // piattaforme incorporabili (vedi App\Support\LiveStream).
    stream: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const isOpen = computed(() => Boolean(props.stream?.embedUrl));

const close = () => emit('close');

const dialogEl = ref(null);

// `showModal()` mette la finestra nel top layer: il fuoco resta dentro, l'Esc
// la chiude e lo sfondo lo disegna ::backdrop. Prima erano tre cose scritte a
// mano, e l'Esc funzionava solo grazie a un ascoltatore su tutta la pagina.
watch(isOpen, async (open) => {
    if (typeof document === 'undefined') {
        return;
    }

    await nextTick();

    const el = dialogEl.value;

    if (el && open && !el.open) {
        el.showModal();
    }

    if (el && !open && el.open) {
        el.close();
    }

    // La pagina sotto non deve scorrere: showModal() blocca il clic, non la rotella.
    document.body.style.overflow = open ? 'hidden' : '';
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <dialog
        ref="dialogEl"
        class="live-stream-dialog"
        :aria-label="stream?.title || $t('stream.modal_title')"
        @close="close"
        @click.self="close"
    >
        <div v-if="isOpen" class="w-full max-w-5xl">
            <div class="flex items-center justify-between gap-4 mb-3">
                <p class="text-white font-bold uppercase tracking-wider text-sm truncate">
                    <span class="inline-flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-savino-red animate-pulse"></span>
                        {{ stream?.title || $t('stream.modal_title') }}
                    </span>
                </p>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <a
                        v-if="stream?.url"
                        :href="stream.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-white/60 hover:text-white text-xs font-bold uppercase tracking-wider"
                    >{{ $t('stream.open_external') }}</a>
                    <button
                        type="button"
                        class="w-10 h-10 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20"
                        :aria-label="$t('stream.close')"
                        @click="close"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>

            <div class="relative w-full aspect-video bg-black rounded-xl overflow-hidden shadow-2xl">
                <iframe
                    :src="stream.embedUrl"
                    class="absolute inset-0 w-full h-full border-0"
                    :title="stream?.title || $t('stream.modal_title')"
                    allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                    allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin"
                ></iframe>
        </div>
        </div>
    </dialog>
</template>

<style scoped>
/* Il browser dà a <dialog> bordo, sfondo e dimensioni proprie: qui serve una
   finestra a tutto schermo con il video al centro. */
.live-stream-dialog {
    width: 100%;
    max-width: none;
    height: 100%;
    max-height: none;
    padding: 1rem;
    border: 0;
    background: transparent;
    overflow: hidden;
}

.live-stream-dialog[open] {
    display: flex;
    align-items: center;
    justify-content: center;
}

.live-stream-dialog::backdrop {
    background: rgb(0 0 0 / 0.9);
}
</style>
