<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useSanitize } from '@/Composables/useSanitize'
import { useOgMeta } from '@/Composables/useOgMeta'

const $t = useTranslations();

defineOptions({ layout: PublicLayout })

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

const defaultActivities = [
    {
        icon: '🏐',
        title: $t('summer_camp.activity_volleyball'),
        description: $t('summer_camp.activity_volleyball_desc')
    },
    {
        icon: '🏋️',
        title: $t('summer_camp.activity_fitness'),
        description: $t('summer_camp.activity_fitness_desc')
    },
    {
        icon: '🎯',
        title: 'Talent Day',
        description: $t('summer_camp.activity_talent_desc')
    },
    {
        icon: '🏊',
        title: $t('summer_camp.activity_water'),
        description: $t('summer_camp.activity_water_desc')
    },
    {
        icon: '🎨',
        title: $t('summer_camp.activity_creative'),
        description: $t('summer_camp.activity_creative_desc')
    },
    {
        icon: '🤝',
        title: 'Team Building',
        description: $t('summer_camp.activity_teambuilding_desc')
    }
]

const defaultDates = [
    { period: $t('summer_camp.week_1'), dates: $t('summer_camp.dates_week_1'), status: $t('summer_camp.status_open') },
    { period: $t('summer_camp.week_2'), dates: $t('summer_camp.dates_week_2'), status: $t('summer_camp.status_open') },
    { period: $t('summer_camp.week_3'), dates: $t('summer_camp.dates_week_3'), status: $t('summer_camp.status_limited') },
    { period: $t('summer_camp.week_4'), dates: $t('summer_camp.dates_week_4'), status: $t('summer_camp.status_coming_soon') }
]

const activities = computed(() => cd.value.activities || defaultActivities)
const dates = computed(() => cd.value.dates || defaultDates)

const defaultHighlights = [
    $t('summer_camp.highlight_staff'),
    $t('summer_camp.highlight_age_groups'),
    $t('summer_camp.highlight_insurance'),
    $t('summer_camp.highlight_meals')
]
const highlights = computed(() => cd.value.highlights || defaultHighlights)

const ogMeta = useOgMeta({
    title: props.page?.title ?? 'Summer Camp & Experience',
    description: cd.value.meta_description || $t('summer_camp.og_description'),
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

    <!-- Hero -->
    <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
        <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
            <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_label || $t('summer_camp.hero_label') }}</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                {{ page?.title ?? 'Summer Camp & Experience' }}
            </h1>
            <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
            <p class="text-white/70 text-lg max-w-2xl mx-auto">
                {{ cd.hero_subtitle || $t('summer_camp.hero_subtitle') }}
            </p>
        </div>
    </section>

    <!-- Camp Description -->
    <section class="py-20 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">{{ cd.camp_section_label || $t('summer_camp.camp_section_label') }}</span>
                    <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                        {{ cd.camp_title || $t('summer_camp.camp_title') }}
                    </h2>
                    <div class="w-12 h-1 bg-savino-gold mt-4 mb-6"></div>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        {{ cd.camp_description_1 || $t('summer_camp.camp_desc_1') }}
                    </p>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        {{ cd.camp_description_2 || $t('summer_camp.camp_desc_2') }}
                    </p>
                    <ul class="space-y-3">
                        <li v-for="item in highlights" :key="item" class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-savino-gold/20 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3.5 h-3.5 text-savino-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <span class="text-gray-700 text-sm">{{ item }}</span>
                        </li>
                    </ul>
                </div>
                <div class="bg-gradient-to-br from-savino-blue/10 to-savino-gold/10 rounded-2xl p-8 flex items-center justify-center min-h-[300px]">
                    <div class="text-center">
                        <span class="text-6xl">🏐</span>
                        <p class="text-savino-blue font-bold mt-4 text-lg">{{ cd.camp_badge_title || 'Summer Camp 2026' }}</p>
                        <p class="text-gray-500 text-sm mt-1">{{ cd.camp_badge_subtitle || $t('summer_camp.badge_subtitle') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Activities Grid -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">{{ cd.activities_section_label || $t('summer_camp.activities_label') }}</span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                    {{ cd.activities_title || $t('summer_camp.activities_title') }}
                </h2>
                <div class="w-12 h-1 bg-savino-gold mx-auto mt-4"></div>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <div
                    v-for="(activity, index) in activities"
                    :key="index"
                    class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1 border border-gray-100"
                >
                    <span class="text-4xl block mb-4">{{ activity.icon }}</span>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">
                        {{ activity.title }}
                    </h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        {{ activity.description }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Dates -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]">{{ cd.dates_section_label || $t('summer_camp.dates_label') }}</span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                    {{ cd.dates_title || $t('summer_camp.dates_title') }}
                </h2>
                <div class="w-12 h-1 bg-savino-gold mx-auto mt-4"></div>
            </div>
            <div class="space-y-4">
                <div
                    v-for="(dateInfo, index) in dates"
                    :key="index"
                    class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-gray-50 rounded-xl p-6 border border-gray-100 hover:border-savino-blue/30 transition-colors"
                >
                    <div class="flex items-center gap-4 mb-3 sm:mb-0">
                        <span class="w-10 h-10 rounded-full bg-savino-blue/10 flex items-center justify-center text-savino-blue font-bold text-sm">
                            {{ index + 1 }}
                        </span>
                        <div>
                            <h4 class="font-bold text-gray-900">{{ dateInfo.period }}</h4>
                            <p class="text-gray-500 text-sm">{{ dateInfo.dates }}</p>
                        </div>
                    </div>
                    <span
                        class="text-xs font-bold uppercase tracking-wider px-4 py-1.5 rounded-full"
                        :class="dateInfo.status === $t('summer_camp.status_limited')
                            ? 'bg-savino-red/10 text-savino-red'
                            : dateInfo.status === $t('summer_camp.status_coming_soon')
                                ? 'bg-gray-200 text-gray-500'
                                : 'bg-savino-gold/10 text-savino-gold'"
                       
                    >
                        {{ dateInfo.status }}
                    </span>
                </div>
            </div>
            <div class="text-center mt-12">
                <a
                    :href="cd.cta_url || (contact.email ? 'mailto:' + contact.email : '#')"
                    class="inline-flex items-center gap-2 bg-savino-gold text-white font-bold uppercase tracking-wider text-sm px-8 py-4 rounded-lg hover:bg-savino-gold/90 transition-colors"
                >
                    {{ cd.cta_text || $t('summer_camp.cta_enroll') }}
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <!-- Dynamic Content -->
    <section v-if="page?.content" class="py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg max-w-none" v-html="safeContent"></div>
        </div>
    </section>
</template>
