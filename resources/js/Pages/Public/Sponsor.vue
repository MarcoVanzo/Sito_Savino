<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    page: Object,
    sponsors: {
        type: Object,
        default: () => ({
            main: [],
            gold: [],
            silver: [],
            technical: [],
            standard: [],
        }),
    },
});

const tierConfig = {
    main: { label: 'Title Sponsor', bg: 'bg-gradient-to-br from-savino-gold/20 to-savino-gold/5', border: 'border-savino-gold/30', size: 'h-32 md:h-40', cols: 'grid-cols-1 sm:grid-cols-2', gap: 'gap-8' },
    gold: { label: 'Gold Partner', bg: 'bg-gradient-to-br from-yellow-50 to-amber-50', border: 'border-amber-200', size: 'h-24 md:h-32', cols: 'grid-cols-2 sm:grid-cols-3', gap: 'gap-6' },
    silver: { label: 'Silver Partner', bg: 'bg-gray-50', border: 'border-gray-200', size: 'h-20 md:h-24', cols: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4', gap: 'gap-5' },
    technical: { label: 'Partner Tecnici', bg: 'bg-white', border: 'border-gray-100', size: 'h-16 md:h-20', cols: 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-5', gap: 'gap-4' },
    standard: { label: 'Supporter', bg: 'bg-white', border: 'border-gray-100', size: 'h-14 md:h-16', cols: 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-6', gap: 'gap-4' },
};

const tiers = ['main', 'gold', 'silver', 'technical', 'standard'];
</script>

<template>
    <Head>
        <title>{{ page?.title ?? 'Sponsor & Partner' }} — Savino Del Bene Volley</title>
        <meta v-if="page?.meta_description" name="description" :content="page.meta_description" />
        <meta v-else name="description" content="Scopri i partner e gli sponsor della Savino Del Bene Volley. Un network di eccellenza che supporta la pallavolo femminile italiana." />
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.04]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Crect x=&quot;10&quot; y=&quot;10&quot; width=&quot;40&quot; height=&quot;40&quot; rx=&quot;4&quot; fill=&quot;none&quot; stroke=&quot;%23C5A55A&quot; stroke-width=&quot;1&quot;/%3E%3C/svg%3E'); background-size: 60px 60px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]" style="font-family: 'Montserrat', sans-serif;">I Nostri Partner</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4" style="font-family: 'Montserrat', sans-serif;">
                    {{ page?.title ?? 'Sponsor & Partner' }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto" style="font-family: 'Montserrat', sans-serif;">
                    Un network di eccellenza che condivide la passione per lo sport e i valori della Savino Del Bene Volley. Insieme, costruiamo il futuro della pallavolo.
                </p>
            </div>
        </section>

        <!-- SPONSOR TIERS -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <div
                    v-for="tier in tiers"
                    :key="tier"
                    class="mb-20 last:mb-0"
                >
                    <!-- Tier Header -->
                    <div class="text-center mb-10">
                        <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]" style="font-family: 'Montserrat', sans-serif;">{{ tierConfig[tier].label }}</span>
                        <div class="w-12 h-0.5 bg-savino-gold mx-auto mt-3"></div>
                    </div>

                    <!-- Sponsor Logos Grid -->
                    <div
                        v-if="sponsors[tier] && sponsors[tier].length > 0"
                        class="grid items-center justify-items-center"
                        :class="[tierConfig[tier].cols, tierConfig[tier].gap]"
                    >
                        <div
                            v-for="(sponsor, index) in sponsors[tier]"
                            :key="index"
                            class="w-full rounded-xl p-6 flex items-center justify-center transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 border"
                            :class="[tierConfig[tier].bg, tierConfig[tier].border]"
                        >
                            <img
                                v-if="sponsor.logo_url"
                                :src="sponsor.logo_url"
                                :alt="sponsor.name"
                                class="max-w-full object-contain"
                                :class="tierConfig[tier].size"
                                loading="lazy"
                            />
                            <div v-else class="flex flex-col items-center justify-center" :class="tierConfig[tier].size">
                                <div class="text-savino-blue font-bold text-center uppercase tracking-wide" style="font-family: 'Montserrat', sans-serif;">
                                    {{ sponsor.name }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty Tier Placeholder -->
                    <div v-else class="text-center py-8">
                        <div class="inline-flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-full px-6 py-3">
                            <div class="w-2 h-2 rounded-full bg-savino-gold/50"></div>
                            <span class="text-gray-400 text-sm font-medium" style="font-family: 'Montserrat', sans-serif;">Sponsor in fase di definizione</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- BECOME A SPONSOR CTA -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-savino-blue">
            <div class="max-w-4xl mx-auto text-center">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.2em]" style="font-family: 'Montserrat', sans-serif;">Unisciti a Noi</span>
                <h2 class="text-3xl md:text-4xl font-black text-white uppercase tracking-tighter mt-3" style="font-family: 'Montserrat', sans-serif;">
                    Diventa Partner
                </h2>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-8"></div>
                <p class="text-white/80 text-lg leading-relaxed max-w-2xl mx-auto mb-10" style="font-family: 'Montserrat', sans-serif;">
                    Associa il tuo brand a una realtà sportiva di primo livello. Offriamo pacchetti di visibilità personalizzati con presenza su maglia, LED bordocampo, digital e hospitality al Palazzo Wanny.
                </p>
                <div class="grid sm:grid-cols-3 gap-6 mb-12">
                    <div v-for="(stat, index) in [
                        { value: '2M+', label: 'Impressioni Social' },
                        { value: '50K+', label: 'Spettatori Stagione' },
                        { value: '100+', label: 'Eventi Annuali' }
                    ]" :key="index" class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-6">
                        <div class="text-savino-gold text-3xl font-black" style="font-family: 'Montserrat', sans-serif;">{{ stat.value }}</div>
                        <div class="text-white/60 text-sm font-medium mt-1" style="font-family: 'Montserrat', sans-serif;">{{ stat.label }}</div>
                    </div>
                </div>
                <button class="inline-flex items-center gap-2 bg-savino-gold text-savino-blue px-8 py-4 font-bold text-sm uppercase tracking-wider rounded-lg hover:bg-savino-gold/90 transition-colors" style="font-family: 'Montserrat', sans-serif;">
                    Contattaci per una Proposta
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                </button>
            </div>
        </section>
    </PublicLayout>
</template>
