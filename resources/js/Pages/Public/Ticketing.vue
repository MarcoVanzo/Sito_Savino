<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useSanitize } from '@/Composables/useSanitize'
import { useOgMeta } from '@/Composables/useOgMeta'
import { useSafeUrl } from '@/Composables/useSafeUrl'
import { useLocale } from '@/Composables/useLocale'
import { mapCmsPlans } from '@/Support/ticketingPlans.js'
import { blocchiTicketing } from '@/Support/ticketingBlocks.js'

const $t = useTranslations();
const { safeUrl } = useSafeUrl();
const { isEnglish } = useLocale();

// gli slug pubblici sono tradotti, quindi il link va scelto sulla lingua corrente
const contactUrl = computed(() => (isEnglish.value ? '/en/contacts' : '/contatti'))

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    }
})

const { sanitize } = useSanitize()
const safeContent = computed(() => sanitize(props.page?.content))

const cd = computed(() => props.page?.content_data ?? {})

// I piani arrivano SOLO dal CMS, senza prezzi di esempio come fallback:
// la normalizzazione (e il perché) sta in mapCmsPlans, coperta dai test.
const plans = computed(() => mapCmsPlans(cd.value.plans, { t: $t, safeUrl }))

// Lo spazio in evidenza, i vantaggi, le fasi della campagna e la Gift Card:
// ogni blocco compare solo se la redazione lo ha compilato. La biglietteria
// non ha listino (i prezzi cambiano di partita in partita) e vive di questi.
const blocchi = computed(() => blocchiTicketing(cd.value, { t: $t, safeUrl }))
const feature = computed(() => blocchi.value.feature)
const benefits = computed(() => blocchi.value.benefits)
const phases = computed(() => blocchi.value.phases)
const giftCard = computed(() => blocchi.value.giftCard)

// Il listino si mostra se ci sono piani; senza piani resta solo il messaggio
// "campagna non ancora aperta", e solo se la redazione lo ha scritto.
const showPlans = computed(() => plans.value.length > 0 || !!cd.value.plans_empty)
const showInfo = computed(() => !!(cd.value.info_heading || cd.value.online_title || cd.value.online_description || cd.value.boxoffice_title || cd.value.boxoffice_description))

// Link alla biglietteria esterna (Vivaticket): gestito dal CMS, senza link
// il pulsante non esiste — meglio nessun bottone che un bottone che non porta
// da nessuna parte.
const ticketsUrl = computed(() => safeUrl(cd.value.tickets_url))
const ticketsButtonText = computed(() => cd.value.tickets_button_text || $t('ticketing.tickets_button'))

// La scheda "Online" delle informazioni rimanda alla biglietteria: se il
// link sta solo nello spazio in evidenza, usa quello.
const infoUrl = computed(() => ticketsUrl.value || feature.value?.buttonUrl || null)
const infoButtonText = computed(() => (ticketsUrl.value ? ticketsButtonText.value : feature.value?.buttonText))

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('ticketing.og_title'),
    description: props.page?.meta_description || $t('ticketing.og_description'),
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
                <span v-if="cd.hero_label" class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_label }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title ?? $t('ticketing.og_title') }}</h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
                <p v-if="cd.hero_subtitle" class="text-white/70 text-lg max-w-2xl mx-auto">{{ cd.hero_subtitle }}</p>

                <div v-if="ticketsUrl" class="mt-10">
                    <a
                        :href="ticketsUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 bg-savino-fucsia text-savino-blue px-8 py-4 rounded-lg font-bold uppercase tracking-wider text-sm hover:bg-savino-fucsia/90 transition-colors shadow-lg shadow-savino-fucsia/20"
                    >
                        {{ ticketsButtonText }}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                    </a>
                    <p v-if="cd.tickets_note" class="text-white/50 text-xs mt-3">{{ cd.tickets_note }}</p>
                </div>
            </div>
        </section>

        <!-- Spazio in evidenza: testo, grafica e pulsante -->
        <section v-if="feature" class="py-16 bg-white" data-test="ticketing-feature">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    class="grid gap-10 items-center"
                    :class="feature.image && feature.hasText ? 'md:grid-cols-12' : 'grid-cols-1'"
                >
                    <!-- La grafica non è un link: con il solo pulsante sotto, un secondo
                         collegamento identico confonderebbe chi naviga da tastiera o con
                         uno screen reader. -->
                    <div v-if="feature.image" :class="feature.hasText ? 'md:col-span-6' : ''">
                        <div class="overflow-hidden rounded-2xl shadow-xl">
                            <img :src="feature.image" :alt="feature.title || page?.title || ''" class="w-full h-auto object-cover" loading="lazy" />
                        </div>
                    </div>

                    <div v-if="feature.hasText" :class="feature.image ? 'md:col-span-6' : 'max-w-3xl mx-auto text-center'">
                        <h2 v-if="feature.title" class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight">{{ feature.title }}</h2>
                        <div class="w-16 h-1 bg-savino-fucsia mt-4 mb-6" :class="feature.image ? '' : 'mx-auto'"></div>
                        <p v-if="feature.text" class="text-gray-600 text-lg leading-relaxed whitespace-pre-line">{{ feature.text }}</p>
                        <a
                            v-if="feature.buttonUrl"
                            :href="feature.buttonUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 mt-8 bg-savino-blue text-white px-8 py-4 rounded-lg font-bold uppercase tracking-wider text-sm hover:bg-savino-blue/90 transition-colors shadow-lg shadow-savino-blue/20"
                        >
                            {{ feature.buttonText }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                        </a>
                    </div>

                    <!-- Solo grafica e pulsante, senza testo: il pulsante sta sotto l'immagine -->
                    <div v-if="feature.buttonUrl && !feature.hasText" class="text-center">
                        <a
                            :href="feature.buttonUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 bg-savino-blue text-white px-8 py-4 rounded-lg font-bold uppercase tracking-wider text-sm hover:bg-savino-blue/90 transition-colors shadow-lg shadow-savino-blue/20"
                        >
                            {{ feature.buttonText }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Subscription Plans -->
        <section v-if="showPlans" class="py-16 bg-gray-50" data-test="ticketing-plans">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 v-if="cd.plans_heading" class="text-3xl font-black text-gray-900 uppercase tracking-tight text-center mb-2">{{ cd.plans_heading }}</h2>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-12"></div>

                <!-- Nessun listino pubblicato: si dice che non c'è, non si inventa -->
                <div v-if="plans.length === 0" class="max-w-2xl mx-auto text-center bg-white rounded-2xl border border-gray-100 shadow-sm px-8 py-12">
                    <p class="text-gray-600 text-lg">{{ cd.plans_empty }}</p>
                    <Link :href="contactUrl" class="inline-block mt-6 px-6 py-3 rounded-lg bg-savino-blue text-white font-bold uppercase tracking-wider text-sm hover:bg-savino-blue/90 transition-colors">
                        {{ $t('ticketing.plans_empty_cta') }}
                    </Link>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div
                        v-for="plan in plans"
                        :key="plan.name"
                        class="relative rounded-2xl overflow-hidden transition-all duration-500 hover:-translate-y-2"
                        :class="plan.highlight
                            ? 'bg-gradient-to-b from-savino-blue to-savino-blue/90 text-white shadow-2xl shadow-savino-blue/30 scale-105 z-10'
                            : 'bg-white text-gray-900 shadow-lg border border-gray-100 hover:shadow-xl'"
                    >
                        <!-- Popular Badge -->
                        <div v-if="plan.highlight && cd.popular_badge" class="absolute top-0 right-0">
                            <div class="bg-savino-fucsia text-white text-[10px] font-black uppercase tracking-widest px-4 py-1.5 rounded-bl-xl">
                                {{ cd.popular_badge }}
                            </div>
                        </div>

                        <div class="p-8">
                            <!-- Plan Name -->
                            <h3 class="text-sm font-bold uppercase tracking-wider mb-4 text-savino-fucsia">{{ plan.name }}</h3>

                            <!-- Price -->
                            <div class="flex items-baseline gap-1 mb-6">
                                <span class="text-lg" :class="plan.highlight ? 'text-white/60' : 'text-gray-400'">€</span>
                                <span class="text-5xl font-black">{{ plan.price }}</span>
                                <span class="text-sm" :class="plan.highlight ? 'text-white/60' : 'text-gray-400'">/{{ plan.period }}</span>
                            </div>

                            <!-- Tariffe ridotte dello stesso posto -->
                            <dl v-if="plan.rates.length" class="mb-6 space-y-1.5">
                                <div v-for="rate in plan.rates" :key="rate.label" class="flex items-baseline justify-between text-sm">
                                    <dt :class="plan.highlight ? 'text-white/60' : 'text-gray-500'">{{ rate.label }}</dt>
                                    <dd class="font-bold tabular-nums" :class="plan.highlight ? 'text-white' : 'text-savino-blue'">&euro;&nbsp;{{ rate.price }}</dd>
                                </div>
                            </dl>

                            <div class="h-px mb-6" :class="plan.highlight ? 'bg-white/20' : 'bg-gray-100'"></div>

                            <!-- Features -->
                            <ul v-if="plan.features.length" class="space-y-3 mb-8">
                                <li v-for="vantaggio in plan.features" :key="vantaggio" class="flex items-start gap-3 text-sm">
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" :class="plan.highlight ? 'text-savino-fucsia' : 'text-savino-blue'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span :class="plan.highlight ? 'text-white/90' : 'text-gray-600'">{{ vantaggio }}</span>
                                </li>
                            </ul>

                            <!-- CTA: solo se il CMS ha una destinazione, altrimenti niente pulsante -->
                            <a
                                v-if="plan.ctaUrl"
                                :href="plan.ctaUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block text-center w-full py-3.5 rounded-lg font-bold uppercase tracking-wider text-sm transition-all duration-300"
                                :class="plan.highlight
                                    ? 'bg-savino-fucsia text-white hover:bg-savino-fucsia/90 shadow-lg shadow-savino-fucsia/30'
                                    : 'bg-savino-blue text-white hover:bg-savino-blue/90 shadow-lg shadow-savino-blue/20'"
                            >{{ plan.cta }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Vantaggi per gli abbonati -->
        <section v-if="benefits.length" class="py-16 bg-white" data-test="ticketing-benefits">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 v-if="cd.benefits_heading" class="text-3xl font-black text-gray-900 uppercase tracking-tight text-center mb-2">{{ cd.benefits_heading }}</h2>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-12"></div>

                <ul class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <li
                        v-for="(benefit, idx) in benefits"
                        :key="idx"
                        class="flex items-start gap-4 bg-gray-50 rounded-2xl border border-gray-100 px-6 py-5"
                    >
                        <span class="flex-shrink-0 w-8 h-8 rounded-full bg-savino-fucsia/10 text-savino-fucsia flex items-center justify-center mt-0.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        </span>
                        <span
                            class="text-gray-700 leading-relaxed [&_p]:m-0 [&_p+p]:mt-2 [&_strong]:font-bold [&_a]:text-savino-blue [&_a]:underline"
                            v-html="sanitize(benefit)"
                        ></span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Fasi della campagna: conferme, prelazioni, nuovi abbonati -->
        <section v-if="phases.length" class="py-16 bg-white" data-test="ticketing-phases">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 v-if="cd.phases_heading" class="text-3xl font-black text-gray-900 uppercase tracking-tight text-center mb-2">{{ cd.phases_heading }}</h2>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mb-12"></div>

                <ol class="space-y-6">
                    <li
                        v-for="(phase, idx) in phases"
                        :key="idx"
                        class="relative bg-gray-50 rounded-2xl border border-gray-100 p-6 md:p-8 md:pl-24"
                    >
                        <span class="hidden md:flex absolute left-8 top-8 w-10 h-10 rounded-full bg-savino-blue text-white font-black items-center justify-center">{{ idx + 1 }}</span>
                        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 mb-3">
                            <h3 class="text-xl font-black text-savino-blue uppercase tracking-tight">{{ phase.title }}</h3>
                            <span v-if="phase.period" class="text-sm font-bold text-savino-fucsia uppercase tracking-wider">{{ phase.period }}</span>
                        </div>
                        <p v-if="phase.description" class="text-gray-600 leading-relaxed whitespace-pre-line">{{ phase.description }}</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- Gift Card -->
        <section v-if="giftCard" class="py-16 bg-gradient-to-br from-savino-blue via-savino-blue to-gray-900" data-test="ticketing-gift-card">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid gap-10 items-center" :class="giftCard.image ? 'md:grid-cols-12' : 'grid-cols-1'">
                    <div v-if="giftCard.image" class="md:col-span-5 flex justify-center">
                        <img
                            :src="giftCard.image"
                            :alt="giftCard.title"
                            width="390"
                            height="390"
                            loading="lazy"
                            class="w-full max-w-[390px] h-auto rounded-2xl shadow-2xl shadow-black/30"
                        />
                    </div>
                    <div :class="giftCard.image ? 'md:col-span-7' : 'max-w-3xl mx-auto text-center'">
                        <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ $t('ticketing.gift_card_label') }}</span>
                        <h2 class="text-3xl md:text-4xl font-black text-white uppercase tracking-tight mt-3">{{ giftCard.title }}</h2>
                        <div class="w-16 h-1 bg-savino-fucsia mt-4 mb-6" :class="giftCard.image ? '' : 'mx-auto'"></div>
                        <p v-if="giftCard.text" class="text-white/80 text-lg leading-relaxed whitespace-pre-line">{{ giftCard.text }}</p>
                        <a
                            v-if="giftCard.url"
                            :href="giftCard.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 mt-8 bg-savino-fucsia text-white px-8 py-4 rounded-lg font-bold uppercase tracking-wider text-sm hover:bg-savino-fucsia/90 transition-colors shadow-lg shadow-savino-fucsia/30"
                        >
                            {{ giftCard.buttonText }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Info Acquisto -->
        <section v-if="showInfo" class="py-16 bg-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 v-if="cd.info_heading" class="text-3xl font-black text-gray-900 uppercase tracking-tight mb-2">{{ cd.info_heading }}</h2>
                <div class="w-12 h-1 bg-savino-fucsia mb-10"></div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-100">
                        <div class="w-12 h-12 rounded-full bg-savino-blue/10 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 v-if="cd.online_title" class="text-lg font-bold text-gray-900 mb-2">{{ cd.online_title }}</h3>
                        <p v-if="cd.online_description" class="text-gray-500 text-sm leading-relaxed">{{ cd.online_description }}</p>
                        <a
                            v-if="infoUrl"
                            :href="infoUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 mt-4 text-savino-blue font-bold text-sm uppercase tracking-wider hover:underline"
                        >
                            {{ infoButtonText }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </a>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-100">
                        <div class="w-12 h-12 rounded-full bg-savino-fucsia/10 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-savino-fucsia" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <h3 v-if="cd.boxoffice_title" class="text-lg font-bold text-gray-900 mb-2">{{ cd.boxoffice_title }}</h3>
                        <p v-if="cd.boxoffice_description" class="text-gray-500 text-sm leading-relaxed">{{ cd.boxoffice_description }}</p>
                    </div>
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
