<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useFormatPrice } from '@/Composables/useFormatPrice';
import { useTranslations } from '@/Composables/useTranslations.js';

const $t = useTranslations();
const { formatPrice } = useFormatPrice();

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
});

const ogMeta = useOgMeta({
    title: $t('my_orders.og_title'),
});

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString(document.documentElement.lang === 'en' ? 'en-GB' : 'it-IT');
};
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[30vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter">
                    {{ $t('my_orders.title') }}
                </h1>
            </div>
        </section>

        <!-- ORDERS SECTION -->
        <section class="py-16 bg-gray-50 min-h-screen">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div v-if="orders.data.length === 0" class="bg-white rounded-2xl shadow-sm p-12 text-center border border-gray-100">
                    <div class="mx-auto w-24 h-24 mb-6 text-gray-300">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $t('my_orders.empty_title') }}</h3>
                    <p class="text-gray-500 mb-8">{{ $t('my_orders.empty_description') }}</p>
                    <Link :href="route('shop')" class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-savino-blue hover:bg-savino-blue/90 transition-colors duration-200">
                        {{ $t('my_orders.go_to_shop') }}
                    </Link>
                </div>

                <div v-else class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('my_orders.col_order') }}</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('my_orders.col_date') }}</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('my_orders.col_status') }}</th>
                                    <th scope="col" class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('my_orders.col_items') }}</th>
                                    <th scope="col" class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('my_orders.col_total') }}</th>
                                    <th scope="col" class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('my_orders.col_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="order in orders.data" :key="order.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900">{{ order.order_number }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(order.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="{
                                            'bg-yellow-100 text-yellow-800': order.status_color === 'warning',
                                            'bg-green-100 text-green-800': order.status_color === 'success',
                                            'bg-red-100 text-red-800': order.status_color === 'danger',
                                            'bg-blue-100 text-blue-800': order.status_color === 'info',
                                            'bg-gray-100 text-gray-800': !order.status_color
                                        }">
                                            {{ order.status_label || order.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                                        {{ order.items_count || 0 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right text-gray-900">
                                        {{ formatPrice(order.total_price) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link :href="route('shop.order.show', order.order_number)" class="text-savino-gold hover:text-savino-blue transition-colors">
                                            {{ $t('my_orders.details') }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="orders.links && orders.links.length > 3" class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <!-- Mobile pagination logic here if needed -->
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    {{ $t('my_orders.showing') }} <span class="font-medium">{{ orders.from }}</span> {{ $t('my_orders.to') }} <span class="font-medium">{{ orders.to }}</span> {{ $t('my_orders.of') }} <span class="font-medium">{{ orders.total }}</span> {{ $t('my_orders.results') }}
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <template v-for="(link, k) in orders.links" :key="k">
                                        <div v-if="link.url === null" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-300" v-html="link.label"></div>
                                        <Link v-else :href="link.url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium" :class="{'text-savino-gold font-bold bg-gray-50 z-10': link.active, 'text-gray-500 hover:bg-gray-50': !link.active}" v-html="link.label"></Link>
                                    </template>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </PublicLayout>
</template>
