<script setup>\nimport { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useSanitize } from '@/Composables/useSanitize'
import { useOgMeta } from '@/Composables/useOgMeta'

const $t = useTranslations();

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    }
})

const { sanitize } = useSanitize()
const safeContent = computed(() => sanitize(props.page?.content))

const inertiaPage = usePage()
const settings = computed(() => inertiaPage.props.siteSettings ?? {})
const contact = computed(() => settings.value.contact ?? {})
const cd = computed(() => props.page?.content_data ?? {})

const youthTeams = computed(() => cd.value.youth_teams ?? [
    {
        name: 'Under 18',
        category: 'Serie C',
        coach: 'Alessandro Tozzi',
        training: 'Lun-Mer-Ven 16:00-18:00',
        players: 14,
        color: 'savino-blue'
    },
    {
        name: 'Under 16',
        category: $t('youth.category_regional'),
        coach: 'Francesca Galli',
        training: 'Mar-Gio-Sab 15:00-17:00',
        players: 16,
        color: 'savino-gold'
    },
    {
        name: 'Under 14',
        category: $t('youth.category_provincial'),
        coach: 'Simone Marchetti',
        training: 'Lun-Mer-Ven 14:30-16:30',
        players: 18,
        color: 'savino-red'
    },
    {
        name: 'Under 12',
        category: 'Minivolley',
        coach: 'Laura Rinaldi',
        training: 'Mar-Gio 14:00-15:30',
        players: 20,
        color: 'savino-blue'
    }
])

const values = computed(() => cd.value.values ?? [
    {
        icon: 'star',
        title: $t('youth.value_1_title'),
        description: $t('youth.value_1_desc')
    },
    {
        icon: 'heart',
        title: $t('youth.value_2_title'),
        description: $t('youth.value_2_desc')
    },
    {
        icon: 'trophy',
        title: $t('youth.value_3_title'),
        description: $t('youth.value_3_desc')
    },
    {
        icon: 'users',
        title: $t('youth.value_4_title'),
        description: $t('youth.value_4_desc')
    }
])

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('youth.og_title'),
    description: cd.value.meta_description || $t('youth.og_description'),
})
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
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_subtitle || $t('youth.hero_subtitle') }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title ?? $t('youth.og_title') }}</h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">{{ cd.hero_description || $t('youth.hero_description') }}</p>
            </div>
        </section>

        <!-- Introduction -->
        <section class="py-16 bg-white">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <span class="text-savino-gold text-sm font-bold uppercase tracking-wider">{{ cd.intro_label || $t('youth.intro_label') }}</span>
                        <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight mt-3 mb-4">{{ cd.intro_title || $t('youth.intro_title') }}</h2>
                        <div class="w-12 h-1 bg-savino-gold mb-6"></div>
                        <p class="text-gray-600 leading-relaxed mb-4">
                            {{ cd.intro_paragraph_1 || $t('youth.intro_paragraph_1') }}
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            {{ cd.intro_paragraph_2 || $t('youth.intro_paragraph_2') }}
                        </p>
                    </div>
                    <div class="bg-gradient-to-br from-savino-blue/5 to-savino-gold/5 rounded-2xl p-8 border border-gray-100">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="text-center">
                                <span class="text-4xl font-black text-savino-blue block">{{ cd.stat_athletes || '70+' }}</span>
                                <span class="text-sm text-gray-500 font-semibold mt-1 block">{{ cd.stat_athletes_label || $t('youth.stat_athletes_label') }}</span>
                            </div>
                            <div class="text-center">
                                <span class="text-4xl font-black text-savino-gold block">{{ cd.stat_categories || '4' }}</span>
                                <span class="text-sm text-gray-500 font-semibold mt-1 block">{{ cd.stat_categories_label || $t('youth.stat_categories_label') }}</span>
                            </div>
                            <div class="text-center">
                                <span class="text-4xl font-black text-savino-red block">{{ cd.stat_coaches || '12' }}</span>
                                <span class="text-sm text-gray-500 font-semibold mt-1 block">{{ cd.stat_coaches_label || $t('youth.stat_coaches_label') }}</span>
                            </div>
                            <div class="text-center">
                                <span class="text-4xl font-black text-savino-blue block">{{ cd.stat_years || '15+' }}</span>
                                <span class="text-sm text-gray-500 font-semibold mt-1 block">{{ cd.stat_years_label || $t('youth.stat_years_label') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Values -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight text-center mb-2">{{ cd.values_title || $t('youth.values_title') }}</h2>
                <div class="w-16 h-1 bg-savino-gold mx-auto mb-12"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div
                        v-for="value in values"
                        :key="value.title"
                        class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-500 border border-gray-100 hover:-translate-y-1 text-center"
                    >
                        <div class="w-14 h-14 rounded-full bg-savino-blue/10 flex items-center justify-center mx-auto mb-4">
                            <!-- Star -->
                            <svg v-if="value.icon === 'star'" class="w-7 h-7 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                            <!-- Heart -->
                            <svg v-else-if="value.icon === 'heart'" class="w-7 h-7 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                            <!-- Trophy -->
                            <svg v-else-if="value.icon === 'trophy'" class="w-7 h-7 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                            <!-- Users -->
                            <svg v-else class="w-7 h-7 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-black text-gray-900 uppercase tracking-tight mb-2">{{ value.title }}</h3>
                        <p class="text-gray-500 text-sm leading-relaxed">{{ value.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Youth Teams Grid -->
        <section class="py-16 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight mb-2">{{ cd.teams_title || $t('youth.teams_title') }}</h2>
                <div class="w-12 h-1 bg-savino-gold mb-10"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        v-for="team in youthTeams"
                        :key="team.name"
                        class="group bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-500 overflow-hidden border border-gray-100 hover:-translate-y-1"
                    >
                        <!-- Team Header -->
                        <div
                            class="h-32 flex items-center justify-center relative overflow-hidden"
                            :class="{
                                'bg-gradient-to-br from-savino-blue to-savino-blue/80': team.color === 'savino-blue',
                                'bg-gradient-to-br from-savino-gold to-savino-gold/80': team.color === 'savino-gold',
                                'bg-gradient-to-br from-savino-red to-savino-red/80': team.color === 'savino-red',
                            }"
                        >
                            <div class="absolute inset-0 opacity-10">
                                <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-white/20"></div>
                                <div class="absolute -left-4 -bottom-4 w-20 h-20 rounded-full bg-white/10"></div>
                            </div>
                            <h3 class="text-2xl font-black text-white uppercase tracking-tight relative z-10">{{ team.name }}</h3>
                        </div>
                        <!-- Team Info -->
                        <div class="p-5">
                            <span class="text-savino-gold text-xs font-bold uppercase tracking-wider">{{ team.category }}</span>

                            <div class="space-y-3 mt-4">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span class="text-sm text-gray-600">{{ team.coach }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-sm text-gray-600">{{ team.training }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="text-sm text-gray-600">{{ team.players }} {{ $t('youth.players_unit') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Talent Scouting -->
        <section class="py-16 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ cd.scouting_label || 'Talent Scouting' }}</span>
                <h2 class="text-3xl md:text-4xl font-black text-white uppercase tracking-tight mt-4 mb-4">{{ cd.scouting_title || $t('youth.scouting_title') }}</h2>
                <div class="w-16 h-1 bg-savino-gold mx-auto mb-8"></div>
                <p class="text-white/70 text-lg leading-relaxed max-w-2xl mx-auto mb-6">
                    {{ cd.scouting_description || $t('youth.scouting_description') }}
                </p>
                <p class="text-white/50 text-sm mb-8">
                    {{ cd.scouting_info || $t('youth.scouting_info') }}
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a
                        href="/contatti"
                        class="inline-flex items-center justify-center px-8 py-3.5 bg-savino-gold text-white font-bold uppercase tracking-wider rounded-lg hover:bg-savino-gold/90 transition-all duration-300 shadow-lg shadow-savino-gold/30 text-sm"
                       
                    >
                        {{ cd.scouting_cta_primary || $t('youth.scouting_cta_primary') }}
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                    <a
                        :href="'mailto:' + (cd.scouting_email || contact.youth_email || 'giovanili@savinodelbenescandicci.it')"
                        class="inline-flex items-center justify-center px-8 py-3.5 border-2 border-white/30 text-white font-bold uppercase tracking-wider rounded-lg hover:bg-white/10 transition-all duration-300 text-sm"
                       
                    >
                        {{ cd.scouting_cta_secondary || $t('youth.scouting_cta_secondary') }}
                    </a>
                </div>
            </div>
        </section>

        <!-- Page Content (CMS) -->
        <section v-if="page?.content" class="py-16 bg-gray-50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    class="prose prose-lg max-w-none prose-headings:font-black prose-headings:uppercase prose-headings:tracking-tight prose-a:text-savino-blue"
                   
                    v-html="safeContent"
                ></div>
            </div>
        </section>
    </PublicLayout>
</template>
