<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

const slides = [
    '/images/hero1.jpg',
    '/images/hero2.jpg'
];

const currentSlide = ref(0);
let slideInterval;

onMounted(() => {
    slideInterval = setInterval(() => {
        currentSlide.value = (currentSlide.value + 1) % slides.length;
    }, 5000); // Change image every 5 seconds
});

onUnmounted(() => {
    clearInterval(slideInterval);
});

const props = defineProps({
    page: {
        type: Object,
        default: null,
    }
});
</script>

<template>
        <Head>
        <title>{{ page?.meta_title || page?.title || 'Savino Del Bene Volley' }}</title>
        <meta v-if="page?.meta_description" name="description" :content="page.meta_description" />
    </Head>
    <PublicLayout>
        <!-- HERO SECTION -->
        <div class="relative w-full min-h-screen flex items-center bg-gray-900 overflow-hidden -mt-[85px] pt-[85px]">
            <!-- Background Images (Slider with WOW Ken Burns Effect) -->
            <div class="absolute inset-0 w-full h-full">
                <div 
                    v-for="(slide, index) in slides"
                    :key="slide"
                    class="wow-slide absolute inset-0 w-full h-full bg-cover bg-center bg-no-repeat"
                    :class="{'is-active': currentSlide === index}"
                    :style="`background-image: url('${slide}');`"
                ></div>
            </div>
            
            <!-- Dark Gradient Overlay (left to right) -->
            <div class="absolute inset-0 bg-gradient-to-r from-gray-900/90 via-gray-900/50 to-transparent z-0"></div>
            
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
        
        <!-- Quick styles for the WOW transition -->
        <style scoped>
        .wow-slide {
            opacity: 0;
            transform: scale(1.05);
            transition: opacity 2s ease-in-out, transform 10s ease-out;
            z-index: 0;
        }
        .wow-slide.is-active {
            opacity: 1;
            transform: scale(1.15);
            z-index: 10;
        }
        </style>
    </PublicLayout>
</template>
