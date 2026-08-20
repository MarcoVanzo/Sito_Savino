<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { ref, computed, watch } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHero from '@/Components/PageHero.vue';
import SeasonStatsTable from '@/Components/SeasonStatsTable.vue';
import TeamLogo from '@/Components/TeamLogo.vue';
import PlayerPalmaresModal from '@/Components/PlayerPalmaresModal.vue';
import { Head, router } from '@inertiajs/vue3';
import { roleLabels, displayRole, getRoleLabels } from '@/data/playerRoles';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';

const $t = useTranslations();

const { onImgError } = useImageFallback();

const props = defineProps({
    roster: {
        type: Array,
        default: () => [],
    },
    seasonName: {
        type: String,
        default: '',
    },
    staffTecnico: {
        type: Array,
        default: () => [],
    },
    staffMedico: {
        type: Array,
        default: () => [],
    },
    // Totali di stagione già filtrati dal backend: vuoto quando la squadra non
    // ha tabellini (giovanili) o quando la stagione è appena iniziata.
    seasonStats: {
        type: Array,
        default: () => [],
    },
    teamInfo: {
        type: Object,
        default: null,
    },
    // Il palmarès si pubblica solo sulla prima squadra: sulle altre le card
    // non si aprono.
    palmaresEnabled: {
        type: Boolean,
        default: false,
    },
    // Slug dell'atleta il cui banner va aperto: arriva da
    // /stagione/atleta/{slug}, ed è anche quello che l'apertura scrive
    // nell'indirizzo.
    openPlayer: {
        type: String,
        default: null,
    },
});

const ALL_ROLES = '__all__';
const selectedRole = ref(ALL_ROLES);
const roles = [ALL_ROLES, ...Object.keys(roleLabels)];

const translatedRoleLabels = computed(() => getRoleLabels($t));

const filteredRoster = computed(() => {
    if (selectedRole.value === ALL_ROLES) {
        return props.roster;
    }
    return props.roster.filter(item => item.role === selectedRole.value);
});

function getInitials(name) {
    if (typeof name !== 'string') return '';
    return name.trim().split(/\s+/).map(n => n[0]).join('').toUpperCase();
}

const ogMeta = useOgMeta({
    title: $t('stagione.og_title') + (props.seasonName ? ' — ' + props.seasonName : ''),
    description: $t('stagione.og_description'),
});

// Il banner ha un indirizzo proprio — così il link è condivisibile e il tasto
// indietro lo chiude — ma non aspetta il server per aprirsi: lo stato locale
// comanda a schermo, l'indirizzo lo segue. Il giro sul server chiede solo
// `openPlayer`, gli altri dati restano quelli già in pagina.
const openedSlug = ref(props.openPlayer);

watch(() => props.openPlayer, (slug) => {
    openedSlug.value = slug;
});

const openedPlayer = computed(
    () => (openedSlug.value
        ? props.roster.find(item => item.playerSlug === openedSlug.value) ?? null
        : null),
);

const openedStats = computed(
    () => (openedSlug.value
        ? props.seasonStats.find(row => row.playerSlug === openedSlug.value) ?? null
        : null),
);

function navigate(url) {
    router.get(url, {}, {
        only: ['openPlayer'],
        preserveState: true,
        preserveScroll: true,
    });
}

function openPalmares(item) {
    if (!props.palmaresEnabled || !item.playerSlug) return;

    openedSlug.value = item.playerSlug;
    navigate(route('stagione.atleta', { slug: item.playerSlug }));
}

function closePalmares() {
    openedSlug.value = null;
    navigate(route('stagione'));
}
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
        <meta property="og:title" :content="ogMeta.title" />
        <meta property="og:description" :content="ogMeta.description" />
        <meta property="og:image" :content="ogMeta.image" />
        <meta property="og:url" :content="ogMeta.url" />
        <meta property="og:type" :content="ogMeta.type" />
    </Head>
    <PublicLayout>
        <!-- Hero -->
        <PageHero
            :subtitle="$t('stagione.hero_subtitle')"
            :title="$t('stagione.og_title') + (seasonName ? ' — ' + seasonName : '')"
            :description="$t('stagione.hero_description')"
        />

        <!-- Roster Content -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Filtri Ruolo -->
                <div class="flex flex-wrap justify-center gap-3 mb-12">
                    <button
v-for="role in roles"
                        :key="role"
                        type="button"
                        class="px-5 py-2.5 rounded-full text-sm font-bold uppercase tracking-wider transition-all duration-300"
                        :class="selectedRole === role
                            ? 'bg-savino-blue text-white shadow-lg shadow-savino-blue/30'
                            : 'bg-white text-gray-600 hover:bg-savino-blue/10 hover:text-savino-blue border border-gray-200'"
                        @click="selectedRole = role"
                    >
                        {{ role === '__all__' ? $t('stagione.filter_all') : translatedRoleLabels[role] ?? role }}
                    </button>
                </div>

                <!-- Griglia Atlete -->
                <div v-if="filteredRoster.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <component
                        :is="palmaresEnabled && item.playerSlug ? 'button' : 'div'"
                        v-for="item in filteredRoster"
                        :key="item.id"
                        :type="palmaresEnabled && item.playerSlug ? 'button' : null"
                        :aria-label="palmaresEnabled && item.playerSlug
                            ? item.player.first_name + ' ' + item.player.last_name + ' — ' + $t('palmares.open_hint')
                            : null"
                        :class="palmaresEnabled && item.playerSlug ? 'cursor-pointer focus:outline-none focus:ring-2 focus:ring-savino-gold focus:ring-offset-2' : ''"
                        class="group bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-xl border border-gray-100 transition-all duration-500 hover:-translate-y-1 text-left w-full"
                        @click="openPalmares(item)"
                    >
                        <!-- Immagine Atleta -->
                        <!-- Riquadro verticale e ancoraggio in alto: le foto ufficiali sono
                             ritratti, e un box 4:3 centrato tagliava teste e diciture. -->
                        <div class="aspect-[3/4] relative bg-gray-100 overflow-hidden">
                            <img
                                v-if="item.official_photo_url"
                                :src="item.official_photo_url"
                                :alt="item.player.first_name + ' ' + item.player.last_name"
                                class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-700"
                                loading="lazy"
                                @error="onImgError"
                            />
                            <!-- Fallback -->
                            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-b from-gray-100 to-gray-200">
                                <svg class="w-20 h-20 text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            </div>

                            <!-- Badge Numero Maglia -->
                            <div class="absolute top-4 left-4 bg-savino-blue text-white w-10 h-10 flex items-center justify-center rounded-full font-bold text-lg shadow-lg">
                                {{ item.jersey_number || '–' }}
                            </div>

                            <!-- Invito all'apertura: compare sulla foto al passaggio del mouse -->
                            <div
                                v-if="palmaresEnabled && item.playerSlug"
                                class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-savino-blue/90 to-transparent px-4 pt-10 pb-4 translate-y-full group-hover:translate-y-0 group-focus:translate-y-0 transition-transform duration-500"
                            >
                                <span class="text-white text-sm font-semibold">{{ $t('palmares.open_hint') }} →</span>
                            </div>
                        </div>

                        <!-- Dati Atleta -->
                        <div class="p-5">
                            <div class="text-xs font-bold text-savino-gold uppercase tracking-wider mb-1">
                                {{ displayRole(item.role, $t) }}
                            </div>
                            <h3 class="text-xl text-savino-blue font-black tracking-tight mb-3">
                                {{ item.player.first_name }} {{ item.player.last_name }}
                            </h3>

                            <div class="flex gap-4 text-sm text-gray-500">
                                <div v-if="item.player.nationality"><strong>{{ $t('stagione.nationality_short') }}:</strong> {{ item.player.nationality }}</div>
                                <div v-if="item.height_cm"><strong>{{ $t('stagione.height_short') }}:</strong> {{ item.height_cm }} cm</div>
                                <div v-if="item.player.date_of_birth"><strong>{{ $t('stagione.year_short') }}:</strong> {{ new Date(item.player.date_of_birth).getFullYear() }}</div>
                            </div>
                        </div>
                    </component>
                </div>

                <!-- Empty state -->
                <div v-else class="text-center py-20">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-savino-blue/10 flex items-center justify-center">
                        <svg class="w-12 h-12 text-savino-blue/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                    <p class="text-gray-500 text-lg font-semibold">{{ $t('stagione.empty_role') }}</p>
                    <button type="button" class="mt-4 px-6 py-2.5 bg-savino-gold text-white text-sm font-bold uppercase tracking-wider rounded-lg hover:bg-savino-gold/90 transition-colors" @click="selectedRole = '__all__'">
                        {{ $t('stagione.show_all') }}
                    </button>
                </div>

            </div>
        </section>

        <!-- Statistiche di stagione della rosa -->
        <section v-if="roster.length > 0" class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3 mb-2">
                    <TeamLogo v-if="teamInfo" :src="teamInfo.logo" :name="teamInfo.name" size="md" />
                    <div>
                        <span class="text-savino-gold text-xs font-bold uppercase tracking-[0.3em]">{{ $t('stagione.stats_eyebrow') }}</span>
                        <h2 class="text-2xl md:text-3xl font-black text-savino-blue uppercase tracking-tight">
                            {{ $t('stagione.stats_title') }}<span v-if="seasonName"> — {{ seasonName }}</span>
                        </h2>
                    </div>
                </div>
                <div class="w-12 h-1 bg-savino-gold mb-8"></div>

                <!-- Le giovanili non hanno tabellini della Lega: si dice, non si
                     riempie la pagina di zeri. -->
                <p
                    v-if="seasonStats.length === 0"
                    class="bg-gray-50 rounded-xl border border-gray-100 px-6 py-10 text-center text-gray-500"
                >{{ $t('stagione.stats_empty') }}</p>

                <template v-else>
                    <p class="text-sm text-gray-500 mb-4">{{ $t('stagione.stats_sort_hint') }}</p>
                    <SeasonStatsTable :rows="seasonStats" />
                    <p class="text-xs text-gray-400 mt-4">{{ $t('stagione.stats_legend') }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $t('stagione.stats_note') }}</p>
                </template>
            </div>
        </section>

        <!-- Staff Tecnico -->
        <section v-if="staffTecnico.length > 0" class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ $t('stagione.our_team') }}</span>
                    <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tight mt-3">{{ $t('stagione.coaching_staff') }}</h2>
                    <div class="w-16 h-1 bg-savino-gold mx-auto mt-4"></div>
                </div>
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
                                class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-700"
                                loading="lazy"
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
        </section>

        <!-- Staff Medico -->
        <section v-if="staffMedico.length > 0" class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ $t('stagione.athlete_support') }}</span>
                    <h2 class="text-3xl md:text-4xl font-black text-savino-red uppercase tracking-tight mt-3">{{ $t('stagione.medical_staff') }}</h2>
                    <div class="w-16 h-1 bg-savino-red mx-auto mt-4"></div>
                </div>
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
                                class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-700"
                                loading="lazy"
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
        </section>

        <PlayerPalmaresModal
            :item="openedPlayer"
            :stats="openedStats"
            @close="closePalmares"
        />
    </PublicLayout>
</template>

