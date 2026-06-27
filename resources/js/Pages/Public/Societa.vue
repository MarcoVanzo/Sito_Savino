<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    page: Object,
});
</script>

<template>
    <Head>
        <title>{{ page?.title ?? 'La Società' }} — Savino Del Bene Volley</title>
        <meta v-if="page?.meta_description" name="description" :content="page.meta_description" />
        <meta v-else name="description" content="Scopri la storia, l'organigramma e le strutture della Savino Del Bene Volley. Dal 1982, una tradizione di eccellenza nella pallavolo femminile italiana." />
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
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Dal 1982</span>
                </div>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mb-6">
                    {{ page?.title ?? 'La Società' }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-8"></div>
                <p class="text-white/80 text-lg md:text-xl max-w-3xl mx-auto leading-relaxed">
                    Oltre quarant'anni di passione, tradizione e successi nel panorama della pallavolo femminile italiana. Una storia costruita con determinazione e visione.
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
                            Una Tradizione di Eccellenza
                        </h2>
                        <div class="w-16 h-1 bg-savino-gold mb-8"></div>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            Fondata nel 1982 a Scandicci, la Savino Del Bene Volley è diventata una delle realtà più importanti della pallavolo femminile italiana. Dalle origini nel campionato regionale alla Serie A1, il percorso del club è stato segnato da una crescita costante.
                        </p>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            Con la partnership strategica del Gruppo Savino Del Bene, il club ha raggiunto traguardi storici: la Finale Scudetto, la partecipazione alla CEV Champions League e la conquista di un posto stabile tra le migliori squadre d'Europa.
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            Oggi la Savino Del Bene Volley rappresenta un modello di gestione sportiva, con un settore giovanile d'eccellenza, un impegno sociale concreto e una visione proiettata verso il futuro.
                        </p>
                    </div>
                    <div class="relative">
                        <div class="aspect-[4/3] bg-gradient-to-br from-savino-blue to-gray-900 rounded-lg overflow-hidden shadow-2xl">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <div class="text-savino-gold text-7xl font-black">40+</div>
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
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">Organigramma</span>
                    <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mt-3">
                        Il Nostro Team Dirigenziale
                    </h2>
                    <div class="w-16 h-1 bg-savino-gold mx-auto mt-4"></div>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div v-for="(role, index) in [
                        { title: 'Presidente', name: 'Presidenza', desc: 'Guida strategica e visione del club' },
                        { title: 'Direttore Generale', name: 'Direzione', desc: 'Gestione operativa e coordinamento' },
                        { title: 'Direttore Sportivo', name: 'Area Tecnica', desc: 'Pianificazione sportiva e roster' },
                        { title: 'Head Coach', name: 'Staff Tecnico', desc: 'Guida tecnica della prima squadra' }
                    ]" :key="index" class="group text-center">
                        <div class="w-28 h-28 mx-auto mb-5 rounded-full bg-gradient-to-br from-savino-blue to-gray-800 flex items-center justify-center shadow-lg group-hover:shadow-savino-gold/20 group-hover:shadow-xl transition-all duration-300">
                            <svg class="w-12 h-12 text-savino-gold/70" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </div>
                        <h3 class="text-xs font-bold text-savino-gold uppercase tracking-[0.15em] mb-1">{{ role.title }}</h3>
                        <p class="text-savino-blue font-bold text-lg">{{ role.name }}</p>
                        <p class="text-gray-500 text-sm mt-1">{{ role.desc }}</p>
                    </div>
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
                            Palazzo Wanny
                        </h2>
                        <div class="w-16 h-1 bg-savino-gold mb-8"></div>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            Il Palazzo Wanny di Firenze è la casa della Savino Del Bene Volley. Con una capienza di oltre 4.000 posti, l'impianto offre un'esperienza unica per tifosi e appassionati di pallavolo.
                        </p>
                        <div class="grid grid-cols-2 gap-6 mt-8">
                            <div class="bg-gray-50 p-5 rounded-lg">
                                <div class="text-savino-blue text-2xl font-black">4.000+</div>
                                <div class="text-gray-500 text-sm font-medium mt-1">Posti a Sedere</div>
                            </div>
                            <div class="bg-gray-50 p-5 rounded-lg">
                                <div class="text-savino-blue text-2xl font-black">Serie A1</div>
                                <div class="text-gray-500 text-sm font-medium mt-1">Omologazione</div>
                            </div>
                        </div>
                        <div class="mt-8 flex items-start gap-3 text-gray-600">
                            <svg class="w-5 h-5 text-savino-gold flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <span class="text-sm">Via del Tridente, 5 — 50127 Firenze (FI)</span>
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
                        <p class="text-white/70 text-sm">info@savinodelbenevolley.com</p>
                    </div>
                    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-lg p-8 hover:bg-white/10 transition-all duration-300">
                        <svg class="w-8 h-8 text-savino-gold mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                        <h3 class="text-white font-bold uppercase text-sm tracking-wider mb-2">Telefono</h3>
                        <p class="text-white/70 text-sm">+39 055 XXX XXXX</p>
                    </div>
                    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-lg p-8 hover:bg-white/10 transition-all duration-300">
                        <svg class="w-8 h-8 text-savino-gold mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <h3 class="text-white font-bold uppercase text-sm tracking-wider mb-2">Sede</h3>
                        <p class="text-white/70 text-sm">Scandicci (FI), Toscana</p>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
