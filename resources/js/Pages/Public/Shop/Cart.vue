<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { computed, ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useCart } from '@/Composables/useCart.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';


const $t = useTranslations();

const { updateQuantity, removeItem } = useCart();
const { formatPrice } = useFormatPrice();
const { onImgError } = useImageFallback();

const props = defineProps({
    cart: { type: Object, default: () => ({ items: [] }) },
    total: { type: Number, default: 0 },
    itemCount: { type: Number, default: 0 },
});

const ogMeta = useOgMeta({
    title: $t('shop.your_cart'),
    description: $t('shop.cart_description'),
});

const isEmpty = computed(() => !props.cart?.items?.length);

// --- Loading indicators for cart item operations ---
const loadingItems = ref(new Set());

const handleUpdateQuantity = (itemId, newQty) => {
    if (newQty < 1) return;
    loadingItems.value.add(itemId);
    updateQuantity(itemId, newQty);
    // Clear loading state after Inertia finishes
    setTimeout(() => {
        loadingItems.value = new Set([...loadingItems.value].filter(id => id !== itemId));
    }, 600);
};

const handleRemoveItem = (itemId) => {
    loadingItems.value.add(itemId);
    removeItem(itemId);
    setTimeout(() => {
        loadingItems.value = new Set([...loadingItems.value].filter(id => id !== itemId));
    }, 600);
};
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="robots" content="noindex, nofollow" />
        <meta name="description" :content="ogMeta.description" />
        <meta property="og:title" :content="ogMeta.title" />
        <meta property="og:url" :content="ogMeta.url" />
        <meta property="og:type" :content="ogMeta.type" />
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[25vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.05]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ $t('shop.hero_label') }}</span>
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ $t('shop.your_cart') }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4"></div>
            </div>
        </section>

        <!-- CART CONTENT -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <!-- Cart with Items -->
                <div v-if="!isEmpty" class="grid grid-cols-1 lg:grid-cols-3 gap-12">
                    <!-- Cart Items (2/3 width) -->
                    <div class="lg:col-span-2">
                        <!-- Header -->
                        <div class="hidden md:grid grid-cols-12 gap-4 pb-4 border-b-2 border-gray-100 text-xs font-bold text-gray-400 uppercase tracking-wider">
                            <div class="col-span-6">{{ $t('shop.product') }}</div>
                            <div class="col-span-2 text-center">{{ $t('shop.price') }}</div>
                            <div class="col-span-2 text-center">{{ $t('shop.quantity') }}</div>
                            <div class="col-span-2 text-right">{{ $t('shop.total') }}</div>
                        </div>

                        <!-- Items -->
                        <div
                            v-for="item in cart.items"
                            :key="item.id"
                            class="grid grid-cols-1 md:grid-cols-12 gap-4 py-6 border-b border-gray-100 items-center"
                        >
                            <!-- Product Image + Info -->
                            <div class="md:col-span-6 flex items-center gap-4">
                                <div class="w-20 h-20 flex-shrink-0 bg-gray-50 rounded-lg overflow-hidden">
                                    <img
                                        v-if="item.image"
                                        :src="item.image"
                                        :alt="item.name"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                        @error="onImgError"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <svg class="w-8 h-8 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-savino-blue font-bold text-sm">{{ item.name }}</h3>
                                    <p v-if="item.variant" class="text-xs text-gray-500 mt-0.5">{{ item.variant }}</p>
                                    <!-- Mobile: Remove button inline -->
                                    <button
                                        @click="handleRemoveItem(item.id)"
                                        :disabled="loadingItems.has(item.id)"
                                        :aria-label="$t('shop.remove_from_cart') || 'Rimuovi dal carrello'"
                                        class="md:hidden text-xs text-red-400 hover:text-red-600 mt-1 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ $t('shop.remove') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Unit Price -->
                            <div class="md:col-span-2 text-center">
                                <span class="md:hidden text-xs text-gray-400 mr-2">{{ $t('shop.price') }}:</span>
                                <span class="text-sm font-semibold text-gray-700">{{ formatPrice(item.price) }}</span>
                            </div>

                            <!-- Quantity -->
                            <div class="md:col-span-2 flex items-center justify-center">
                                <span class="md:hidden text-xs text-gray-400 mr-2">{{ $t('shop.quantity') }}:</span>
                                <div class="inline-flex items-center border border-gray-200 rounded-lg overflow-hidden">
                                    <button
                                        @click="handleUpdateQuantity(item.id, item.quantity - 1)"
                                        :disabled="item.quantity <= 1 || loadingItems.has(item.id)"
                                        :aria-label="$t('shop.decrease_quantity') || 'Diminuisci quantità'"
                                        class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                    >
                                        <div v-if="loadingItems.has(item.id)" class="animate-spin w-3.5 h-3.5 border-2 border-gray-300 border-t-savino-blue rounded-full"></div>
                                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                                    </button>
                                    <span class="w-10 h-8 flex items-center justify-center text-sm font-bold text-savino-blue border-x border-gray-200">
                                        {{ item.quantity }}
                                    </span>
                                    <button
                                        @click="handleUpdateQuantity(item.id, item.quantity + 1)"
                                        :disabled="item.quantity >= (item.stock ?? 99) || loadingItems.has(item.id)"
                                        :aria-label="$t('shop.increase_quantity') || 'Aumenta quantità'"
                                        class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <div v-if="loadingItems.has(item.id)" class="animate-spin w-3.5 h-3.5 border-2 border-gray-300 border-t-savino-blue rounded-full"></div>
                                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Line Total + Remove -->
                            <div class="md:col-span-2 flex items-center justify-end gap-3">
                                <span class="text-sm font-black text-savino-blue">{{ formatPrice(item.price * item.quantity) }}</span>
                                <!-- Desktop: Remove button -->
                                <button
                                    @click="handleRemoveItem(item.id)"
                                    :disabled="loadingItems.has(item.id)"
                                    class="hidden md:flex w-8 h-8 items-center justify-center rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                    :title="$t('shop.remove')"
                                    :aria-label="$t('shop.remove_from_cart') || 'Rimuovi dal carrello'"
                                >
                                    <div v-if="loadingItems.has(item.id)" class="animate-spin w-4 h-4 border-2 border-gray-300 border-t-red-500 rounded-full"></div>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Continue Shopping -->
                        <div class="mt-8">
                            <Link :href="route('shop')" class="inline-flex items-center gap-2 text-sm text-savino-blue font-semibold hover:text-savino-gold transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                                {{ $t('shop.continue_shopping') }}
                            </Link>
                        </div>
                    </div>

                    <!-- Cart Summary Sidebar (1/3 width) -->
                    <div class="lg:col-span-1">
                        <div class="bg-gray-50 rounded-2xl p-8 sticky top-32">
                            <h3 class="text-lg font-black text-savino-blue uppercase tracking-tight mb-6">{{ $t('shop.order_summary') }}</h3>

                            <div class="space-y-4 mb-6">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">{{ $t('shop.subtotal') }} ({{ itemCount }} {{ $t('shop.items') }})</span>
                                    <span class="font-semibold text-gray-700">{{ formatPrice(total) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">{{ $t('shop.shipping') }}</span>
                                    <span class="text-xs font-medium text-savino-gold uppercase">{{ $t('shop.calculated_at_checkout') }}</span>
                                </div>
                            </div>

                            <div class="border-t-2 border-gray-200 pt-4 mb-8">
                                <div class="flex justify-between">
                                    <span class="text-lg font-black text-savino-blue uppercase">{{ $t('shop.total') }}</span>
                                    <span class="text-lg font-black text-savino-red">{{ formatPrice(total) }}</span>
                                </div>
                            </div>

                            <Link
                                :href="route('shop.checkout')"
                                class="block w-full text-center bg-savino-blue text-white font-bold uppercase tracking-wider text-sm px-8 py-4 rounded-xl hover:bg-savino-gold hover:text-savino-blue transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                            >
                                {{ $t('shop.proceed_to_checkout') }}
                            </Link>

                            <!-- Security Badges -->
                            <div class="mt-6 flex items-center justify-center gap-4 text-xs text-gray-400">
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                    <span>{{ $t('shop.secure_checkout') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty Cart State -->
                <div v-else class="text-center py-20">
                    <div class="max-w-lg mx-auto">
                        <div class="w-32 h-32 mx-auto mb-8 rounded-full bg-gradient-to-br from-savino-blue to-gray-900 flex items-center justify-center shadow-2xl">
                            <svg class="w-14 h-14 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        </div>
                        <h2 class="text-3xl md:text-4xl font-black text-savino-blue uppercase tracking-tighter mb-4">
                            {{ $t('shop.empty_cart_title') }}
                        </h2>
                        <div class="w-16 h-1 bg-savino-gold mx-auto mb-6"></div>
                        <p class="text-gray-600 text-lg leading-relaxed mb-8">
                            {{ $t('shop.empty_cart_description') }}
                        </p>
                        <Link :href="route('shop')" class="inline-flex items-center gap-3 bg-savino-blue text-white font-bold uppercase tracking-wider text-sm px-10 py-4 rounded-xl hover:bg-savino-gold hover:text-savino-blue transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                            {{ $t('shop.explore_shop') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>

    </PublicLayout>
</template>
