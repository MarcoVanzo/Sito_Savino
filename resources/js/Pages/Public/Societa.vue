<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';

const { onImgError } = useImageFallback();

const props = defineProps({
    page: Object,
    dirigenza: {
        type: Array,
        default: () => []
    },
});

const inertiaPage = usePage();
const settings = computed(() => inertiaPage.props.siteSettings ?? {});
const contact = computed(() => settings.value.contact ?? {});

// content_data dal backend con fallback
const cd = computed(() => props.page?.content_data ?? {});

// Hero
const heroSubheading = computed(() => cd.value.hero_subheading || 'Dal 1982');
const heroDescription = computed(() => cd.value.hero_description || "Oltre quarant'anni di passione, tradizione e successi nel panorama della pallavolo femminile italiana. Una storia costruita con determinazione e visione.");

// Storia
const storiaTitle = computed(() => cd.value.storia_title || 'Una Tradizione di Eccellenza');
const storiaParagraphs = computed(() => {
    if (cd.value.storia_paragraphs && cd.value.storia_paragraphs.length > 0) return cd.value.storia_paragraphs;
    return [
        'Fondata nel 1982 a Scandicci, la Savino Del Bene Volley è diventata una delle realtà più importanti della pallavolo femminile italiana. Dalle origini nel campionato regionale alla Serie A1, il percorso del club è stato segnato da una crescita costante.',
        'Con la partnership strategica del Gruppo Savino Del Bene, il club ha raggiunto traguardi storici: la Finale Scudetto, la partecipazione alla CEV Champions League e la conquista di un posto stabile tra le migliori squadre d\'Europa.',
        'Oggi la Savino Del Bene Volley rappresenta un modello di gestione sportiva, con un settore giovanile d\'eccellenza, un impegno sociale concreto e una visione proiettata verso il futuro.',
    ];
});
const storiaYears = computed(() => cd.value.storia_years || '40+');

// Organigramma
const orgTitle = computed(() => cd.value.org_title || 'Il Nostro Team Dirigenziale');

function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase()
}

// Palazzetto
const palazzettoTitle = computed(() => cd.value.palazzetto_title || 'Palazzo Wanny');
const palazzettoDescription = computed(() => cd.value.palazzetto_description || "Il Palazzo Wanny di Firenze è la casa della Savino Del Bene Volley. Con una capienza di oltre 4.000 posti, l'impianto offre un'esperienza unica per tifosi e appassionati di pallavolo.");
const palazzettoCapacity = computed(() => cd.value.palazzetto_capacity || '4.000+');
const palazzettoHomologation = computed(() => cd.value.palazzetto_homologation || 'Serie A1');
const palazzettoAddress = computed(() => cd.value.palazzetto_address || contact.value.address || 'Via del Tridente, 5 — 50127 Firenze (FI)');

// Contatti
const contactEmail = computed(() => contact.value.email || 'info@savinodelbenevolley.com');
const contactPhone = computed(() => contact.value.phone || '+39 055 XXX XXXX');
const contactLocation = computed(() => contact.value.city || 'Scandicci (FI), Toscana');

const ogMeta = useOgMeta({
    title: props.page?.title ?? 'La Società',
    description: props.page?.meta_description || "Scopri la storia, l'organigramma e le strutture della Savino Del Bene Volley. Dal 1982, una tradizione di eccellenza nella pallavolo femminile italiana.",
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
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-savino-blue via-gray-900 to-savino-blue"></div>
            <!-- Geometric Pattern Overlay -->
            <div class="absolute inset-0 opacity-[0.04]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M30 0L60 30L30 60L0 30Z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;1&quot;/%3E%3C/svg%3E'); background-size: 60px 60px;"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-savino-blue/80 to-transparent"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <div class="inline-block mb-6">
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ heroSubheading }}</span>
                </div>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mb-6">
                    {{ page?.title ?? 'La Società' }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-8"></div>
                <p class="text-white/80 text-lg md:text-xl max-w-3xl mx-auto leading-relaxed">
                    {{ heroDescription }}
                </p>
            </div>
        </section>

        <!-- LA STORIA -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <div class="grid md:grid-cols-2 gap-16 items-center">
                    <div>
                        <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">La Nostra Storia</span>
                        <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mt-3 mb-6">
                            {{ storiaTitle }}
                        </h2>
                        <div class="w-16 h-1 bg-savino-gold mb-8"></div>
                        <p v-for="(para, idx) in storiaParagraphs" :key="idx" class="text-gray-600 leading-relaxed mb-6">
                            {{ para }}
                        </p>
                    </div>
                    <div class="relative">
                        <div class="aspect-[4/3] bg-gradient-to-br from-savino-blue to-gray-900 rounded-lg overflow-hidden shadow-2xl">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <div class="text-savino-gold text-7xl font-black">{{ storiaYears }}</div>
                                    <div class="text-white/80 text-sm font-bold uppercase tracking-[0.2em] mt-3">Anni di Storia</div>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -bottom-4 -right-4 w-24 h-24 bg-savino-gold/20 rounded-lg"></div>
                        <div class="absolute -top-4 -left-4 w-16 h-16 border-2 border-savino-gold/30 rounded-lg"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ORGANIGRAMMA -->
        <section id="organigramma" class="py-20 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">Organigramma</span>
                    <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mt-3">
                        {{ orgTitle }}
                    </h2>
                    <div class="w-16 h-1 bg-savino-gold mx-auto mt-4"></div>
                </div>

                <div v-if="dirigenza.length > 0" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    <div
                        v-for="member in dirigenza"
                        :key="member.id"
                        class="group bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-500 overflow-hidden border border-gray-100 hover:-translate-y-1"
                    >
                        <div class="relative h-48 bg-gradient-to-br from-gray-700 to-gray-900 flex items-center justify-center overflow-hidden">
                            <img
                                v-if="member.photo_url"
                                :src="member.photo_url"
                                :alt="member.name"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                @error="onImgError"
                            />
                            <span v-else class="text-4xl font-black text-white/30">{{ getInitials(member.name) }}</span>
                        </div>
                        <div class="p-5 text-center">
                            <h3 class="text-base font-black text-gray-900 uppercase tracking-tight">{{ member.name }}</h3>
                            <p class="text-savino-gold text-sm font-bold mt-1">{{ member.role }}</p>
                        </div>
                    </div>
                </div>

                <!-- Empty state fallback -->
                <div v-else class="text-center py-12">
                    <p class="text-gray-500">Organigramma in aggiornamento.</p>
                </div>
            </div>
        </section>

        <!-- PALAZZETTO -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <div class="grid md:grid-cols-2 gap-16 items-center">
                    <div class="order-2 md:order-1">
                        <div class="aspect-video bg-gradient-to-br from-gray-800 to-savino-blue rounded-lg overflow-hidden shadow-2xl relative">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <svg class="w-16 h-16 text-savino-gold/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            </div>
                        </div>
                    </div>
                    <div class="order-1 md:order-2">
                        <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">La Nostra Casa</span>
                        <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mt-3 mb-6">
                            {{ palazzettoTitle }}
                        </h2>
                        <div class="w-16 h-1 bg-savino-gold mb-8"></div>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            {{ palazzettoDescription }}
                        </p>
                        <div class="grid grid-cols-2 gap-6 mt-8">
                            <div class="bg-gray-50 p-5 rounded-lg">
                                <div class="text-savino-blue text-2xl font-black">{{ palazzettoCapacity }}</div>
                                <div class="text-gray-500 text-sm font-medium mt-1">Posti a Sedere</div>
                            </div>
                            <div class="bg-gray-50 p-5 rounded-lg">
                                <div class="text-savino-blue text-2xl font-black">{{ palazzettoHomologation }}</div>
                                <div class="text-gray-500 text-sm font-medium mt-1">Omologazione</div>
                            </div>
                        </div>
                        <div class="mt-8 flex items-start gap-3 text-gray-600">
                            <svg class="w-5 h-5 text-savino-gold flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <span class="text-sm">{{ palazzettoAddress }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CONTATTI -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-savino-blue">
            <div class="max-w-5xl mx-auto text-center">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">Resta in Contatto</span>
                <h2 class="text-3xl md:text-4xl font-black text-white uppercase tracking-tighter mt-3">
                    Contattaci
                </h2>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-12"></div>
                <div class="grid sm:grid-cols-3 gap-8">
                    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-lg p-8 hover:bg-white/10 transition-all duration-300">
                        <svg class="w-8 h-8 text-savino-gold mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        <h3 class="text-white font-bold uppercase text-sm tracking-wider mb-2">Email</h3>
                        <p class="text-white/70 text-sm">{{ contactEmail }}</p>
                    </div>
                    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-lg p-8 hover:bg-white/10 transition-all duration-300">
                        <svg class="w-8 h-8 text-savino-gold mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                        <h3 class="text-white font-bold uppercase text-sm tracking-wider mb-2">Telefono</h3>
                        <p class="text-white/70 text-sm">{{ contactPhone }}</p>
                    </div>
                    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-lg p-8 hover:bg-white/10 transition-all duration-300">
                        <svg class="w-8 h-8 text-savino-gold mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <h3 class="text-white font-bold uppercase text-sm tracking-wider mb-2">Sede</h3>
                        <p class="text-white/70 text-sm">{{ contactLocation }}</p>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
