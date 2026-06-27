<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    page: Object,
    products: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head>
        <title>{{ page?.title ?? 'Shop Ufficiale' }} — Savino Del Bene Volley</title>
        <meta v-if="page?.meta_description" name="description" :content="page.meta_description" />
        <meta v-else name="description" content="Lo shop ufficiale della Savino Del Bene Volley. Maglie, abbigliamento e merchandise per i tifosi." />
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.05]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Merchandise Ufficiale</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ page?.title ?? 'Shop Ufficiale' }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">
                    Indossa i colori della Savino Del Bene Volley. Maglie ufficiali, abbigliamento e accessori per i veri tifosi.
                </p>
            </div>
        </section>

        <!-- PRODUCTS GRID or COMING SOON -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <!-- Products Available -->
                <div v-if="products.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    <div
                        v-for="product in products"
                        :key="product.id"
                        class="group bg-white border border-gray-100 rounded-lg overflow-hidden shadow-sm hover:shadow-xl transition-all duration-500 transform hover:-translate-y-1"
                    >
                        <!-- Product Image -->
                        <div class="relative aspect-square bg-gray-50 overflow-hidden">
                            <img
                                v-if="product.image_url"
                                :src="product.image_url"
                                :alt="product.name"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                loading="lazy"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <svg class="w-16 h-16 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                            </div>
                            <!-- Quick View Overlay -->
                            <div class="absolute inset-0 bg-savino-blue/80 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                <span class="text-white text-sm font-bold uppercase tracking-wider border-2 border-savino-gold px-6 py-3 hover:bg-savino-gold hover:text-savino-blue transition-colors">Dettagli</span>
                            </div>
                        </div>

                        <!-- Product Info -->
                        <div class="p-5">
                            <h3 class="text-savino-blue font-bold text-lg mb-2 group-hover:text-savino-gold transition-colors">
                                {{ product.name }}
                            </h3>
                            <p class="text-savino-red font-black text-xl">
                                € {{ typeof product.price === 'number' ? product.price.toFixed(2) : product.price }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Coming Soon State -->
                <div v-else class="text-center py-20">
                    <div class="max-w-lg mx-auto">
                        <!-- Animated Shopping Icon -->
                        <div class="w-32 h-32 mx-auto mb-8 rounded-full bg-gradient-to-br from-savino-blue to-gray-900 flex items-center justify-center shadow-2xl">
                            <svg class="w-14 h-14 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        </div>
                        <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mb-4">
                            Negozio in Arrivo
                        </h2>
                        <div class="w-16 h-1 bg-savino-gold mx-auto mb-6"></div>
                        <p class="text-gray-600 text-lg leading-relaxed mb-8">
                            Stiamo preparando qualcosa di speciale per te. Il nostro shop ufficiale sarà presto online con maglie, abbigliamento e merchandise esclusivo della Savino Del Bene Volley.
                        </p>
                        <div class="inline-flex items-center gap-3 bg-savino-blue/5 border border-savino-blue/10 rounded-full px-8 py-4">
                            <div class="w-2 h-2 rounded-full bg-savino-gold animate-pulse"></div>
                            <span class="text-savino-blue text-sm font-bold uppercase tracking-wider">Prossimamente</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
