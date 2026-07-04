<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { Head, Link } from '@inertiajs/vue3';
import LOGOS from '@/Constants/logos.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';

const $t = useTranslations();

const { onImgError } = useImageFallback();
</script>

<template>
    <Head>
        <title>{{ $t('shop.maintenance_title') || 'Shop in manutenzione' }} — Savino Del Bene Volley</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900 flex items-center justify-center px-4 sm:px-6 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-[0.03]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>

        <!-- Ambient Glow -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-savino-gold/5 rounded-full blur-3xl"></div>

        <div class="relative z-10 text-center max-w-lg">
            <!-- Logo -->
            <div class="mb-10">
                <img
                    :src="LOGOS.VOLLEY"
                    alt="Savino Del Bene Volley"
                    class="h-32 w-auto mx-auto drop-shadow-[0_10px_30px_rgba(0,0,0,0.5)]"
                    @error="onImgError"
                />
            </div>

            <!-- Maintenance Icon -->
            <div class="w-20 h-20 mx-auto mb-8 rounded-full bg-white/10 backdrop-blur-sm flex items-center justify-center">
                <svg class="w-9 h-9 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>

            <!-- Heading -->
            <h1 class="text-3xl md:text-4xl lg:text-5xl font-black text-white uppercase tracking-tighter mb-4">
                {{ $t('shop.maintenance_heading') || 'Shop in manutenzione' }}
            </h1>
            <div class="w-16 h-1 bg-savino-gold mx-auto mb-6"></div>

            <!-- Description -->
            <p class="text-white/70 text-lg leading-relaxed mb-10">
                {{ $t('shop.maintenance_description') || 'Stiamo lavorando per migliorare la tua esperienza di acquisto. Lo shop tornerà disponibile a breve. Grazie per la pazienza!' }}
            </p>

            <!-- Status Indicator -->
            <div class="inline-flex items-center gap-3 bg-white/5 backdrop-blur-sm border border-white/10 rounded-full px-6 py-3 mb-10">
                <div class="w-2 h-2 rounded-full bg-savino-gold animate-pulse"></div>
                <span class="text-white/80 text-sm font-bold uppercase tracking-wider">{{ $t('shop.maintenance_status') || 'Manutenzione in corso' }}</span>
            </div>

            <!-- CTA -->
            <div class="block">
                <Link
                    :href="route('home')"
                    class="inline-flex items-center gap-3 bg-white text-savino-blue font-bold uppercase tracking-wider text-sm px-10 py-4 rounded-xl hover:bg-savino-gold hover:text-savino-blue transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                    {{ $t('shop.back_to_home') || 'Torna alla home' }}
                </Link>
            </div>
        </div>
    </div>
</template>
