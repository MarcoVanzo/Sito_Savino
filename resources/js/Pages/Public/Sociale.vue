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

const projects = [
    {
        icon: '🏐',
        title: 'Volley4All',
        color: 'savino-blue',
        description: 'Pallavolo gratuita per ragazzi provenienti da famiglie in difficoltà economica. Lo sport come diritto, non come privilegio.',
        tag: 'Inclusione Sportiva'
    },
    {
        icon: '📚',
        title: 'Scuola & Sport',
        color: 'savino-gold',
        description: 'Programma di doposcuola che unisce supporto scolastico e attività sportiva, promuovendo la crescita a 360°.',
        tag: 'Educazione'
    },
    {
        icon: '♿',
        title: 'Inclusione',
        color: 'savino-red',
        description: 'Attività di sitting volley e progetti dedicati ad atleti con disabilità. Abbattere le barriere attraverso lo sport.',
        tag: 'Accessibilità'
    },
    {
        icon: '🌱',
        title: 'Sostenibilità',
        color: 'savino-blue',
        description: 'Iniziative green per ridurre l\'impatto ambientale degli eventi sportivi e sensibilizzare la community.',
        tag: 'Ambiente'
    }
]

const impactNumbers = [
    { value: '500+', label: 'Ragazzi Coinvolti' },
    { value: '12', label: 'Scuole Partner' },
    { value: '30+', label: 'Eventi Sociali' },
    { value: '€50K', label: 'Fondi Raccolti' }
]
</script>

<template>
    <Head :title="page?.title ?? 'Progetti Sociali'" />

    <!-- Hero -->
    <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
        <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
            <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]" style="font-family: 'Montserrat', sans-serif;">Sport e Società</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4" style="font-family: 'Montserrat', sans-serif;">
                {{ page?.title ?? 'Progetti Sociali' }}
            </h1>
            <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
            <p class="text-white/70 text-lg max-w-2xl mx-auto" style="font-family: 'Montserrat', sans-serif;">
                Il nostro impegno per la comunità va oltre il campo da gioco.
            </p>
        </div>
    </section>

    <!-- Mission -->
    <section class="py-20 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]" style="font-family: 'Montserrat', sans-serif;">La Nostra Missione</span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2" style="font-family: 'Montserrat', sans-serif;">
                    Sport Come Strumento Sociale
                </h2>
                <div class="w-12 h-1 bg-savino-gold mx-auto mt-4 mb-8"></div>
                <p class="text-gray-600 text-lg leading-relaxed" style="font-family: 'Montserrat', sans-serif;">
                    La Savino Del Bene crede fermamente nel potere trasformativo dello sport.
                    Attraverso i nostri progetti sociali, lavoriamo ogni giorno per costruire una comunità
                    più inclusiva, sostenibile e attenta ai bisogni di tutti.
                </p>
                <p class="text-gray-600 text-lg leading-relaxed mt-4" style="font-family: 'Montserrat', sans-serif;">
                    Dalla pallavolo per tutti ai programmi educativi, dal sitting volley alle iniziative
                    ambientali: ogni progetto è un passo verso un futuro migliore.
                </p>
            </div>
        </div>
    </section>

    <!-- Projects Grid -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]" style="font-family: 'Montserrat', sans-serif;">Iniziative</span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2" style="font-family: 'Montserrat', sans-serif;">
                    I Nostri Progetti
                </h2>
                <div class="w-12 h-1 bg-savino-gold mx-auto mt-4"></div>
            </div>
            <div class="grid md:grid-cols-2 gap-8">
                <div
                    v-for="(project, index) in projects"
                    :key="index"
                    class="bg-white rounded-2xl p-8 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-100 group"
                >
                    <div class="flex items-start justify-between mb-6">
                        <span class="text-5xl">{{ project.icon }}</span>
                        <span
                            class="text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full"
                            :class="{
                                'bg-savino-blue/10 text-savino-blue': project.color === 'savino-blue',
                                'bg-savino-gold/10 text-savino-gold': project.color === 'savino-gold',
                                'bg-savino-red/10 text-savino-red': project.color === 'savino-red'
                            }"
                            style="font-family: 'Montserrat', sans-serif;"
                        >
                            {{ project.tag }}
                        </span>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight mb-3 group-hover:text-savino-blue transition-colors" style="font-family: 'Montserrat', sans-serif;">
                        {{ project.title }}
                    </h3>
                    <p class="text-gray-600 leading-relaxed" style="font-family: 'Montserrat', sans-serif;">
                        {{ project.description }}
                    </p>
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <a href="#" class="inline-flex items-center gap-2 text-savino-blue text-sm font-bold uppercase tracking-wider hover:text-savino-gold transition-colors" style="font-family: 'Montserrat', sans-serif;">
                            Scopri di più
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Impact Numbers -->
    <section class="py-20 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]" style="font-family: 'Montserrat', sans-serif;">Risultati</span>
                <h2 class="text-3xl md:text-4xl font-black text-white uppercase tracking-tight mt-2" style="font-family: 'Montserrat', sans-serif;">
                    Il Nostro Impatto
                </h2>
                <div class="w-12 h-1 bg-savino-gold mx-auto mt-4"></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div
                    v-for="(stat, index) in impactNumbers"
                    :key="index"
                    class="text-center"
                >
                    <div class="text-4xl md:text-5xl font-black text-savino-gold mb-2" style="font-family: 'Montserrat', sans-serif;">
                        {{ stat.value }}
                    </div>
                    <div class="text-white/70 text-sm font-medium uppercase tracking-wider" style="font-family: 'Montserrat', sans-serif;">
                        {{ stat.label }}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Dynamic Content -->
    <section v-if="page?.content" class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg max-w-none" style="font-family: 'Montserrat', sans-serif;" v-html="safeContent"></div>
        </div>
    </section>
</template>
