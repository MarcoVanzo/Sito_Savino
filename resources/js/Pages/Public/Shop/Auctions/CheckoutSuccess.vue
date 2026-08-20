<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useAuctionCheckout } from '@/Composables/useAuctionCheckout.js';

const $t = useTranslations();
const { formatPrice } = useFormatPrice();
const { onImgError } = useImageFallback();

const props = defineProps({
    auction: { type: Object, default: () => ({}) },
    order: { type: Object, default: () => ({}) },
});

const { localized, auctionImage } = useAuctionCheckout(props);

const auctionTitle = computed(() => localized(props.auction?.title));
const productImage = computed(() => auctionImage(props.auction?.product));

const shippingAddress = computed(() => props.order?.shipping_address ?? null);

const isPaymentConfirmed = computed(() =>
    ['paid', 'processing', 'shipped', 'delivered'].includes(props.order?.status)
);
const isAwaitingWebhook = computed(() => props.order?.status === 'pending');

// Il webhook Stripe può arrivare qualche secondo dopo il redirect: si ricarica
// la sola prop `order` per un massimo di 60s.
let pollInterval = null;
const pollCount = ref(0);
const maxPolls = 12; // 12 × 5s = 60s

onMounted(() => {
    if (!isAwaitingWebhook.value) return;
    pollInterval = setInterval(() => {
        pollCount.value++;
        if (pollCount.value >= maxPolls) {
            clearInterval(pollInterval);
            pollInterval = null;
            return;
        }
        router.reload({ only: ['order'], preserveScroll: true });
    }, 5000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = null;
});

const ogMeta = useOgMeta({
    title: $t('auction_checkout.success_og_title'),
    description: $t('auction_checkout.success_og_description'),
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
        <section class="relative min-h-[30vh] flex items-center justify-center overflow-hidden bg-green-600">
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter">
                    {{ $t('auction_checkout.success_title') }}
                </h1>
                <p class="mt-4 text-white/90 text-lg">
                    {{ $t('auction_checkout.success_thanks') }}
                </p>
            </div>
        </section>

        <!-- DETTAGLI -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 p-8 text-center">

                    <p class="text-gray-500 mb-2">{{ $t('checkout_success.order_number_label') }}</p>
                    <h2 class="text-3xl font-bold text-savino-blue mb-6">{{ order?.order_number ?? '—' }}</h2>

                    <!-- Lotto vinto -->
                    <div class="flex items-center gap-4 text-left bg-gray-50 rounded-lg p-4 mb-6">
                        <div class="w-16 h-16 bg-white rounded-lg overflow-hidden flex-shrink-0 border border-gray-100">
                            <img v-if="productImage" :src="productImage" :alt="auctionTitle" class="w-full h-full object-cover" @error="onImgError" />
                            <div v-else class="w-full h-full flex items-center justify-center text-2xl">🏐</div>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] font-bold uppercase tracking-widest text-savino-fucsia mb-0.5">
                                {{ $t('auction_checkout.badge_won') }}
                            </span>
                            <p class="font-medium text-gray-900 truncate">{{ auctionTitle }}</p>
                        </div>
                    </div>

                    <!-- Indirizzo -->
                    <div v-if="shippingAddress" class="text-left bg-gray-50 rounded-lg p-4 mb-6">
                        <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('checkout_success.shipping_to') }}</h4>
                        <template v-if="shippingAddress?.first_name">
                            <p class="font-medium text-gray-900">{{ shippingAddress.first_name }} {{ shippingAddress.last_name }}</p>
                            <p class="text-gray-600">{{ shippingAddress.street }}</p>
                            <p class="text-gray-600">
                                {{ shippingAddress.zip_code }} {{ shippingAddress.city }}
                                <span v-if="shippingAddress.province">({{ shippingAddress.province }})</span>
                            </p>
                        </template>
                        <template v-else-if="shippingAddress?.raw_address">
                            <p class="text-gray-600 whitespace-pre-line">{{ shippingAddress.raw_address }}</p>
                        </template>
                    </div>

                    <!-- Stato pagamento -->
                    <div class="rounded-lg p-4 mb-8" :class="{
                        'bg-green-50 border border-green-200 text-green-700': isPaymentConfirmed,
                        'bg-amber-50 border border-amber-200 text-amber-700': !isPaymentConfirmed,
                    }">
                        <p v-if="isPaymentConfirmed" class="font-medium flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ $t('checkout_success.payment_received') }}
                        </p>
                        <p v-else class="font-medium flex items-center justify-center gap-2">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ $t('checkout_success.payment_processing') }}
                        </p>
                    </div>

                    <div class="border-t border-b border-gray-100 py-6 mb-8 text-left space-y-4">
                        <div class="flex justify-between items-center text-lg">
                            <span class="text-gray-600">{{ $t('checkout_success.order_total') }}</span>
                            <span class="font-bold text-gray-900">{{ formatPrice(order?.total_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">{{ $t('checkout_success.payment_method') }}</span>
                            <span class="font-medium text-gray-900 capitalize">{{ order?.payment_gateway?.replace('_', ' ') ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a
                            v-if="order?.order_token"
                            :href="route('shop.order.receipt', order.order_token)"
                            class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-savino-blue hover:bg-savino-blue/90 transition-colors duration-200"
                        >
                            {{ $t('checkout_success.download_receipt') }}
                        </a>
                        <Link
                            :href="route('shop.auctions.index')"
                            class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 shadow-sm text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200"
                        >
                            {{ $t('auction_checkout.back_to_auctions') }}
                        </Link>
                    </div>

                    <div class="mt-8 text-sm">
                        <Link :href="route('shop.orders')" class="text-savino-fucsia hover:underline font-medium">
                            {{ $t('checkout_success.view_orders') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
