<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useFormatPrice } from '@/Composables/useFormatPrice';

const $t = (typeof window !== 'undefined' && window.$t) ? window.$t : ((key) => key);
const { formatPrice } = useFormatPrice();

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const ogMeta = useOgMeta({
    title: 'Ordine Confermato - Savino Del Bene Volley',
    description: 'Il tuo ordine è stato confermato con successo.',
});
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
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
                    Ordine Confermato!
                </h1>
                <p class="mt-4 text-white/90 text-lg">
                    Grazie per il tuo acquisto.
                </p>
            </div>
        </section>

        <!-- ORDER DETAILS SECTION -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 p-8 text-center">
                    <p class="text-gray-500 mb-2">Il tuo numero d'ordine è:</p>
                    <h2 class="text-3xl font-bold text-savino-blue mb-6">{{ order.order_number }}</h2>

                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 mb-8 text-savino-blue">
                        <template v-if="order.payment_gateway === 'bank_transfer'">
                            <p class="font-medium">In attesa del bonifico. Dettagli bancari inviati via email.</p>
                        </template>
                        <template v-else>
                            <p class="font-medium">Pagamento ricevuto con successo.</p>
                        </template>
                    </div>

                    <div class="border-t border-b border-gray-100 py-6 mb-8 text-left space-y-4">
                        <div class="flex justify-between items-center text-lg">
                            <span class="text-gray-600">Totale Ordine:</span>
                            <span class="font-bold text-gray-900">{{ formatPrice(order.total_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Metodo di pagamento:</span>
                            <span class="font-medium text-gray-900 capitalize">{{ order.payment_gateway.replace('_', ' ') }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a :href="route('shop.order.receipt', order.order_token)" class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-savino-blue hover:bg-savino-blue/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-savino-blue transition-colors duration-200">
                            Scarica Ricevuta
                        </a>
                        <Link :href="route('shop.index')" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 shadow-sm text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-savino-blue transition-colors duration-200">
                            Continua lo shopping
                        </Link>
                    </div>

                    <div class="mt-8 text-sm" v-if="$page.props.auth.user">
                        <Link :href="route('shop.my-orders')" class="text-savino-gold hover:underline font-medium">
                            Vedi i tuoi ordini
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
