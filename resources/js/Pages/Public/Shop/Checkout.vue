<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useOgMeta } from '@/Composables/useOgMeta'
import { useFormatPrice } from '@/Composables/useFormatPrice.js'

const $t = useTranslations();
const { formatPrice } = useFormatPrice();

const page = usePage();
const user = () => page.props.auth?.user;

const countryNames = {
    IT: 'Italia', DE: 'Germania', FR: 'Francia', ES: 'Spagna',
    AT: 'Austria', BE: 'Belgio', NL: 'Paesi Bassi', PT: 'Portogallo',
    GR: 'Grecia', IE: 'Irlanda', FI: 'Finlandia', SE: 'Svezia',
    DK: 'Danimarca', PL: 'Polonia', CZ: 'Repubblica Ceca', RO: 'Romania',
    HU: 'Ungheria', BG: 'Bulgaria', HR: 'Croazia', SK: 'Slovacchia',
    SI: 'Slovenia', LT: 'Lituania', LV: 'Lettonia', EE: 'Estonia',
    CY: 'Cipro', LU: 'Lussemburgo', MT: 'Malta',
    GB: 'Regno Unito', CH: 'Svizzera', US: 'Stati Uniti',
};


const props = defineProps({
    cart: {
        type: Object,
        default: () => ({ items: [], total: 0 })
    },
    cartTotal: {
        type: Number,
        default: 0
    },
    itemCount: {
        type: Number,
        default: 0
    },
    shippingZones: {
        type: Array,
        default: () => []
    },
    paymentGateways: {
        type: Array,
        default: () => []
    },
})

const authUser = user();
const form = useForm({
    guest_name: authUser?.name || '',
    guest_email: authUser?.email || '',
    guest_phone: '',
    shipping_address: '',
    billing_address: '',
    country: 'IT',
    payment_gateway: '',
    coupon_code: '',
    notes: '',
    privacy_accepted: false,
});

const billingSameAsShipping = ref(true);

watch(billingSameAsShipping, (val) => {
    if (val) form.billing_address = form.shipping_address;
});
watch(() => form.shipping_address, (val) => {
    if (billingSameAsShipping.value) form.billing_address = val;
});

const selectedZone = computed(() => {
    return props.shippingZones.find(z => {
        const countries = z.countries || [];
        return countries.includes(form.country) || countries.includes('*');
    });
});

const shippingCost = computed(() => {
    if (!selectedZone.value) return 0;
    if (selectedZone.value.free_threshold && props.cartTotal >= selectedZone.value.free_threshold) return 0;
    return selectedZone.value.flat_rate || 0;
});

const couponStatus = ref(null); // null, 'loading', 'valid', 'invalid'
const couponMessage = ref('');
const couponDiscount = ref(0);

const validateCoupon = async () => {
    if (!form.coupon_code) return;
    couponStatus.value = 'loading';
    try {
        const response = await fetch(route('shop.checkout.validate-coupon'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                coupon_code: form.coupon_code,
                guest_email: form.guest_email,
            }),
        });
        const data = await response.json();
        if (data.valid) {
            couponStatus.value = 'valid';
            couponDiscount.value = data.discount;
            couponMessage.value = data.message;
        } else {
            couponStatus.value = 'invalid';
            couponDiscount.value = 0;
            couponMessage.value = data.message;
        }
    } catch (e) {
        couponStatus.value = 'invalid';
        couponMessage.value = $t('shop_checkout.coupon_error');
    }
};

const removeCoupon = () => {
    form.coupon_code = '';
    couponStatus.value = null;
    couponMessage.value = '';
    couponDiscount.value = 0;
};

const orderTotal = computed(() => {
    return Math.max(0, props.cartTotal + shippingCost.value - couponDiscount.value).toFixed(2);
});



const submitOrder = () => {
    form.post(route('shop.checkout.store'), {
        preserveScroll: true,
    });
};

const ogMeta = useOgMeta({
    title: $t('shop_checkout.og_title'),
    description: $t('shop_checkout.og_description'),
})
</script>

<template>
    <Head>
      <title>{{ ogMeta.title }}</title>
      <meta name="robots" content="noindex, nofollow" />
      <meta name="description" :content="ogMeta.description" />
      <meta property="og:title" :content="ogMeta.title" />
      <meta property="og:description" :content="ogMeta.description" />
      <meta property="og:image" :content="ogMeta.image" />
      <meta property="og:url" :content="ogMeta.url" />
      <meta property="og:type" :content="ogMeta.type" />
    </Head>

    <PublicLayout>

    <!-- Hero -->
    <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
        <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
            <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ $t('shop_checkout.hero_label') }}</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                {{ $t('shop_checkout.og_title') }}
            </h1>
            <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
            <p class="text-white/70 text-lg max-w-2xl mx-auto">
                {{ $t('shop_checkout.hero_subtitle') }}
            </p>
        </div>
    </section>

    <!-- Checkout Content -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Guest Banner: Login / Register -->
            <div v-if="!user()" class="mb-8 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex flex-col sm:flex-row items-center gap-4 sm:gap-6 px-6 py-5 sm:px-8">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-savino-blue/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                    <div class="flex-1 text-center sm:text-left">
                        <p class="text-gray-900 font-bold text-base">
                            {{ $t('shop_checkout.guest_banner_title') }}
                        </p>
                        <p class="text-gray-500 text-sm mt-1">
                            <Link :href="route('login')" class="font-bold text-savino-blue hover:text-savino-blue/80 underline underline-offset-2 transition-colors">
                                {{ $t('shop_checkout.guest_banner_login') }}
                            </Link>
                            {{ $t('shop_checkout.guest_banner_or') }}
                            <Link :href="route('shop.register')" class="font-bold text-savino-gold hover:text-savino-gold/80 underline underline-offset-2 transition-colors">
                                {{ $t('shop_checkout.guest_banner_register') }}
                            </Link>
                            {{ $t('shop_checkout.guest_banner_benefits') }}
                        </p>
                    </div>
                </div>
                <div class="border-t border-gray-100 px-6 py-3 sm:px-8 bg-gray-50/50">
                    <p class="text-xs text-gray-400 text-center sm:text-left">
                        {{ $t('shop_checkout.guest_banner_continue') }}
                    </p>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-8">

                <!-- Shipping Form (2 cols) -->
                <div class="lg:col-span-2 space-y-8">

                    <!-- Shipping Info -->
                    <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="w-8 h-8 rounded-full bg-savino-blue text-white flex items-center justify-center text-sm font-bold">1</span>
                            <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">{{ $t('shop_checkout.shipping_title') }}</h2>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label for="checkout-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_fullname') }}</label>
                                <input
                                    id="checkout-name"
                                    v-model="form.guest_name"
                                    type="text"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                    :placeholder="$t('shop_checkout.placeholder_fullname')"
                                />
                                <p v-if="form.errors.guest_name" class="mt-1 text-sm text-red-500">{{ form.errors.guest_name }}</p>
                            </div>
                            <div>
                                <label for="checkout-email" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_email') }}</label>
                                <input
                                    id="checkout-email"
                                    v-model="form.guest_email"
                                    type="email"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                    :placeholder="$t('shop_checkout.placeholder_email')"
                                />
                                <p v-if="form.errors.guest_email" class="mt-1 text-sm text-red-500">{{ form.errors.guest_email }}</p>
                            </div>
                            <div>
                                <label for="checkout-phone" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_phone') }}</label>
                                <input
                                    id="checkout-phone"
                                    v-model="form.guest_phone"
                                    type="tel"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                    :placeholder="$t('shop_checkout.placeholder_phone')"
                                />
                                <p v-if="form.errors.guest_phone" class="mt-1 text-sm text-red-500">{{ form.errors.guest_phone }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="checkout-address" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_shipping_address') }}</label>
                                <textarea
                                    id="checkout-address"
                                    v-model="form.shipping_address"
                                    rows="2"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm resize-none"
                                    :placeholder="$t('shop_checkout.placeholder_shipping_address')"
                                ></textarea>
                                <p v-if="form.errors.shipping_address" class="mt-1 text-sm text-red-500">{{ form.errors.shipping_address }}</p>
                            </div>
                            <div>
                                <label for="checkout-country" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_country') }}</label>
                                <select id="checkout-country" v-model="form.country" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm">
                                    <option value="" disabled>{{ $t('shop_checkout.select_country') }}</option>
                                    <template v-for="zone in shippingZones" :key="zone.id">
                                        <option v-for="country in (zone.countries || [])" :key="country" :value="country">
                                            {{ countryNames[country] || country }}
                                        </option>
                                    </template>
                                </select>
                                <p v-if="form.errors.country" class="mt-1 text-sm text-red-500">{{ form.errors.country }}</p>
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 pb-3 cursor-pointer">
                                    <input type="checkbox" v-model="billingSameAsShipping" class="w-4 h-4 text-savino-blue border-gray-300 rounded" />
                                    <span class="text-sm text-gray-600">{{ $t('shop_checkout.billing_same_as_shipping') }}</span>
                                </label>
                            </div>
                            <div v-if="!billingSameAsShipping" class="sm:col-span-2">
                                <label for="checkout-billing" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_billing_address') }}</label>
                                <textarea
                                    id="checkout-billing"
                                    v-model="form.billing_address"
                                    rows="2"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm resize-none"
                                    :placeholder="$t('shop_checkout.placeholder_billing_address')"
                                ></textarea>
                                <p v-if="form.errors.billing_address" class="mt-1 text-sm text-red-500">{{ form.errors.billing_address }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="checkout-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_notes') }}</label>
                                <textarea
                                    id="checkout-notes"
                                    v-model="form.notes"
                                    rows="3"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm resize-none"
                                    :placeholder="$t('shop_checkout.placeholder_notes')"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy Checkbox -->
                    <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.privacy_accepted"
                                class="mt-1 w-4 h-4 text-savino-blue border-gray-300 rounded focus:ring-savino-blue/20"
                            />
                            <span class="text-sm text-gray-600">
                                {{ $t('shop.accept_privacy_1') }}
                                <a :href="route('pages.show', 'privacy-policy')" target="_blank" class="text-savino-blue underline hover:text-savino-blue/80">{{ $t('shop.accept_privacy_2') }}</a>
                            </span>
                        </label>
                        <p v-if="form.errors.privacy_accepted" class="mt-1 text-sm text-red-500">{{ form.errors.privacy_accepted }}</p>
                    </div>

                    <!-- Coupon Code -->
                    <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                        <div class="flex items-center gap-3 mb-4">
                            <svg class="w-5 h-5 text-savino-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">{{ $t('shop_checkout.coupon_label') }}</h3>
                        </div>
                        <div v-if="couponStatus !== 'valid'" class="flex gap-2">
                            <input
                                type="text"
                                v-model="form.coupon_code"
                                class="flex-1 px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm uppercase"
                                :placeholder="$t('shop_checkout.coupon_placeholder')"
                                @keyup.enter="validateCoupon"
                            />
                            <button
                                type="button"
                                @click="validateCoupon"
                                :disabled="!form.coupon_code || couponStatus === 'loading'"
                                class="px-6 py-3 bg-savino-blue text-white text-sm font-bold uppercase rounded-lg hover:bg-savino-blue/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2"
                            >
                                <svg v-if="couponStatus === 'loading'" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {{ $t('shop_checkout.coupon_apply') }}
                            </button>
                        </div>
                        <div v-else class="flex items-center justify-between bg-green-50 border border-green-200 rounded-lg px-4 py-3">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-sm font-medium text-green-800">{{ couponMessage }}</span>
                            </div>
                            <button type="button" @click="removeCoupon" class="text-sm text-red-500 hover:text-red-700 font-medium">
                                {{ $t('shop_checkout.coupon_remove') }}
                            </button>
                        </div>
                        <p v-if="couponStatus === 'invalid'" class="mt-2 text-sm text-red-500">{{ couponMessage }}</p>
                    </div>

                    <!-- Payment Gateway Selector -->
                    <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="w-8 h-8 rounded-full bg-savino-blue text-white flex items-center justify-center text-sm font-bold">2</span>
                            <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">{{ $t('shop_checkout.payment_title') }}</h2>
                        </div>
                        <div class="space-y-3">
                            <label
                                v-for="gateway in paymentGateways"
                                :key="gateway.value"
                                class="flex items-center gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200"
                                :class="form.payment_gateway === gateway.value ? 'border-savino-blue bg-savino-blue/5' : 'border-gray-200 hover:border-gray-300'"
                            >
                                <input
                                    type="radio"
                                    :value="gateway.value"
                                    v-model="form.payment_gateway"
                                    class="w-5 h-5 text-savino-blue border-gray-300 focus:ring-savino-blue/20"
                                />
                                <div class="flex-1">
                                    <span class="font-bold text-gray-900">{{ gateway.label }}</span>
                                </div>
                            </label>
                        </div>
                        <p v-if="!paymentGateways.length" class="text-gray-400 text-sm text-center py-4">{{ $t('shop_checkout.no_gateways') }}</p>
                        <p v-if="form.errors.payment_gateway" class="mt-2 text-sm text-red-500">{{ form.errors.payment_gateway }}</p>
                    </div>
                </div>

                <!-- Order Summary Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100 sticky top-24">
                        <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight mb-6">
                            {{ $t('shop_checkout.order_summary') }}
                        </h2>

                        <!-- Cart Items -->
                        <div v-if="cart.items.length > 0" class="space-y-4 mb-6">
                            <div
                                v-for="(item, index) in cart.items"
                                :key="index"
                                class="flex items-center gap-3 pb-4 border-b border-gray-100 last:border-0 last:pb-0"
                            >
                                <div class="w-14 h-14 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                                    <img v-if="item.product?.image_url" :src="item.product.image_url" :alt="item.name" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</p>
                                    <p class="text-xs text-gray-500">{{ $t('shop_checkout.quantity_label') }} {{ item.quantity ?? 1 }}</p>
                                </div>
                                <span class="text-sm font-bold text-gray-900 flex-shrink-0">
                                    {{ formatPrice(item.price) }}
                                </span>
                            </div>
                        </div>

                        <!-- Empty Cart -->
                        <div v-else class="text-center py-6 mb-6">
                            <span class="text-4xl block mb-2">🛒</span>
                            <p class="text-gray-400 text-sm">{{ $t('shop_checkout.empty_cart') }}</p>
                        </div>

                        <!-- Totals -->
                        <div class="space-y-3 pt-4 border-t border-gray-100">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">{{ $t('shop_checkout.subtotal') }}</span>
                                <span class="text-gray-900 font-medium">{{ formatPrice(cartTotal) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">{{ $t('shop_checkout.shipping') }}</span>
                                <span class="text-gray-900 font-medium" :class="{ 'text-green-600': shippingCost === 0 }">
                                    {{ shippingCost === 0 ? $t('shop_checkout.free_shipping') : formatPrice(shippingCost) }}
                                </span>
                            </div>
                            <div v-if="shippingCost > 0 && selectedZone?.free_threshold" class="text-xs text-savino-gold">
                                {{ $t('shop_checkout.free_shipping_threshold') }}
                            </div>
                            <div v-if="couponDiscount > 0" class="flex justify-between text-sm">
                                <span class="text-green-600">{{ $t('shop_checkout.discount') }}</span>
                                <span class="text-green-600 font-medium">-{{ formatPrice(couponDiscount) }}</span>
                            </div>
                            <div class="flex justify-between pt-3 border-t border-gray-200">
                                <span class="font-bold text-gray-900">{{ $t('shop_checkout.total') }}</span>
                                <span class="text-xl font-black text-savino-blue">
                                    {{ formatPrice(orderTotal) }}
                                </span>
                            </div>
                        </div>

                        <!-- CTA -->
                        <button
                            @click="submitOrder"
                            :disabled="form.processing || cart.items.length === 0 || !form.payment_gateway"
                            class="w-full mt-8 bg-savino-gold text-white font-bold uppercase tracking-wider text-sm px-8 py-4 rounded-lg hover:bg-savino-gold/90 transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            <svg v-if="form.processing" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            {{ form.processing ? $t('shop_checkout.processing') : $t('shop_checkout.confirm_order') }}
                        </button>

                        <p class="text-xs text-gray-400 text-center mt-4">
                            {{ $t('shop_checkout.payment_secure_note') }}
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>
    </PublicLayout>
</template>
