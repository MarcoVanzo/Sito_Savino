<script setup>
/**
 * Coda multimediale di una pagina: prima il video, poi le foto.
 *
 * Le pagine dei progetti sociali raccontavano a parole cose che sono fatte di
 * campo e di persone, e in redazione non c'era modo di allegare né un filmato
 * né uno scatto. Il blocco compare solo se c'è qualcosa da mostrare.
 *
 * L'indirizzo del video è già stato filtrato dal backend
 * (App\Support\LiveStream): `embedUrl` è valorizzato solo per le piattaforme
 * che si possono incorporare, per le altre resta il link e si apre altrove.
 */
import { useTranslations } from '@/Composables/useTranslations.js';
import { computed, nextTick, ref } from 'vue';

const $t = useTranslations();

const props = defineProps({
    videoEmbedUrl: {
        type: String,
        default: null,
    },
    videoUrl: {
        type: String,
        default: null,
    },
    images: {
        type: Array,
        default: () => [],
    },
});

const hasVideo = computed(() => Boolean(props.videoEmbedUrl || props.videoUrl));
const hasImages = computed(() => props.images.length > 0);
const hasSomething = computed(() => hasVideo.value || hasImages.value);

// Ingrandimento della foto: indice nell'elenco, null quando è chiuso.
const zoomIndex = ref(null);
const zoomImage = computed(() => (zoomIndex.value === null ? null : props.images[zoomIndex.value]));

// La foto ingrandita e' un <dialog>: aperta con showModal() sta nel top layer,
// il fuoco ci resta dentro e l'Esc la chiude senza doverlo ascoltare a mano.
const zoomEl = ref(null);

function openImage(index) {
    zoomIndex.value = index;
    nextTick(() => {
        if (zoomEl.value && !zoomEl.value.open) {
            zoomEl.value.showModal();
        }
    });
}

function closeImage() {
    zoomIndex.value = null;

    if (zoomEl.value?.open) {
        zoomEl.value.close();
    }
}
</script>

<template>
    <section v-if="hasSomething" class="py-16 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Video -->
            <div v-if="hasVideo" class="mb-16">
                <h3 class="text-2xl font-black text-savino-blue mb-6 uppercase tracking-tight">
                    {{ $t('content_page.video_title') }}
                </h3>

                <div v-if="videoEmbedUrl" class="aspect-video overflow-hidden rounded-2xl bg-savino-blue shadow-lg">
                    <iframe
                        :src="videoEmbedUrl"
                        :title="$t('content_page.video_title')"
                        class="w-full h-full border-0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    ></iframe>
                </div>

                <!-- Piattaforma non incorporabile: si apre dove sta di casa. -->
                <a
                    v-else
                    :href="videoUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 font-bold text-savino-blue hover:text-savino-fucsia transition-colors"
                >
                    {{ $t('content_page.video_open') }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                </a>
            </div>

            <!-- Galleria -->
            <div v-if="hasImages">
                <h3 class="text-2xl font-black text-savino-blue mb-6 uppercase tracking-tight">
                    {{ $t('content_page.gallery_title') }}
                </h3>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <button
                        v-for="(image, idx) in images"
                        :key="idx"
                        type="button"
                        class="group relative aspect-[4/3] overflow-hidden rounded-2xl border border-gray-100 bg-gray-50"
                        @click="openImage(idx)"
                    >
                        <img
                            :src="image.thumb || image.url"
                            :alt="image.name"
                            loading="lazy"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                    </button>
                </div>
            </div>
        </div>

        <!-- Foto ingrandita -->
        <dialog
            ref="zoomEl"
            class="foto-ingrandita"
            :aria-label="zoomImage?.name"
            @close="closeImage"
            @click="closeImage"
        >
            <button
                type="button"
                class="absolute top-5 right-5 text-white/70 hover:text-white transition-colors"
                :aria-label="$t('content_page.gallery_close')"
                @click.stop="closeImage"
            >
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img v-if="zoomImage" :src="zoomImage.url" :alt="zoomImage.name" class="max-w-full max-h-full object-contain rounded-lg" @click.stop />
        </dialog>
    </section>
</template>

<style scoped>
/* Il browser dà a <dialog> bordo, sfondo e dimensioni proprie: qui serve la
   foto al centro di uno schermo scuro. */
.foto-ingrandita {
    width: 100%;
    max-width: none;
    height: 100%;
    max-height: none;
    padding: 1rem;
    border: 0;
    background: transparent;
    overflow: hidden;
}

.foto-ingrandita[open] {
    display: flex;
    align-items: center;
    justify-content: center;
}

.foto-ingrandita::backdrop {
    background: rgb(0 0 0 / 0.9);
}
</style>
