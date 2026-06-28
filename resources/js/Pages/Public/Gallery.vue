<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, computed, nextTick } from 'vue'

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    },
    media: {
        type: Array,
        default: () => []
    }
})

const placeholderMedia = [
    { id: 1, url: '/images/placeholder-1.jpg', alt: 'Azione di gioco', category: 'Partite' },
    { id: 2, url: '/images/placeholder-2.jpg', alt: 'Esultanza squadra', category: 'Partite' },
    { id: 3, url: '/images/placeholder-3.jpg', alt: 'Allenamento', category: 'Allenamenti' },
    { id: 4, url: '/images/placeholder-4.jpg', alt: 'Tifosi allo stadio', category: 'Tifosi' },
    { id: 5, url: '/images/placeholder-5.jpg', alt: 'Premiazione', category: 'Eventi' },
    { id: 6, url: '/images/placeholder-6.jpg', alt: 'Dietro le quinte', category: 'Backstage' },
    { id: 7, url: '/images/placeholder-7.jpg', alt: 'Riscaldamento pre-gara', category: 'Partite' },
    { id: 8, url: '/images/placeholder-8.jpg', alt: 'Conferenza stampa', category: 'Eventi' },
    { id: 9, url: '/images/placeholder-9.jpg', alt: 'Gruppo squadra', category: 'Backstage' },
]

const displayMedia = computed(() => props.media.length > 0 ? props.media : placeholderMedia)

const categories = computed(() => ['Tutte', ...new Set(displayMedia.value.map(m => m.category))])
const activeCategory = ref('Tutte')

const filteredMedia = computed(() => {
    if (activeCategory.value === 'Tutte') return displayMedia.value
    return displayMedia.value.filter(m => m.category === activeCategory.value)
})

function filterByCategory(cat) {
    activeCategory.value = cat
}

const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const lightboxEl = ref(null)

function openLightbox(index) {
    lightboxIndex.value = index
    lightboxOpen.value = true
    nextTick(() => {
        lightboxEl.value?.focus()
    })
}

function closeLightbox() {
    lightboxOpen.value = false
}

function prevImage() {
    lightboxIndex.value = (lightboxIndex.value - 1 + filteredMedia.value.length) % filteredMedia.value.length
}

function nextImage() {
    lightboxIndex.value = (lightboxIndex.value + 1) % filteredMedia.value.length
}
</script>

<template>
    <Head>
      <title>{{ (page?.title ?? 'Foto Gallery') + ' — Savino Del Bene Volley' }}</title>
      <meta name="description" content="La galleria fotografica ufficiale della Savino Del Bene Volley. Immagini dalle partite, eventi e dietro le quinte." />
      <meta property="og:title" :content="(page?.title ?? 'Foto Gallery') + ' — Savino Del Bene Volley'" />
      <meta property="og:description" content="La galleria fotografica ufficiale della Savino Del Bene Volley. Immagini dalle partite, eventi e dietro le quinte." />
      <meta property="og:image" :content="'/images/logo.png'" />
      <meta property="og:url" :content="$page.props.ziggy?.location || ''" />
      <meta property="og:type" content="website" />
    </Head>

    <PublicLayout>
        <!-- Hero -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">I Nostri Momenti</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title ?? 'Foto Gallery' }}</h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">Rivivi i momenti più emozionanti della nostra stagione attraverso le immagini.</p>
            </div>
        </section>

        <!-- Gallery Content -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Category Filter -->
                <div class="flex flex-wrap gap-3 justify-center mb-12">
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        @click="filterByCategory(cat)"
                        class="px-5 py-2 rounded-full text-sm font-bold uppercase tracking-wider transition-all duration-300"
                        :class="activeCategory === cat
                            ? 'bg-savino-blue text-white shadow-lg shadow-savino-blue/30'
                            : 'bg-white text-gray-600 hover:bg-savino-blue/10 hover:text-savino-blue border border-gray-200'"
                       
                    >{{ cat }}</button>
                </div>

                <!-- Empty State -->
                <div v-if="filteredMedia.length === 0 && media.length === 0" class="text-center py-20">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-savino-gold/10 flex items-center justify-center">
                        <svg class="w-12 h-12 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 uppercase mb-3">Gallery in aggiornamento</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Stiamo preparando i migliori scatti della stagione. Torna a trovarci presto!</p>
                </div>

                <!-- Photo Grid -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="(item, index) in filteredMedia"
                        :key="item.id"
                        @click="openLightbox(index)"
                        class="group relative aspect-[4/3] rounded-xl overflow-hidden cursor-pointer shadow-md hover:shadow-xl transition-all duration-500"
                    >
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-10"></div>
                        <div class="absolute inset-0 bg-savino-blue/20 flex items-center justify-center">
                            <svg class="w-16 h-16 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <img
                            v-if="item.url"
                            :src="item.url"
                            :alt="item.alt"
                            class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                            loading="lazy"
                        />
                        <div class="absolute bottom-0 left-0 right-0 p-4 z-20 translate-y-full group-hover:translate-y-0 transition-transform duration-500">
                            <span class="text-savino-gold text-xs font-bold uppercase tracking-wider">{{ item.category }}</span>
                            <p class="text-white text-sm font-semibold mt-1">{{ item.alt }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Lightbox -->
        <Teleport to="body">
            <div
                v-if="lightboxOpen"
                ref="lightboxEl"
                class="fixed inset-0 z-[100] bg-black/95 flex items-center justify-center"
                tabindex="0"
                @click.self="closeLightbox"
                @keydown.escape="closeLightbox"
                @keydown.arrow-left="prevImage"
                @keydown.arrow-right="nextImage"
            >
                <button @click="closeLightbox" aria-label="Chiudi lightbox" class="absolute top-6 right-6 text-white/70 hover:text-white transition-colors">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <button @click="prevImage" aria-label="Immagine precedente" class="absolute left-4 sm:left-8 text-white/70 hover:text-white transition-colors">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button @click="nextImage" aria-label="Immagine successiva" class="absolute right-4 sm:right-8 text-white/70 hover:text-white transition-colors">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <div class="max-w-5xl max-h-[85vh] px-16">
                    <div class="bg-savino-blue/30 rounded-xl overflow-hidden flex items-center justify-center min-h-[50vh]">
                        <img
                            v-if="filteredMedia[lightboxIndex]?.url"
                            :src="filteredMedia[lightboxIndex]?.url"
                            :alt="filteredMedia[lightboxIndex]?.alt"
                            class="max-w-full max-h-[80vh] object-contain"
                        />
                        <div v-else class="text-white/30 p-20">
                            <svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-white text-center mt-4 text-sm">{{ filteredMedia[lightboxIndex]?.alt }}</p>
                </div>
            </div>
        </Teleport>
    </PublicLayout>
</template>
