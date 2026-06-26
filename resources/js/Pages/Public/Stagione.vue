<script setup>
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    roster: Array
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
        <title>{{ page?.meta_title || page?.title || 'Savino Del Bene Volley' }}</title>
        <meta v-if="page?.meta_description" name="description" :content="page.meta_description" />
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
                        <span class="text-3xl text-[#ED028C] font-sans uppercase tracking-widest mt-4 block">Stagione 2026/2027</span>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    <div 
                        v-for="item in filteredRoster" 
                        :key="item.id"
                        class="bg-white rounded-lg overflow-hidden shadow-[0_4px_15px_rgba(0,0,0,0.05)] border border-[#eee]"
                    >
                        <!-- Immagine di Copertina -->
                        <div class="h-[250px] relative">
                            <img 
                                v-if="item.official_photo_url" 
                                :src="'/storage/' + item.official_photo_url" 
                                :alt="item.player.first_name + ' ' + item.player.last_name"
                                class="w-full h-full object-cover object-center"
                            />
                            
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

            </div>
        </div>
    </PublicLayout>
</template>
