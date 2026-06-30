<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { useImageFallback } from '@/Composables/useImageFallback.js'

const { onImgError } = useImageFallback()

const props = defineProps({
    dirigenza: {
        type: Array,
        default: () => []
    },
    page: {
        type: Object,
        default: null
    }
})

function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase()
}
</script>

<template>
    <Head>
      <title>{{ page?.title || 'Organigramma' }} — Savino Del Bene Volley</title>
      <meta name="description" :content="page?.meta_description || 'L\'organigramma e la dirigenza della Savino Del Bene Volley.'" />
      <meta property="og:title" :content="(page?.title || 'Organigramma') + ' — Savino Del Bene Volley'" />
      <meta property="og:description" :content="page?.meta_description || 'L\'organigramma e la dirigenza della Savino Del Bene Volley.'" />
      <meta property="og:image" :content="'/images/logo.png'" />
      <meta property="og:url" :content="$page.props.ziggy?.location || ''" />
      <meta property="og:type" content="website" />
    </Head>

    <PublicLayout>
        <!-- Hero -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">La Società</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title || 'Organigramma' }}</h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                
                <div v-if="page?.content" class="prose prose-lg prose-invert max-w-3xl mx-auto" v-html="page.content"></div>
                <p v-else class="text-white/70 text-lg max-w-2xl mx-auto">La struttura dirigenziale e organizzativa della nostra società.</p>
            </div>
        </section>

        <!-- Dirigenza Grid -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div v-if="dirigenza.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    <div
                        v-for="member in dirigenza"
                        :key="member.id"
                        class="group bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-500 overflow-hidden border border-gray-100 hover:-translate-y-1"
                    >
                        <div class="relative h-56 bg-gradient-to-br from-gray-700 to-gray-900 flex items-center justify-center overflow-hidden">
                            <img
                                v-if="member.photo_url"
                                :src="member.photo_url"
                                :alt="member.name"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                @error="onImgError"
                            />
                            <span v-else class="text-5xl font-black text-white/30">{{ getInitials(member.name) }}</span>
                        </div>
                        <div class="p-5 text-center">
                            <h3 class="text-lg font-black text-gray-900 uppercase tracking-tight">{{ member.name }}</h3>
                            <p class="text-savino-gold text-sm font-bold mt-1">{{ member.role }}</p>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="dirigenza.length === 0" class="text-center py-20">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-savino-blue/10 flex items-center justify-center">
                        <svg class="w-12 h-12 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 uppercase mb-3">Organigramma in aggiornamento</h3>
                    <p class="text-gray-500 max-w-md mx-auto">La struttura societaria sarà visibile a breve.</p>
                </div>

            </div>
        </section>
    </PublicLayout>
</template>
