<script setup>
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHero from '@/Components/PageHero.vue';
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
    <Head title="Roster Serie A1">
        <meta name="description" content="La rosa ufficiale della Savino Del Bene Volley per la stagione in corso. Scopri le atlete del roster Serie A1." />
    </Head>
    <PublicLayout>
        <!-- Hero -->
        <PageHero
            subtitle="La Nostra Squadra"
            :title="'Roster Serie A1' + (seasonName ? ' — ' + seasonName : '')"
            description="Scopri le atlete della Savino Del Bene Volley per la stagione in corso."
        />

        <!-- Roster Content -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Filtri Ruolo -->
                <div class="flex flex-wrap justify-center gap-3 mb-12">
                    <button
                        v-for="role in roles"
                        :key="role"
                        @click="selectedRole = role"
                        class="px-5 py-2 rounded-full text-sm font-bold uppercase tracking-wider transition-all duration-300"
                        :class="selectedRole === role
                            ? 'bg-savino-blue text-white shadow-lg shadow-savino-blue/30'
                            : 'bg-white text-gray-600 hover:bg-savino-blue/10 hover:text-savino-blue border border-gray-200'"
                    >
                        {{ role }}
                    </button>
                </div>

                <!-- Griglia Atlete -->
                <div v-if="filteredRoster.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div
                        v-for="item in filteredRoster"
                        :key="item.id"
                        class="group bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-xl border border-gray-100 transition-all duration-500 hover:-translate-y-1"
                    >
                        <!-- Immagine Atleta -->
                        <div class="h-64 relative bg-gray-100 overflow-hidden">
                            <img
                                v-if="item.official_photo_url"
                                :src="'/storage/' + item.official_photo_url"
                                :alt="item.player.first_name + ' ' + item.player.last_name"
                                class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700"
                                loading="lazy"
                            />
                            <!-- Fallback -->
                            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-b from-gray-100 to-gray-200">
                                <svg class="w-20 h-20 text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            </div>

                            <!-- Badge Numero Maglia -->
                            <div class="absolute top-4 left-4 bg-savino-blue text-white w-10 h-10 flex items-center justify-center rounded-full font-bold text-lg shadow-lg">
                                {{ item.jersey_number || '–' }}
                            </div>
                        </div>

                        <!-- Dati Atleta -->
                        <div class="p-5">
                            <div class="text-xs font-bold text-savino-gold uppercase tracking-wider mb-1">
                                {{ item.role }}
                            </div>
                            <h3 class="text-xl text-savino-blue font-black tracking-tight mb-3">
                                {{ item.player.first_name }} {{ item.player.last_name }}
                            </h3>

                            <div class="flex gap-4 text-sm text-gray-500">
                                <div v-if="item.player.nationality"><strong>Naz:</strong> {{ item.player.nationality }}</div>
                                <div v-if="item.height_cm"><strong>Alt:</strong> {{ item.height_cm }} cm</div>
                                <div v-if="item.player.date_of_birth"><strong>Anno:</strong> {{ new Date(item.player.date_of_birth).getFullYear() }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="text-center py-20">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-savino-blue/10 flex items-center justify-center">
                        <svg class="w-12 h-12 text-savino-blue/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                    <p class="text-gray-500 text-lg font-semibold">Nessuna atleta trovata per il ruolo selezionato.</p>
                    <button @click="selectedRole = 'Tutti'" class="mt-4 px-6 py-2.5 bg-savino-gold text-white text-sm font-bold uppercase tracking-wider rounded-lg hover:bg-savino-gold/90 transition-colors">
                        Mostra tutte
                    </button>
                </div>

            </div>
        </section>
    </PublicLayout>
</template>
