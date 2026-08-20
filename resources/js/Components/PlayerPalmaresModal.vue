<script setup>
/**
 * Banner del palmarès di un'atleta.
 *
 * Riceve la riga di roster già preparata dal backend (`item.palmares` è
 * aggregato lì, in cache): qui non si calcola niente, si dispone soltanto.
 *
 * Il caso senza palmarès non è uno stato d'errore — le atlete più giovani non
 * ne hanno ancora — e la finestra resta utile lo stesso: anagrafica, numeri di
 * stagione e collegamento alle foto.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { displayRole } from '@/data/playerRoles';

const $t = useTranslations();
const { onImgError } = useImageFallback();

const props = defineProps({
    // Riga di roster (con `player`, `palmares`, `playerSlug`), oppure null.
    item: {
        type: Object,
        default: null,
    },
    // Riga della tabella statistiche della stessa atleta, se esiste.
    stats: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const panel = ref(null);

const isOpen = computed(() => Boolean(props.item));
const player = computed(() => props.item?.player ?? null);
const palmares = computed(() => props.item?.palmares ?? null);

const fullName = computed(() => {
    const p = player.value;
    return p ? `${p.first_name} ${p.last_name}`.trim() : '';
});

const birthYear = computed(() => {
    const date = player.value?.date_of_birth;
    if (!date) return null;
    const year = new Date(date).getFullYear();
    return Number.isNaN(year) ? null : year;
});

// Le tre caselle in testata: si mostrano solo quelle con un numero dentro.
const counters = computed(() => {
    const totals = palmares.value?.totals ?? {};
    return [
        { key: 'club', value: totals.club ?? 0 },
        { key: 'national', value: totals.national ?? 0 },
        { key: 'individual', value: totals.individual ?? 0 },
    ].filter((counter) => counter.value > 0);
});

const medalTone = {
    gold: { ring: 'ring-savino-fucsia/40', fill: 'bg-savino-fucsia' },
    silver: { ring: 'ring-gray-400/40', fill: 'bg-[#B8BCC0]' },
    bronze: { ring: 'ring-[#A9713B]/40', fill: 'bg-[#A9713B]' },
};

const close = () => emit('close');

const onKeydown = (event) => {
    if (event.key === 'Escape') close();
};

// Con la finestra aperta la pagina sotto non deve scorrere, e l'Esc deve
// chiudere anche quando il focus non è ancora entrato nel pannello.
//
// `immediate`: aprendo /stagione/atleta/{slug} da un link il componente nasce
// già con l'atleta dentro, quindi non c'è nessun cambio di stato da osservare
// — senza, quel caso resterebbe senza Esc e senza blocco dello scorrimento.
watch(isOpen, (open) => {
    if (typeof document === 'undefined') return;

    document.body.style.overflow = open ? 'hidden' : '';

    if (open) {
        document.addEventListener('keydown', onKeydown);
        requestAnimationFrame(() => panel.value?.focus());
    } else {
        document.removeEventListener('keydown', onKeydown);
    }
}, { immediate: true });

onBeforeUnmount(() => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <!-- Niente <Transition> in uscita: l'elemento resta nel DOM finché la
             transizione non finisce, e un pannello a schermo intero rimasto lì
             a opacità zero continuerebbe a intercettare i click sulla pagina.
             L'entrata è animata in CSS, l'uscita è immediata. -->
        <div
            v-if="isOpen"
            class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center bg-gray-900/80 backdrop-blur-sm sm:p-6 palmares-overlay"
            role="dialog"
            aria-modal="true"
            :aria-label="fullName"
            @click.self="close"
        >
            <div
                ref="panel"
                tabindex="-1"
                class="relative w-full sm:max-w-4xl max-h-[92vh] sm:max-h-[88vh] flex flex-col bg-white rounded-t-3xl sm:rounded-2xl overflow-hidden shadow-2xl outline-none palmares-panel"
            >
                <!-- Chiusura -->
                <button
                    type="button"
                    class="absolute top-4 right-4 z-20 w-9 h-9 rounded-full bg-white/15 hover:bg-white/30 text-white flex items-center justify-center transition-colors"
                    :aria-label="$t('common.close')"
                    @click="close"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>

                <!-- Testata -->
                <div class="relative shrink-0 bg-gradient-to-br from-savino-blue via-savino-blue to-gray-900 text-white">
                    <!-- Maniglia del foglio su mobile -->
                    <div class="sm:hidden pt-3 flex justify-center">
                        <span class="block w-10 h-1 rounded-full bg-white/30"></span>
                    </div>

                    <div class="flex gap-4 sm:gap-6 p-5 sm:p-7">
                        <div class="shrink-0 w-24 sm:w-40 aspect-[3/4] rounded-xl overflow-hidden bg-white/10">
                            <img
                                v-if="item.official_photo_url"
                                :src="item.official_photo_url"
                                :alt="fullName"
                                class="w-full h-full object-cover object-top"
                                @error="onImgError"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-white/25" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            </div>
                        </div>

                        <div class="min-w-0 flex-1 pr-8">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span v-if="item.jersey_number" class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-full bg-white text-savino-blue font-black text-sm">
                                    {{ item.jersey_number }}
                                </span>
                                <span class="text-savino-fucsia text-[11px] sm:text-xs font-bold uppercase tracking-[0.2em]">
                                    {{ displayRole(item.role, $t) }}
                                </span>
                            </div>

                            <h2 class="mt-2 text-2xl sm:text-4xl font-black uppercase tracking-tight leading-none">
                                {{ fullName }}
                            </h2>

                            <p class="mt-2 text-white/70 text-xs sm:text-sm">
                                <span v-if="player?.nationality">{{ player.nationality }}</span>
                                <span v-if="birthYear"> · {{ birthYear }}</span>
                                <span v-if="item.height_cm"> · {{ item.height_cm }} cm</span>
                            </p>

                            <div class="w-12 h-1 bg-savino-fucsia mt-4"></div>

                            <div v-if="counters.length" class="mt-4 flex gap-3 sm:gap-6">
                                <div v-for="counter in counters" :key="counter.key">
                                    <div class="text-2xl sm:text-3xl font-black leading-none">{{ counter.value }}</div>
                                    <div class="text-[10px] sm:text-[11px] uppercase tracking-wider text-white/60 mt-1">
                                        {{ $t('palmares.counter_' + counter.key) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Corpo -->
                <div class="flex-1 overflow-y-auto px-5 sm:px-7 py-6">
                    <div v-if="palmares" class="space-y-8">
                        <section v-for="group in palmares.groups" :key="group.category">
                            <h3 class="text-savino-blue text-xs font-black uppercase tracking-[0.2em]">
                                {{ $t('palmares.group_' + group.category) }}
                            </h3>
                            <div class="w-8 h-0.5 bg-savino-fucsia mt-2 mb-4"></div>

                            <ul class="space-y-2">
                                <li
                                    v-for="(entry, index) in group.items"
                                    :key="group.category + '-' + index"
                                    class="flex items-start gap-3 rounded-lg bg-gray-50 px-4 py-3"
                                >
                                    <!-- Disco per le medaglie, coppa per i
                                         titoli di club, stella per i premi
                                         personali: il simbolo dice a colpo
                                         d'occhio di che riga si tratta. -->
                                    <span
                                        v-if="entry.medal"
                                        class="shrink-0 mt-0.5 w-5 h-5 rounded-full ring-4"
                                        :class="[medalTone[entry.medal]?.fill, medalTone[entry.medal]?.ring]"
                                        :title="$t('palmares.medal_' + entry.medal)"
                                    ></span>
                                    <svg v-else-if="group.category === 'individual'" class="shrink-0 mt-0.5 w-5 h-5 text-savino-fucsia" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="m12 2 2.9 6.26 6.85.72-5.12 4.6 1.45 6.72L12 16.9l-6.08 3.4 1.45-6.72-5.12-4.6 6.85-.72L12 2Z"/>
                                    </svg>
                                    <svg v-else class="shrink-0 mt-0.5 w-5 h-5 text-savino-blue" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M18 2h3v3a4 4 0 0 1-3.4 3.95A6 6 0 0 1 13 12.9V16h3v2H8v-2h3v-3.1a6 6 0 0 1-4.6-3.95A4 4 0 0 1 3 5V2h3V1h12v1Zm0 2v4a2 2 0 0 0 1-1.73V4h-1ZM5 4v2.27A2 2 0 0 0 6 8V4H5Zm1 15h12v3H6v-3Z"/>
                                    </svg>

                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-gray-900 leading-snug">
                                            <span v-if="entry.count > 1" class="text-savino-blue">{{ entry.count }}× </span>{{ entry.competition }}
                                        </p>
                                        <p v-if="entry.note" class="text-sm text-savino-red font-semibold">{{ entry.note }}</p>
                                        <p v-if="entry.editions.length" class="text-sm text-gray-500 mt-0.5">
                                            {{ entry.editions.join(' · ') }}
                                        </p>
                                    </div>
                                </li>
                            </ul>
                        </section>
                    </div>

                    <!-- Nessun palmarès: non è un errore, si dice e basta. -->
                    <div v-else class="text-center py-8">
                        <div class="w-14 h-14 mx-auto rounded-full bg-savino-blue/5 flex items-center justify-center">
                            <svg class="w-7 h-7 text-savino-blue/30" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h3v3a4 4 0 0 1-3.4 3.95A6 6 0 0 1 13 12.9V16h3v2H8v-2h3v-3.1a6 6 0 0 1-4.6-3.95A4 4 0 0 1 3 5V2h3V1h12v1Zm0 2v4a2 2 0 0 0 1-1.73V4h-1ZM5 4v2.27A2 2 0 0 0 6 8V4H5Zm1 15h12v3H6v-3Z"/></svg>
                        </div>
                        <p class="mt-4 text-gray-500">{{ $t('palmares.empty') }}</p>
                    </div>

                    <!-- Numeri della stagione in corso -->
                    <section v-if="stats" class="mt-8 pt-6 border-t border-gray-100">
                        <h3 class="text-savino-blue text-xs font-black uppercase tracking-[0.2em]">
                            {{ $t('palmares.season_title') }}
                        </h3>
                        <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div
v-for="cell in [
                                { key: 'matches', value: stats.matchesPlayed },
                                { key: 'sets', value: stats.setsPlayed },
                                { key: 'points', value: stats.points },
                                { key: 'blocks', value: stats.blocks },
                            ]" :key="cell.key" class="rounded-lg bg-gray-50 px-4 py-3">
                                <dt class="text-[10px] uppercase tracking-wider text-gray-400">{{ $t('palmares.season_' + cell.key) }}</dt>
                                <dd class="text-xl font-black text-savino-blue">{{ cell.value ?? 0 }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <!-- Piede -->
                <div class="shrink-0 border-t border-gray-100 bg-gray-50 px-5 sm:px-7 py-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-[11px] text-gray-400">
                        <template v-if="palmares?.source">
                            {{ $t('palmares.source') }}
                            <a :href="palmares.source.url" target="_blank" rel="noopener nofollow" class="underline hover:text-savino-blue">
                                {{ palmares.source.name }}
                            </a>
                        </template>
                    </p>
                    <Link
                        v-if="item.playerSlug"
                        :href="route('gallery.atleta', { slug: item.playerSlug })"
                        class="inline-flex items-center gap-2 text-sm font-bold text-savino-blue hover:text-savino-fucsia transition-colors"
                    >
                        {{ $t('palmares.gallery_link') }}
                        <span aria-hidden="true">→</span>
                    </Link>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.palmares-overlay {
    animation: palmares-fade 180ms ease-out;
}

.palmares-panel {
    animation: palmares-rise 220ms cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes palmares-fade {
    from { opacity: 0; }
}

@keyframes palmares-rise {
    from { opacity: 0; transform: translateY(1.5rem); }
}

@media (prefers-reduced-motion: reduce) {
    .palmares-overlay,
    .palmares-panel {
        animation: none;
    }
}
</style>
