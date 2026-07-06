<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';

const $t = useTranslations();

const props = defineProps({
    supportEmail: { type: String, default: null },
    supportPhone: { type: String, default: null },
    supportHours: { type: String, default: null },
    supportNotes: { type: String, default: null },
});

const ogMeta = useOgMeta({
    title: $t('shop_contacts.og_title'),
    description: $t('shop_contacts.og_description'),
});

const contactCards = [
    {
        show: () => props.supportEmail,
        icon: 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75',
        label: $t('shop_contacts.email'),
        value: () => props.supportEmail,
        href: () => 'mailto:' + props.supportEmail,
    },
    {
        show: () => props.supportPhone,
        icon: 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25',
        label: $t('shop_contacts.phone'),
        value: () => props.supportPhone,
        href: () => 'tel:' + props.supportPhone,
    },
    {
        show: () => props.supportHours,
        icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
        label: $t('shop_contacts.hours'),
        value: () => props.supportHours,
        href: () => null,
    },
];
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
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ $t('shop_contacts.hero_label') }}</span>
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ $t('shop_contacts.hero_title') }}
                </h1>
                <p class="text-gray-400 mt-4 max-w-2xl mx-auto">
                    {{ $t('shop_contacts.hero_subtitle') }}
                </p>
            </div>
        </section>

        <!-- CONTENT -->
        <section class="bg-gray-900 py-16">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumbs -->
                <nav class="mb-8 text-sm">
                    <ol class="flex items-center gap-2 text-gray-500">
                        <li><Link :href="route('shop')" class="hover:text-savino-gold transition-colors">{{ $t('shop_contacts.breadcrumb_shop') }}</Link></li>
                        <li>/</li>
                        <li class="text-white">{{ $t('shop_contacts.breadcrumb_current') }}</li>
                    </ol>
                </nav>

                <!-- Contact Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <template v-for="(card, index) in contactCards" :key="index">
                        <div
                            v-if="card.show()"
                            class="bg-gray-800/50 border border-gray-700 rounded-xl p-6 text-center hover:border-savino-gold/30 transition-all duration-300"
                        >
                            <div class="w-14 h-14 rounded-full bg-savino-gold/10 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="card.icon" />
                                </svg>
                            </div>
                            <h3 class="text-gray-400 text-xs font-bold uppercase tracking-[0.2em] mb-2">{{ card.label }}</h3>
                            <component
                                :is="card.href() ? 'a' : 'p'"
                                :href="card.href() || undefined"
                                class="text-white font-bold text-lg"
                                :class="{ 'hover:text-savino-gold transition-colors': card.href() }"
                            >
                                {{ card.value() }}
                            </component>
                        </div>
                    </template>
                </div>

                <!-- Notes -->
                <div v-if="supportNotes" class="mt-8 bg-gray-800/30 border border-gray-800 rounded-xl p-6">
                    <h3 class="text-savino-gold text-xs font-bold uppercase tracking-[0.2em] mb-3">{{ $t('shop_contacts.additional_info') }}</h3>
                    <p class="text-gray-400 text-sm leading-relaxed whitespace-pre-line">{{ supportNotes }}</p>
                </div>

                <!-- Empty State -->
                <div
                    v-if="!supportEmail && !supportPhone && !supportHours"
                    class="text-center py-16"
                >
                    <div class="w-20 h-20 rounded-full bg-gray-800 flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25" />
                        </svg>
                    </div>
                    <p class="text-gray-400 text-lg font-bold">{{ $t('shop_contacts.updating_title') }}</p>
                    <p class="text-gray-500 text-sm mt-2">{{ $t('shop_contacts.updating_description') }}</p>
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
                        {{ $t('shop_contacts.back_to_shop') }}
                    </Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
