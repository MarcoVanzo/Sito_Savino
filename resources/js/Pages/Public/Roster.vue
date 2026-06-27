<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    page: Object,
    players: {
        type: Array,
        default: () => [],
    },
});

const placeholderPlayers = [
    { id: 1, first_name: 'Atleta', last_name: '1', number: 1, role: 'Palleggiatrice' },
    { id: 2, first_name: 'Atleta', last_name: '2', number: 7, role: 'Schiacciatrice' },
    { id: 3, first_name: 'Atleta', last_name: '3', number: 10, role: 'Centrale' },
    { id: 4, first_name: 'Atleta', last_name: '4', number: 4, role: 'Opposta' },
    { id: 5, first_name: 'Atleta', last_name: '5', number: 14, role: 'Libero' },
    { id: 6, first_name: 'Atleta', last_name: '6', number: 9, role: 'Schiacciatrice' },
    { id: 7, first_name: 'Atleta', last_name: '7', number: 3, role: 'Centrale' },
    { id: 8, first_name: 'Atleta', last_name: '8', number: 11, role: 'Palleggiatrice' },
];

const displayPlayers = computed(() => props.players.length > 0 ? props.players : placeholderPlayers);
const isPlaceholder = computed(() => props.players.length === 0);

const roleColors = {
    'Palleggiatrice': 'bg-savino-gold',
    'Schiacciatrice': 'bg-savino-red',
    'Centrale': 'bg-savino-blue',
    'Opposta': 'bg-purple-600',
    'Libero': 'bg-emerald-600',
};
</script>

<template>
    <Head>
        <title>{{ page?.title ?? 'Roster' }} — Savino Del Bene Volley</title>
        <meta v-if="page?.meta_description" name="description" :content="page.meta_description" />
        <meta v-else name="description" content="La rosa ufficiale della Savino Del Bene Volley. Scopri le atlete che compongono il roster per la stagione corrente." />
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.04]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; viewBox=&quot;0 0 40 40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Ccircle cx=&quot;20&quot; cy=&quot;20&quot; r=&quot;8&quot; fill=&quot;%23C5A55A&quot;/%3E%3C/svg%3E'); background-size: 40px 40px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Stagione 2025/26</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ page?.title ?? 'Il Nostro Roster' }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">
                    Le atlete che difendono i colori della Savino Del Bene Volley in Serie A1 e nelle competizioni europee.
                </p>
            </div>
        </section>

        <!-- ROSTER GRID -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-7xl mx-auto">
                <!-- Placeholder notice -->
                <div v-if="isPlaceholder" class="text-center mb-12">
                    <div class="inline-flex items-center gap-2 bg-savino-gold/10 border border-savino-gold/30 rounded-full px-6 py-3">
                        <svg class="w-5 h-5 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="text-savino-blue text-sm font-semibold">Roster in fase di aggiornamento — Dati di anteprima</span>
                    </div>
                </div>

                <!-- Player Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div
                        v-for="player in displayPlayers"
                        :key="player.id"
                        class="group relative bg-white rounded-lg overflow-hidden shadow-md hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-1"
                    >
                        <!-- Player Photo Area -->
                        <div class="relative h-72 bg-gradient-to-b from-gray-100 to-gray-200 overflow-hidden">
                            <img
                                v-if="player.photo_url"
                                :src="player.photo_url"
                                :alt="player.first_name + ' ' + player.last_name"
                                class="w-full h-full object-cover object-top"
                                loading="lazy"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <svg class="w-24 h-24 text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            </div>

                            <!-- Dark gradient overlay on hover -->
                            <div class="absolute inset-0 bg-gradient-to-t from-savino-blue/90 via-savino-blue/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                            <!-- Jersey Number Badge -->
                            <div class="absolute top-4 left-4 w-10 h-10 rounded-full bg-savino-blue text-white flex items-center justify-center font-black text-lg shadow-lg">
                                {{ player.number ?? '#' }}
                            </div>

                            <!-- Role Badge -->
                            <div class="absolute top-4 right-4">
                                <span :class="roleColors[player.role] ?? 'bg-gray-600'" class="text-white text-[10px] font-bold uppercase tracking-wider px-3 py-1 rounded-full">
                                    {{ player.role }}
                                </span>
                            </div>

                            <!-- Hover Content -->
                            <div class="absolute bottom-0 left-0 right-0 p-5 translate-y-full group-hover:translate-y-0 transition-transform duration-500">
                                <p class="text-white/90 text-sm">
                                    Scopri di più su questa atleta →
                                </p>
                            </div>
                        </div>

                        <!-- Player Info -->
                        <div class="p-5">
                            <h3 class="text-xl font-black text-savino-blue uppercase tracking-tight">
                                {{ player.first_name }} {{ player.last_name }}
                            </h3>
                            <p class="text-gray-500 text-sm font-medium mt-1">
                                {{ player.role }}
                            </p>
                        </div>

                        <!-- Bottom Accent -->
                        <div class="h-1 bg-gradient-to-r from-savino-gold via-savino-gold to-transparent w-0 group-hover:w-full transition-all duration-500"></div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
