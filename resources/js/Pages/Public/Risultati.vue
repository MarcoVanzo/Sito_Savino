<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    },
    games: {
        type: Array,
        default: () => []
    },
    standings: {
        type: Array,
        default: () => []
    }
})

const placeholderGames = [
    { id: 1, date: '22 Giu 2025', home: 'Savino Del Bene', away: 'Squadra A', scoreHome: 3, scoreAway: 1, status: 'Conclusa' },
    { id: 2, date: '15 Giu 2025', home: 'Squadra B', away: 'Savino Del Bene', scoreHome: 0, scoreAway: 3, status: 'Conclusa' },
    { id: 3, date: '08 Giu 2025', home: 'Savino Del Bene', away: 'Squadra C', scoreHome: 3, scoreAway: 2, status: 'Conclusa' },
    { id: 4, date: '01 Giu 2025', home: 'Squadra D', away: 'Savino Del Bene', scoreHome: 1, scoreAway: 3, status: 'Conclusa' },
    { id: 5, date: '25 Mag 2025', home: 'Savino Del Bene', away: 'Squadra E', scoreHome: 3, scoreAway: 0, status: 'Conclusa' },
]

const placeholderStandings = [
    { pos: 1, team: 'Savino Del Bene', pts: 48, played: 18, won: 16, lost: 2, setWon: 50, setLost: 12 },
    { pos: 2, team: 'Squadra A', pts: 42, played: 18, won: 14, lost: 4, setWon: 45, setLost: 18 },
    { pos: 3, team: 'Squadra B', pts: 38, played: 18, won: 13, lost: 5, setWon: 42, setLost: 22 },
    { pos: 4, team: 'Squadra C', pts: 33, played: 18, won: 11, lost: 7, setWon: 38, setLost: 28 },
    { pos: 5, team: 'Squadra D', pts: 28, played: 18, won: 9, lost: 9, setWon: 32, setLost: 30 },
    { pos: 6, team: 'Squadra E', pts: 22, played: 18, won: 7, lost: 11, setWon: 26, setLost: 36 },
    { pos: 7, team: 'Squadra F', pts: 18, played: 18, won: 6, lost: 12, setWon: 22, setLost: 40 },
    { pos: 8, team: 'Squadra G', pts: 10, played: 18, won: 3, lost: 15, setWon: 14, setLost: 46 },
]

const displayGames = computed(() => props.games.length > 0 ? props.games : placeholderGames)
const displayStandings = computed(() => props.standings.length > 0 ? props.standings : placeholderStandings)

function isWin(game) {
    const isSavinoHome = game.home.includes('Savino')
    if (isSavinoHome) return game.scoreHome > game.scoreAway
    return game.scoreAway > game.scoreHome
}
</script>

<template>
    <Head>
      <title>{{ page?.title ?? 'Risultati e Classifica' }}</title>
    </Head>

    <PublicLayout>
        <!-- Hero -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Stagione 2025/26</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title ?? 'Risultati e Classifica' }}</h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">Segui i risultati delle nostre partite e la classifica aggiornata del campionato.</p>
            </div>
        </section>

        <!-- Ultime Partite -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight mb-2">Ultime Partite</h2>
                <div class="w-12 h-1 bg-savino-gold mb-10"></div>

                <div class="space-y-4">
                    <div
                        v-for="game in displayGames"
                        :key="game.id"
                        class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden border border-gray-100"
                    >
                        <div class="flex items-center">
                            <!-- Date -->
                            <div class="w-28 sm:w-32 flex-shrink-0 bg-savino-blue text-white text-center py-5 px-3">
                                <span class="text-xs font-bold uppercase tracking-wider block">{{ game.date }}</span>
                                <span class="text-[10px] text-white/60 mt-1 block">{{ game.status }}</span>
                            </div>
                            <!-- Match -->
                            <div class="flex-1 flex items-center justify-between px-4 sm:px-8 py-4">
                                <div class="flex-1 text-right pr-4">
                                    <span
                                        class="text-sm sm:text-base font-bold"
                                        :class="game.home.includes('Savino') ? 'text-savino-blue' : 'text-gray-700'"
                                       
                                    >{{ game.home }}</span>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="text-2xl font-black text-gray-900">{{ game.scoreHome }}</span>
                                    <span class="text-gray-400 text-lg">-</span>
                                    <span class="text-2xl font-black text-gray-900">{{ game.scoreAway }}</span>
                                </div>
                                <div class="flex-1 text-left pl-4">
                                    <span
                                        class="text-sm sm:text-base font-bold"
                                        :class="game.away.includes('Savino') ? 'text-savino-blue' : 'text-gray-700'"
                                       
                                    >{{ game.away }}</span>
                                </div>
                            </div>
                            <!-- Result Badge -->
                            <div class="w-20 flex-shrink-0 flex justify-center pr-4">
                                <span
                                    class="px-3 py-1 rounded-full text-xs font-bold uppercase"
                                    :class="isWin(game) ? 'bg-green-100 text-green-700' : 'bg-savino-red/10 text-savino-red'"
                                   
                                >{{ isWin(game) ? 'Vittoria' : 'Sconfitta' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Classifica -->
        <section class="py-16 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight mb-2">Classifica</h2>
                <div class="w-12 h-1 bg-savino-gold mb-10"></div>

                <div class="overflow-x-auto rounded-xl shadow-lg border border-gray-100">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-savino-blue text-white">
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider">Pos</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider">Squadra</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">G</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">V</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">P</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">SV</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">SP</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-center">Punti</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in displayStandings"
                                :key="row.pos"
                                class="border-b border-gray-100 transition-colors duration-200"
                                :class="row.team.includes('Savino') ? 'bg-savino-gold/10 font-bold' : 'hover:bg-gray-50'"
                            >
                                <td class="px-4 py-3 text-sm">
                                    <span
                                        class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black"
                                        :class="row.pos <= 3 ? 'bg-savino-gold text-white' : 'bg-gray-200 text-gray-600'"
                                    >{{ row.pos }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm font-bold" :class="row.team.includes('Savino') ? 'text-savino-blue' : 'text-gray-900'">{{ row.team }}</td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600">{{ row.played }}</td>
                                <td class="px-4 py-3 text-sm text-center text-green-600 font-semibold">{{ row.won }}</td>
                                <td class="px-4 py-3 text-sm text-center text-savino-red font-semibold">{{ row.lost }}</td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600">{{ row.setWon }}</td>
                                <td class="px-4 py-3 text-sm text-center text-gray-600">{{ row.setLost }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-lg font-black text-savino-blue">{{ row.pts }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-400 mt-4 text-center">G = Giocate · V = Vinte · P = Perse · SV = Set Vinti · SP = Set Persi</p>
            </div>
        </section>
    </PublicLayout>
</template>
