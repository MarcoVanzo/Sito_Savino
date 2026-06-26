<script setup>
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    roster: {
        type: Array,
        default: () => [],
    },
    seasonName: {
        type: String,
        default: '',
    },
});

const selectedRole = ref('Tutti');
const roles = ['Tutti', 'Palleggiatrice', 'Schiacciatrice', 'Centrale', 'Opposta', 'Libero'];

const filteredRoster = computed(() => {
    if (selectedRole.value === 'Tutti') {
        return props.roster;
    }
    return props.roster.filter(item => item.role === selectedRole.value);
});
</script>

<template>
        <Head>
        <title>Roster Serie A1 — Savino Del Bene Volley</title>
        <meta name="description" content="La rosa ufficiale della Savino Del Bene Volley per la stagione in corso. Scopri le atlete del roster Serie A1." />
    </Head>
    <PublicLayout>
        <div class="bg-savino-blue min-h-screen py-16">
            <!-- Copertina Squadra -->
            <div class="w-full h-[400px] bg-gray-900 relative">
                <!-- Se l'immagine non c'è, usiamo un fallback generico o un colore piatto -->
                <div class="absolute inset-0 bg-savino-blue/50 z-10"></div>
                <!-- Visto che non ho l'immagine della squadra, metto un placeholder coerente con i mockup (Ognjenovic/Esultanza) -->
                <div class="w-full h-full object-cover bg-[url('/storage/images/roster/team_cover.jpg')] bg-center bg-cover"></div>
                <div class="absolute inset-0 flex items-center justify-center z-20">
                    <h1 class="font-serif text-5xl md:text-7xl font-bold text-white tracking-wide shadow-black drop-shadow-2xl text-center px-4">
                        Roster Serie A1 <br/>
                        <span class="text-3xl text-[#ED028C] font-sans uppercase tracking-widest mt-4 block">Stagione {{ seasonName || '' }}</span>
                    </h1>
                </div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
                
                <!-- Filtri Ruolo -->
                <div class="flex flex-wrap justify-center gap-2 mb-12">
                    <button 
                        v-for="role in roles" 
                        :key="role"
                        @click="selectedRole = role"
                        class="px-5 py-2 rounded-full text-[13px] font-bold uppercase transition-colors"
                        :class="selectedRole === role ? 'bg-[#ED028C] text-white' : 'bg-white text-savino-blue border border-[#eee] hover:border-[#ED028C] hover:text-[#ED028C]'"
                    >
                        {{ role }}
                    </button>
                </div>

                <!-- Griglia Atlete -->
                <div v-if="filteredRoster.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    <div 
                        v-for="item in filteredRoster" 
                        :key="item.id"
                        class="bg-white rounded-lg overflow-hidden shadow-[0_4px_15px_rgba(0,0,0,0.05)] border border-[#eee]"
                    >
                        <!-- Immagine di Copertina -->
                        <div class="h-[250px] relative bg-gray-100">
                            <img 
                                v-if="item.official_photo_url" 
                                :src="'/storage/' + item.official_photo_url" 
                                :alt="item.player.first_name + ' ' + item.player.last_name"
                                class="w-full h-full object-cover object-center"
                                loading="lazy"
                            />
                            <!-- Fallback per atlete senza foto -->
                            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-b from-gray-100 to-gray-200">
                                <svg class="w-20 h-20 text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            </div>
                            
                            <!-- Badge Numero di Maglia -->
                            <div class="absolute top-[15px] left-[15px] bg-savino-blue text-white w-[40px] h-[40px] flex items-center justify-center rounded-full font-bold text-[18px]">
                                {{ item.jersey_number || 'N°' }}
                            </div>
                        </div>

                        <!-- Dati Atleta -->
                        <div class="p-5">
                            <div class="text-[12px] font-semibold text-[#ED028C] uppercase tracking-[1px] mb-[5px]">
                                {{ item.role }}
                            </div>
                            <h3 class="m-0 mb-[10px] text-[22px] text-savino-blue font-bold">
                                {{ item.player.first_name }} {{ item.player.last_name }}
                            </h3>
                            
                            <div class="flex gap-[15px] text-[14px] text-gray-500">
                                <div v-if="item.player.nationality"><strong>Naz:</strong> {{ item.player.nationality }}</div>
                                <div v-if="item.height_cm"><strong>Alt:</strong> {{ item.height_cm }} cm</div>
                                <div v-if="item.player.date_of_birth"><strong>Anno:</strong> {{ new Date(item.player.date_of_birth).getFullYear() }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="text-center py-20">
                    <svg class="w-16 h-16 text-white/30 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <p class="text-white/70 text-lg font-semibold">Nessuna atleta trovata per il ruolo selezionato.</p>
                    <button @click="selectedRole = 'Tutti'" class="mt-4 px-6 py-2 bg-[#ED028C] text-white text-sm font-bold uppercase rounded-full hover:bg-[#ff30a6] transition-colors">
                        Mostra tutte
                    </button>
                </div>

            </div>
        </div>
    </PublicLayout>
</template>
