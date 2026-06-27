<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';

const slides = [
    '/images/hero1.jpg',
    '/images/hero2.jpg'
];

const currentSlide = ref(0);
const isTransitioning = ref(false);
let slideInterval;

const nextSlide = () => {
    isTransitioning.value = true;
    currentSlide.value = (currentSlide.value + 1) % slides.length;
    // Reset transition flag after the crossfade completes
    setTimeout(() => {
        isTransitioning.value = false;
    }, 1800);
};

onMounted(() => {
    slideInterval = setInterval(nextSlide, 6000);
});

onUnmounted(() => {
    clearInterval(slideInterval);
});
</script>

<template>
        <Head>
        <title>Savino Del Bene Volley — Sito Ufficiale</title>
        <meta name="description" content="Sito ufficiale della Savino Del Bene Volley. Scopri il roster, il calendario e i risultati della Serie A1 femminile." />
    </Head>
    <PublicLayout>
        <!-- HERO SECTION -->
        <div class="hero-wrapper relative w-full min-h-screen flex items-center bg-gray-900 overflow-hidden">
            <!-- Background Images (Cinematic Ken Burns Crossfade) -->
            <div class="absolute inset-0 w-full h-full">
                <div 
                    v-for="(slide, index) in slides"
                    :key="slide"
                    class="hero-slide absolute inset-0 w-full h-full"
                    :class="{
                        'is-active': currentSlide === index,
                        'is-leaving': currentSlide !== index
                    }"
                >
                    <div 
                        class="absolute inset-0 w-full h-full bg-cover bg-center bg-no-repeat hero-slide-inner"
                        :class="{ 'ken-burns-active': currentSlide === index }"
                        :style="`background-image: url('${slide}');`"
                    ></div>
                </div>
            </div>
            
            <!-- Cinematic Gradient Overlay -->
            <div class="absolute inset-0 z-[1]">
                <!-- Left-side deep gradient for text readability -->
                <div class="absolute inset-0 bg-gradient-to-r from-gray-900/90 via-gray-900/50 to-transparent"></div>
                <!-- Bottom vignette for depth -->
                <div class="absolute inset-0 bg-gradient-to-t from-gray-900/40 via-transparent to-transparent"></div>
                <!-- Subtle film grain texture -->
                <div class="absolute inset-0 opacity-[0.03] mix-blend-overlay" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noise%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.65%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noise)%22/%3E%3C/svg%3E');"></div>
            </div>
            
            <!-- Slide Indicators -->
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-20 flex gap-3">
                <button 
                    v-for="(slide, index) in slides"
                    :key="'indicator-' + index"
                    @click="currentSlide = index"
                    class="group relative h-1 rounded-full transition-all duration-700 overflow-hidden"
                    :class="currentSlide === index ? 'w-12 bg-white/30' : 'w-6 bg-white/20 hover:bg-white/30'"
                    :aria-label="'Slide ' + (index + 1)"
                >
                    <span 
                        v-if="currentSlide === index"
                        class="absolute inset-0 bg-white rounded-full origin-left slide-progress"
                    ></span>
                </button>
            </div>
            
            <!-- Content -->
            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-20">
                <div class="max-w-3xl ml-auto text-right">
                    <h1 class="text-white font-sans font-black text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl leading-none tracking-tighter mb-4 drop-shadow-[0_4px_4px_rgba(0,0,0,0.8)] uppercase">
                        <span class="block whitespace-nowrap">SAVINO DEL BENE</span>
                        <span class="block text-[#D90000] mt-2">VOLLEY</span>
                    </h1>
                    <p class="text-white font-sans font-bold text-2xl sm:text-3xl md:text-4xl tracking-widest uppercase mb-12 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                        Scatena la Potenza.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 justify-end">
                        <Link 
                            href="/stagione" 
                            class="inline-flex items-center justify-center px-8 py-4 border-2 border-savino-gold bg-gray-900/40 hover:bg-savino-gold text-white hover:text-gray-900 text-sm font-bold uppercase tracking-widest transition-all duration-300 backdrop-blur-sm"
                        >
                            Prossima Partita
                        </Link>
                        <Link 
                            href="/ticketing" 
                            class="inline-flex items-center justify-center px-8 py-4 border-2 border-savino-red bg-savino-red hover:bg-white hover:text-savino-red hover:border-white text-white text-sm font-bold uppercase tracking-widest transition-all duration-300 shadow-[0_0_20px_rgba(237,2,140,0.4)]"
                        >
                            Biglietteria
                        </Link>
                    </div>
                </div>
            </div>
        </div>
        
    </PublicLayout>
</template>

<style scoped>
/* === HERO WRAPPER: Bleed under sticky header === */
.hero-wrapper {
    margin-top: -85px;
    padding-top: 85px;
}

/* === SLIDE CROSSFADE === */
.hero-slide {
    opacity: 0;
    z-index: 0;
    transition: opacity 1.8s cubic-bezier(0.4, 0, 0.2, 1);
}
.hero-slide.is-active {
    opacity: 1;
    z-index: 2;
}
.hero-slide.is-leaving {
    opacity: 0;
    z-index: 1;
}

/* === KEN BURNS CINEMATIC ZOOM/PAN === */
.hero-slide-inner {
    transform: scale(1);
    transition: none;
}
.hero-slide-inner.ken-burns-active {
    animation: kenBurns 7s ease-out forwards;
}

@keyframes kenBurns {
    0% {
        transform: scale(1) translate(0, 0);
    }
    100% {
        transform: scale(1.12) translate(-1%, -0.5%);
    }
}

/* Alternate direction for visual variety — applied via nth-child */
.hero-slide:nth-child(even) .hero-slide-inner.ken-burns-active {
    animation: kenBurnsAlt 7s ease-out forwards;
}

@keyframes kenBurnsAlt {
    0% {
        transform: scale(1.04) translate(-1%, 0.5%);
    }
    100% {
        transform: scale(1.14) translate(0.5%, -1%);
    }
}

/* === SLIDE PROGRESS INDICATOR === */
.slide-progress {
    animation: progressFill 6s linear forwards;
}

@keyframes progressFill {
    0% {
        transform: scaleX(0);
    }
    100% {
        transform: scaleX(1);
    }
}
</style>
