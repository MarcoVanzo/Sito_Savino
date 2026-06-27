<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    },
    staff: {
        type: Array,
        default: () => []
    }
})

const placeholderStaff = [
    { id: 1, name: 'Marco Rossi', role: 'Head Coach', bio: 'Oltre 15 anni di esperienza nella pallavolo di alto livello. Ha guidato diverse squadre alla vittoria in campionati nazionali e internazionali.', photo: null, category: 'Tecnico' },
    { id: 2, name: 'Luca Bianchi', role: 'Vice Allenatore', bio: 'Specializzato nella preparazione tattica e nell\'analisi video delle partite avversarie.', photo: null, category: 'Tecnico' },
    { id: 3, name: 'Anna Verdi', role: 'Preparatore Atletico', bio: 'Laureata in Scienze Motorie con specializzazione in performance sportiva. Responsabile della preparazione fisica della squadra.', photo: null, category: 'Tecnico' },
    { id: 4, name: 'Dr. Paolo Neri', role: 'Medico Sportivo', bio: 'Specialista in medicina dello sport con esperienza ventennale nel settore professionistico.', photo: null, category: 'Medico' },
    { id: 5, name: 'Dr.ssa Sara Russo', role: 'Fisioterapista', bio: 'Esperta in riabilitazione sportiva e prevenzione degli infortuni. Si occupa del recupero post-infortunio delle atlete.', photo: null, category: 'Medico' },
    { id: 6, name: 'Fabio Esposito', role: 'Scout Man', bio: 'Responsabile dell\'analisi statistica e dello scouting delle squadre avversarie tramite software avanzato.', photo: null, category: 'Tecnico' },
    { id: 7, name: 'Giulia Martini', role: 'Team Manager', bio: 'Coordina la logistica e l\'organizzazione della squadra, gestendo trasferte e comunicazioni interne.', photo: null, category: 'Staff' },
    { id: 8, name: 'Roberto Conti', role: 'Preparatore Portieri', bio: 'Allenatore specializzato nel ruolo del libero, con attenzione ai fondamentali difensivi.', photo: null, category: 'Tecnico' },
]

const displayStaff = props.staff.length > 0 ? props.staff : placeholderStaff

function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase()
}

function getCategoryColor(category) {
    switch (category) {
        case 'Tecnico': return 'bg-savino-blue text-white'
        case 'Medico': return 'bg-savino-red text-white'
        case 'Staff': return 'bg-savino-gold text-white'
        default: return 'bg-gray-500 text-white'
    }
}
</script>

<template>
    <Head>
      <title>{{ page?.title ?? 'Staff Tecnico e Medico' }}</title>
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

        <!-- Staff Grid -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    <div
                        v-for="member in displayStaff"
                        :key="member.id"
                        class="group bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-500 overflow-hidden border border-gray-100 hover:-translate-y-1"
                    >
                        <!-- Photo / Avatar -->
                        <div class="relative h-56 bg-gradient-to-br from-savino-blue to-savino-blue/70 flex items-center justify-center overflow-hidden">
                            <img
                                v-if="member.photo"
                                :src="member.photo"
                                :alt="member.name"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                            />
                            <span v-else class="text-5xl font-black text-white/30">{{ getInitials(member.name) }}</span>
                            <div class="absolute top-3 right-3">
                                <span
                                    class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                    :class="getCategoryColor(member.category)"
                                   
                                >{{ member.category }}</span>
                            </div>
                        </div>
                        <!-- Info -->
                        <div class="p-5">
                            <h3 class="text-lg font-black text-gray-900 uppercase tracking-tight">{{ member.name }}</h3>
                            <p class="text-savino-gold text-sm font-bold mt-1">{{ member.role }}</p>
                            <div class="w-8 h-0.5 bg-savino-gold mt-3 mb-3"></div>
                            <p class="text-gray-500 text-sm leading-relaxed">{{ member.bio }}</p>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="displayStaff.length === 0" class="text-center py-20">
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
