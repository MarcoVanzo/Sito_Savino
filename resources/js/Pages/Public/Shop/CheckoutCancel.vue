<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';

const $t = (typeof window !== 'undefined' && window.$t) ? window.$t : ((key) => key);

const props = defineProps({
    order: {
        type: Object,
        default: null,
    },
    message: {
        type: String,
        default: null,
    },
});

const ogMeta = useOgMeta({
    title: 'Pagamento Annullato - Savino Del Bene Volley',
    description: 'Il pagamento dell\'ordine non è stato completato.',
});
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[30vh] flex items-center justify-center overflow-hidden bg-amber-500">
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter">
                    Pagamento non completato
                </h1>
            </div>
        </section>

        <!-- DETAILS SECTION -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 p-8 text-center">
                    
                    <p class="text-gray-700 text-lg mb-6">
                        {{ message || "Il pagamento è stato annullato. L'ordine è ancora in sospeso." }}
                    </p>

                    <template v-if="order">
                        <p class="text-gray-500 text-sm mb-2">Riferimento ordine:</p>
                        <p class="text-xl font-bold text-gray-900 mb-8">{{ order.order_number }}</p>
                        
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a v-if="order.order_token" :href="route('shop.order.receipt', order.order_token)" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 shadow-sm text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                                Dettagli Ordine
                            </a>
                            <Link :href="route('shop.checkout')" class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-savino-blue hover:bg-savino-blue/90 transition-colors duration-200">
                                Riprova il pagamento
                            </Link>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex justify-center mt-6">
                            <Link :href="route('shop.index')" class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-savino-blue hover:bg-savino-blue/90 transition-colors duration-200">
                                Torna allo shop
                            </Link>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
