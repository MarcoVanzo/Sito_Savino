<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useLocale } from '@/Composables/useLocale.js';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useAuctionCheckout } from '@/Composables/useAuctionCheckout.js';

const $t = useTranslations();
const { formatDate } = useLocale();

const props = defineProps({
    // Il controller passa solo id, title e winner_checkout_deadline.
    auction: { type: Object, default: () => ({}) },
});

const { localized } = useAuctionCheckout(props);

const auctionTitle = computed(() => localized(props.auction?.title));

const deadline = computed(() => {
    const raw = props.auction?.winner_checkout_deadline;
    if (!raw) return '';
    return formatDate(raw, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
});

const ogMeta = useOgMeta({
    title: $t('auction_checkout.expired_og_title'),
    description: $t('auction_checkout.expired_og_description'),
});
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="robots" content="noindex, nofollow" />
        <meta name="description" :content="ogMeta.description" />
    </Head>

    <PublicLayout>
        <!-- HERO -->
        <section class="relative min-h-[30vh] flex items-center justify-center overflow-hidden bg-gray-700">
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <svg class="w-10 h-10 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter">
                    {{ $t('auction_checkout.expired_title') }}
                </h1>
            </div>
        </section>

        <!-- DETTAGLI -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 p-8 text-center">

                    <p class="text-gray-700 text-lg mb-6">
                        {{ $t('auction_checkout.expired_message') }}
                    </p>

                    <div v-if="auctionTitle" class="bg-gray-50 rounded-lg px-4 py-3 mb-4">
                        <span class="block text-[10px] font-bold uppercase tracking-widest text-savino-fucsia mb-1">
                            {{ $t('auction_checkout.expired_lot') }}
                        </span>
                        <p class="font-medium text-gray-900">{{ auctionTitle }}</p>
                    </div>

                    <p v-if="deadline" class="text-sm text-gray-500 mb-8">
                        {{ $t('auction_checkout.expired_deadline_was') }} <strong class="text-gray-900">{{ deadline }}</strong>
                    </p>

                    <p class="text-sm text-gray-500 mb-8">
                        {{ $t('auction_checkout.expired_contact') }}
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link
                            :href="route('shop.auctions.index')"
                            class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-savino-blue hover:bg-savino-blue/90 transition-colors duration-200"
                        >
                            {{ $t('auction_checkout.back_to_auctions') }}
                        </Link>
                        <Link
                            :href="route('contatti')"
                            class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 shadow-sm text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200"
                        >
                            {{ $t('auction_checkout.contact_us') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
