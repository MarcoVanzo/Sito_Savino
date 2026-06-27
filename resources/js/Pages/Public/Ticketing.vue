<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useSanitize } from '@/Composables/useSanitize'

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    }
})

const { sanitize } = useSanitize()
const safeContent = computed(() => sanitize(props.page?.content))

const plans = [
    {
        name: 'Singola Partita',
        price: '15',
        period: 'a partita',
        features: [
            'Accesso al Palazzo Wanny',
            'Posto in tribuna laterale',
            'Acquisto online o in cassa',
        ],
        highlight: false,
        cta: 'Acquista Biglietto'
    },
    {
        name: 'Abbonamento Gold',
        price: '199',
        period: 'stagione',
        features: [
            'Tutte le partite casalinghe',
            'Posto numerato in tribuna centrale',
            'Accesso prioritario al palazzetto',
            'Sconto 10% su merchandising',
            'Meet & Greet con le atlete',
        ],
        highlight: true,
        cta: 'Sottoscrivi Ora'
    },
    {
        name: 'Abbonamento Base',
        price: '99',
        period: 'stagione',
        features: [
            'Tutte le partite casalinghe',
            'Posto in tribuna laterale',
            'Ingresso dedicato',
        ],
        highlight: false,
        cta: 'Sottoscrivi Ora'
    }
]
</script>

<template>
    <Head>
      <title>{{ page?.title ?? 'Biglietteria' }}</title>
    </Head>

    <PublicLayout>
        <!-- Hero -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Vivi l'Emozione</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title ?? 'Biglietteria' }}</h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">Scegli il tuo posto e vivi l'emozione della pallavolo dal vivo al Palazzo Wanny.</p>
            </div>
        </section>

        <!-- Subscription Plans -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight text-center mb-2">Scegli il tuo Abbonamento</h2>
                <div class="w-16 h-1 bg-savino-gold mx-auto mb-12"></div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div
                        v-for="plan in plans"
                        :key="plan.name"
                        class="relative rounded-2xl overflow-hidden transition-all duration-500 hover:-translate-y-2"
                        :class="plan.highlight
                            ? 'bg-gradient-to-b from-savino-blue to-savino-blue/90 text-white shadow-2xl shadow-savino-blue/30 scale-105 z-10'
                            : 'bg-white text-gray-900 shadow-lg border border-gray-100 hover:shadow-xl'"
                    >
                        <!-- Popular Badge -->
                        <div v-if="plan.highlight" class="absolute top-0 right-0">
                            <div class="bg-savino-gold text-white text-[10px] font-black uppercase tracking-widest px-4 py-1.5 rounded-bl-xl">
                                Più Popolare
                            </div>
                        </div>

                        <div class="p-8">
                            <!-- Plan Name -->
                            <h3
                                class="text-sm font-bold uppercase tracking-wider mb-4"
                                :class="plan.highlight ? 'text-savino-gold' : 'text-savino-gold'"
                               
                            >{{ plan.name }}</h3>

                            <!-- Price -->
                            <div class="flex items-baseline gap-1 mb-6">
                                <span class="text-lg" :class="plan.highlight ? 'text-white/60' : 'text-gray-400'">€</span>
                                <span class="text-5xl font-black">{{ plan.price }}</span>
                                <span class="text-sm" :class="plan.highlight ? 'text-white/60' : 'text-gray-400'">/{{ plan.period }}</span>
                            </div>

                            <!-- Divider -->
                            <div class="w-full h-px mb-6" :class="plan.highlight ? 'bg-white/20' : 'bg-gray-200'"></div>

                            <!-- Features -->
                            <ul class="space-y-3 mb-8">
                                <li
                                    v-for="feature in plan.features"
                                    :key="feature"
                                    class="flex items-start gap-3 text-sm"
                                   
                                >
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" :class="plan.highlight ? 'text-savino-gold' : 'text-savino-blue'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span :class="plan.highlight ? 'text-white/90' : 'text-gray-600'">{{ feature }}</span>
                                </li>
                            </ul>

                            <!-- CTA Button -->
                            <button
                                class="w-full py-3.5 rounded-lg font-bold uppercase tracking-wider text-sm transition-all duration-300"
                                :class="plan.highlight
                                    ? 'bg-savino-gold text-white hover:bg-savino-gold/90 shadow-lg shadow-savino-gold/30'
                                    : 'bg-savino-blue text-white hover:bg-savino-blue/90 shadow-lg shadow-savino-blue/20'"
                               
                            >{{ plan.cta }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Info Acquisto -->
        <section class="py-16 bg-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight mb-2">Informazioni sull'Acquisto</h2>
                <div class="w-12 h-1 bg-savino-gold mb-10"></div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-100">
                        <div class="w-12 h-12 rounded-full bg-savino-blue/10 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Online</h3>
                        <p class="text-gray-500 text-sm leading-relaxed">Acquista i biglietti comodamente online tramite il nostro sistema di ticketing. Pagamento sicuro con carta di credito o PayPal.</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-100">
                        <div class="w-12 h-12 rounded-full bg-savino-gold/10 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Al Botteghino</h3>
                        <p class="text-gray-500 text-sm leading-relaxed">La biglietteria del Palazzo Wanny apre 2 ore prima dell'inizio di ogni partita. Accettiamo contanti e pagamenti elettronici.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Page Content (CMS) -->
        <section v-if="page?.content" class="py-16 bg-gray-50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    class="prose prose-lg max-w-none prose-headings:font-black prose-headings:uppercase prose-headings:tracking-tight prose-a:text-savino-blue"
                   
                    v-html="safeContent"
                ></div>
            </div>
        </section>
    </PublicLayout>
</template>
