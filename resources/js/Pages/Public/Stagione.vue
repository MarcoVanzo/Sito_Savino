<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    roster: Array
});
</script>

<template>
    <Head title="Roster Serie A1" />
    <PublicLayout>
        <div class="bg-savino-blue min-h-screen py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Intestazione della Pagina -->
                <div class="text-center mb-16">
                    <h1 class="font-serif text-4xl md:text-5xl font-bold text-white mb-4">Roster Serie A1</h1>
                    <p class="text-gray-300 max-w-2xl mx-auto">
                        La squadra della Savino Del Bene Volley per la stagione 2026/2027.
                    </p>
                </div>

                <!-- Griglia Atlete -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    <div 
                        v-for="item in roster" 
                        :key="item.id"
                        class="group relative bg-white rounded-xl overflow-hidden shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-[#ED028C]/30 border border-transparent hover:border-[#ED028C]/50"
                    >
                        <!-- Immagine di Copertina -->
                        <div class="h-[350px] bg-gray-200 relative overflow-hidden">
                            <img 
                                v-if="item.official_photo_url" 
                                :src="'/images/roster/' + item.official_photo_url" 
                                :alt="item.player.first_name + ' ' + item.player.last_name"
                                class="w-full h-full object-cover object-top transition-transform duration-500 group-hover:scale-105"
                            />
                            
                            <!-- Badge Numero di Maglia -->
                            <div class="absolute top-4 left-4 bg-savino-blue text-white w-12 h-12 flex items-center justify-center rounded-full font-bold text-xl shadow-lg border-2 border-white/20">
                                {{ item.jersey_number }}
                            </div>
                            
                            <!-- Badge Capitana -->
                            <div v-if="item.is_captain" class="absolute top-4 right-4 bg-[#C9A84C] text-white px-3 py-1 text-xs font-bold uppercase rounded shadow-lg">
                                Capitana
                            </div>
                        </div>

                        <!-- Dati Atleta (Glassmorphism effect in the bottom part, or simple white card) -->
                        <div class="p-5 bg-white relative z-10">
                            <div class="text-[#ED028C] text-xs font-bold uppercase tracking-wider mb-1">
                                {{ item.role }}
                            </div>
                            <h3 class="font-serif text-2xl font-bold text-savino-blue mb-3">
                                {{ item.player.first_name }} <br/> 
                                <span class="uppercase tracking-tight">{{ item.player.last_name }}</span>
                            </h3>
                            
                            <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-500 mb-4">
                                <div v-if="item.player.nationality" class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    {{ item.player.nationality }}
                                </div>
                                <div v-if="item.height_cm" class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    {{ item.player.date_of_birth ? new Date(item.player.date_of_birth).getFullYear() : 'N/D' }}
                                </div>
                                <div v-if="item.height_cm" class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                                    {{ item.height_cm }} cm
                                </div>
                            </div>
                            
                            <!-- Quick Stats if available -->
                            <div v-if="item.player.stats && item.player.stats.length > 0" class="pt-4 mt-2 border-t border-gray-100 grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <div class="text-xs text-gray-400 uppercase">Punti</div>
                                    <div class="font-bold text-savino-blue">{{ item.player.stats[0].points }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400 uppercase">Muri</div>
                                    <div class="font-bold text-savino-blue">{{ item.player.stats[0].blocks }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400 uppercase">Ace</div>
                                    <div class="font-bold text-savino-blue">{{ item.player.stats[0].aces }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </PublicLayout>
</template>
