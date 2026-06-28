<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useSanitize } from '@/Composables/useSanitize'

defineOptions({ layout: PublicLayout })

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    }
})

const { sanitize } = useSanitize()
const safeContent = computed(() => sanitize(props.page?.content))

const pressKitItems = [
    {
        icon: '📸',
        title: 'Foto Ufficiali',
        description: 'Immagini ad alta risoluzione della squadra, dello staff e del palazzetto.',
        format: 'ZIP — 45 MB'
    },
    {
        icon: '🎨',
        title: 'Logo e Brand Kit',
        description: 'Loghi in tutti i formati, palette colori, font e linee guida del brand.',
        format: 'ZIP — 12 MB'
    },
    {
        icon: '📄',
        title: 'Cartella Stampa',
        description: 'Comunicati stampa, schede tecniche e profili delle atlete.',
        format: 'PDF — 8 MB'
    },
    {
        icon: '📊',
        title: 'Statistiche Stagionali',
        description: 'Dati e statistiche aggiornate della stagione in corso.',
        format: 'PDF — 3 MB'
    }
]

const contacts = [
    {
        role: 'Ufficio Stampa',
        name: 'Responsabile Comunicazione',
        email: 'stampa@savinodelbenevolley.it',
        phone: '+39 055 000 0000'
    },
    {
        role: 'Social Media',
        name: 'Social Media Manager',
        email: 'social@savinodelbenevolley.it',
        phone: '+39 055 000 0001'
    },
    {
        role: 'Accrediti & Media',
        name: 'Coordinatore Media',
        email: 'media@savinodelbenevolley.it',
        phone: '+39 055 000 0002'
    }
]
</script>

<template>
    <Head>
      <title>{{ (page?.title ?? 'Comunicazione') + ' — Savino Del Bene Volley' }}</title>
      <meta name="description" content="Area comunicazione della Savino Del Bene Volley. Comunicati stampa, media kit e contatti per la stampa." />
      <meta property="og:title" :content="(page?.title ?? 'Comunicazione') + ' — Savino Del Bene Volley'" />
      <meta property="og:description" content="Area comunicazione della Savino Del Bene Volley. Comunicati stampa, media kit e contatti per la stampa." />
      <meta property="og:image" :content="'/images/logo.png'" />
      <meta property="og:url" :content="$page.props.ziggy?.location || ''" />
      <meta property="og:type" content="website" />
    </Head>

    <!-- Hero -->
    <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
        <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
            <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Area Stampa</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                {{ page?.title ?? 'Comunicazione' }}
            </h1>
            <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
            <p class="text-white/70 text-lg max-w-2xl mx-auto">
                Risorse, contatti e materiali per giornalisti e operatori media.
            </p>
        </div>
    </section>

    <!-- Press Accreditation -->
    <section class="py-20 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">Accrediti</span>
                    <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                        Accreditamento Stampa
                    </h2>
                    <div class="w-12 h-1 bg-savino-gold mt-4 mb-6"></div>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Giornalisti, fotografi e operatori video possono richiedere l'accreditamento per le partite casalinghe
                        e gli eventi organizzati dalla Savino Del Bene Volley.
                    </p>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        L'accreditamento consente l'accesso alla tribuna stampa, alla zona mista post-partita
                        e alle conferenze stampa pre e post gara.
                    </p>
                    <div class="bg-savino-blue/5 rounded-xl p-6 border border-savino-blue/10">
                        <h4 class="font-bold text-gray-900 mb-3">Come richiedere l'accredito:</h4>
                        <ol class="space-y-2 text-gray-600 text-sm">
                            <li class="flex items-start gap-2">
                                <span class="text-savino-gold font-bold">1.</span>
                                Inviare una mail a <strong>media@savinodelbenevolley.it</strong>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-savino-gold font-bold">2.</span>
                                Indicare testata, nome del giornalista e tipo di accredito richiesto
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-savino-gold font-bold">3.</span>
                                Inviare la richiesta almeno 48 ore prima dell'evento
                            </li>
                        </ol>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-savino-blue/10 to-savino-gold/10 rounded-2xl p-8 flex items-center justify-center min-h-[300px]">
                    <div class="text-center">
                        <span class="text-6xl">🎤</span>
                        <p class="text-savino-blue font-bold mt-4 text-lg">Media Hub</p>
                        <p class="text-gray-500 text-sm mt-1">Tutto ciò di cui hai bisogno</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Press Kit Downloads -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">Download</span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                    Press Kit
                </h2>
                <div class="w-12 h-1 bg-savino-gold mx-auto mt-4"></div>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div
                    v-for="(item, index) in pressKitItems"
                    :key="index"
                    class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1 border border-gray-100 group cursor-pointer"
                >
                    <span class="text-4xl block mb-4">{{ item.icon }}</span>
                    <h3 class="text-base font-bold text-gray-900 mb-2 group-hover:text-savino-blue transition-colors">
                        {{ item.title }}
                    </h3>
                    <p class="text-gray-600 text-sm leading-relaxed mb-4">
                        {{ item.description }}
                    </p>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <span class="text-xs text-gray-400">{{ item.format }}</span>
                        <svg class="w-5 h-5 text-savino-blue group-hover:translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Press Contacts -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">Contatti</span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                    Ufficio Comunicazione
                </h2>
                <div class="w-12 h-1 bg-savino-gold mx-auto mt-4"></div>
            </div>
            <div class="space-y-6">
                <div
                    v-for="(contact, index) in contacts"
                    :key="index"
                    class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-gray-50 rounded-xl p-6 border border-gray-100"
                >
                    <div class="mb-3 sm:mb-0">
                        <span class="text-xs font-bold text-savino-gold uppercase tracking-wider">{{ contact.role }}</span>
                        <h4 class="font-bold text-gray-900 mt-1">{{ contact.name }}</h4>
                    </div>
                    <div class="flex flex-col sm:items-end gap-1">
                        <a :href="'mailto:' + contact.email" class="text-savino-blue text-sm hover:underline">
                            {{ contact.email }}
                        </a>
                        <span class="text-gray-500 text-sm">{{ contact.phone }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Dynamic Content -->
    <section v-if="page?.content" class="py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg max-w-none" v-html="safeContent"></div>
        </div>
    </section>
</template>
