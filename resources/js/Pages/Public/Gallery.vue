<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import { useImageFallback } from '@/Composables/useImageFallback.js'
import { useOgMeta } from '@/Composables/useOgMeta'

const { onImgError } = useImageFallback()

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    },
    media: {
        type: Array,
        default: () => []
    },
    athletes: {
        type: Array,
        default: () => []
    },
    currentAthlete: {
        type: Object,
        default: null
    }
})

// ── Display data ──────────────────────────────────────────────
const displayMedia = computed(() => props.media)

// ── Category filter ───────────────────────────────────────────
const categories = computed(() => {
    const cats = [...new Set(displayMedia.value.map(m => m.category).filter(Boolean))]
    return ['Tutte', ...cats]
})
const activeCategory = ref('Tutte')

const filteredMedia = computed(() => {
    if (activeCategory.value === 'Tutte') return displayMedia.value
    return displayMedia.value.filter(m => m.category === activeCategory.value)
})

function filterByCategory(cat) {
    activeCategory.value = cat
}

// ── Athlete search / dropdown ─────────────────────────────────
const athleteSearchOpen = ref(false)
const athleteQuery = ref('')
const filteredAthletes = computed(() => {
    if (!athleteQuery.value) return props.athletes
    const q = athleteQuery.value.toLowerCase()
    return props.athletes.filter(a => a.name.toLowerCase().includes(q))
})

function selectAthlete(athlete) {
    athleteSearchOpen.value = false
    athleteQuery.value = ''
    router.visit(route('gallery.atleta', { slug: athlete.slug }))
}

function clearAthlete() {
    athleteSearchOpen.value = false
    athleteQuery.value = ''
    router.visit(route('gallery'))
}

// Close dropdown on click outside
const athleteDropdownRef = ref(null)
function handleClickOutside(e) {
    if (athleteDropdownRef.value && !athleteDropdownRef.value.contains(e.target)) {
        athleteSearchOpen.value = false
    }
}
onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))

// ── Lightbox ──────────────────────────────────────────────────
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const lightboxEl = ref(null)
const lightboxTransition = ref(false)

function openLightbox(index) {
    lightboxIndex.value = index
    lightboxOpen.value = true
    nextTick(() => {
        lightboxEl.value?.focus()
        requestAnimationFrame(() => { lightboxTransition.value = true })
    })
}

function closeLightbox() {
    lightboxTransition.value = false
    setTimeout(() => { lightboxOpen.value = false }, 300)
}

function prevImage() {
    lightboxIndex.value = (lightboxIndex.value - 1 + filteredMedia.value.length) % filteredMedia.value.length
}

function nextImage() {
    lightboxIndex.value = (lightboxIndex.value + 1) % filteredMedia.value.length
}

// Touch / swipe support for lightbox
const touchStartX = ref(0)
function onTouchStart(e) { touchStartX.value = e.touches[0].clientX }
function onTouchEnd(e) {
    const diff = touchStartX.value - e.changedTouches[0].clientX
    if (Math.abs(diff) > 60) { diff > 0 ? nextImage() : prevImage() }
}

// ── Scroll reveal animation ──────────────────────────────────
const galleryGridRef = ref(null)
const revealedItems = ref(new Set())

onMounted(() => {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    revealedItems.value.add(entry.target.dataset.idx)
                    observer.unobserve(entry.target)
                }
            })
        },
        { threshold: 0.1, rootMargin: '60px' }
    )

    nextTick(() => {
        galleryGridRef.value?.querySelectorAll('[data-idx]').forEach(el => {
            observer.observe(el)
        })
    })

    // Re-observe when filteredMedia changes
    watch(filteredMedia, () => {
        revealedItems.value = new Set()
        nextTick(() => {
            galleryGridRef.value?.querySelectorAll('[data-idx]').forEach(el => {
                observer.observe(el)
            })
        })
    })
})

// ── Image loading ─────────────────────────────────────────────
const loadedImages = ref(new Set())
function onImageLoad(id) { loadedImages.value.add(id) }

// ── Stats ─────────────────────────────────────────────────────
const totalPhotos = computed(() => displayMedia.value.length)
const totalCategories = computed(() => new Set(displayMedia.value.map(m => m.category).filter(Boolean)).size)
const totalTaggedAthletes = computed(() => props.athletes?.length || 0)

const ogMeta = useOgMeta({
    title: props.currentAthlete ? 'Foto di ' + props.currentAthlete.name : (props.page?.title ?? 'Foto Gallery'),
    description: 'La galleria fotografica ufficiale della Savino Del Bene Volley. Immagini dalle partite, eventi e dietro le quinte.',
})
</script>

<template>
    <Head>
      <title>{{ ogMeta.title }}</title>
      <meta name="description" :content="ogMeta.description" />
      <meta property="og:title" :content="ogMeta.title" />
      <meta property="og:description" :content="ogMeta.description" />
      <meta property="og:image" :content="ogMeta.image" />
      <meta property="og:url" :content="ogMeta.url" />
      <meta property="og:type" :content="ogMeta.type" />
    </Head>

    <PublicLayout>
        <!-- ═══════════════ HERO ═══════════════ -->
        <section class="gallery-hero">
            <div class="gallery-hero__bg"></div>
            <div class="gallery-hero__particles">
                <span v-for="n in 20" :key="n" class="gallery-hero__particle" :style="{
                    '--delay': Math.random() * 5 + 's',
                    '--x': Math.random() * 100 + '%',
                    '--size': (Math.random() * 3 + 1) + 'px',
                    '--duration': (Math.random() * 8 + 6) + 's',
                }"></span>
            </div>

            <div class="gallery-hero__content">
                <div class="gallery-hero__badge">
                    <svg class="gallery-hero__badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                    </svg>
                    <span>Foto Gallery</span>
                </div>

                <h1 class="gallery-hero__title">
                    <template v-if="currentAthlete">
                        <span class="gallery-hero__title-small">Le foto di</span>
                        {{ currentAthlete.name }}
                    </template>
                    <template v-else>{{ page?.title ?? 'Foto Gallery' }}</template>
                </h1>

                <div class="gallery-hero__divider">
                    <span></span><span></span><span></span>
                </div>

                <p class="gallery-hero__subtitle">
                    Rivivi i momenti più emozionanti della nostra stagione attraverso le immagini.
                </p>

                <!-- Stats pills -->
                <div v-if="totalPhotos > 0" class="gallery-hero__stats">
                    <div class="gallery-hero__stat">
                        <span class="gallery-hero__stat-number">{{ totalPhotos }}</span>
                        <span class="gallery-hero__stat-label">Foto</span>
                    </div>
                    <div class="gallery-hero__stat">
                        <span class="gallery-hero__stat-number">{{ totalCategories }}</span>
                        <span class="gallery-hero__stat-label">Categorie</span>
                    </div>
                    <div v-if="totalTaggedAthletes > 0" class="gallery-hero__stat">
                        <span class="gallery-hero__stat-number">{{ totalTaggedAthletes }}</span>
                        <span class="gallery-hero__stat-label">Atlete Taggate</span>
                    </div>
                </div>
            </div>

            <!-- Scroll hint -->
            <div class="gallery-hero__scroll">
                <svg class="gallery-hero__scroll-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
            </div>
        </section>

        <!-- ═══════════════ FILTERS ═══════════════ -->
        <section class="gallery-filters">
            <div class="gallery-filters__inner">
                <!-- Category filter chips -->
                <div class="gallery-filters__categories">
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        @click="filterByCategory(cat)"
                        class="gallery-filters__chip"
                        :class="{ 'gallery-filters__chip--active': activeCategory === cat }"
                    >
                        <svg v-if="cat === 'Tutte'" class="gallery-filters__chip-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.5 3A2.5 2.5 0 003 5.5v2.879a2.5 2.5 0 00.732 1.767l6.5 6.5a2.5 2.5 0 003.536 0l2.878-2.878a2.5 2.5 0 000-3.536l-6.5-6.5A2.5 2.5 0 008.38 3H5.5zM6 7a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                        <svg v-else-if="cat === 'Partite'" class="gallery-filters__chip-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM10 7a3 3 0 100 6 3 3 0 000-6z"/>
                        </svg>
                        <svg v-else-if="cat === 'Allenamenti'" class="gallery-filters__chip-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.993 6.993 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                        </svg>
                        <svg v-else class="gallery-filters__chip-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M1 5.25A2.25 2.25 0 013.25 3h13.5A2.25 2.25 0 0119 5.25v9.5A2.25 2.25 0 0116.75 17H3.25A2.25 2.25 0 011 14.75v-9.5zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 00.75-.75v-2.69l-2.22-2.219a.75.75 0 00-1.06 0l-1.91 1.909.47.47a.75.75 0 11-1.06 1.06L6.53 8.091a.75.75 0 00-1.06 0L2.5 11.06z"/>
                        </svg>
                        {{ cat }}
                        <span v-if="cat !== 'Tutte'" class="gallery-filters__chip-count">
                            {{ displayMedia.filter(m => m.category === cat).length }}
                        </span>
                    </button>
                </div>

                <!-- Athlete search -->
                <div v-if="athletes && athletes.length > 0" class="gallery-filters__athlete" ref="athleteDropdownRef">
                    <!-- Current athlete badge -->
                    <div v-if="currentAthlete" class="gallery-filters__current-athlete">
                        <div class="gallery-filters__current-athlete-avatar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                        </div>
                        <span class="gallery-filters__current-athlete-name">{{ currentAthlete.name }}</span>
                        <button @click="clearAthlete" class="gallery-filters__current-athlete-clear" aria-label="Mostra tutte le atlete">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Search trigger -->
                    <button
                        v-else
                        @click.stop="athleteSearchOpen = !athleteSearchOpen"
                        class="gallery-filters__search-btn"
                    >
                        <svg class="gallery-filters__search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                        <span>Cerca per Atleta</span>
                        <svg class="gallery-filters__search-chevron" :class="{ 'gallery-filters__search-chevron--open': athleteSearchOpen }" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </button>

                    <!-- Dropdown -->
                    <Transition name="dropdown">
                        <div v-if="athleteSearchOpen" class="gallery-filters__dropdown">
                            <div class="gallery-filters__dropdown-search">
                                <svg class="gallery-filters__dropdown-search-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                                </svg>
                                <input
                                    v-model="athleteQuery"
                                    type="text"
                                    placeholder="Cerca atleta..."
                                    class="gallery-filters__dropdown-input"
                                    @click.stop
                                />
                            </div>
                            <div class="gallery-filters__dropdown-list">
                                <button
                                    v-for="athlete in filteredAthletes"
                                    :key="athlete.id"
                                    @click="selectAthlete(athlete)"
                                    class="gallery-filters__dropdown-item"
                                >
                                    <div class="gallery-filters__dropdown-avatar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                        </svg>
                                    </div>
                                    <span>{{ athlete.name }}</span>
                                    <svg class="gallery-filters__dropdown-arrow" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                                <p v-if="filteredAthletes.length === 0" class="gallery-filters__dropdown-empty">
                                    Nessuna atleta trovata
                                </p>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Photo count -->
            <div class="gallery-filters__count">
                <span>{{ filteredMedia.length }}</span> foto{{ filteredMedia.length !== 1 ? '' : '' }}
                <template v-if="activeCategory !== 'Tutte'"> in <strong>{{ activeCategory }}</strong></template>
                <template v-if="currentAthlete"> · <strong>{{ currentAthlete.name }}</strong></template>
            </div>
        </section>

        <!-- ═══════════════ GALLERY GRID ═══════════════ -->
        <section class="gallery-grid-section">
            <!-- Empty State -->
            <div v-if="filteredMedia.length === 0" class="gallery-empty">
                <div class="gallery-empty__icon-wrap">
                    <svg class="gallery-empty__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
                    </svg>
                </div>
                <h3 class="gallery-empty__title">Gallery in aggiornamento</h3>
                <p class="gallery-empty__text">
                    Stiamo preparando i migliori scatti della stagione.<br>Torna a trovarci presto!
                </p>
            </div>

            <!-- Masonry Grid -->
            <div v-else ref="galleryGridRef" class="gallery-masonry">
                <div
                    v-for="(item, index) in filteredMedia"
                    :key="item.id"
                    :data-idx="index"
                    @click="openLightbox(index)"
                    class="gallery-masonry__item"
                    :class="{
                        'gallery-masonry__item--revealed': revealedItems.has(String(index)),
                        'gallery-masonry__item--tall': index % 5 === 0,
                        'gallery-masonry__item--wide': index % 7 === 3,
                    }"
                    :style="{ '--stagger': (index % 8) * 0.06 + 's' }"
                >
                    <!-- Skeleton loader -->
                    <div v-if="!loadedImages.has(item.id)" class="gallery-masonry__skeleton">
                        <div class="gallery-masonry__skeleton-shimmer"></div>
                    </div>

                    <img
                        :src="item.thumb || item.url"
                        :alt="item.alt"
                        class="gallery-masonry__img"
                        :class="{ 'gallery-masonry__img--loaded': loadedImages.has(item.id) }"
                        loading="lazy"
                        @load="onImageLoad(item.id)"
                        @error="onImgError"
                    />

                    <!-- Hover overlay -->
                    <div class="gallery-masonry__overlay">
                        <div class="gallery-masonry__overlay-top">
                            <span class="gallery-masonry__category">{{ item.category }}</span>
                        </div>
                        <div class="gallery-masonry__overlay-bottom">
                            <p class="gallery-masonry__alt">{{ item.alt }}</p>
                            <div class="gallery-masonry__zoom">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════ LIGHTBOX ═══════════════ -->
        <Teleport to="body">
            <Transition name="lightbox-backdrop">
                <div
                    v-if="lightboxOpen"
                    ref="lightboxEl"
                    class="gallery-lightbox"
                    :class="{ 'gallery-lightbox--visible': lightboxTransition }"
                    tabindex="0"
                    @click.self="closeLightbox"
                    @keydown.escape="closeLightbox"
                    @keydown.arrow-left="prevImage"
                    @keydown.arrow-right="nextImage"
                    @touchstart.passive="onTouchStart"
                    @touchend.passive="onTouchEnd"
                >
                    <!-- Close -->
                    <button @click="closeLightbox" aria-label="Chiudi lightbox" class="gallery-lightbox__close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    <!-- Navigation -->
                    <button @click.stop="prevImage" aria-label="Immagine precedente" class="gallery-lightbox__nav gallery-lightbox__nav--prev">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <button @click.stop="nextImage" aria-label="Immagine successiva" class="gallery-lightbox__nav gallery-lightbox__nav--next">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <!-- Image container -->
                    <div class="gallery-lightbox__content" @click.stop>
                        <img
                            v-if="filteredMedia[lightboxIndex]?.url"
                            :src="filteredMedia[lightboxIndex]?.url"
                            :alt="filteredMedia[lightboxIndex]?.alt"
                            class="gallery-lightbox__img"
                            @error="onImgError"
                        />

                        <!-- Info bar -->
                        <div class="gallery-lightbox__info">
                            <div class="gallery-lightbox__info-left">
                                <span class="gallery-lightbox__info-category">{{ filteredMedia[lightboxIndex]?.category }}</span>
                                <span class="gallery-lightbox__info-alt">{{ filteredMedia[lightboxIndex]?.alt }}</span>
                            </div>
                            <span class="gallery-lightbox__info-counter">
                                {{ lightboxIndex + 1 }} / {{ filteredMedia.length }}
                            </span>
                        </div>
                    </div>

                    <!-- Thumbnail strip -->
                    <div class="gallery-lightbox__thumbs">
                        <button
                            v-for="(item, i) in filteredMedia.slice(Math.max(0, lightboxIndex - 4), lightboxIndex + 5)"
                            :key="item.id"
                            @click.stop="lightboxIndex = Math.max(0, lightboxIndex - 4) + i"
                            class="gallery-lightbox__thumb"
                            :class="{ 'gallery-lightbox__thumb--active': Math.max(0, lightboxIndex - 4) + i === lightboxIndex }"
                        >
                            <img :src="item.thumb || item.url" :alt="item.alt" @error="onImgError" />
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </PublicLayout>
</template>

<style scoped>
/* ═══════════════════════════════════════════════════
   GALLERY PAGE — Premium Photo Gallery Experience
   ═══════════════════════════════════════════════════ */

/* ── HERO ────────────────────────────────────────── */
.gallery-hero {
    position: relative;
    min-height: 55vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.gallery-hero__bg {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #0a0f1a 0%, #003063 40%, #001a3a 70%, #0a0f1a 100%);
}

.gallery-hero__bg::after {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 20% 50%, rgba(201,168,76,0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 30%, rgba(0,48,99,0.3) 0%, transparent 50%);
}

/* Floating particles */
.gallery-hero__particles {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.gallery-hero__particle {
    position: absolute;
    bottom: -10px;
    left: var(--x);
    width: var(--size);
    height: var(--size);
    background: rgba(201,168,76,0.4);
    border-radius: 50%;
    animation: particleFloat var(--duration) ease-in-out var(--delay) infinite;
}

@keyframes particleFloat {
    0%, 100% { transform: translateY(0) scale(0); opacity: 0; }
    10% { opacity: 1; transform: scale(1); }
    90% { opacity: 0.6; }
    100% { transform: translateY(-55vh) scale(0); opacity: 0; }
}

.gallery-hero__content {
    position: relative;
    z-index: 10;
    max-width: 56rem;
    margin: 0 auto;
    padding: 5rem 1.5rem;
    text-align: center;
}

.gallery-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(201,168,76,0.12);
    border: 1px solid rgba(201,168,76,0.25);
    border-radius: 9999px;
    padding: 0.4rem 1.2rem;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(8px);
}

.gallery-hero__badge-icon {
    width: 1rem;
    height: 1rem;
    color: #C9A84C;
}

.gallery-hero__badge span {
    color: #C9A84C;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.2em;
}

.gallery-hero__title {
    font-size: clamp(2.2rem, 6vw, 4rem);
    font-weight: 900;
    color: white;
    text-transform: uppercase;
    letter-spacing: -0.03em;
    line-height: 1.1;
    margin-bottom: 1rem;
}

.gallery-hero__title-small {
    display: block;
    font-size: 0.4em;
    color: rgba(255,255,255,0.5);
    font-weight: 600;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    margin-bottom: 0.3rem;
}

.gallery-hero__divider {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 1.2rem;
}

.gallery-hero__divider span:nth-child(1) {
    width: 2rem;
    height: 2px;
    background: rgba(201,168,76,0.3);
    border-radius: 1px;
}

.gallery-hero__divider span:nth-child(2) {
    width: 0.5rem;
    height: 0.5rem;
    background: #C9A84C;
    border-radius: 50%;
}

.gallery-hero__divider span:nth-child(3) {
    width: 2rem;
    height: 2px;
    background: rgba(201,168,76,0.3);
    border-radius: 1px;
}

.gallery-hero__subtitle {
    color: rgba(255,255,255,0.55);
    font-size: 1.05rem;
    max-width: 32rem;
    margin: 0 auto 2rem;
    line-height: 1.6;
}

.gallery-hero__stats {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
    flex-wrap: wrap;
}

.gallery-hero__stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.15rem;
}

.gallery-hero__stat-number {
    font-size: 1.8rem;
    font-weight: 900;
    color: #C9A84C;
    line-height: 1;
}

.gallery-hero__stat-label {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    color: rgba(255,255,255,0.4);
}

.gallery-hero__scroll {
    position: absolute;
    bottom: 1.5rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    animation: scrollBounce 2s ease-in-out infinite;
}

.gallery-hero__scroll-icon {
    width: 1.5rem;
    height: 1.5rem;
    color: rgba(201,168,76,0.4);
}

@keyframes scrollBounce {
    0%, 100% { transform: translateX(-50%) translateY(0); }
    50% { transform: translateX(-50%) translateY(8px); }
}

/* ── FILTERS ─────────────────────────────────────── */
.gallery-filters {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid rgba(0,48,99,0.08);
    padding: 1rem 0;
}

.gallery-filters__inner {
    max-width: 80rem;
    margin: 0 auto;
    padding: 0 1.5rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.gallery-filters__categories {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.gallery-filters__chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.45rem 1rem;
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: rgba(0,48,99,0.04);
    color: #4b5563;
}

.gallery-filters__chip:hover {
    background: rgba(0,48,99,0.1);
    color: #003063;
    transform: translateY(-1px);
}

.gallery-filters__chip--active {
    background: #003063;
    color: white;
    border-color: #003063;
    box-shadow: 0 4px 14px rgba(0,48,99,0.25);
}

.gallery-filters__chip--active:hover {
    background: #003063;
    color: white;
}

.gallery-filters__chip-icon {
    width: 0.85rem;
    height: 0.85rem;
    flex-shrink: 0;
}

.gallery-filters__chip-count {
    background: rgba(0,0,0,0.1);
    border-radius: 9999px;
    padding: 0.1rem 0.4rem;
    font-size: 0.65rem;
    font-weight: 800;
}

.gallery-filters__chip--active .gallery-filters__chip-count {
    background: rgba(255,255,255,0.2);
}

/* Athlete Search */
.gallery-filters__athlete {
    position: relative;
}

.gallery-filters__search-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.2rem;
    border-radius: 9999px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #003063;
    background: rgba(0,48,99,0.06);
    border: 1.5px solid rgba(0,48,99,0.12);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.gallery-filters__search-btn:hover {
    background: rgba(0,48,99,0.12);
    border-color: rgba(0,48,99,0.2);
    box-shadow: 0 2px 8px rgba(0,48,99,0.1);
}

.gallery-filters__search-icon {
    width: 1.1rem;
    height: 1.1rem;
    color: #C9A84C;
}

.gallery-filters__search-chevron {
    width: 1rem;
    height: 1rem;
    transition: transform 0.3s;
}

.gallery-filters__search-chevron--open {
    transform: rotate(180deg);
}

/* Current athlete badge */
.gallery-filters__current-athlete {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.5rem 0.35rem 0.35rem;
    border-radius: 9999px;
    background: linear-gradient(135deg, #003063, #004a96);
    color: white;
    font-size: 0.82rem;
    font-weight: 600;
    box-shadow: 0 4px 14px rgba(0,48,99,0.3);
}

.gallery-filters__current-athlete-avatar {
    width: 1.8rem;
    height: 1.8rem;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
}

.gallery-filters__current-athlete-avatar svg {
    width: 1rem;
    height: 1rem;
}

.gallery-filters__current-athlete-clear {
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s;
    border: none;
    color: white;
    margin-left: 0.25rem;
}

.gallery-filters__current-athlete-clear:hover {
    background: rgba(255,255,255,0.3);
}

/* Dropdown */
.gallery-filters__dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    width: 18rem;
    background: white;
    border-radius: 1rem;
    box-shadow: 0 20px 40px rgba(0,0,0,0.12), 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,48,99,0.08);
    overflow: hidden;
    z-index: 50;
}

.gallery-filters__dropdown-search {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.gallery-filters__dropdown-search-icon {
    width: 1rem;
    height: 1rem;
    color: #9ca3af;
    flex-shrink: 0;
}

.gallery-filters__dropdown-input {
    border: none;
    outline: none;
    width: 100%;
    font-size: 0.85rem;
    color: #1f2937;
    background: transparent;
}

.gallery-filters__dropdown-input::placeholder {
    color: #9ca3af;
}

.gallery-filters__dropdown-list {
    max-height: 16rem;
    overflow-y: auto;
    padding: 0.5rem;
}

.gallery-filters__dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: none;
    background: transparent;
    border-radius: 0.5rem;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    color: #374151;
    transition: all 0.2s;
    text-align: left;
}

.gallery-filters__dropdown-item:hover {
    background: rgba(0,48,99,0.06);
    color: #003063;
}

.gallery-filters__dropdown-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(0,48,99,0.08), rgba(201,168,76,0.08));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.gallery-filters__dropdown-avatar svg {
    width: 1rem;
    height: 1rem;
    color: #003063;
}

.gallery-filters__dropdown-arrow {
    width: 1rem;
    height: 1rem;
    margin-left: auto;
    color: #9ca3af;
    transition: transform 0.2s;
}

.gallery-filters__dropdown-item:hover .gallery-filters__dropdown-arrow {
    transform: translateX(3px);
    color: #003063;
}

.gallery-filters__dropdown-empty {
    padding: 1.5rem 1rem;
    text-align: center;
    color: #9ca3af;
    font-size: 0.82rem;
}

/* Dropdown transition */
.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.96);
}

/* Photo count */
.gallery-filters__count {
    max-width: 80rem;
    margin: 0.5rem auto 0;
    padding: 0 1.5rem;
    font-size: 0.75rem;
    color: #9ca3af;
}

.gallery-filters__count span {
    font-weight: 700;
    color: #003063;
}

/* ── MASONRY GRID ────────────────────────────────── */
.gallery-grid-section {
    background: #f8f9fb;
    padding: 2.5rem 0 4rem;
    min-height: 50vh;
}

.gallery-masonry {
    max-width: 84rem;
    margin: 0 auto;
    padding: 0 1rem;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.6rem;
}

@media (min-width: 640px) {
    .gallery-masonry {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }
}

@media (min-width: 1024px) {
    .gallery-masonry {
        grid-template-columns: repeat(4, 1fr);
        gap: 0.85rem;
    }
}

.gallery-masonry__item {
    position: relative;
    border-radius: 0.75rem;
    overflow: hidden;
    cursor: pointer;
    aspect-ratio: 1;
    opacity: 0;
    transform: translateY(30px) scale(0.96);
    transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1) var(--stagger),
                transform 0.6s cubic-bezier(0.4, 0, 0.2, 1) var(--stagger);
}

.gallery-masonry__item--revealed {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.gallery-masonry__item--tall {
    grid-row: span 2;
    aspect-ratio: auto;
}

@media (max-width: 639px) {
    .gallery-masonry__item--tall {
        grid-row: span 1;
        aspect-ratio: 1;
    }
    .gallery-masonry__item--wide {
        grid-column: span 1;
    }
}

@media (min-width: 640px) {
    .gallery-masonry__item--wide {
        grid-column: span 2;
        aspect-ratio: 2/1;
    }
}

/* Skeleton */
.gallery-masonry__skeleton {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #e5e7eb 0%, #f3f4f6 50%, #e5e7eb 100%);
    z-index: 1;
}

.gallery-masonry__skeleton-shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 30%, rgba(255,255,255,0.6) 50%, transparent 70%);
    animation: shimmer 1.5s ease-in-out infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Image */
.gallery-masonry__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1), filter 0.4s;
    opacity: 0;
}

.gallery-masonry__img--loaded {
    opacity: 1;
    transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1), filter 0.4s, opacity 0.4s;
}

.gallery-masonry__item:hover .gallery-masonry__img {
    transform: scale(1.08);
    filter: brightness(0.75);
}

/* Overlay */
.gallery-masonry__overlay {
    position: absolute;
    inset: 0;
    z-index: 5;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.8rem;
    opacity: 0;
    transition: opacity 0.4s;
}

.gallery-masonry__item:hover .gallery-masonry__overlay {
    opacity: 1;
}

.gallery-masonry__overlay-top {
    display: flex;
    justify-content: flex-start;
}

.gallery-masonry__category {
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(8px);
    color: #C9A84C;
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 0.25rem 0.6rem;
    border-radius: 9999px;
    border: 1px solid rgba(201,168,76,0.2);
    transform: translateY(-8px);
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.gallery-masonry__item:hover .gallery-masonry__category {
    transform: translateY(0);
}

.gallery-masonry__overlay-bottom {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.5rem;
    transform: translateY(8px);
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.gallery-masonry__item:hover .gallery-masonry__overlay-bottom {
    transform: translateY(0);
}

.gallery-masonry__alt {
    color: white;
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1.3;
    text-shadow: 0 1px 4px rgba(0,0,0,0.5);
    margin: 0;
}

.gallery-masonry__zoom {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.3s, transform 0.3s;
}

.gallery-masonry__zoom svg {
    width: 1.1rem;
    height: 1.1rem;
    color: white;
}

.gallery-masonry__item:hover .gallery-masonry__zoom:hover {
    background: rgba(201,168,76,0.5);
    transform: scale(1.1);
}

/* ── EMPTY STATE ─────────────────────────────────── */
.gallery-empty {
    max-width: 28rem;
    margin: 0 auto;
    padding: 5rem 1.5rem;
    text-align: center;
}

.gallery-empty__icon-wrap {
    width: 5rem;
    height: 5rem;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(201,168,76,0.1), rgba(0,48,99,0.05));
    display: flex;
    align-items: center;
    justify-content: center;
}

.gallery-empty__icon {
    width: 2.5rem;
    height: 2.5rem;
    color: #C9A84C;
}

.gallery-empty__title {
    font-size: 1.5rem;
    font-weight: 900;
    text-transform: uppercase;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.gallery-empty__text {
    color: #6b7280;
    font-size: 0.95rem;
    line-height: 1.6;
}

/* ── LIGHTBOX ────────────────────────────────────── */
.gallery-lightbox {
    position: fixed;
    inset: 0;
    z-index: 100;
    background: rgba(0,0,0,0.96);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    outline: none;
    opacity: 0;
    transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.gallery-lightbox--visible {
    opacity: 1;
}

.gallery-lightbox__close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 110;
    width: 2.8rem;
    height: 2.8rem;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    color: rgba(255,255,255,0.6);
}

.gallery-lightbox__close:hover {
    background: rgba(255,255,255,0.15);
    color: white;
    transform: rotate(90deg);
}

.gallery-lightbox__close svg {
    width: 1.3rem;
    height: 1.3rem;
}

.gallery-lightbox__nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 110;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    color: rgba(255,255,255,0.5);
}

.gallery-lightbox__nav:hover {
    background: rgba(201,168,76,0.2);
    border-color: rgba(201,168,76,0.3);
    color: #C9A84C;
}

.gallery-lightbox__nav svg {
    width: 1.3rem;
    height: 1.3rem;
}

.gallery-lightbox__nav--prev { left: 1rem; }
.gallery-lightbox__nav--next { right: 1rem; }

@media (min-width: 768px) {
    .gallery-lightbox__nav--prev { left: 2rem; }
    .gallery-lightbox__nav--next { right: 2rem; }
    .gallery-lightbox__nav {
        width: 3.5rem;
        height: 3.5rem;
    }
}

.gallery-lightbox__content {
    max-width: 70rem;
    max-height: 75vh;
    width: 100%;
    padding: 0 4rem;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.gallery-lightbox__img {
    max-width: 100%;
    max-height: 68vh;
    object-fit: contain;
    border-radius: 0.5rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}

.gallery-lightbox__info {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    max-width: 40rem;
    margin-top: 1rem;
    padding: 0.6rem 1rem;
    background: rgba(255,255,255,0.05);
    border-radius: 9999px;
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.06);
}

.gallery-lightbox__info-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    overflow: hidden;
}

.gallery-lightbox__info-category {
    color: #C9A84C;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    background: rgba(201,168,76,0.12);
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    white-space: nowrap;
}

.gallery-lightbox__info-alt {
    color: rgba(255,255,255,0.6);
    font-size: 0.82rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gallery-lightbox__info-counter {
    color: rgba(255,255,255,0.4);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    white-space: nowrap;
    margin-left: 1rem;
}

/* Thumbnail strip */
.gallery-lightbox__thumbs {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    margin-top: 1rem;
    padding: 0 1rem;
}

.gallery-lightbox__thumb {
    width: 3rem;
    height: 3rem;
    border-radius: 0.4rem;
    overflow: hidden;
    cursor: pointer;
    border: 2px solid transparent;
    opacity: 0.4;
    transition: all 0.3s;
    padding: 0;
    background: none;
    flex-shrink: 0;
}

.gallery-lightbox__thumb:hover {
    opacity: 0.7;
}

.gallery-lightbox__thumb--active {
    opacity: 1;
    border-color: #C9A84C;
    box-shadow: 0 0 12px rgba(201,168,76,0.3);
}

.gallery-lightbox__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

@media (max-width: 640px) {
    .gallery-lightbox__thumbs {
        display: none;
    }
    .gallery-lightbox__content {
        padding: 0 1rem;
    }
}

/* Lightbox transition */
.lightbox-backdrop-enter-active,
.lightbox-backdrop-leave-active {
    transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.lightbox-backdrop-enter-from,
.lightbox-backdrop-leave-to {
    opacity: 0;
}
</style>
