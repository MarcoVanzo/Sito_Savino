<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useOgMeta } from '@/Composables/useOgMeta';

const props = defineProps({
    nextGame: {
        type: Object,
        default: null,
    },
    latestNews: {
        type: Array,
        default: () => [],
    },
    heroSlides: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

// Impostazioni home dal backend con fallback
const settings = computed(() => page.props.siteSettings ?? {});
const homeSettings = computed(() => settings.value.home ?? {});

// Hero slides dal prop (backend) con fallback a immagini statiche
const slides = computed(() => {
    if (props.heroSlides && props.heroSlides.length > 0) {
        return props.heroSlides.map(s => typeof s === 'string' ? s : (s.image || s.url || s));
    }
    return ['/images/hero1.jpg', '/images/hero2.jpg'];
});

// Hero testi dal backend con fallback
const heroTitle = computed(() => homeSettings.value.hero_title || 'SAVINO DEL BENE');
const heroSubtitle = computed(() => homeSettings.value.hero_subtitle || 'VOLLEY');
const heroTagline = computed(() => homeSettings.value.hero_tagline || 'Scatena la Potenza.');
const heroCta1Label = computed(() => homeSettings.value.hero_cta1_label || 'Prossima Partita');
const heroCta1Url = computed(() => homeSettings.value.hero_cta1_url || '/stagione');
const heroCta2Label = computed(() => homeSettings.value.hero_cta2_label || 'Biglietteria');
const heroCta2Url = computed(() => homeSettings.value.hero_cta2_url || '/ticketing');

// Stats section dal backend con fallback
const statsTitle = computed(() => homeSettings.value.stats_title || 'Il Club in Numeri');
const statsSubtitle = computed(() => homeSettings.value.stats_subtitle || 'I Numeri');
const stats = computed(() => {
    if (homeSettings.value.stats && homeSettings.value.stats.length > 0) {
        return homeSettings.value.stats;
    }
    return [
        { value: '40+', label: 'Anni di Storia', icon: '🏆' },
        { value: '4.000+', label: 'Posti al Palazzo Wanny', icon: '🏟️' },
        { value: 'A1', label: 'Serie — Massima Divisione', icon: '🏐' },
        { value: 'CEV', label: 'Champions League', icon: '🌍' },
    ];
});

const currentSlide = ref(0);

let slideInterval;

const nextSlide = () => {
    currentSlide.value = (currentSlide.value + 1) % slides.value.length;
};

onMounted(() => {
    slideInterval = setInterval(nextSlide, 6000);
});

onUnmounted(() => {
    clearInterval(slideInterval);
});

const formattedMatchDate = computed(() => {
    if (!props.nextGame?.match_date) return null;
    return new Date(props.nextGame.match_date).toLocaleDateString('it-IT', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const formatNewsDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('it-IT', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const ogMeta = useOgMeta({
    title: 'Sito Ufficiale',
    description: 'Sito ufficiale della Savino Del Bene Volley. Scopri il roster, il calendario e i risultati della Serie A1 femminile.',
});
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
                        <span class="block whitespace-nowrap">{{ heroTitle }}</span>
                        <span class="block text-[#D90000] mt-2">{{ heroSubtitle }}</span>
                    </h1>
                    <p class="text-white font-sans font-bold text-2xl sm:text-3xl md:text-4xl tracking-widest uppercase mb-12 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                        {{ heroTagline }}
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 justify-end">
                        <Link 
                            :href="heroCta1Url" 
                            class="inline-flex items-center justify-center px-8 py-4 border-2 border-savino-gold bg-gray-900/40 hover:bg-savino-gold text-white hover:text-gray-900 text-sm font-bold uppercase tracking-widest transition-all duration-300 backdrop-blur-sm"
                        >
                            {{ heroCta1Label }}
                        </Link>
                        <Link 
                            :href="heroCta2Url" 
                            class="inline-flex items-center justify-center px-8 py-4 border-2 border-savino-red bg-savino-red hover:bg-white hover:text-savino-red hover:border-white text-white text-sm font-bold uppercase tracking-widest transition-all duration-300 shadow-[0_0_20px_rgba(237,2,140,0.4)]"
                        >
                            {{ heroCta2Label }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- PROSSIMA PARTITA -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Prossimo Impegno</span>
                    <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mt-3">
                        Prossima Partita
                    </h2>
                    <div class="w-16 h-1 bg-savino-gold mx-auto mt-4"></div>
                </div>
                <div class="max-w-4xl mx-auto bg-gradient-to-r from-savino-blue via-gray-900 to-savino-blue rounded-2xl overflow-hidden shadow-2xl">
                    <div class="px-8 py-12 md:px-16 md:py-14">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-8">
                            <!-- Home Team -->
                            <div class="text-center md:text-right flex-1">
                                <img src="/images/Logo_Savino.jpeg" :alt="nextGame?.home_team?.name ?? 'Savino Del Bene'" class="w-20 h-20 rounded-xl object-cover mx-auto md:ml-auto md:mr-0 mb-4 shadow-lg" />
                                <h3 class="text-white font-black text-xl uppercase tracking-tight">{{ nextGame?.home_team?.name ?? 'Savino Del Bene' }}</h3>
                                <span class="text-savino-gold text-xs font-bold uppercase tracking-wider">Casa</span>
                            </div>
                            <!-- VS -->
                            <div class="text-center px-6">
                                <div class="text-white/20 text-5xl font-black">VS</div>
                                <div class="mt-3 bg-savino-gold/20 backdrop-blur-sm rounded-lg px-4 py-2">
                                    <div class="text-savino-gold text-xs font-bold uppercase tracking-wider">{{ nextGame?.competition_type ?? 'Serie A1' }}</div>
                                    <div class="text-white text-sm font-bold mt-1">{{ formattedMatchDate ?? 'Data da definire' }}</div>
                                </div>
                            </div>
                            <!-- Away Team -->
                            <div class="text-center md:text-left flex-1">
                                <div class="w-20 h-20 rounded-xl bg-white/10 mx-auto md:mr-auto md:ml-0 mb-4 flex items-center justify-center">
                                    <svg class="w-10 h-10 text-white/30" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                </div>
                                <h3 class="text-white font-black text-xl uppercase tracking-tight">{{ nextGame?.away_team?.name ?? 'Avversario' }}</h3>
                                <span class="text-white/50 text-xs font-bold uppercase tracking-wider">{{ nextGame?.location ?? 'Trasferta' }}</span>
                            </div>
                        </div>
                        <!-- CTA -->
                        <div class="text-center mt-10">
                            <Link href="/ticketing" class="inline-flex items-center gap-2 bg-savino-gold text-white font-bold uppercase tracking-wider text-sm px-8 py-3.5 rounded-lg hover:bg-savino-gold/90 transition-all duration-300 shadow-lg shadow-savino-gold/30">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                                Acquista Biglietti
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- IL CLUB IN NUMERI -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ statsSubtitle }}</span>
                    <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mt-3">
                        {{ statsTitle }}
                    </h2>
                    <div class="w-16 h-1 bg-savino-gold mx-auto mt-4"></div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                    <div v-for="stat in stats" :key="stat.label" class="text-center group">
                        <div class="w-20 h-20 mx-auto mb-5 rounded-2xl bg-savino-blue/5 flex items-center justify-center group-hover:bg-savino-blue/10 transition-colors duration-300">
                            <span class="text-3xl">{{ stat.icon }}</span>
                        </div>
                        <div class="text-savino-blue text-3xl md:text-4xl font-black tracking-tight">{{ stat.value }}</div>
                        <div class="text-gray-500 text-sm font-medium mt-1">{{ stat.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- NEWS IN EVIDENZA -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-12">
                    <div>
                        <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Ultime Notizie</span>
                        <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mt-3">News</h2>
                        <div class="w-16 h-1 bg-savino-gold mt-4"></div>
                    </div>
                    <Link href="/news" class="mt-6 sm:mt-0 inline-flex items-center gap-2 text-savino-blue font-bold text-sm uppercase tracking-wider hover:text-savino-gold transition-colors">
                        Tutte le News
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                    </Link>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- News reali dal backend -->
                    <template v-if="latestNews.length > 0">
                        <Link v-for="post in latestNews" :key="post.id" :href="`/news/${post.slug}`" class="group bg-gray-50 rounded-2xl overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-500 hover:-translate-y-1">
                            <div class="aspect-video overflow-hidden">
                                <img v-if="post.image_url" :src="post.image_url" :alt="post.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />
                                <div v-else class="w-full h-full bg-gradient-to-br from-savino-blue/10 to-savino-gold/10 flex items-center justify-center">
                                    <span class="text-savino-gold text-4xl font-black opacity-30">SDB</span>
                                </div>
                            </div>
                            <div class="p-6">
                                <span class="text-savino-gold text-xs font-bold uppercase tracking-wider">{{ formatNewsDate(post.published_at) }}</span>
                                <h3 class="text-lg font-bold text-gray-900 mt-2 group-hover:text-savino-blue transition-colors line-clamp-2">{{ post.title }}</h3>
                                <p v-if="post.excerpt" class="text-gray-500 text-sm mt-3 leading-relaxed line-clamp-3">{{ post.excerpt }}</p>
                            </div>
                        </Link>
                    </template>
                    <!-- Fallback placeholder -->
                    <template v-else>
                        <div v-for="i in 3" :key="i" class="group bg-gray-50 rounded-2xl overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-500 hover:-translate-y-1">
                            <div class="aspect-video bg-gradient-to-br from-savino-blue/10 to-savino-gold/10 flex items-center justify-center">
                                <svg class="w-12 h-12 text-savino-blue/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                            </div>
                            <div class="p-6">
                                <span class="text-savino-gold text-xs font-bold uppercase tracking-wider">In arrivo</span>
                                <h3 class="text-lg font-bold text-gray-900 mt-2 group-hover:text-savino-blue transition-colors">Le ultime notizie saranno disponibili qui</h3>
                                <p class="text-gray-500 text-sm mt-3 leading-relaxed">Segui il club per restare aggiornato su tutte le novità della stagione.</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- CTA SPLIT BANNER -->
        <section class="py-0">
            <div class="grid md:grid-cols-2">
                <Link href="/ticketing" class="group relative bg-savino-blue py-16 px-8 text-center hover:bg-savino-blue/90 transition-colors duration-300 overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700"></div>
                    <div class="relative">
                        <svg class="w-10 h-10 text-savino-gold mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                        <h3 class="text-white text-2xl font-black uppercase tracking-tight">Biglietteria</h3>
                        <p class="text-white/60 text-sm mt-2">Acquista i biglietti per la prossima partita</p>
                        <span class="inline-flex items-center gap-1 text-savino-gold text-sm font-bold uppercase tracking-wider mt-4 group-hover:gap-3 transition-all">
                            Scopri
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                        </span>
                    </div>
                </Link>
                <Link href="/shop" class="group relative bg-gray-900 py-16 px-8 text-center hover:bg-gray-800 transition-colors duration-300 overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700"></div>
                    <div class="relative">
                        <svg class="w-10 h-10 text-savino-gold mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        <h3 class="text-white text-2xl font-black uppercase tracking-tight">Shop Ufficiale</h3>
                        <p class="text-white/60 text-sm mt-2">Maglie, merchandise e accessori della squadra</p>
                        <span class="inline-flex items-center gap-1 text-savino-gold text-sm font-bold uppercase tracking-wider mt-4 group-hover:gap-3 transition-all">
                            Vai allo Shop
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                        </span>
                    </div>
                </Link>
            </div>
        </section>

    </PublicLayout>
</template>

<style scoped>
/* === HERO WRAPPER: Bleed under sticky header === */
.hero-wrapper {
    margin-top: -85px;
    padding-top: 85px;
}

/* === FLUID CINEMATIC DISSOLVE === */
.hero-slide {
    opacity: 0;
    z-index: 0;
    /* Long, smooth dissolve — both slides remain visible during the crossfade period */
    transition: opacity 2.8s cubic-bezier(0.4, 0, 0.2, 1);
    filter: brightness(1) saturate(1);
    will-change: opacity, transform;
}

.hero-slide.is-active {
    opacity: 1;
    z-index: 2;
    /* Very subtle bloom — just a gentle warmth, no harsh flash */
    animation: slideBloomIn 3.5s cubic-bezier(0.25, 0.1, 0.25, 1) forwards;
}

.hero-slide.is-leaving {
    opacity: 0;
    z-index: 1;
    /* Matched timing with the incoming slide for true overlap dissolve */
    transition: opacity 3.2s cubic-bezier(0.4, 0, 0.2, 1);
    /* Very mild dimming — keeps the image natural during fadeout */
    filter: brightness(0.92) saturate(0.95);
}

/* Subtle bloom: gentle brightness shift, almost imperceptible but adds life */
@keyframes slideBloomIn {
    0% {
        filter: brightness(1.06) saturate(0.95);
    }
    50% {
        filter: brightness(1.02) saturate(1.02);
    }
    100% {
        filter: brightness(1) saturate(1);
    }
}

/* === KEN BURNS — SLOW CINEMATIC DRIFT === */
.hero-slide-inner {
    transform: scale(1.02);
    transition: none;
    will-change: transform;
}

/* Slide 1: slow drift from center to upper-right */
.hero-slide:nth-child(odd) .hero-slide-inner.ken-burns-active {
    animation: kenBurnsDriftRight 10s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
}

@keyframes kenBurnsDriftRight {
    0% {
        transform: scale(1.02) translate(0, 0);
    }
    100% {
        transform: scale(1.12) translate(-1%, -0.7%);
    }
}

/* Slide 2: slow drift from lower-right to center */
.hero-slide:nth-child(even) .hero-slide-inner.ken-burns-active {
    animation: kenBurnsDriftLeft 10s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
}

@keyframes kenBurnsDriftLeft {
    0% {
        transform: scale(1.10) translate(-0.8%, 0.5%);
    }
    100% {
        transform: scale(1.04) translate(0.3%, -0.3%);
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

/* Respect reduced motion preferences */
@media (prefers-reduced-motion: reduce) {
    .hero-slide {
        transition: opacity 0.5s ease;
        filter: none !important;
        animation: none !important;
    }
    .hero-slide-inner {
        animation: none !important;
        transform: scale(1) !important;
    }
}
</style>
