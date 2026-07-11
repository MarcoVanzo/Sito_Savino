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
    title: props.page?.title ?? 'Storia',
    description: props.page?.meta_description || 'La Storia del Club',
});

// Mock timeline data for the premium UI since CMS only provides generic text
const timeline = [
    { year: '1982', title: 'Le Origini', description: 'La Pallavolo Scandicci nasce nel cuore del territorio toscano. I primi passi nei campionati locali pongono le basi per un progetto ambizioso.' },
    { year: '2012', title: 'La Svolta Savino Del Bene', description: 'L\'ingresso del Gruppo Savino Del Bene come title sponsor trasforma le ambizioni del club. Inizia la scalata verso l\'élite del volley nazionale.' },
    { year: '2014', title: 'Promozione in Serie A1', description: 'Un traguardo storico: la squadra conquista l\'accesso alla massima serie italiana, portando Scandicci nel panorama nazionale.' },
    { year: '2018', title: 'Debutto Europeo', description: 'Prima qualificazione in CEV Champions League. La Savino Del Bene Volley si affaccia sul palcoscenico internazionale sfidando le big europee.' },
    { year: '2022', title: 'CEV Challenge Cup', description: 'Il primo storico trofeo europeo. Una cavalcata trionfale che arricchisce la bacheca del club e consacra il progetto a livello internazionale.' },
    { year: '2023', title: 'CEV Cup', description: 'Ancora un successo continentale. La Savino Del Bene solleva la CEV Cup, dimostrando continuità di risultati e mentalità vincente.' },
    { year: '2024', title: 'Finale Scudetto', description: 'Per la prima volta nella storia, il club raggiunge la Finale Scudetto, lottando punto a punto per il tricolore e riempiendo il Palazzo Wanny.' }
];
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
        <!-- Premium Hero Section -->
        <PageHero 
            :title="page?.title" 
            :subtitle="page?.meta_description" 
            image="/images/hero-1.jpg" 
        />

        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-gray-50 relative overflow-hidden">
            <!-- Decorative Background Element -->
            <div class="absolute top-0 right-0 w-[800px] h-[800px] bg-savino-gold/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3"></div>
            <div class="absolute bottom-0 left-0 w-[600px] h-[600px] bg-savino-blue/5 rounded-full blur-3xl translate-y-1/3 -translate-x-1/3"></div>
            
            <div class="max-w-5xl mx-auto relative z-10">
                <!-- Content from CMS -->
                <div v-if="page?.content" class="bg-white/80 backdrop-blur-xl rounded-3xl p-8 md:p-14 shadow-xl border border-white/40 mb-24 relative transform hover:-translate-y-1 transition-all duration-500">
                    <div class="absolute -top-6 -left-6 w-14 h-14 bg-gradient-to-br from-savino-blue to-[#004b99] text-white rounded-2xl flex items-center justify-center shadow-[0_10px_20px_rgba(0,48,99,0.3)] rotate-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                    </div>
                    <div 
                        class="prose prose-lg max-w-none prose-headings:font-black prose-headings:text-savino-blue prose-h2:text-3xl prose-p:text-gray-600 prose-p:leading-relaxed prose-a:text-savino-gold prose-a:no-underline hover:prose-a:underline"
                        v-html="safeContent"
                    ></div>
                </div>

                <!-- Interactive Timeline -->
                <div class="mt-20">
                    <div class="text-center mb-20">
                        <h2 class="text-4xl md:text-5xl font-black text-savino-blue uppercase tracking-tighter mb-4 drop-shadow-sm">Le Tappe Fondamentali</h2>
                        <div class="w-24 h-1.5 bg-gradient-to-r from-savino-red to-savino-pink mx-auto rounded-full"></div>
                    </div>

                    <div class="relative wrap overflow-hidden p-4 md:p-10 h-full">
                        <!-- Vertical line -->
                        <div class="hidden md:block absolute border-opacity-20 border-savino-blue h-full border-l-[3px] left-1/2 -ml-[1.5px] top-0 rounded-full"></div>
                        
                        <div v-for="(item, index) in timeline" :key="index" 
                             class="mb-12 flex justify-between items-center w-full group timeline-item"
                             :class="index % 2 === 0 ? 'md:flex-row-reverse left-timeline' : 'right-timeline'">
                            
                            <div class="hidden md:block order-1 w-5/12"></div>
                            
                            <div class="z-20 flex items-center order-1 bg-gradient-to-br from-savino-gold to-[#e0bc55] shadow-lg w-14 h-14 rounded-full ring-4 ring-white group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 flex-shrink-0 md:mx-auto absolute md:relative left-0 md:left-auto">
                                <span class="mx-auto font-bold text-white text-base drop-shadow-md">{{ item.year.substring(2) }}'</span>
                            </div>
                            
                            <div class="order-1 w-full md:w-5/12 pl-20 md:pl-0 px-0 md:px-6 py-4 transition-all duration-500"
                                 :class="index % 2 === 0 ? 'md:text-right text-left' : 'text-left'">
                                <div class="bg-white p-8 rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.04)] border border-gray-50 group-hover:-translate-y-2 group-hover:shadow-[0_20px_50px_rgba(0,0,0,0.08)] transition-all duration-300 relative overflow-hidden">
                                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r" :class="index % 2 === 0 ? 'from-savino-blue to-savino-red' : 'from-savino-gold to-savino-pink'"></div>
                                    <h4 class="font-black text-savino-gold text-xl mb-1 tracking-wider">{{ item.year }}</h4>
                                    <h3 class="font-black text-2xl text-savino-blue mb-3 uppercase tracking-tight leading-tight">{{ item.title }}</h3>
                                    <p class="text-gray-600 leading-relaxed font-medium text-base">{{ item.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
/* Mobile adjustments done via Tailwind classes */
.timeline-item {
    position: relative;
}
@media (max-width: 767px) {
    .wrap::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 44px; /* 16px padding + 28px (half of 56px circle) */
        width: 3px;
        background-color: rgba(0, 48, 99, 0.2);
        border-radius: 9999px;
    }
}
</style>
