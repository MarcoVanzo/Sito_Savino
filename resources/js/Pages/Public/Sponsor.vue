<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';

const { onImgError } = useImageFallback();

const $t = useTranslations();

const props = defineProps({
    page: Object,
    // Livelli già ordinati e tradotti dal server (App\Services\SponsorDirectory):
    // qui si decide solo quanto grandi mostrare i loghi.
    tiers: {
        type: Array,
        default: () => [],
    },
});

const inertiaPage = usePage();
const settings = computed(() => inertiaPage.props.siteSettings ?? {});
const contact = computed(() => settings.value.contact ?? {});
const cd = computed(() => props.page?.content_data ?? {});

// Il livello decide l'impaginazione: il title sponsor grande e da solo,
// i supporter piccoli e in fila.
// Il fondo e' bianco per tutti: un logo e' disegnato per stare sul bianco, e i
// riquadri colorati ne cambiavano la resa. Il livello decide solo quanto grande
// e' il riquadro e quanti ne stanno in fila.
//
// Il logo riempie il riquadro invece di galleggiarci dentro: si fissa
// l'altezza del riquadro e l'immagine ci sta dentro per intero
// (`object-contain`), cosi' marchi larghi e marchi quadrati restano
// otticamente della stessa importanza.
const sizeConfig = {
    hero: { box: 'h-32 md:h-40 p-6', cols: 'grid-cols-1', gap: 'gap-8', wrap: 'max-w-md mx-auto' },
    large: { box: 'h-24 md:h-28 p-5', cols: 'grid-cols-2 sm:grid-cols-3', gap: 'gap-5', wrap: '' },
    medium: { box: 'h-20 md:h-24 p-4', cols: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4', gap: 'gap-4', wrap: '' },
    small: { box: 'h-16 md:h-20 p-3', cols: 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-5', gap: 'gap-3', wrap: '' },
};

const configFor = (size) => sizeConfig[size] ?? sizeConfig.small;

// L'indirizzo a cui arrivano le richieste di sponsorizzazione, con oggetto
// già scritto: chi riceve la mail sa da dove arriva senza aprirla.
const sponsorMailto = computed(() => {
    const address = cd.value.contact_email || contact.value.email;

    if (!address) {
        return null;
    }

    const subject = cd.value.contact_subject || $t('sponsor.mail_subject');

    return `mailto:${address}?subject=${encodeURIComponent(subject)}`;
});

// I numeri d'impatto arrivano dal CMS (Pagine → Sponsor): quelli che stavano
// qui comparivano online anche con i campi del pannello vuoti.
const impactStats = computed(() => [
    { value: cd.value.stat1_value, label: cd.value.stat1_label || $t('sponsor.stat_social') },
    { value: cd.value.stat2_value, label: cd.value.stat2_label || $t('sponsor.stat_spectators') },
    { value: cd.value.stat3_value, label: cd.value.stat3_label || $t('sponsor.stat_events') },
].filter((stat) => stat.value));

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('sponsor.og_title'),
    description: props.page?.meta_description || $t('sponsor.og_description'),
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
        <!-- HERO SECTION -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.04]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Crect x=&quot;10&quot; y=&quot;10&quot; width=&quot;40&quot; height=&quot;40&quot; rx=&quot;4&quot; fill=&quot;none&quot; stroke=&quot;%23C5A55A&quot; stroke-width=&quot;1&quot;/%3E%3C/svg%3E'); background-size: 60px 60px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_subtitle || $t('sponsor.hero_subtitle') }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ page?.title ?? $t('sponsor.og_title') }}
                </h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">
                    {{ cd.hero_description || $t('sponsor.hero_description') }}
                </p>
            </div>
        </section>

        <!-- SPONSOR TIERS -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <div v-if="tiers.length === 0" class="text-center py-8">
                    <p class="text-gray-500 text-lg">{{ $t('sponsor.tier_empty') }}</p>
                </div>

                <div
                    v-for="tier in tiers"
                    :key="tier.key"
                    class="mb-20 last:mb-0"
                >
                    <!-- Tier Header -->
                    <div class="text-center mb-10">
                        <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.2em]">{{ tier.label }}</span>
                        <div class="w-12 h-0.5 bg-savino-fucsia mx-auto mt-3"></div>
                    </div>

                    <!-- Sponsor Logos Grid -->
                    <div
                        class="grid items-center justify-items-center"
                        :class="[configFor(tier.size).cols, configFor(tier.size).gap, configFor(tier.size).wrap]"
                    >
                        <component
                            :is="sponsor.website_url ? 'a' : 'div'"
                            v-for="sponsor in tier.sponsors"
                            :key="sponsor.id"
                            :href="sponsor.website_url || undefined"
                            :target="sponsor.website_url ? '_blank' : undefined"
                            :rel="sponsor.website_url ? 'noopener noreferrer' : undefined"
                            :title="sponsor.name"
                            class="w-full rounded-xl bg-white border border-gray-200 flex items-center justify-center transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5"
                            :class="configFor(tier.size).box"
                        >
                            <img
                                v-if="sponsor.logo_url"
                                :src="sponsor.logo_url"
                                :alt="sponsor.name"
                                class="w-full h-full object-contain"
                                loading="lazy"
                                @error="onImgError"
                            />
                            <div v-else class="w-full h-full flex flex-col items-center justify-center">
                                <div class="text-savino-blue font-bold text-center uppercase tracking-wide">
                                    {{ sponsor.name }}
                                </div>
                            </div>
                        </component>
                    </div>
                </div>
            </div>
        </section>

        <!-- BECOME A SPONSOR CTA -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-savino-blue">
            <div class="max-w-4xl mx-auto text-center">
                <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.2em]">{{ cd.cta_subtitle || $t('sponsor.cta_subtitle') }}</span>
                <h2 class="text-3xl md:text-4xl font-black text-white uppercase tracking-tighter mt-3">
                    {{ cd.cta_title || $t('sponsor.cta_title') }}
                </h2>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-8"></div>
                <p class="text-white/80 text-lg leading-relaxed max-w-2xl mx-auto mb-10">
                    {{ cd.cta_description || $t('sponsor.cta_description') }}
                </p>
                <div v-if="impactStats.length" class="grid sm:grid-cols-3 gap-6 mb-12">
                    <div
                        v-for="(stat, index) in impactStats"
                        :key="index"
                        class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-6"
                    >
                        <div class="text-savino-fucsia text-3xl font-black">{{ stat.value }}</div>
                        <div class="text-white/60 text-sm font-medium mt-1">{{ stat.label }}</div>
                    </div>
                </div>
                <a
                    v-if="sponsorMailto"
                    :href="sponsorMailto"
                    class="inline-flex items-center gap-2 bg-savino-fucsia text-savino-blue px-8 py-4 font-bold text-sm uppercase tracking-wider rounded-lg hover:bg-savino-fucsia/90 transition-colors"
                >
                    {{ cd.cta_button_text || $t('sponsor.cta_button') }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                </a>
            </div>
        </section>
    </PublicLayout>
</template>
