<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSanitize } from '@/Composables/useSanitize';
import { useOgMeta } from '@/Composables/useOgMeta';
import PageHero from '@/Components/PageHero.vue';

const { sanitize } = useSanitize();
const $t = useTranslations();

const props = defineProps({
    page: Object,
});

const safeContent = computed(() => sanitize(props.page?.content));

const ogMeta = useOgMeta({
    title: props.page?.title ?? 'Il Palazzetto',
    description: props.page?.meta_description || 'Palazzo Wanny - La casa della Savino Del Bene Volley',
});

const cd = computed(() => props.page?.content_data ?? {});

const venueName = computed(() => cd.value?.venue_name || 'Palazzo Wanny');
const venueAddress = computed(() => cd.value?.venue_address || 'Via del Cavallaccio, 18/20/22/24 — 50142 Firenze (FI)');
const mapsLink = computed(() => cd.value?.maps_link || 'https://www.google.com/maps/place/Palazzo+Wanny/@43.7725946,11.1989035,17z/data=!3m1!4b1!4m6!3m5!1s0x132a514d3f32c3f9:0x6b4a2e5d5225c5d0!8m2!3d43.7725946!4d11.1989035!16s%2Fg%2F11q26v9v3g');
const mapsIframeSrc = computed(() => cd.value?.maps_iframe_src || 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2880.088656114169!2d11.2001!3d43.7917!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x132a514d3f32c3f9%3A0x6b4a2e5d5225c5d0!2sPalazzo%20Wanny!5e0!3m2!1sit!2sit!4v1700000000000!5m2!1sit!2sit');

const services = computed(() => {
    if (cd.value?.services && Array.isArray(cd.value.services) && cd.value.services.length > 0) {
        return cd.value.services;
    }
    return [
        { name: $t('societa.palazzetto_service_capacity') || 'Capienza 4000 Posti', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
        { name: $t('societa.palazzetto_service_disabled') || 'Accesso Disabili', icon: 'M19 14l-7 7m0 0l-7-7m7 7V3' },
        { name: $t('societa.palazzetto_service_parking') || 'Parcheggio Ampio', icon: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4' },
        { name: $t('societa.palazzetto_service_hospitality') || 'Bar e Area Hospitality', icon: 'M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M6.75 21A3.75 3.75 0 013 17.25V11.5A2.25 2.25 0 015.25 9.25h13.5A2.25 2.25 0 0121 11.5v5.75A3.75 3.75 0 0117.25 21H6.75z' }
    ];
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
        <PageHero
            :title="page?.title"
            :subtitle="page?.meta_description"
            :image="page?.cover_url || '/images/palazzetto-hero.jpg'"
        />

        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white relative">
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    
                    <!-- Text Content -->
                    <div class="order-2 lg:order-1">
                        <div class="inline-block px-4 py-1.5 bg-savino-gold/10 text-savino-gold font-bold rounded-full text-sm tracking-wide mb-6">
                            {{ $t('societa.palazzetto_hero_title') || 'LA NOSTRA CASA' }}
                        </div>
                        <h2 class="text-4xl md:text-5xl font-black text-savino-blue tracking-tight mb-8">{{ venueName }}</h2>
                        
                        <div 
                            v-if="page?.content"
                            class="prose prose-lg max-w-none prose-headings:font-black prose-headings:text-savino-blue prose-h2:hidden prose-p:text-gray-600 prose-p:leading-relaxed prose-a:text-savino-gold hover:prose-a:underline mb-10"
                            v-html="safeContent"
                        ></div>

                        <!-- Services Grid -->
                        <div class="grid grid-cols-2 gap-6 mt-12">
                            <div v-for="(service, idx) in services" :key="idx" class="flex items-start space-x-4 bg-gray-50 p-5 rounded-2xl border border-gray-100 hover:shadow-md transition-shadow">
                                <div class="bg-white p-3 rounded-xl text-savino-red shadow-sm">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="service.icon" />
                                    </svg>
                                </div>
                                <div class="pt-1">
                                    <h4 class="font-bold text-savino-blue leading-tight">{{ service.name }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visual / Map -->
                    <div class="order-1 lg:order-2">
                        <div class="relative rounded-[2.5rem] overflow-hidden shadow-2xl group border-8 border-white">
                            <div class="absolute inset-0 bg-savino-blue/10 group-hover:bg-transparent transition-colors duration-500 z-10 pointer-events-none"></div>
                            <img :src="page?.cover_url || '/images/palazzetto-hero.jpg'" alt="Palazzo Wanny" class="w-full h-[500px] object-cover transform group-hover:scale-105 transition-transform duration-700" onerror="this.src='https://placehold.co/800x600/ED028C/FFF?text=Palazzo+Wanny'" />
                            
                            <!-- Address Card overlay -->
                            <div class="absolute bottom-6 left-6 right-6 bg-white/90 backdrop-blur-xl p-6 rounded-3xl shadow-xl z-20 border border-white/50">
                                <h3 class="font-black text-xl text-savino-blue mb-2">{{ $t('societa.palazzetto_directions_title') || 'Come Arrivare' }}</h3>
                                <p class="text-gray-600 font-medium mb-4">{{ venueAddress }}</p>
                                <a :href="mapsLink" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-sm font-bold text-white bg-savino-blue hover:bg-savino-red px-5 py-2.5 rounded-xl transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    {{ $t('societa.palazzetto_open_gmaps') || 'Apri su Google Maps' }}
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
        
        <!-- Interactive Map Section -->
        <section class="h-[400px] w-full bg-gray-200 grayscale hover:grayscale-0 transition-all duration-700">
            <iframe 
                :src="mapsIframeSrc" 
                width="100%" 
                height="100%" 
                style="border:0;" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </section>
    </PublicLayout>
</template>
