<script setup>
import { computed, nextTick, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AddressAutocomplete from '@/Components/Shop/AddressAutocomplete.vue';
import CountdownTimer from '@/Components/Shop/Auction/CountdownTimer.vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useAuctionCheckout } from '@/Composables/useAuctionCheckout.js';

const $t = useTranslations();
const { formatPrice } = useFormatPrice();
const { onImgError } = useImageFallback();
const page = usePage();

// Props tolleranti: il controller può cambiare (es. riapertura della sessione
// di pagamento sugli ordini Pending) senza rompere il montaggio della pagina.
const props = defineProps({
    auction: { type: Object, default: () => ({}) },
    product: { type: Object, default: null },
    shippingZones: { type: Array, default: () => [] },
    checkoutDeadline: { type: String, default: null },
    winningBid: { type: [Number, String], default: 0 },
    token: { type: String, default: null },
});

const { checkoutToken, localized, auctionImage } = useAuctionCheckout(props);

const auctionTitle = computed(() => localized(props.auction?.title));
const productImage = computed(() => auctionImage(props.product));

const bidAmount = computed(() => Number(props.winningBid ?? 0) || 0);

const authUser = computed(() => page.props.auth?.user ?? null);

const form = useForm({
    shipping_first_name: authUser.value?.name?.split(' ')[0] || '',
    shipping_last_name: authUser.value?.name?.split(' ').slice(1).join(' ') || '',
    shipping_street: '',
    shipping_city: '',
    shipping_zip_code: '',
    shipping_province: '',
    country: 'IT',
    phone: authUser.value?.phone || '',
    codice_fiscale: '',
    billing_same_as_shipping: true,
    billing_first_name: '',
    billing_last_name: '',
    billing_street: '',
    billing_city: '',
    billing_zip_code: '',
    billing_province: '',
    notes: '',
    privacy_accepted: false,
});

const getCountryName = (code) => {
    const translated = $t(`countries.${code}`);
    return (translated && translated !== `countries.${code}`) ? translated : code;
};

const availableCountries = computed(() => {
    const seen = new Set();
    const countries = [];
    for (const zone of props.shippingZones ?? []) {
        for (const code of (zone.countries || [])) {
            if (code !== '*' && !seen.has(code)) {
                seen.add(code);
                countries.push({ code, name: getCountryName(code) });
            }
        }
    }
    return countries.sort((a, b) => a.name.localeCompare(b.name));
});

// Stessa precedenza del server (ShippingZone::findByCountry): prima la
// corrispondenza esatta del paese, poi la zona wildcard.
const selectedZone = computed(() => {
    const zones = props.shippingZones ?? [];
    return zones.find(z => (z.countries || []).includes(form.country))
        ?? zones.find(z => (z.countries || []).includes('*'));
});

const shippingCost = computed(() => {
    const zone = selectedZone.value;
    if (!zone) return 0;
    if (zone.free_threshold && bidAmount.value >= zone.free_threshold) return 0;
    return Number(zone.flat_rate ?? 0) || 0;
});

const orderTotal = computed(() => bidAmount.value + shippingCost.value);

const isSubmittable = computed(() =>
    !form.processing
    && form.privacy_accepted
    && !!form.shipping_first_name?.trim()
    && !!form.shipping_last_name?.trim()
    && !!form.shipping_street?.trim()
    && !!form.shipping_city?.trim()
    && !!form.shipping_zip_code?.trim()
    && !!form.shipping_province?.trim()
    && !!form.phone?.trim()
    && (form.country !== 'IT' || !!form.codice_fiscale?.trim())
);

const submitOrder = () => {
    if (!checkoutToken.value) return;
    form.post(route('shop.auction-checkout.store', { token: checkoutToken.value }), {
        preserveScroll: true,
    });
};

// Reset dei campi di fatturazione quando torna a "uguale alla spedizione"
watch(() => form.billing_same_as_shipping, (isSame) => {
    if (!isSame) return;
    form.billing_first_name = '';
    form.billing_last_name = '';
    form.billing_street = '';
    form.billing_city = '';
    form.billing_zip_code = '';
    form.billing_province = '';
});

watch(() => form.errors, (errors) => {
    nextTick(() => {
        const keys = Object.keys(errors ?? {});
        if (keys.length === 0) return;
        const el = document.querySelector(`[id*="${keys[0].replaceAll('_', '-')}"]`)
            || document.querySelector('.text-red-500');
        el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}, { deep: true });

const ogMeta = useOgMeta({
    title: $t('auction_checkout.og_title'),
    description: $t('auction_checkout.og_description'),
});

const inputClass = 'w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm';
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="robots" content="noindex, nofollow" />
        <meta name="description" :content="ogMeta.description" />
    </Head>

    <PublicLayout>
        <!-- HERO -->
        <section class="relative min-h-[35vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">
                    {{ $t('auction_checkout.hero_label') }}
                </span>
                <h1 class="text-3xl md:text-5xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ $t('auction_checkout.title') }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">
                    {{ $t('auction_checkout.subtitle') }}
                </p>
            </div>
        </section>

        <section class="py-16 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Countdown deadline -->
                <div v-if="checkoutDeadline" class="mb-8 bg-white rounded-2xl shadow-sm border border-amber-200 px-6 py-5">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-center sm:text-left">
                            <p class="text-sm font-bold text-gray-900 uppercase tracking-tight">
                                {{ $t('auction_checkout.deadline_title') }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $t('auction_checkout.deadline_note') }}
                            </p>
                        </div>
                        <CountdownTimer :end-date="checkoutDeadline" :is-active="true" />
                    </div>
                </div>

                <!-- Errore generico dal backend -->
                <div v-if="form.errors.general" class="mb-6 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                    <p class="text-sm text-red-700 font-medium">{{ form.errors.general }}</p>
                </div>

                <div class="grid lg:grid-cols-3 gap-8">

                    <!-- FORM -->
                    <div class="lg:col-span-2 space-y-8">

                        <!-- Contatto -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <h2 class="text-lg font-black text-gray-900 uppercase tracking-tight mb-6">
                                {{ $t('auction_checkout.contact_title') }}
                            </h2>
                            <label for="auction-phone" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ $t('shop_checkout.label_phone') }} *
                            </label>
                            <input
                                id="auction-phone"
                                v-model="form.phone"
                                type="tel"
                                autocomplete="tel"
                                :class="inputClass"
                                :placeholder="$t('shop_checkout.placeholder_phone')"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-500">{{ form.errors.phone }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ $t('shop_checkout.phone_shipping_note') }}</p>
                        </div>

                        <!-- Spedizione -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="w-8 h-8 rounded-full bg-savino-blue text-white flex items-center justify-center text-sm font-bold">1</span>
                                <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">
                                    {{ $t('shop_checkout.shipping_title') }}
                                </h2>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="auction-first-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_first_name') }} *</label>
                                    <input id="auction-first-name" v-model="form.shipping_first_name" type="text" autocomplete="given-name" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_first_name')" />
                                    <p v-if="form.errors.shipping_first_name" class="mt-1 text-sm text-red-500">{{ form.errors.shipping_first_name }}</p>
                                </div>
                                <div>
                                    <label for="auction-last-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_last_name') }} *</label>
                                    <input id="auction-last-name" v-model="form.shipping_last_name" type="text" autocomplete="family-name" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_last_name')" />
                                    <p v-if="form.errors.shipping_last_name" class="mt-1 text-sm text-red-500">{{ form.errors.shipping_last_name }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="auction-street" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_street') }} *</label>
                                    <AddressAutocomplete
                                        id="auction-street"
                                        v-model="form.shipping_street"
                                        :placeholder="$t('shop_checkout.placeholder_street')"
                                        :country="form.country"
                                        @address-selected="(addr) => {
                                            form.shipping_street = addr.street;
                                            form.shipping_city = addr.city;
                                            form.shipping_zip_code = addr.zip_code;
                                            form.shipping_province = addr.province;
                                        }"
                                    />
                                    <p v-if="form.errors.shipping_street" class="mt-1 text-sm text-red-500">{{ form.errors.shipping_street }}</p>
                                </div>
                                <div>
                                    <label for="auction-city" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_city') }} *</label>
                                    <input id="auction-city" v-model="form.shipping_city" type="text" autocomplete="address-level2" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_city')" />
                                    <p v-if="form.errors.shipping_city" class="mt-1 text-sm text-red-500">{{ form.errors.shipping_city }}</p>
                                </div>
                                <div>
                                    <label for="auction-zip" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_zip_code') }} *</label>
                                    <input id="auction-zip" v-model="form.shipping_zip_code" type="text" autocomplete="postal-code" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_zip_code')" />
                                    <p v-if="form.errors.shipping_zip_code" class="mt-1 text-sm text-red-500">{{ form.errors.shipping_zip_code }}</p>
                                </div>
                                <div>
                                    <label for="auction-province" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_province') }} *</label>
                                    <input id="auction-province" v-model="form.shipping_province" type="text" autocomplete="address-level1" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_province')" />
                                    <p v-if="form.errors.shipping_province" class="mt-1 text-sm text-red-500">{{ form.errors.shipping_province }}</p>
                                </div>
                                <div>
                                    <label for="auction-country" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_country') }}</label>
                                    <select id="auction-country" v-model="form.country" autocomplete="country" :class="inputClass">
                                        <option value="" disabled>{{ $t('shop_checkout.select_country') }}</option>
                                        <option v-for="c in availableCountries" :key="c.code" :value="c.code">{{ c.name }}</option>
                                    </select>
                                    <p v-if="form.errors.country" class="mt-1 text-sm text-red-500">{{ form.errors.country }}</p>
                                </div>
                                <div v-if="form.country === 'IT'" class="sm:col-span-2">
                                    <label for="auction-cf" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_codice_fiscale') }} *</label>
                                    <input
                                        id="auction-cf"
                                        v-model="form.codice_fiscale"
                                        type="text"
                                        maxlength="16"
                                        :class="[inputClass, 'uppercase']"
                                        :placeholder="$t('shop_checkout.placeholder_codice_fiscale')"
                                        @input="form.codice_fiscale = form.codice_fiscale.toUpperCase()"
                                    />
                                    <p v-if="form.errors.codice_fiscale" class="mt-1 text-sm text-red-500">{{ form.errors.codice_fiscale }}</p>
                                </div>
                            </div>

                            <!-- Fatturazione -->
                            <div class="mt-6 pt-6 border-t border-gray-100">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.billing_same_as_shipping" class="w-4 h-4 text-savino-blue border-gray-300 rounded" />
                                    <span class="text-sm text-gray-600">{{ $t('shop_checkout.billing_same_as_shipping') }}</span>
                                </label>
                            </div>

                            <div v-if="!form.billing_same_as_shipping" class="mt-6 pt-6 border-t border-gray-100">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $t('shop_checkout.label_billing_address') }}</h3>
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="auction-billing-first-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_first_name') }} *</label>
                                        <input id="auction-billing-first-name" v-model="form.billing_first_name" type="text" autocomplete="given-name" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_first_name')" />
                                        <p v-if="form.errors.billing_first_name" class="mt-1 text-sm text-red-500">{{ form.errors.billing_first_name }}</p>
                                    </div>
                                    <div>
                                        <label for="auction-billing-last-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_last_name') }} *</label>
                                        <input id="auction-billing-last-name" v-model="form.billing_last_name" type="text" autocomplete="family-name" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_last_name')" />
                                        <p v-if="form.errors.billing_last_name" class="mt-1 text-sm text-red-500">{{ form.errors.billing_last_name }}</p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="auction-billing-street" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_street') }} *</label>
                                        <input id="auction-billing-street" v-model="form.billing_street" type="text" autocomplete="address-line1" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_street')" />
                                        <p v-if="form.errors.billing_street" class="mt-1 text-sm text-red-500">{{ form.errors.billing_street }}</p>
                                    </div>
                                    <div>
                                        <label for="auction-billing-city" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_city') }} *</label>
                                        <input id="auction-billing-city" v-model="form.billing_city" type="text" autocomplete="address-level2" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_city')" />
                                        <p v-if="form.errors.billing_city" class="mt-1 text-sm text-red-500">{{ form.errors.billing_city }}</p>
                                    </div>
                                    <div>
                                        <label for="auction-billing-zip" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_zip_code') }} *</label>
                                        <input id="auction-billing-zip" v-model="form.billing_zip_code" type="text" autocomplete="postal-code" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_zip_code')" />
                                        <p v-if="form.errors.billing_zip_code" class="mt-1 text-sm text-red-500">{{ form.errors.billing_zip_code }}</p>
                                    </div>
                                    <div>
                                        <label for="auction-billing-province" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_province') }} *</label>
                                        <input id="auction-billing-province" v-model="form.billing_province" type="text" autocomplete="address-level1" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_province')" />
                                        <p v-if="form.errors.billing_province" class="mt-1 text-sm text-red-500">{{ form.errors.billing_province }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <label for="auction-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_notes') }}</label>
                            <textarea
                                id="auction-notes"
                                v-model="form.notes"
                                rows="3"
                                maxlength="1000"
                                :class="[inputClass, 'resize-none']"
                                :placeholder="$t('shop_checkout.placeholder_notes')"
                            ></textarea>
                            <p v-if="form.errors.notes" class="mt-1 text-sm text-red-500">{{ form.errors.notes }}</p>
                        </div>

                        <!-- Privacy -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.privacy_accepted" class="mt-1 w-4 h-4 text-savino-blue border-gray-300 rounded focus:ring-savino-blue/20" />
                                <span class="text-sm text-gray-600">
                                    {{ $t('shop.accept_privacy_1') }}
                                    <a :href="route('pages.show', 'privacy-policy')" target="_blank" rel="noopener noreferrer" class="text-savino-blue underline hover:text-savino-blue/80">{{ $t('shop.accept_privacy_2') }}</a>
                                </span>
                            </label>
                            <p v-if="form.errors.privacy_accepted" class="mt-1 text-sm text-red-500">{{ form.errors.privacy_accepted }}</p>
                        </div>
                    </div>

                    <!-- RIEPILOGO -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100 sticky top-24">
                            <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight mb-6">
                                {{ $t('shop_checkout.order_summary') }}
                            </h2>

                            <div class="flex items-center gap-3 pb-4 mb-4 border-b border-gray-100">
                                <div class="w-16 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                                    <img v-if="productImage" :src="productImage" :alt="auctionTitle" class="w-full h-full object-cover" @error="onImgError" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-2xl">🏐</div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="inline-block text-[10px] font-bold uppercase tracking-widest text-savino-gold mb-0.5">
                                        {{ $t('auction_checkout.badge_won') }}
                                    </span>
                                    <p class="text-sm font-medium text-gray-900 line-clamp-2">{{ auctionTitle }}</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">{{ $t('auction_checkout.winning_bid') }}</span>
                                    <span class="text-gray-900 font-medium">{{ formatPrice(bidAmount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">{{ $t('shop_checkout.shipping') }}</span>
                                    <div class="text-right">
                                        <span class="font-medium" :class="shippingCost === 0 ? 'text-green-600' : 'text-gray-900'">
                                            {{ shippingCost === 0 ? $t('shop_checkout.free_shipping') : formatPrice(shippingCost) }}
                                        </span>
                                        <div v-if="selectedZone?.estimated_days_min" class="text-xs text-gray-500 mt-1">
                                            📦 {{ $t('shop_checkout.estimated_delivery') }}: {{ selectedZone.estimated_days_min }}-{{ selectedZone.estimated_days_max }} {{ $t('shop_checkout.business_days') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-between pt-3 border-t border-gray-200">
                                    <span class="font-bold text-gray-900">{{ $t('shop_checkout.total') }}</span>
                                    <span class="text-xl font-black text-savino-red">{{ formatPrice(orderTotal) }}</span>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="submitOrder"
                                :disabled="!isSubmittable"
                                class="w-full mt-8 bg-savino-gold text-savino-blue font-bold uppercase tracking-wider text-sm px-8 py-4 rounded-lg hover:bg-savino-gold/90 transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <svg v-if="form.processing" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                {{ form.processing ? $t('shop_checkout.processing') : $t('auction_checkout.pay_now') }}
                            </button>

                            <p class="text-xs text-gray-400 text-center mt-4">
                                {{ $t('auction_checkout.stripe_note') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
