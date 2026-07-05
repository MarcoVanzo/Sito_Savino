<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useOgMeta } from '@/Composables/useOgMeta'
import { useFormatPrice } from '@/Composables/useFormatPrice.js'

const $t = useTranslations();
const { formatPrice } = useFormatPrice();



const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    },
    cart: {
        type: Object,
        default: () => ({ items: [], total: 0 })
    }
})

const form = useForm({
    guest_name: '',
    guest_email: '',
    guest_phone: '',
    shipping_address: '',
    billing_address: '',
    country: 'IT',
    payment_gateway: 'stripe',
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

const shippingCost = computed(() => {
    return props.cart.total >= 50 ? 0 : 5.90;
});

const orderTotal = computed(() => {
    return (props.cart.total + shippingCost.value).toFixed(2);
});



const submitOrder = () => {
    form.post(route('shop.checkout.store'), {
        preserveScroll: true,
    });
};

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('shop_checkout.og_title'),
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
                {{ page?.title ?? $t('shop_checkout.og_title') }}
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
                                    placeholder="+39 333 000 0000"
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
                                <select
                                    id="checkout-country"
                                    v-model="form.country"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                >
                                    <option value="IT">Italia</option>
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

                    <!-- Payment Placeholder -->
                    <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="w-8 h-8 rounded-full bg-savino-blue text-white flex items-center justify-center text-sm font-bold">2</span>
                            <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">{{ $t('shop_checkout.payment_title') }}</h2>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-8 border border-dashed border-gray-300 text-center">
                            <span class="text-4xl block mb-3">💳</span>
                            <p class="text-gray-500 font-medium">{{ $t('shop_checkout.payment_integration') }}</p>
                            <p class="text-gray-400 text-sm mt-1">{{ $t('shop_checkout.payment_coming_soon') }}</p>
                            <div class="flex items-center justify-center gap-4 mt-6">
                                <div class="px-4 py-2 bg-white rounded-lg border border-gray-200 text-sm text-gray-500">Visa</div>
                                <div class="px-4 py-2 bg-white rounded-lg border border-gray-200 text-sm text-gray-500">Mastercard</div>
                                <div class="px-4 py-2 bg-white rounded-lg border border-gray-200 text-sm text-gray-500">PayPal</div>
                            </div>
                        </div>
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
                                <div class="w-14 h-14 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <span class="text-2xl">🛍️</span>
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
                                <span class="text-gray-900 font-medium">{{ formatPrice(cart.total) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">{{ $t('shop_checkout.shipping') }}</span>
                                <span class="text-gray-900 font-medium" :class="{ 'text-green-600': shippingCost === 0 }">
                                    {{ shippingCost === 0 ? $t('shop_checkout.free_shipping') : formatPrice(shippingCost) }}
                                </span>
                            </div>
                            <div v-if="cart.total < 50 && cart.items.length > 0" class="text-xs text-savino-gold">
                                {{ $t('shop_checkout.free_shipping_threshold') }}
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
                            :disabled="cart.items.length === 0"
                            class="w-full mt-8 bg-savino-gold text-white font-bold uppercase tracking-wider text-sm px-8 py-4 rounded-lg hover:bg-savino-gold/90 transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                           
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            {{ $t('shop_checkout.confirm_order') }}
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
