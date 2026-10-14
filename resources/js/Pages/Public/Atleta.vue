<script setup>
/**
 * La pagina di un'atleta della prima squadra (`/stagione/atleta/{slug}`).
 *
 * Prima era un banner sopra la rosa: ora è una pagina con un indirizzo,
 * un titolo e una descrizione propri, che i motori di ricerca possono
 * indicizzare. Tutto arriva pronto dal server (PublicController::
 * stagioneAtleta): qui si dispone soltanto, e le sezioni senza dati spariscono.
 */
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';
import { displayRole } from '@/data/playerRoles';
import { contatoriDelPalmares, nomeCompleto } from '@/Support/paginaAtleta.js';

const $t = useTranslations();
const { onImgError } = useImageFallback();

const props = defineProps({
    atleta: { type: Object, required: true },
    seasonName: { type: String, default: null },
    statistiche: { type: Object, default: null },
    carriera: { type: Array, default: () => [] },
    palmares: { type: Object, default: null },
    notizie: { type: Array, default: () => [] },
});

const fullName = computed(() => nomeCompleto(props.atleta));
const counters = computed(() => contatoriDelPalmares(props.palmares));

const medalTone = {
    gold: { ring: 'ring-savino-fucsia/40', fill: 'bg-savino-fucsia' },
    silver: { ring: 'ring-gray-400/40', fill: 'bg-[#B8BCC0]' },
    bronze: { ring: 'ring-[#A9713B]/40', fill: 'bg-[#A9713B]' },
};

const celleStatistiche = computed(() => {
    const s = props.statistiche;
    if (!s) return [];

    return [
        { key: 'stagione.stats_col_matches', value: s.matchesPlayed },
        { key: 'stagione.stats_col_sets', value: s.setsPlayed },
        { key: 'stagione.stats_col_points', value: s.points },
        { key: 'stagione.stats_col_points_per_set', value: s.pointsPerSet ?? '–' },
        { key: 'stagione.stats_col_attack_points', value: s.attackPoints },
        { key: 'stagione.stats_col_blocks', value: s.blocks },
        { key: 'stagione.stats_col_aces', value: s.aces },
        { key: 'stagione.stats_col_reception_positive', value: s.receptionPositivePct != null ? `${s.receptionPositivePct}%` : '–' },
    ];
});

const formatDate = (iso) => {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' });
};

const ogMeta = useOgMeta({
    title: fullName.value,
    image: props.atleta.official_photo_url,
    type: 'profile',
});
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
        <!-- Testata -->
        <section class="bg-gradient-to-br from-savino-blue via-savino-blue to-gray-900 text-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                <nav :aria-label="$t('common.breadcrumb')" class="text-sm text-white/80 mb-8 flex flex-wrap items-center gap-2">
                    <Link :href="route('home')" class="hover:text-savino-fucsia-chiaro transition-colors">{{ $t('common.home') }}</Link>
                    <span aria-hidden="true">/</span>
                    <Link :href="route('stagione')" class="hover:text-savino-fucsia-chiaro transition-colors">{{ $t('stagione.og_title') }}</Link>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page" class="text-savino-fucsia-chiaro">{{ fullName }}</span>
                </nav>

                <div class="flex flex-col sm:flex-row gap-8 items-start">
                    <div class="shrink-0 w-48 sm:w-64 aspect-[3/4] rounded-2xl overflow-hidden bg-white/10">
                        <img
                            v-if="atleta.official_photo_url"
                            :src="atleta.official_photo_url"
                            :alt="atleta.photo_alt || $t('atleta.photo_alt', { name: fullName })"
                            class="w-full h-full object-cover object-top"
                            @error="onImgError"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <svg class="w-16 h-16 text-white/25" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <span class="text-savino-fucsia-chiaro text-xs font-bold uppercase tracking-[0.3em]">{{ $t('atleta.eyebrow') }}<span v-if="seasonName"> · {{ seasonName }}</span></span>
                        <h1 class="mt-3 text-4xl sm:text-6xl font-black uppercase tracking-tight leading-none">{{ fullName }}</h1>
                        <div class="w-16 h-1 bg-savino-fucsia mt-6"></div>

                        <dl class="mt-6 grid grid-cols-2 sm:grid-cols-3 gap-4 max-w-xl">
                            <div v-if="atleta.jersey_number">
                                <dt class="text-[11px] uppercase tracking-wider text-white/60">{{ $t('atleta.jersey') }}</dt>
                                <dd class="text-2xl font-black">{{ atleta.jersey_number }}</dd>
                            </div>
                            <div v-if="atleta.role">
                                <dt class="text-[11px] uppercase tracking-wider text-white/60">{{ $t('atleta.role') }}</dt>
                                <dd class="text-lg font-bold">{{ displayRole(atleta.role, $t) }}</dd>
                            </div>
                            <div v-if="atleta.nationality">
                                <dt class="text-[11px] uppercase tracking-wider text-white/60">{{ $t('atleta.nationality') }}</dt>
                                <dd class="text-lg font-bold">{{ atleta.nationality }}</dd>
                            </div>
                            <div v-if="atleta.height_cm">
                                <dt class="text-[11px] uppercase tracking-wider text-white/60">{{ $t('atleta.height') }}</dt>
                                <dd class="text-lg font-bold">{{ atleta.height_cm }} cm</dd>
                            </div>
                            <div v-if="atleta.anno_di_nascita">
                                <dt class="text-[11px] uppercase tracking-wider text-white/60">{{ $t('atleta.birth_year') }}</dt>
                                <dd class="text-lg font-bold">{{ atleta.anno_di_nascita }}</dd>
                            </div>
                        </dl>

                        <div v-if="counters.length" class="mt-6 flex gap-6">
                            <div v-for="counter in counters" :key="counter.key">
                                <div class="text-3xl font-black leading-none">{{ counter.value }}</div>
                                <div class="text-[11px] uppercase tracking-wider text-white/60 mt-1">{{ $t('palmares.counter_' + counter.key) }}</div>
                            </div>
                        </div>

                        <a
                            v-if="atleta.instagram_url"
                            :href="atleta.instagram_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-6 inline-flex items-center gap-2 min-h-[44px] text-sm font-bold text-white/90 hover:text-white transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r="0.75" fill="currentColor" /></svg>
                            Instagram
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Statistiche della stagione -->
        <section class="py-12 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-black text-savino-blue uppercase tracking-tight">
                    {{ $t('atleta.stats_title') }}<span v-if="seasonName"> — {{ seasonName }}</span>
                </h2>
                <div class="w-12 h-1 bg-savino-fucsia mt-3 mb-6"></div>

                <template v-if="celleStatistiche.length">
                    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div v-for="cell in celleStatistiche" :key="cell.key" class="rounded-lg bg-gray-50 px-4 py-3">
                            <dt class="text-[11px] uppercase tracking-wider text-gray-600">{{ $t(cell.key) }}</dt>
                            <dd class="text-2xl font-black text-savino-blue">{{ cell.value ?? 0 }}</dd>
                        </div>
                    </dl>
                    <p class="text-xs text-gray-500 mt-3">{{ $t('atleta.stats_source') }}</p>
                </template>
                <p v-else class="text-gray-500">{{ $t('atleta.stats_empty') }}</p>
            </div>
        </section>

        <!-- Carriera nella società -->
        <section v-if="carriera.length" class="py-12 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-black text-savino-blue uppercase tracking-tight">{{ $t('atleta.career_title') }}</h2>
                <div class="w-12 h-1 bg-savino-fucsia mt-3 mb-6"></div>
                <ul class="divide-y divide-gray-200 bg-white rounded-xl border border-gray-100">
                    <li v-for="(tappa, index) in carriera" :key="index" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                        <span class="font-bold text-gray-900">{{ tappa.season }}</span>
                        <span class="text-sm text-gray-600">
                            {{ tappa.team }}<span v-if="tappa.role"> · {{ displayRole(tappa.role, $t) }}</span><span v-if="tappa.jersey_number"> · #{{ tappa.jersey_number }}</span>
                        </span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Palmarès -->
        <section v-if="palmares" class="py-12 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-black text-savino-blue uppercase tracking-tight">{{ $t('atleta.palmares_title') }}</h2>
                <div class="w-12 h-1 bg-savino-fucsia mt-3 mb-6"></div>

                <div class="grid gap-8 md:grid-cols-2">
                    <section v-for="group in palmares.groups" :key="group.category">
                        <h3 class="text-savino-blue text-xs font-black uppercase tracking-[0.2em]">{{ $t('palmares.group_' + group.category) }}</h3>
                        <ul class="mt-4 space-y-2">
                            <li
                                v-for="(entry, index) in group.items"
                                :key="group.category + '-' + index"
                                class="flex items-start gap-3 rounded-lg bg-gray-50 px-4 py-3"
                            >
                                <span
                                    v-if="entry.medal"
                                    class="shrink-0 mt-0.5 w-5 h-5 rounded-full ring-4"
                                    :class="[medalTone[entry.medal]?.fill, medalTone[entry.medal]?.ring]"
                                    :title="$t('palmares.medal_' + entry.medal)"
                                ></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-gray-900 leading-snug">
                                        <span v-if="entry.count > 1" class="text-savino-blue">{{ entry.count }}× </span>{{ entry.competition }}
                                    </p>
                                    <p v-if="entry.note" class="text-sm text-savino-red font-semibold">{{ entry.note }}</p>
                                    <p v-if="entry.editions.length" class="text-sm text-gray-500 mt-0.5">{{ entry.editions.join(' · ') }}</p>
                                </div>
                            </li>
                        </ul>
                    </section>
                </div>

                <p v-if="palmares.source" class="mt-6 text-xs text-gray-500">
                    {{ $t('palmares.source') }}
                    <a :href="palmares.source.url" target="_blank" rel="noopener nofollow" class="underline hover:text-savino-blue">{{ palmares.source.name }}</a>
                </p>
            </div>
        </section>

        <!-- Notizie -->
        <section v-if="notizie.length" class="py-12 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="text-2xl font-black text-savino-blue uppercase tracking-tight">{{ $t('atleta.news_title') }}</h2>
                    <Link :href="route('news.index')" class="text-sm font-bold text-savino-blue hover:text-[#B8066A]">{{ $t('atleta.news_all') }} →</Link>
                </div>
                <div class="w-12 h-1 bg-savino-fucsia mt-3 mb-6"></div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="post in notizie"
                        :key="post.id"
                        :href="route('news.show', { slug: post.slug })"
                        class="group bg-white rounded-xl overflow-hidden border border-gray-100 hover:shadow-lg transition-shadow"
                    >
                        <div class="aspect-[16/10] bg-gray-100 overflow-hidden">
                            <img v-if="post.image_url" :src="post.image_url" :alt="post.title || ''" class="w-full h-full object-cover" loading="lazy" @error="onImgError" />
                        </div>
                        <div class="p-4">
                            <p class="text-xs text-gray-500">{{ formatDate(post.published_at) }}</p>
                            <h3 class="mt-1 font-bold text-gray-900 leading-snug group-hover:text-savino-blue">{{ post.title }}</h3>
                        </div>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Foto -->
        <section class="py-12 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-savino-blue uppercase tracking-tight">{{ $t('atleta.gallery_title') }}</h2>
                    <p class="mt-2 text-gray-600">{{ $t('atleta.gallery_text', { name: fullName }) }}</p>
                </div>
                <Link
                    :href="route('gallery.atleta', { slug: atleta.slug })"
                    class="inline-flex items-center gap-2 min-h-[44px] px-6 rounded-lg bg-savino-blue text-white text-sm font-bold uppercase tracking-wider hover:bg-savino-blue/90 transition-colors"
                >
                    {{ $t('palmares.gallery_link') }} <span aria-hidden="true">→</span>
                </Link>
            </div>
        </section>

        <div class="bg-gray-50 py-8 text-center">
            <Link :href="route('stagione')" class="inline-flex items-center min-h-[44px] text-sm font-bold text-savino-blue hover:text-[#B8066A]">← {{ $t('atleta.back') }}</Link>
        </div>
    </PublicLayout>
</template>
