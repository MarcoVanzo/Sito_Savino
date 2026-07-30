<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const $t = useTranslations();
const { formatPrice } = useFormatPrice();

const props = defineProps({
    auction: { type: Object, required: true },
    hasVerifiedPayment: { type: Boolean, default: false },
    isHighestBidder: { type: Boolean, default: false },
});

const emit = defineEmits(['bid-placed']);

const page = usePage();
const user = computed(() => page.props.auth?.user);

const bidAmount = ref(props.auction.minimum_bid);
const isSubmitting = ref(false);
const error = ref(null);
const success = ref(false);
let successTimer = null;

const quickBids = [5, 10, 20];

const setQuickBid = (extra) => {
    bidAmount.value = Number.parseFloat((props.auction.minimum_bid + extra).toFixed(2));
};

const isValidBid = computed(() => {
    const val = Number.parseFloat(bidAmount.value);
    return !Number.isNaN(val) && val >= props.auction.minimum_bid && val <= props.auction.maximum_bid;
});

const submitBid = async () => {
    if (!isValidBid.value || isSubmitting.value) return;

    isSubmitting.value = true;
    error.value = null;
    success.value = false;

    try {
        const response = await axios.post(route('shop.auctions.bid', props.auction.id), {
            amount: Number.parseFloat(bidAmount.value),
        });

        if (response.data.success) {
            success.value = true;
            emit('bid-placed', response.data.bid);

            // Reset success after 5 seconds
            successTimer = setTimeout(() => { success.value = false; }, 5000);
        }
    } catch (err) {
        if (err.response?.status === 422) {
            error.value = err.response.data.message;
        } else if (err.response?.status === 403) {
            error.value = err.response.data.message;
        } else {
            error.value = $t('auction.bid_error') || 'Impossibile piazzare l\'offerta. Riprova.';
        }
    } finally {
        isSubmitting.value = false;
    }
};

// Update minimum bid when auction data changes (from polling)
const updateMinBid = (newMin) => {
    if (bidAmount.value < newMin) {
        bidAmount.value = newMin;
    }
};

defineExpose({ updateMinBid });

onUnmounted(() => {
    if (successTimer) clearTimeout(successTimer);
});
</script>

<template>
    <div class="bg-gray-800/50 rounded-xl p-5 border border-gray-700/50">
        <!-- Not logged in -->
        <div v-if="!user" class="text-center py-4">
            <p class="text-gray-300 mb-3 text-sm">
                {{ $t('auction.login_to_bid') || 'Accedi per fare un\'offerta' }}
            </p>
            <a
                :href="route('login')"
                class="inline-flex items-center gap-2 bg-savino-gold text-gray-900 font-bold text-sm uppercase tracking-wider px-6 py-3 rounded-lg hover:bg-savino-gold/90 transition-all duration-300"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                Accedi
            </a>
        </div>

        <!-- No verified payment -->
        <div v-else-if="!hasVerifiedPayment" class="text-center py-4">
            <p class="text-gray-300 mb-3 text-sm">
                {{ $t('auction.verify_to_bid') || 'Verifica il metodo di pagamento per partecipare' }}
            </p>
            <a
                :href="route('account.payment-verification')"
                class="inline-flex items-center gap-2 bg-savino-gold text-gray-900 font-bold text-sm uppercase tracking-wider px-6 py-3 rounded-lg hover:bg-savino-gold/90 transition-all duration-300"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                {{ $t('auction.verify_to_bid') || 'Verifica carta' }}
            </a>
        </div>

        <!-- Already highest bidder -->
        <div v-else-if="isHighestBidder && !success" class="text-center py-4">
            <div class="flex items-center justify-center gap-2 text-emerald-400 mb-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="font-bold text-sm">{{ $t('auction.you_are_winning') || 'Stai vincendo!' }}</span>
            </div>
            <p class="text-gray-400 text-xs">{{ $t('auction.already_highest') || 'Sei già il miglior offerente' }}</p>
        </div>

        <!-- Bid form -->
        <div v-else>
            <!-- Success state -->
            <div
                v-if="success"
                class="bg-emerald-900/30 border border-emerald-500/30 rounded-lg p-4 mb-4 text-center animate-pulse"
            >
                <div class="flex items-center justify-center gap-2 text-emerald-400">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-bold text-sm">{{ $t('auction.bid_placed') || 'Sei il miglior offerente!' }}</span>
                </div>
            </div>

            <!-- Error message -->
            <div
                v-if="error"
                class="bg-red-900/30 border border-red-500/30 rounded-lg p-3 mb-4 text-red-300 text-sm"
            >
                {{ error }}
            </div>

            <!-- Bid info -->
            <div class="flex items-center justify-between text-xs text-gray-400 mb-3">
                <span>{{ $t('auctions.min_short') }} <strong class="text-white">{{ formatPrice(auction.minimum_bid) }}</strong></span>
                <span>{{ $t('auctions.max_short') }} <strong class="text-white">{{ formatPrice(auction.maximum_bid) }}</strong></span>
            </div>

            <!-- Quick bid buttons -->
            <div class="grid grid-cols-3 gap-2 mb-3">
                <button type="button"
                    v-for="extra in quickBids"
                    :key="extra"
                    @click="setQuickBid(extra)"
                    :class="[
                        'text-xs font-bold py-2 rounded-lg transition-all duration-200',
                        bidAmount === auction.minimum_bid + extra
                            ? 'bg-savino-gold text-gray-900'
                            : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 hover:text-white',
                    ]"
                >
                    +{{ extra }}€
                </button>
            </div>

            <!-- Amount input + submit -->
            <form @submit.prevent="submitBid" class="space-y-3">
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">€</span>
                    <input
                        v-model.number="bidAmount"
                        type="number"
                        :min="auction.minimum_bid"
                        :max="auction.maximum_bid"
                        step="0.01"
                        :disabled="isSubmitting"
                        class="w-full bg-gray-900 border border-gray-600 text-white text-lg font-bold rounded-lg pl-10 pr-4 py-3 focus:ring-2 focus:ring-savino-gold focus:border-savino-gold transition-all disabled:opacity-50"
                        :placeholder="formatPrice(auction.minimum_bid)"
                        :aria-label="$t('shop.your_bid') || 'La tua offerta'"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="isSubmitting || !isValidBid"
                    class="w-full bg-savino-gold text-gray-900 font-black text-sm uppercase tracking-wider py-4 rounded-lg hover:bg-savino-gold/90 transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg v-if="isSubmitting" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $t('auction.place_bid') || 'Fai un\'offerta' }}
                </button>
            </form>
        </div>
    </div>
</template>
