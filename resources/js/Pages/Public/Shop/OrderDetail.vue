<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useFormatPrice } from '@/Composables/useFormatPrice';
import { useImageFallback } from '@/Composables/useImageFallback';

const $t = useTranslations();
const { formatPrice } = useFormatPrice();
const { onImgError } = useImageFallback();

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const ogMeta = useOgMeta({
    title: $t('shop.order_detail.order_title').replace('{number}', props.order.order_number) + ' - Savino Del Bene Volley',
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute:'2-digit' });
};
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[25vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-12">
                <Link v-if="$page.props.auth.user" :href="route('shop.orders')" class="inline-flex items-center text-sm text-gray-300 hover:text-white mb-4 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    {{ $t('shop.order_detail.back_to_orders') }}
                </Link>
                <h1 class="text-3xl md:text-4xl font-black text-white uppercase tracking-tighter">
                    {{ $t('shop.order_detail.order_title').replace('{number}', order.order_number) }}
                </h1>
                <p class="text-gray-300 mt-2">{{ formatDate(order.created_at) }}</p>
            </div>
        </section>

        <!-- DETAILS SECTION -->
        <section class="py-12 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Left Column: Items and Info -->
                    <div class="flex-1 space-y-8">
                        
                        <!-- Status Banner -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div>
                                <h3 class="text-sm text-gray-500 uppercase tracking-wider font-semibold mb-1">{{ $t('shop.order_detail.order_status') }}</h3>
                                <span class="px-4 py-2 inline-flex text-sm leading-5 font-bold rounded-full" :class="{
                                    'bg-yellow-100 text-yellow-800': order.status_color === 'warning',
                                    'bg-green-100 text-green-800': order.status_color === 'success',
                                    'bg-red-100 text-red-800': order.status_color === 'danger',
                                    'bg-blue-100 text-blue-800': order.status_color === 'info',
                                    'bg-gray-100 text-gray-800': !order.status_color
                                }">
                                    {{ order.status_label || order.status }}
                                </span>
                            </div>
                            <div class="text-center sm:text-right">
                                <a :href="route('shop.order.receipt', order.order_token)" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    {{ $t('shop.order_detail.download_receipt') }}
                                </a>
                            </div>
                        </div>

                        <!-- Items List -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-5 border-b border-gray-100">
                                <h3 class="text-lg font-bold text-gray-900">{{ $t('shop.order_detail.purchased_items') }}</h3>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                <li v-for="item in order.items" :key="item.id" class="p-6 flex items-center gap-6">
                                    <div class="w-20 h-20 flex-shrink-0 bg-gray-50 rounded-md overflow-hidden border border-gray-100">
                                        <img :src="item.image_url || '/images/placeholder.jpg'" :alt="item.name" @error="onImgError" class="w-full h-full object-cover object-center" />
                                    </div>
                                    <div class="flex-1 flex flex-col justify-center">
                                        <h4 class="text-base font-bold text-gray-900">{{ item.name }}</h4>
                                        <p v-if="item.variant_name" class="text-sm text-gray-500 mt-1">{{ item.variant_name }}</p>
                                        <div class="text-sm text-gray-500 mt-2">
                                            {{ $t('shop.order_detail.qty') }}: <span class="font-medium text-gray-900">{{ item.quantity }}</span> x {{ formatPrice(item.price) }}
                                        </div>
                                    </div>
                                    <div class="text-right font-bold text-gray-900">
                                        {{ formatPrice(item.price * item.quantity) }}
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Right Column: Summary & Info -->
                    <div class="lg:w-[400px] space-y-8">
                        
                        <!-- Cost Summary -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-6">{{ $t('shop.order_detail.cost_summary') }}</h3>
                            <div class="space-y-4 text-sm">
                                <div class="flex justify-between text-gray-600">
                                    <span>{{ $t('shop.order_detail.subtotal') }}</span>
                                    <span>{{ formatPrice(order.subtotal || order.total_price - order.shipping_cost) }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <span>{{ $t('shop.order_detail.shipping') }}</span>
                                    <span>{{ formatPrice(order.shipping_cost || 0) }}</span>
                                </div>
                                <div v-if="order.coupon_discount > 0" class="flex justify-between text-savino-red">
                                    <span>{{ $t('shop.order_detail.discount') }}</span>
                                    <span>-{{ formatPrice(order.coupon_discount) }}</span>
                                </div>
                                <div class="border-t border-gray-100 pt-4 flex justify-between items-center">
                                    <span class="text-base font-bold text-gray-900">{{ $t('shop.order_detail.total') }}</span>
                                    <span class="text-xl font-black text-savino-blue">{{ formatPrice(order.total_price) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Info Cards -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">{{ $t('shop.order_detail.shipping_info') }}</h3>
                            <div class="text-sm text-gray-600 space-y-1 bg-gray-50 p-4 rounded-md">
                                <p class="font-bold text-gray-900">{{ order.shipping_address?.first_name }} {{ order.shipping_address?.last_name }}</p>
                                <p>{{ order.shipping_address?.address }}</p>
                                <p>{{ order.shipping_address?.zip_code }} {{ order.shipping_address?.city }}</p>
                                <p>{{ order.shipping_address?.country }}</p>
                            </div>

                            <template v-if="order.tracking_number">
                                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mt-6 mb-4">{{ $t('shop.order_detail.tracking') }}</h3>
                                <div class="bg-blue-50 text-savino-blue p-4 rounded-md text-sm border border-blue-100">
                                    <p class="font-bold">{{ order.tracking_number }}</p>
                                    <a v-if="order.tracking_url" :href="order.tracking_url" target="_blank" class="mt-2 inline-flex items-center text-blue-700 hover:text-blue-900 font-medium">
                                        {{ $t('shop.order_detail.track_shipping') }}
                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </PublicLayout>
</template>
