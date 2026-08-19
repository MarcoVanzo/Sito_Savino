<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useFormatPrice } from '@/Composables/useFormatPrice';
import { useTranslations } from '@/Composables/useTranslations.js';
import { trackPurchase } from '@/meta-pixel.js';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const $t = useTranslations();
const { formatPrice } = useFormatPrice();

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const ogMeta = useOgMeta({
    title: $t('checkout_success.og_title'),
    description: $t('checkout_success.og_description'),
});

// H4: Determine the payment status message based on actual order status + gateway
const isBankTransfer = computed(() => props.order.payment_gateway === 'bank_transfer');
const isPaymentConfirmed = computed(() =>
    ['paid', 'processing', 'shipped', 'delivered'].includes(props.order.status)
);
const isAwaitingWebhook = computed(() =>
    !isBankTransfer.value && props.order.status === 'pending'
);

// Auto-refresh the page every 5s while awaiting webhook, up to 60s max
let pollInterval = null;
let pollCount = ref(0);
const maxPolls = 12; // 12 × 5s = 60s

onMounted(() => {
    // L'ordine con il bonifico non è ancora incassato, ma è comunque una
    // conversione: la campagna ha portato l'acquisto, il resto è amministrazione.
    trackPurchase({
        orderNumber: props.order.order_number,
        value: Number(props.order.total_price),
        items: (props.order.items ?? []).map((item) => ({
            id: item.product_id,
            quantity: item.quantity,
        })),
    });

    if (isAwaitingWebhook.value) {
        pollInterval = setInterval(() => {
            pollCount.value++;
            if (pollCount.value >= maxPolls) {
                clearInterval(pollInterval);
                return;
            }
            router.reload({ only: ['order'], preserveScroll: true });
        }, 5000);
    }
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[30vh] flex items-center justify-center overflow-hidden bg-green-600">
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter">
                    {{ $t('checkout_success.title') }}
                </h1>
                <p class="mt-4 text-white/90 text-lg">
                    {{ $t('checkout_success.thanks') }}
                </p>
            </div>
        </section>

        <!-- ORDER DETAILS SECTION -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 p-8 text-center">
                    <p class="text-gray-500 mb-2">{{ $t('checkout_success.order_number_label') }}</p>
                    <h2 class="text-3xl font-bold text-savino-blue mb-6">{{ order.order_number }}</h2>

                    <!-- Shipping Address -->
                    <div v-if="order.shipping_address" class="text-left bg-gray-50 rounded-lg p-4 mb-6">
                        <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('checkout_success.shipping_to') }}</h4>
                        <template v-if="order.shipping_address?.first_name">
                            <p class="font-medium text-gray-900">{{ order.shipping_address.first_name }} {{ order.shipping_address.last_name }}</p>
                            <p class="text-gray-600">{{ order.shipping_address.street }}</p>
                            <p class="text-gray-600">{{ order.shipping_address.zip_code }} {{ order.shipping_address.city }} <span v-if="order.shipping_address.province">({{ order.shipping_address.province }})</span></p>
                        </template>
                        <template v-else-if="order.shipping_address?.raw_address">
                            <p class="text-gray-600 whitespace-pre-line">{{ order.shipping_address.raw_address }}</p>
                        </template>
                    </div>

                    <!-- Payment Status Banner -->
                    <div class="rounded-lg p-4 mb-8" :class="{
                        'bg-blue-50 border border-blue-100 text-savino-blue': isBankTransfer,
                        'bg-green-50 border border-green-200 text-green-700': isPaymentConfirmed,
                        'bg-amber-50 border border-amber-200 text-amber-700': isAwaitingWebhook,
                    }">
                        <template v-if="isBankTransfer">
                            <p class="font-medium">{{ $t('checkout_success.bank_transfer_pending') }}</p>
                        </template>
                        <template v-else-if="isPaymentConfirmed">
                            <p class="font-medium flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                {{ $t('checkout_success.payment_received') }}
                            </p>
                        </template>
                        <template v-else>
                            <p class="font-medium flex items-center justify-center gap-2">
                                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                {{ $t('checkout_success.payment_processing') }}
                            </p>
                        </template>
                    </div>

                    <div class="border-t border-b border-gray-100 py-6 mb-8 text-left space-y-4">
                        <div class="flex justify-between items-center text-lg">
                            <span class="text-gray-600">{{ $t('checkout_success.order_total') }}</span>
                            <span class="font-bold text-gray-900">{{ formatPrice(order.total_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">{{ $t('checkout_success.payment_method') }}</span>
                            <span class="font-medium text-gray-900 capitalize">{{ order.payment_gateway?.replace('_', ' ') ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a :href="route('shop.order.receipt', order.order_token)" class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-savino-blue hover:bg-savino-blue/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-savino-blue transition-colors duration-200">
                            {{ $t('checkout_success.download_receipt') }}
                        </a>
                        <Link :href="route('shop')" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 shadow-sm text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-savino-blue transition-colors duration-200">
                            {{ $t('checkout_success.continue_shopping') }}
                        </Link>
                    </div>

                    <div class="mt-8 text-sm" v-if="$page.props.auth.user">
                        <Link :href="route('shop.orders')" class="text-savino-gold hover:underline font-medium">
                            {{ $t('checkout_success.view_orders') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

