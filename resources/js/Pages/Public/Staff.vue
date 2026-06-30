<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { useImageFallback } from '@/Composables/useImageFallback.js'

const { onImgError } = useImageFallback()

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    },
    staffTecnico: {
        type: Array,
        default: () => []
    },
    staffMedico: {
        type: Array,
        default: () => []
    }
})

function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase()
}

</script>

<template>
    <Head>
      <title>{{ (page?.title ?? 'Staff Tecnico e Medico') + ' — Savino Del Bene Volley' }}</title>
      <meta name="description" content="Lo staff tecnico e medico della Savino Del Bene Volley. Allenatori, preparatori e team di supporto." />
      <meta property="og:title" :content="(page?.title ?? 'Staff Tecnico e Medico') + ' — Savino Del Bene Volley'" />
      <meta property="og:description" content="Lo staff tecnico e medico della Savino Del Bene Volley. Allenatori, preparatori e team di supporto." />
      <meta property="og:image" :content="'/images/logo.png'" />
      <meta property="og:url" :content="$page.props.ziggy?.location || ''" />
      <meta property="og:type" content="website" />
    </Head>

    <PublicLayout>
        <!-- Hero -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Il Nostro Team</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title ?? 'Staff Tecnico e Medico' }}</h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">I professionisti che guidano e supportano le nostre atlete ogni giorno.</p>
            </div>
        </section>

        <!-- Staff Sections -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-20">
                
                <!-- Staff Tecnico -->
                <div v-if="staffTecnico.length > 0">
                    <h2 class="text-3xl font-black text-savino-blue uppercase tracking-tight mb-8">Staff Tecnico</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                        <div
                            v-for="member in staffTecnico"
                            :key="member.id"
                            class="group bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-500 overflow-hidden border border-gray-100 hover:-translate-y-1"
                        >
                            <div class="relative h-56 bg-gradient-to-br from-savino-blue to-savino-blue/70 flex items-center justify-center overflow-hidden">
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
                </div>

                <!-- Staff Medico -->
                <div v-if="staffMedico.length > 0">
                    <h2 class="text-3xl font-black text-savino-red uppercase tracking-tight mb-8">Staff Medico</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                        <div
                            v-for="member in staffMedico"
                            :key="member.id"
                            class="group bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-500 overflow-hidden border border-gray-100 hover:-translate-y-1"
                        >
                            <div class="relative h-56 bg-gradient-to-br from-savino-red to-savino-red/70 flex items-center justify-center overflow-hidden">
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
                                <p class="text-savino-red text-sm font-bold mt-1">{{ member.role }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="staffTecnico.length === 0 && staffMedico.length === 0" class="text-center py-20">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-savino-blue/10 flex items-center justify-center">
                        <svg class="w-12 h-12 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 uppercase mb-3">Staff in aggiornamento</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Le informazioni sullo staff saranno disponibili a breve.</p>
                </div>

            </div>
        </section>
    </PublicLayout>
</template>
