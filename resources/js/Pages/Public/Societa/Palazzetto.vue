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
    description: props.page?.meta_description,
});

const cd = computed(() => props.page?.content_data ?? {});

// Nome, indirizzo, mappa e servizi arrivano dal CMS (Pagine → Palazzetto):
// i valori che stavano qui restavano online anche dopo averli cambiati.
const venueName = computed(() => cd.value?.venue_name || props.page?.title || '');
const venueAddress = computed(() => cd.value?.venue_address || '');
const mapsLink = computed(() => cd.value?.maps_link || '');
const mapsIframeSrc = computed(() => cd.value?.maps_iframe_src || '');

const services = computed(() => Array.isArray(cd.value?.services) ? cd.value.services : []);
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
            :image="page?.cover_url"
        />

        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white relative">
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    
                    <!-- Text Content -->
                    <div class="order-2 lg:order-1">
                        <div class="inline-block px-4 py-1.5 bg-savino-fucsia/10 text-savino-fucsia font-bold rounded-full text-sm tracking-wide mb-6">
                            {{ $t('societa.palazzetto_hero_title') }}
                        </div>
                        <h2 class="text-4xl md:text-5xl font-black text-savino-blue tracking-tight mb-8">{{ venueName }}</h2>
                        
                        <div 
                            v-if="page?.content"
                            class="prose prose-lg max-w-none prose-headings:font-black prose-headings:text-savino-blue prose-h2:hidden prose-p:text-gray-600 prose-p:leading-relaxed prose-a:text-savino-fucsia hover:prose-a:underline mb-10"
                            v-html="safeContent"
                        ></div>

                        <!-- Services Grid -->
                        <div v-if="services.length" class="grid grid-cols-2 gap-6 mt-12">
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
                            <!-- La foto è la copertina della pagina, caricata dal pannello. -->
                            <img
                                v-if="page?.cover_url"
                                :src="page.cover_url"
                                :alt="venueName"
                                class="w-full h-[500px] object-cover transform group-hover:scale-105 transition-transform duration-700"
                            />
                            <div v-else class="w-full h-[500px] bg-gradient-to-br from-savino-blue to-gray-900"></div>
                            
                            <!-- Address Card overlay -->
                            <div class="absolute bottom-6 left-6 right-6 bg-white/90 backdrop-blur-xl p-6 rounded-3xl shadow-xl z-20 border border-white/50">
                                <h3 class="font-black text-xl text-savino-blue mb-2">{{ $t('societa.palazzetto_directions_title') }}</h3>
                                <p v-if="venueAddress" class="text-gray-600 font-medium mb-4">{{ venueAddress }}</p>
                                <a v-if="mapsLink" :href="mapsLink" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-sm font-bold text-white bg-savino-blue hover:bg-savino-red px-5 py-2.5 rounded-xl transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    {{ $t('societa.palazzetto_open_gmaps') }}
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
        
        <!-- Interactive Map Section -->
        <section v-if="mapsIframeSrc" class="h-[400px] w-full bg-gray-200 grayscale hover:grayscale-0 transition-all duration-700">
            <iframe
                :src="mapsIframeSrc"
                :title="$t('societa.palazzetto_map_title')"
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
