<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';

const $t = useTranslations();

defineProps({
    sizeGuides: { type: Array, default: () => [] },
    supportEmail: { type: String, default: null },
});

const ogMeta = useOgMeta({
    title: $t('shop_size_guide.og_title'),
    description: $t('shop_size_guide.og_description'),
});
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
        <meta property="og:title" :content="ogMeta.title" />
        <meta property="og:url" :content="ogMeta.url" />
        <meta property="og:type" :content="ogMeta.type" />
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[25vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.05]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ $t('shop_size_guide.hero_label') }}</span>
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ $t('shop_size_guide.hero_title') }}
                </h1>
                <p class="text-gray-400 mt-4 max-w-2xl mx-auto">
                    {{ $t('shop_size_guide.hero_subtitle') }}
                </p>
            </div>
        </section>

        <!-- CONTENT -->
        <section class="bg-gray-900 py-16">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumbs -->
                <nav class="mb-8 text-sm">
                    <ol class="flex items-center gap-2 text-gray-500">
                        <li><Link :href="route('shop')" class="hover:text-savino-gold transition-colors">{{ $t('shop_size_guide.breadcrumb_shop') }}</Link></li>
                        <li>/</li>
                        <li class="text-white">{{ $t('shop_size_guide.breadcrumb_current') }}</li>
                    </ol>
                </nav>

                <!-- PDF Documents Grid -->
                <div v-if="sizeGuides.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <a
                        v-for="(guide, index) in sizeGuides"
                        :key="index"
                        :href="guide.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group bg-gray-800/50 border border-gray-700 rounded-xl p-6 hover:border-savino-gold/50 hover:bg-gray-800 transition-all duration-300"
                    >
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-savino-gold/10 flex items-center justify-center flex-shrink-0 group-hover:bg-savino-gold/20 transition-colors">
                                <svg class="w-6 h-6 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                            </div>
                            <div class="flex-grow min-w-0">
                                <h3 class="text-white font-bold text-sm uppercase tracking-wider truncate group-hover:text-savino-gold transition-colors">
                                    {{ guide.name }}
                                </h3>
                                <p class="text-gray-500 text-xs mt-1 uppercase tracking-wider">{{ $t('shop_size_guide.pdf_download') }}</p>
                            </div>
                            <svg class="w-5 h-5 text-gray-600 group-hover:text-savino-gold transition-colors flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </div>
                    </a>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-16">
                    <div class="w-20 h-20 rounded-full bg-gray-800 flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <p class="text-gray-400 text-lg font-bold">{{ $t('shop_size_guide.empty_title') }}</p>
                    <p class="text-gray-500 text-sm mt-2">{{ $t('shop_size_guide.empty_description') }}</p>
                </div>

                <!-- Support Contact -->
                <div v-if="supportEmail" class="mt-12 bg-gray-800/30 border border-gray-800 rounded-xl p-6 text-center">
                    <p class="text-gray-400 text-sm">
                        {{ $t('shop_size_guide.need_help') }}
                        <a :href="'mailto:' + supportEmail" class="text-savino-gold hover:underline font-bold ml-1">
                            {{ supportEmail }}
                        </a>
                    </p>
                </div>

                <!-- Back to Shop -->
                <div class="mt-12 text-center">
                    <Link
                        :href="route('shop')"
                        class="inline-flex items-center gap-2 text-gray-400 hover:text-savino-gold transition-colors text-sm font-bold uppercase tracking-wider"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        {{ $t('shop_size_guide.back_to_shop') }}
                    </Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
