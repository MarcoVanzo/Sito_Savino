<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useAuctionCheckout } from '@/Composables/useAuctionCheckout.js';

const $t = useTranslations();
const { formatPrice } = useFormatPrice();

const props = defineProps({
    auction: { type: Object, default: () => ({}) },
    order: { type: Object, default: null },
    canRetry: { type: Boolean, default: false },
    // Il controller passa null quando l'ordine non è più pagabile.
    retryUrl: { type: String, default: null },
    message: { type: String, default: null },
});

const { localized, checkoutToken } = useAuctionCheckout(props);

const auctionTitle = computed(() => localized(props.auction?.title));

// Se retryUrl non arriva (props del controller in evoluzione) si ricostruisce
// dal token, così il pulsante "riprova" non sparisce.
const resolvedRetryUrl = computed(() => {
    if (!props.canRetry) return null;
    if (props.retryUrl) return props.retryUrl;
    if (!checkoutToken.value) return null;
    try {
        return route('shop.auction-checkout.show', { token: checkoutToken.value });
    } catch {
        return null;
    }
});

const ogMeta = useOgMeta({
    title: $t('auction_checkout.cancel_og_title'),
    description: $t('auction_checkout.cancel_og_description'),
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
        <section class="relative min-h-[30vh] flex items-center justify-center overflow-hidden bg-amber-500">
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter">
                    {{ $t('checkout_cancel.title') }}
                </h1>
            </div>
        </section>

        <!-- DETTAGLI -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 p-8 text-center">

                    <p class="text-gray-700 text-lg mb-6">
                        {{ message || (canRetry ? $t('auction_checkout.cancel_message_retry') : $t('auction_checkout.cancel_message')) }}
                    </p>

                    <div v-if="auctionTitle" class="bg-gray-50 rounded-lg px-4 py-3 mb-6">
                        <span class="block text-[10px] font-bold uppercase tracking-widest text-savino-fucsia mb-1">
                            {{ $t('auction_checkout.badge_won') }}
                        </span>
                        <p class="font-medium text-gray-900">{{ auctionTitle }}</p>
                    </div>

                    <template v-if="order">
                        <p class="text-gray-500 text-sm mb-1">{{ $t('checkout_cancel.order_ref') }}</p>
                        <p class="text-xl font-bold text-gray-900 mb-2">{{ order.order_number ?? '—' }}</p>
                        <p v-if="order.total_price != null" class="text-sm text-gray-500 mb-8">
                            {{ $t('checkout_success.order_total') }} <strong class="text-gray-900">{{ formatPrice(order.total_price) }}</strong>
                        </p>
                    </template>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link
                            v-if="resolvedRetryUrl"
                            :href="resolvedRetryUrl"
                            class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-savino-blue hover:bg-savino-blue/90 transition-colors duration-200"
                        >
                            {{ $t('checkout_cancel.retry_payment') }}
                        </Link>
                        <Link
                            v-if="order?.order_number"
                            :href="route('shop.order.show', order.order_number)"
                            class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 shadow-sm text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200"
                        >
                            {{ $t('checkout_cancel.order_details') }}
                        </Link>
                        <Link
                            v-if="!resolvedRetryUrl"
                            :href="route('shop.auctions.index')"
                            class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 shadow-sm text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200"
                        >
                            {{ $t('auction_checkout.back_to_auctions') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
