<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useSanitize } from '@/Composables/useSanitize'
import { useOgMeta } from '@/Composables/useOgMeta'
import { useSafeUrl } from '@/Composables/useSafeUrl'
import PageMediaTail from '@/Components/PageMediaTail.vue'

const $t = useTranslations();
const { safeUrl } = useSafeUrl();

defineOptions({ layout: PublicLayout })

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    }
})

const { sanitize } = useSanitize()
const safeContent = computed(() => sanitize(props.page?.content))
const cd = computed(() => props.page?.content_data ?? {})

// Progetti e numeri arrivano solo dal CMS: un elenco scritto qui dentro
// finirebbe online senza che in redazione esista niente da modificare.
const projects = computed(() => Array.isArray(cd.value.projects) ? cd.value.projects : [])

const impactNumbers = computed(() => Array.isArray(cd.value.impact_stats) ? cd.value.impact_stats : [])

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('sociale.og_title'),
    description: $t('sociale.og_description'),
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
            <span v-if="cd.hero_badge" class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_badge }}</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                {{ page?.title ?? $t('sociale.og_title') }}
            </h1>
            <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
            <p v-if="cd.hero_description" class="text-white/70 text-lg max-w-2xl mx-auto">
                {{ cd.hero_description }}
            </p>
        </div>
    </section>

    <!-- Mission -->
    <section class="py-20 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center">
                <span v-if="cd.mission_badge" class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.2em]">{{ cd.mission_badge }}</span>
                <h2 v-if="cd.mission_title" class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                    {{ cd.mission_title }}
                </h2>
                <div class="w-12 h-1 bg-savino-fucsia mx-auto mt-4 mb-8"></div>
                <p v-if="cd.mission_text_1" class="text-gray-600 text-lg leading-relaxed">
                    {{ cd.mission_text_1 }}
                </p>
                <p v-if="cd.mission_text_2" class="text-gray-600 text-lg leading-relaxed mt-4">
                    {{ cd.mission_text_2 }}
                </p>
            </div>
        </div>
    </section>

    <!-- Projects Grid -->
    <section v-if="projects.length" class="py-20 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span v-if="cd.initiatives_badge" class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.2em]">{{ cd.initiatives_badge }}</span>
                <h2 v-if="cd.initiatives_title" class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                    {{ cd.initiatives_title }}
                </h2>
                <div class="w-12 h-1 bg-savino-fucsia mx-auto mt-4"></div>
            </div>
            <div class="grid md:grid-cols-2 gap-8">
                <div
                    v-for="(project, index) in projects"
                    :key="index"
                    class="bg-white rounded-2xl p-8 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-100 group"
                >
                    <div class="flex items-start justify-between mb-6">
                        <span v-if="project.icon" class="text-5xl">{{ project.icon }}</span>
                        <span
                            v-if="project.tag"
                            class="text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full"
                            :class="{
                                'bg-savino-blue/10 text-savino-blue': project.color === 'savino-blue',
                                'bg-savino-fucsia/10 text-savino-fucsia': project.color === 'savino-fucsia',
                                'bg-savino-red/10 text-savino-red': project.color === 'savino-red'
                            }"
                           
                        >
                            {{ project.tag }}
                        </span>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight mb-3 group-hover:text-savino-blue transition-colors">
                        {{ project.title }}
                    </h3>
                    <p v-if="project.description" class="text-gray-600 leading-relaxed">
                        {{ project.description }}
                    </p>
                    <div v-if="safeUrl(project.link)" class="mt-6 pt-6 border-t border-gray-100">
                        <a :href="safeUrl(project.link)" class="inline-flex items-center gap-2 text-savino-blue text-sm font-bold uppercase tracking-wider hover:text-savino-fucsia transition-colors">
                            {{ $t('common.discover') }}
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Impact Numbers -->
    <section v-if="impactNumbers.length" class="py-20 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span v-if="cd.results_badge" class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.2em]">{{ cd.results_badge }}</span>
                <h2 v-if="cd.impact_title" class="text-3xl md:text-4xl font-black text-white uppercase tracking-tight mt-2">
                    {{ cd.impact_title }}
                </h2>
                <div class="w-12 h-1 bg-savino-fucsia mx-auto mt-4"></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div
                    v-for="(stat, index) in impactNumbers"
                    :key="index"
                    class="text-center"
                >
                    <div class="text-4xl md:text-5xl font-black text-savino-fucsia mb-2">
                        {{ stat.value }}
                    </div>
                    <div class="text-white/70 text-sm font-medium uppercase tracking-wider">
                        {{ stat.label }}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Dynamic Content -->
    <section v-if="page?.content" class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg max-w-none" v-html="safeContent"></div>
        </div>
    </section>

    <!-- Foto e video del progetto, dopo il racconto -->
    <PageMediaTail
        :video-embed-url="cd.video_embed_url"
        :video-url="cd.video_url"
        :images="page?.gallery_images ?? []"
    />
</template>
