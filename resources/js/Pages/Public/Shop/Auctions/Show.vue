<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import DOMPurify from 'dompurify';

const sanitizeHtml = (html) => {
  if (!html) return '';
  if (typeof window === 'undefined') return html;
  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'h4', 'span', 'div', 'blockquote'],
    ALLOWED_ATTR: ['href', 'target', 'rel', 'class']
  });
};
import CountdownTimer from '@/Components/Shop/Auction/CountdownTimer.vue';
import BidForm from '@/Components/Shop/Auction/BidForm.vue';
import BidHistory from '@/Components/Shop/Auction/BidHistory.vue';
import SocialShare from '@/Components/Shop/Auction/SocialShare.vue';

const $t = useTranslations();
const { formatPrice } = useFormatPrice();
const { onImgError } = useImageFallback();
const page = usePage();

const props = defineProps({
    auction: { type: Object, required: true },
    bids: { type: Array, default: () => [] },
    userIsHighestBidder: { type: Boolean, default: false },
    hasVerifiedPayment: { type: Boolean, default: false },
    rulesText: { type: String, default: null },
});

const user = computed(() => page.props.auth?.user);

// Reactive state updated by polling
const currentAuction = ref({ ...props.auction });
const currentBids = ref([...props.bids]);
const isHighestBidder = ref(props.userIsHighestBidder);

// Image gallery
const selectedImage = ref(0);
const selectImage = (index) => { selectedImage.value = index; };
const mainImage = computed(() => {
    const images = currentAuction.value.images;
    return images && images.length > 0 ? images[selectedImage.value]?.original : currentAuction.value.image;
});

// OG Meta
const ogMeta = useOgMeta({
    title: `${props.auction.title} — Asta Savino Del Bene Volley`,
    description: props.auction.current_bid
        ? `Offerta attuale: ${formatPrice(props.auction.current_bid)}. ${props.auction.description || ''}`
        : `A partire da ${formatPrice(props.auction.starting_price)}. ${props.auction.description || ''}`,
    image: props.auction.image,
    type: 'product',
});

const displayPrice = computed(() => currentAuction.value.current_bid || currentAuction.value.starting_price);
const priceLabel = computed(() =>
    currentAuction.value.current_bid
        ? ($t('auction.current_bid') || 'Offerta attuale')
        : ($t('auction.starting_price') || 'Prezzo di partenza'),
);

// --- Polling ---
const bidFormRef = ref(null);
let pollInterval = null;
const isEndingSoon = ref(false);

const pollStatus = async () => {
    if (!currentAuction.value.is_active) return;

    try {
        const res = await fetch(`/api/aste/${currentAuction.value.id}/status`);
        if (!res.ok) return;
        const data = await res.json();

        // Update auction data
        currentAuction.value = {
            ...currentAuction.value,
            current_bid: data.current_bid,
            end_date: data.end_date,
            minimum_bid: data.minimum_bid,
            maximum_bid: data.maximum_bid,
            is_active: data.is_active,
            reserve_met: data.reserve_met,
        };

        // Update bids
        if (data.latest_bids) {
            currentBids.value = data.latest_bids.map(bid => ({
                ...bid,
                is_mine: false, // Will be updated below if user matches
            }));
        }

        // Update bid form minimum
        if (bidFormRef.value) {
            bidFormRef.value.updateMinBid(data.minimum_bid);
        }

        // Check if user is still highest
        if (user.value && data.latest_bids?.length > 0) {
            // We can't check user_id from API (masked), so we rely on the 'is_mine' from bid-placed event
        }

        // If auction ended, stop polling
        if (!data.is_active) {
            stopPolling();
        }
    } catch {
        // Silently fail, will retry
    }
};

const startPolling = () => {
    if (pollInterval) return;
    const interval = isEndingSoon.value ? 2000 : 5000;
    pollInterval = setInterval(pollStatus, interval);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const onEndingSoon = () => {
    isEndingSoon.value = true;
    stopPolling();
    startPolling(); // Restart with 2s interval
};

const onAuctionEnded = () => {
    stopPolling();
    currentAuction.value.is_active = false;
};

const onBidPlaced = (bid) => {
    isHighestBidder.value = true;
    pollStatus(); // Immediate refresh after bid
};

onMounted(() => {
    if (currentAuction.value.is_active) {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

// Rules modal
const showRules = ref(false);
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
        <meta property="og:title" :content="ogMeta.title" />
        <meta property="og:description" :content="ogMeta.description" />
        <meta property="og:image" :content="ogMeta.image" />
        <meta property="og:url" :content="ogMeta.url" />
        <meta property="og:type" :content="ogMeta.type" />
    </Head>
    <PublicLayout>
        <!-- Hero compact -->
        <section class="relative bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900 pt-24 pb-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
                    <Link :href="route('shop')" class="hover:text-savino-gold transition-colors">Shop</Link>
                    <span>/</span>
                    <Link :href="route('shop.auctions.index')" class="hover:text-savino-gold transition-colors">Aste</Link>
                    <span>/</span>
                    <span class="text-gray-300 truncate">{{ currentAuction.title }}</span>
                </nav>
            </div>
        </section>

        <!-- Main Content -->
        <section class="bg-gray-950 py-8 lg:py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                    <!-- Left Column: Image Gallery -->
                    <div>
                        <!-- Main Image -->
                        <div class="relative aspect-square bg-gray-800 rounded-2xl overflow-hidden mb-4">
                            <img
                                v-if="mainImage"
                                :src="mainImage"
                                :alt="currentAuction.title"
                                class="w-full h-full object-cover"
                                @error="onImgError"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <svg class="w-20 h-20 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>

                            <!-- Status badge -->
                            <div class="absolute top-4 left-4 flex gap-2">
                                <span v-if="currentAuction.is_active" class="bg-red-500/20 text-red-400 border border-red-500/30 text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-full backdrop-blur-sm">
                                    🔴 LIVE
                                </span>
                                <span v-if="currentAuction.is_charity" class="bg-savino-gold/20 text-savino-gold border border-savino-gold/30 text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-full backdrop-blur-sm">
                                    🎗️ Charity
                                </span>
                            </div>
                        </div>

                        <!-- Thumbnails -->
                        <div v-if="currentAuction.images && currentAuction.images.length > 1" class="grid grid-cols-4 sm:grid-cols-5 gap-2">
                            <button
                                v-for="(img, index) in currentAuction.images"
                                :key="index"
                                @click="selectImage(index)"
                                :class="[
                                    'aspect-square rounded-lg overflow-hidden border-2 transition-all duration-200',
                                    selectedImage === index
                                        ? 'border-savino-gold ring-2 ring-savino-gold/30'
                                        : 'border-transparent opacity-60 hover:opacity-100',
                                ]"
                            >
                                <img :src="img.thumbnail" :alt="`${currentAuction.title} ${index + 1}`" class="w-full h-full object-cover" @error="onImgError" />
                            </button>
                        </div>
                    </div>

                    <!-- Right Column: Info + Bid -->
                    <div class="flex flex-col">
                        <!-- Title -->
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-white uppercase tracking-tighter mb-4">
                            {{ currentAuction.title }}
                        </h1>
                        <p v-if="currentAuction.product_size" class="text-gray-400 mb-4 text-sm">
                            {{ $t('shop.size') || 'Taglia' }}: <span class="text-white font-bold">{{ currentAuction.product_size }}</span>
                        </p>

                        <!-- Price -->
                        <div class="bg-gray-800/50 rounded-xl p-5 mb-5 border border-gray-700/50">
                            <span class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1">
                                {{ priceLabel }}
                            </span>
                            <div class="flex items-end gap-3">
                                <span class="text-savino-gold font-black text-4xl sm:text-5xl tabular-nums">
                                    {{ formatPrice(displayPrice) }}
                                </span>
                                <span v-if="currentAuction.bids_count > 0" class="text-gray-400 text-sm pb-1">
                                    {{ currentAuction.bids_count }} {{ currentAuction.bids_count === 1 ? $t('auction.bid_singular') : $t('auction.bid_plural') }}
                                </span>
                            </div>

                            <!-- Reserve notice -->
                            <div v-if="currentAuction.has_reserve && !currentAuction.reserve_met" class="mt-2 text-amber-400 text-xs flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                {{ $t('auction.reserve_not_met') || 'Prezzo di riserva non raggiunto' }}
                            </div>
                        </div>

                        <!-- Countdown -->
                        <div class="mb-5">
                            <CountdownTimer
                                :end-date="currentAuction.end_date"
                                :is-active="currentAuction.is_active"
                                @ending-soon="onEndingSoon"
                                @ended="onAuctionEnded"
                            />
                        </div>

                        <!-- User status -->
                        <div v-if="user && currentAuction.is_active" class="mb-5">
                            <div
                                v-if="isHighestBidder"
                                class="bg-emerald-900/20 border border-emerald-500/20 rounded-lg p-3 text-center"
                            >
                                <span class="text-emerald-400 font-bold text-sm flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    {{ $t('auction.you_are_winning') || 'Stai vincendo!' }}
                                </span>
                            </div>
                        </div>

                        <!-- Bid Form -->
                        <BidForm
                            v-if="currentAuction.is_active"
                            ref="bidFormRef"
                            :auction="currentAuction"
                            :has-verified-payment="hasVerifiedPayment"
                            :is-highest-bidder="isHighestBidder"
                            @bid-placed="onBidPlaced"
                        />

                        <!-- Ended: Winner checkout CTA -->
                        <div
                            v-if="!currentAuction.is_active && currentAuction.status === 'ended' && auction.winner_checkout_token"
                            class="bg-savino-gold/10 border border-savino-gold/30 rounded-xl p-5 text-center"
                        >
                            <p class="text-savino-gold font-bold text-lg mb-3">🎉 {{ $t('auction.ended_badge') || 'Asta conclusa!' }}</p>
                        </div>

                        <!-- Social Share -->
                        <div class="mt-5 flex items-center gap-3">
                            <span class="text-gray-500 text-xs uppercase tracking-wider">{{ $t('auction.share') || 'Condividi' }}:</span>
                            <SocialShare :title="currentAuction.title" :url="ogMeta.url" />
                        </div>
                    </div>
                </div>

                <!-- Below-fold sections -->
                <div class="mt-12 grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                    <!-- Bid History -->
                    <div class="bg-gray-900 rounded-2xl p-6 border border-gray-800">
                        <BidHistory :bids="currentBids" />
                    </div>

                    <!-- Description + Rules -->
                    <div class="space-y-6">
                        <!-- Description -->
                        <div v-if="currentAuction.description" class="bg-gray-900 rounded-2xl p-6 border border-gray-800">
                            <h3 class="text-white font-bold text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ $t('auction.description') || 'Descrizione' }}
                            </h3>
                            <div class="text-gray-300 text-sm leading-relaxed prose prose-invert prose-sm max-w-none" v-html="sanitizeHtml(currentAuction.description)" />
                        </div>

                        <!-- Charity Description -->
                        <div v-if="currentAuction.charity_description" class="bg-savino-gold/5 rounded-2xl p-6 border border-savino-gold/20">
                            <h3 class="text-savino-gold font-bold text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
                                🎗️ {{ $t('auction.charity_label') || 'Asta benefica' }}
                            </h3>
                            <div class="text-gray-300 text-sm leading-relaxed" v-html="sanitizeHtml(currentAuction.charity_description)" />
                        </div>

                        <!-- Rules -->
                        <div v-if="rulesText" class="bg-gray-900 rounded-2xl p-6 border border-gray-800">
                            <h3 class="text-white font-bold text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ $t('auction.rules') || 'Regolamento asta' }}
                            </h3>
                            <div class="text-gray-300 text-sm leading-relaxed prose prose-invert prose-sm max-w-none" v-html="sanitizeHtml(rulesText)" />
                        </div>

                        <!-- Bid Increment Info -->
                        <div class="bg-gray-900 rounded-2xl p-6 border border-gray-800">
                            <h3 class="text-white font-bold text-sm uppercase tracking-wider mb-3">
                                📊 {{ $t('auction.details') || 'Dettagli asta' }}
                            </h3>
                            <dl class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <dt class="text-gray-400">{{ $t('auction.starting_price_label') || 'Prezzo di partenza' }}</dt>
                                    <dd class="text-white font-bold">{{ formatPrice(currentAuction.starting_price) }}</dd>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <dt class="text-gray-400">{{ $t('auction.min_increment') || 'Rilancio minimo' }}</dt>
                                    <dd class="text-white font-bold">{{ formatPrice(currentAuction.bid_increment) }}</dd>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <dt class="text-gray-400">{{ $t('auction.max_increment') || 'Rilancio massimo' }}</dt>
                                    <dd class="text-white font-bold">{{ formatPrice(currentAuction.max_bid_jump) }}</dd>
                                </div>
                                <div v-if="currentAuction.has_reserve" class="flex justify-between text-sm">
                                    <dt class="text-gray-400">{{ $t('auction.reserve_price') || 'Prezzo di riserva' }}</dt>
                                    <dd :class="currentAuction.reserve_met ? 'text-emerald-400' : 'text-amber-400'" class="font-bold">
                                        {{ currentAuction.reserve_met ? '✅ ' + ($t('auction.reached') || 'Raggiunto') : '⚠️ ' + ($t('auction.not_reached') || 'Non raggiunto') }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- JSON-LD Structured Data -->
        <component :is="'script'" type="application/ld+json">
            {{ JSON.stringify({
                '@context': 'https://schema.org',
                '@type': 'Product',
                name: currentAuction.title,
                description: currentAuction.description,
                image: mainImage,
                offers: {
                    '@type': 'Offer',
                    price: displayPrice,
                    priceCurrency: 'EUR',
                    availability: currentAuction.is_active ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
                },
            }) }}
        </component>
    </PublicLayout>
</template>
