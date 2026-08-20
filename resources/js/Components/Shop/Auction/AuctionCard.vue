<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import CountdownTimer from '@/Components/Shop/Auction/CountdownTimer.vue';

const $t = useTranslations();
const { formatPrice } = useFormatPrice();
const { onImgError } = useImageFallback();

const props = defineProps({
    auction: { type: Object, required: true },
});

const statusConfig = computed(() => {
    switch (props.auction.status) {
        case 'active':
            return { label: '🔴 LIVE', class: 'bg-red-500/20 text-red-400 border border-red-500/30' };
        case 'scheduled':
            return { label: '⏰ In arrivo', class: 'bg-amber-500/20 text-amber-400 border border-amber-500/30' };
        case 'ended':
            return { label: '✅ Conclusa', class: 'bg-gray-500/20 text-gray-400 border border-gray-500/30' };
        default:
            return { label: props.auction.status, class: 'bg-gray-500/20 text-gray-400' };
    }
});

const displayPrice = computed(() => {
    return props.auction.current_bid || props.auction.starting_price;
});

const priceLabel = computed(() => {
    return props.auction.current_bid
        ? ($t('auction.current_bid') || 'Offerta attuale')
        : ($t('auction.starting_price') || 'Prezzo di partenza');
});
</script>

<template>
    <Link
        :href="route('shop.auctions.show', auction.slug)"
        class="group relative bg-gray-900 rounded-xl overflow-hidden shadow-md hover:shadow-2xl transition-all duration-500 transform hover:scale-[1.02] flex flex-col"
    >
        <!-- Image -->
        <div class="relative aspect-[4/3] bg-gray-800 overflow-hidden">
            <img
                v-if="auction.image"
                :src="auction.image"
                :alt="auction.title"
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                loading="lazy"
                @error="onImgError"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-16 h-16 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>

            <!-- Status Badge -->
            <div class="absolute top-3 left-3">
                <span :class="['text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full', statusConfig.class]">
                    {{ statusConfig.label }}
                </span>
            </div>

            <!-- Charity Badge -->
            <div v-if="auction.is_charity" class="absolute top-3 right-3">
                <span class="bg-savino-fucsia/20 text-savino-fucsia border border-savino-fucsia/30 text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full">
                    🎗️ Charity
                </span>
            </div>

            <!-- Hover Overlay -->
            <div
                class="absolute inset-0 bg-savino-blue/70 opacity-0 group-hover:opacity-100 transition-opacity duration-300 hidden sm:flex items-center justify-center"
            >
                <span class="text-white text-xs font-bold uppercase tracking-wider border-2 border-savino-fucsia px-5 py-2.5 hover:bg-savino-fucsia hover:text-gray-900 transition-colors">
                    {{ $t('shop.product_details') || 'Dettagli' }}
                </span>
            </div>
        </div>

        <!-- Info -->
        <div class="p-5 flex flex-col flex-grow">
            <!-- Title -->
            <h3 class="text-white font-bold text-base mb-3 leading-tight group-hover:text-savino-fucsia transition-colors duration-300 line-clamp-2">
                {{ auction.title }}
            </h3>

            <!-- Price + Bids -->
            <div class="mt-auto">
                <div class="flex items-end justify-between mb-3">
                    <div>
                        <span class="block text-[10px] uppercase tracking-wider text-gray-400 mb-0.5">
                            {{ priceLabel }}
                        </span>
                        <span class="text-savino-fucsia font-black text-xl">
                            {{ formatPrice(displayPrice) }}
                        </span>
                    </div>
                    <div v-if="auction.bids_count > 0" class="text-right">
                        <span class="text-gray-400 text-xs">
                            {{ auction.bids_count }} {{ auction.bids_count === 1 ? 'offerta' : 'offerte' }}
                        </span>
                    </div>
                </div>

                <!-- Countdown (only for active) -->
                <CountdownTimer
                    v-if="auction.is_active"
                    :end-date="auction.end_date"
                    :is-active="auction.is_active"
                />
            </div>
        </div>
    </Link>
</template>
