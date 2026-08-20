<script setup>
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useTranslations } from '@/Composables/useTranslations.js';

const $t = useTranslations();
const { formatPrice } = useFormatPrice();

defineProps({
    bids: { type: Array, default: () => [] },
});

const timeAgo = (isoDate) => {
    const seconds = Math.floor((Date.now() - new Date(isoDate).getTime()) / 1000);
    if (seconds < 60) return `${seconds}s fa`;
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} min fa`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h fa`;
    const days = Math.floor(hours / 24);
    return `${days}g fa`;
};
</script>

<template>
    <div>
        <h3 class="text-white font-bold text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-savino-fucsia" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            {{ $t('auction.bid_history') || 'Storico offerte' }}
        </h3>

        <div v-if="bids.length === 0" class="text-gray-500 text-sm italic py-4 text-center">
            {{ $t('auction.no_bids_yet') || 'Nessuna offerta ancora.' }}
        </div>

        <div v-else class="space-y-2">
            <div
                v-for="(bid, index) in bids"
                :key="index"
                :class="[
                    'flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-300',
                    bid.is_mine
                        ? 'bg-savino-fucsia/10 border border-savino-fucsia/30'
                        : 'bg-gray-800/50 hover:bg-gray-800',
                    index === 0 ? 'ring-1 ring-savino-fucsia/20' : '',
                ]"
            >
                <div class="flex items-center gap-2 min-w-0">
                    <!-- Crown for highest bid -->
                    <svg v-if="index === 0" class="w-4 h-4 text-savino-fucsia flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 2l2.5 5 5.5.5-4 4 1 5.5L10 14l-5 3 1-5.5-4-4 5.5-.5L10 2z" />
                    </svg>
                    <span :class="['text-sm font-mono truncate', bid.is_mine ? 'text-savino-fucsia font-bold' : 'text-gray-300']">
                        {{ bid.bidder }}
                    </span>
                </div>

                <div class="flex items-center gap-3 flex-shrink-0">
                    <span :class="['text-sm font-bold tabular-nums', index === 0 ? 'text-savino-fucsia' : 'text-white']">
                        {{ formatPrice(bid.amount) }}
                    </span>
                    <span class="text-gray-500 text-xs whitespace-nowrap">
                        {{ timeAgo(bid.placed_at) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
