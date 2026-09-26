<script setup>
import { vaiAlPrimoErrore } from '@/Support/primoErrore.js';
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useOgMeta } from '@/Composables/useOgMeta'
import { useFormatPrice } from '@/Composables/useFormatPrice.js'
import { trackInitiateCheckout } from '@/meta-pixel.js'
import { costoDiSpedizione } from '@/Support/spedizione.js'

const $t = useTranslations();
const { formatPrice } = useFormatPrice();
import AddressAutocomplete from '@/Components/Shop/AddressAutocomplete.vue';
import AccettazioneCondizioni from '@/Components/Shop/AccettazioneCondizioni.vue';
import PulsanteOrdine from '@/Components/Shop/PulsanteOrdine.vue';

const page = usePage();
const user = () => page.props.auth?.user;

const props = defineProps({
    cart: {
        type: Object,
        default: () => ({ items: [], total: 0 })
    },
    cartTotal: {
        type: Number,
        default: 0
    },
    cartWeight: {
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

onMounted(() => {
    if (props.cart.items.length === 0) {
        router.visit(route('shop'));

        return;
    }

    trackInitiateCheckout({
        value: Number(props.cartTotal),
        numItems: Number(props.itemCount),
    });
});

const authUser = user();
const form = useForm({
    shipping_first_name: authUser?.name?.split(' ')[0] || '',
    shipping_last_name: authUser?.name?.split(' ').slice(1).join(' ') || '',
    shipping_street: '',
    shipping_city: '',
    shipping_zip_code: '',
    shipping_province: '',
    country: 'IT',
    billing_same_as_shipping: true,
    billing_first_name: '',
    billing_last_name: '',
    billing_street: '',
    billing_city: '',
    billing_zip_code: '',
    billing_province: '',
    billing_country: 'IT',
    payment_gateway: '',
    coupon_code: '',
    notes: '',
    privacy_accepted: false,
    guest_name: authUser?.name || '',
    guest_email: authUser?.email || '',
    guest_phone: '',
    codice_fiscale: '',
    phone: authUser?.phone || '',
});

// Step logic
const currentStep = ref(1);
const stepValidationError = ref('');

const validateStep1 = () => {
    const required = [
        { field: 'shipping_first_name', value: form.shipping_first_name },
        { field: 'shipping_last_name', value: form.shipping_last_name },
        { field: 'shipping_street', value: form.shipping_street },
        { field: 'shipping_city', value: form.shipping_city },
        { field: 'shipping_zip_code', value: form.shipping_zip_code },
        { field: 'shipping_province', value: form.shipping_province },
    ];
    // Codice Fiscale obbligatorio per Italia
    if (form.country === 'IT') {
        required.push({ field: 'codice_fiscale', value: form.codice_fiscale });
    }
    // Telefono obbligatorio per utenti registrati
    if (authUser) {
        required.push({ field: 'phone', value: form.phone });
    }
    // Guest fields required if not auth
    if (!authUser) {
        required.push(
            { field: 'guest_name', value: form.guest_name },
            { field: 'guest_email', value: form.guest_email },
        );
    }
    // Anche la fatturazione, quando e' diversa dalla spedizione
    if (!form.billing_same_as_shipping) {
        ['billing_first_name', 'billing_last_name', 'billing_street', 'billing_city', 'billing_zip_code']
            .forEach((field) => required.push({ field, value: form[field] }));
    }

    // Ogni campo vuoto riceve il proprio errore (aria-invalid + messaggio
    // sotto il campo) e il focus va sul primo: un solo avviso generico in cima
    // non diceva a chi usa uno screen reader quale campo mancasse (WCAG 3.3.1).
    const missing = required.filter(r => !r.value?.toString().trim()).map(r => r.field);
    form.clearErrors(...required.map(r => r.field));
    if (missing.length > 0) {
        missing.forEach((field) => form.setError(field, $t('shop_checkout.field_required')));
        stepValidationError.value = $t('shop_checkout.step_validation_required');
        vaiAlPrimoErrore();
        return false;
    }
    stepValidationError.value = '';
    return true;
};

const goToStep2 = () => {
    if (validateStep1()) {
        currentStep.value = 2;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const goToStep1 = () => {
    currentStep.value = 1;
    stepValidationError.value = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const getCountryName = (code) => {
    const translated = $t(`countries.${code}`);
    return (translated && translated !== `countries.${code}`) ? translated : code;
};

const availableCountries = computed(() => {
    const seen = new Set();
    const countries = [];
    for (const zone of props.shippingZones) {
        for (const code of (zone.countries || [])) {
            if (code !== '*' && !seen.has(code)) {
                seen.add(code);
                countries.push({ code, name: getCountryName(code) });
            }
        }
    }
    return countries.sort((a, b) => a.name.localeCompare(b.name));
});

const selectedZone = computed(() => {
    // Stessa precedenza del server (ShippingZone::findByCountry):
    // prima la corrispondenza esatta del paese, poi la zona wildcard.
    const exact = props.shippingZones.find(z => (z.countries || []).includes(form.country));
    if (exact) return exact;

    return props.shippingZones.find(z => (z.countries || []).includes('*'));
});

// Il conto sta in `Support/spedizione.js`, condiviso con il checkout dell'asta
// e specchio di ShippingZone::calculateShippingCost: soglia gratuita, poi la
// fascia che contiene il peso, poi la tariffa base. Converte anche i numeri,
// che con il cast `decimal:2` arrivano come stringhe (4 + "7.90" = "47.90").
const shippingCost = computed(() => costoDiSpedizione(selectedZone.value, {
    subtotale: props.cartTotal,
    peso: props.cartWeight,
}));

const couponStatus = ref(null);
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
    } catch (error) {
        if (import.meta.env.DEV) console.debug('verifica coupon non riuscita', error);
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
    const subtotal = Number(props.cartTotal) || 0;
    const discount = Number(couponDiscount.value) || 0;

    return Math.max(0, subtotal + shippingCost.value - discount).toFixed(2);
});

// Fix #9: Riporta allo Step 1 se il backend ritorna errori su campi dello Step 1
const step1Fields = ['shipping_first_name', 'shipping_last_name', 'shipping_street',
    'shipping_city', 'shipping_zip_code', 'shipping_province',
    'guest_name', 'guest_email', 'guest_phone', 'country',
    'billing_first_name', 'billing_last_name', 'billing_street',
    'billing_city', 'billing_zip_code', 'billing_province',
    'billing_country', 'codice_fiscale', 'phone'];

// Gli errori che il server lega a un campo del modulo si vedono sotto il
// campo; gli altri (merce esaurita, carrello cambiato, coupon non piu' valido)
// non hanno un posto dove comparire e finiscono nel riquadro sopra il pulsante.
const riquadroErrori = ref(null);
const campiDelModulo = computed(() => [...step1Fields, 'payment_gateway', 'privacy_accepted', 'notes']);
const erroriGenerali = computed(() => Object.entries(form.errors)
    .filter(([campo]) => !campiDelModulo.value.includes(campo))
    .map(([, messaggio]) => messaggio));

const submitOrder = () => {
    form.post(route('shop.checkout.store'), {
        preserveScroll: true,
        onError: (errori) => {
            if (Object.keys(errori).some((campo) => campiDelModulo.value.includes(campo))) {
                vaiAlPrimoErrore();
            } else {
                nextTick(() => riquadroErrori.value?.focus());
            }
        },
    });
};


// Reset billing fields when billing_same_as_shipping is toggled back to true
watch(() => form.billing_same_as_shipping, (isSame) => {
    if (isSame) {
        form.billing_country = form.country;
        form.billing_first_name = '';
        form.billing_last_name = '';
        form.billing_street = '';
        form.billing_city = '';
        form.billing_zip_code = '';
        form.billing_province = '';
    }
});

// Il focus sul primo campo in errore lo porta vaiAlPrimoErrore (onError):
// qui si torna solo al passo 1 quando l'errore sta li'.
watch(() => form.errors, (errors) => {
    if (currentStep.value === 2 && step1Fields.some(f => errors[f])) {
        currentStep.value = 1;
    }
}, { deep: true });

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
            <span class="text-savino-fucsia-chiaro text-sm font-bold uppercase tracking-[0.3em]">{{ $t('shop_checkout.hero_label') }}</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                {{ $t('shop_checkout.og_title') }}
            </h1>
            <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
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
                            <Link :href="route('shop.register')" class="font-bold text-savino-fucsia hover:text-savino-fucsia/80 underline underline-offset-2 transition-colors">
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

            <!-- Step Progress Indicator -->
            <div class="mb-10">
                <div class="flex items-center justify-center">
                    <!-- Step 1 -->
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full font-bold text-sm transition-all duration-300"
                             :class="currentStep >= 1 ? (currentStep > 1 ? 'bg-savino-fucsia text-white' : 'bg-savino-blue text-white') : 'bg-gray-200 text-gray-500'">
                            <svg v-if="currentStep > 1" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <span v-else>1</span>
                        </div>
                        <span class="ml-3 text-sm font-bold hidden sm:inline" :class="currentStep >= 1 ? 'text-gray-900' : 'text-gray-400'">
                            📦 {{ $t('shop_checkout.step_shipping') }}
                        </span>
                    </div>
                    <!-- Line -->
                    <div class="w-16 sm:w-24 h-0.5 mx-4 transition-all duration-300" :class="currentStep > 1 ? 'bg-savino-fucsia' : 'bg-gray-200'"></div>
                    <!-- Step 2 -->
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full font-bold text-sm transition-all duration-300"
                             :class="currentStep >= 2 ? 'bg-savino-blue text-white' : 'bg-gray-200 text-gray-500'">
                            <span>2</span>
                        </div>
                        <span class="ml-3 text-sm font-bold hidden sm:inline" :class="currentStep >= 2 ? 'text-gray-900' : 'text-gray-400'">
                            💳 {{ $t('shop_checkout.step_payment') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Validation Error -->
            <div v-if="stepValidationError" role="alert" class="mb-6 bg-red-50 border border-red-200 rounded-xl px-4 py-3 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <p class="text-sm text-red-700 font-medium">{{ stepValidationError }}</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-8">

                <!-- Form Section (2 cols) -->
                <div class="lg:col-span-2 space-y-8">

                    <!-- STEP 1: Shipping Info -->
                    <template v-if="currentStep === 1">

                        <!-- Guest Info (only when NOT authenticated) -->
                        <div v-if="!user()" class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="w-8 h-8 rounded-full bg-savino-blue/10 text-savino-blue flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                </span>
                                <h2 class="text-lg font-black text-gray-900 uppercase tracking-tight">{{ $t('shop_checkout.label_fullname') }}</h2>
                            </div>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label for="checkout-guest-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_fullname') }}</label>
                                    <input
                                        id="checkout-guest-name"
                                        v-model="form.guest_name"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.guest_name"
                                :aria-describedby="form.errors.guest_name ? 'errore-guest_name' : undefined"
                                        type="text"
                                        autocomplete="name"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                        :placeholder="$t('shop_checkout.placeholder_fullname')"
                                    />
                                    <p v-if="form.errors.guest_name" id="errore-guest_name" class="mt-1 text-sm text-red-700">{{ form.errors.guest_name }}</p>
                                </div>
                                <div>
                                    <label for="checkout-email" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_email') }}</label>
                                    <input
                                        id="checkout-email"
                                        v-model="form.guest_email"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.guest_email"
                                :aria-describedby="form.errors.guest_email ? 'errore-guest_email' : undefined"
                                        type="email"
                                        autocomplete="email"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                        :placeholder="$t('shop_checkout.placeholder_email')"
                                    />
                                    <p v-if="form.errors.guest_email" id="errore-guest_email" class="mt-1 text-sm text-red-700">{{ form.errors.guest_email }}</p>
                                </div>
                                <div>
                                    <label for="checkout-phone" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_phone') }}</label>
                                    <input
                                        id="checkout-phone"
                                        v-model="form.guest_phone"
                                :aria-invalid="!!form.errors.guest_phone"
                                :aria-describedby="form.errors.guest_phone ? 'errore-guest_phone' : undefined"
                                        type="tel"
                                        autocomplete="tel"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                        :placeholder="$t('shop_checkout.placeholder_phone')"
                                    />
                                    <p v-if="form.errors.guest_phone" id="errore-guest_phone" class="mt-1 text-sm text-red-700">{{ form.errors.guest_phone }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Phone for authenticated users -->
                        <div v-if="user()" class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="w-8 h-8 rounded-full bg-savino-blue/10 text-savino-blue flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                    </svg>
                                </span>
                                <h2 class="text-lg font-black text-gray-900 uppercase tracking-tight">{{ $t('shop_checkout.label_contact_phone') }}</h2>
                            </div>
                            <div>
                                <label for="checkout-auth-phone" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_phone') }} *</label>
                                <input
                                    id="checkout-auth-phone"
                                    v-model="form.phone"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.phone"
                                :aria-describedby="form.errors.phone ? 'errore-phone' : undefined"
                                    type="tel"
                                    autocomplete="tel"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                    :placeholder="$t('shop_checkout.placeholder_phone')"
                                />
                                <p v-if="form.errors.phone" id="errore-phone" class="mt-1 text-sm text-red-700">{{ form.errors.phone }}</p>
                                <p class="mt-1 text-xs text-gray-400">{{ $t('shop_checkout.phone_shipping_note') }}</p>
                            </div>
                        </div>

                        <!-- Shipping Address -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="w-8 h-8 rounded-full bg-savino-blue text-white flex items-center justify-center text-sm font-bold">1</span>
                                <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">{{ $t('shop_checkout.shipping_title') }}</h2>
                            </div>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <!-- First Name -->
                                <div>
                                    <label for="checkout-first-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_first_name') }} *</label>
                                    <input
                                        id="checkout-first-name"
                                        v-model="form.shipping_first_name"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.shipping_first_name"
                                :aria-describedby="form.errors.shipping_first_name ? 'errore-shipping_first_name' : undefined"
                                        type="text"
                                        autocomplete="given-name"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                        :placeholder="$t('shop_checkout.placeholder_first_name')"
                                    />
                                    <p v-if="form.errors.shipping_first_name" id="errore-shipping_first_name" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_first_name }}</p>
                                </div>
                                <!-- Last Name -->
                                <div>
                                    <label for="checkout-last-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_last_name') }} *</label>
                                    <input
                                        id="checkout-last-name"
                                        v-model="form.shipping_last_name"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.shipping_last_name"
                                :aria-describedby="form.errors.shipping_last_name ? 'errore-shipping_last_name' : undefined"
                                        type="text"
                                        autocomplete="family-name"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                        :placeholder="$t('shop_checkout.placeholder_last_name')"
                                    />
                                    <p v-if="form.errors.shipping_last_name" id="errore-shipping_last_name" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_last_name }}</p>
                                </div>
                                <!-- Street Address with Autocomplete (full width) -->
                                <div class="sm:col-span-2">
                                    <label for="checkout-street" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_street') }} *</label>
                                    <AddressAutocomplete
                                        id="checkout-street"
                                        v-model="form.shipping_street"
                                        required
                                        aria-required="true"
                                        :aria-invalid="!!form.errors.shipping_street"
                                        :aria-describedby="form.errors.shipping_street ? 'errore-shipping_street' : undefined"
                                        :placeholder="$t('shop_checkout.placeholder_street')"
                                        :country="form.country"
                                        @address-selected="(addr) => {
                                            form.shipping_street = addr.street;
                                            form.shipping_city = addr.city;
                                            form.shipping_zip_code = addr.zip_code;
                                            form.shipping_province = addr.province;
                                        }"
                                    />
                                    <p v-if="form.errors.shipping_street" id="errore-shipping_street" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_street }}</p>
                                </div>
                                <!-- City -->
                                <div>
                                    <label for="checkout-city" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_city') }} *</label>
                                    <input
                                        id="checkout-city"
                                        v-model="form.shipping_city"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.shipping_city"
                                :aria-describedby="form.errors.shipping_city ? 'errore-shipping_city' : undefined"
                                        type="text"
                                        autocomplete="address-level2"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                        :placeholder="$t('shop_checkout.placeholder_city')"
                                    />
                                    <p v-if="form.errors.shipping_city" id="errore-shipping_city" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_city }}</p>
                                </div>
                                <!-- ZIP Code -->
                                <div>
                                    <label for="checkout-zip" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_zip_code') }} *</label>
                                    <input
                                        id="checkout-zip"
                                        v-model="form.shipping_zip_code"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.shipping_zip_code"
                                :aria-describedby="form.errors.shipping_zip_code ? 'errore-shipping_zip_code' : undefined"
                                        type="text"
                                        autocomplete="postal-code"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                        :placeholder="$t('shop_checkout.placeholder_zip_code')"
                                    />
                                    <p v-if="form.errors.shipping_zip_code" id="errore-shipping_zip_code" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_zip_code }}</p>
                                </div>
                                <!-- Province -->
                                <div>
                                    <label for="checkout-province" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_province') }} *</label>
                                    <input
                                        id="checkout-province"
                                        v-model="form.shipping_province"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.shipping_province"
                                :aria-describedby="form.errors.shipping_province ? 'errore-shipping_province' : undefined"
                                        type="text"
                                        autocomplete="address-level1"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                        :placeholder="$t('shop_checkout.placeholder_province')"
                                    />
                                    <p v-if="form.errors.shipping_province" id="errore-shipping_province" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_province }}</p>
                                </div>
                                <!-- Country -->
                                <div>
                                    <label for="checkout-country" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_country') }}</label>
                                    <select id="checkout-country" v-model="form.country"
                                :aria-invalid="!!form.errors.country"
                                :aria-describedby="form.errors.country ? 'errore-country' : undefined" autocomplete="country" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm">
                                        <option value="" disabled>{{ $t('shop_checkout.select_country') }}</option>
                                        <option v-for="c in availableCountries" :key="c.code" :value="c.code">
                                            {{ c.name }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.country" id="errore-country" class="mt-1 text-sm text-red-700">{{ form.errors.country }}</p>
                                </div>

                                <!-- Codice Fiscale (obbligatorio per Italia) -->
                                <div class="sm:col-span-2" v-if="form.country === 'IT'">
                                    <label for="checkout-cf" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_codice_fiscale') }} *</label>
                                    <input
                                        id="checkout-cf"
                                        v-model="form.codice_fiscale"
                                :required="form.country === 'IT'" :aria-required="form.country === 'IT' ? 'true' : undefined"
                                :aria-invalid="!!form.errors.codice_fiscale"
                                :aria-describedby="form.errors.codice_fiscale ? 'errore-codice_fiscale' : undefined"
                                        type="text"
                                        maxlength="16"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm uppercase"
                                        :placeholder="$t('shop_checkout.placeholder_codice_fiscale')"
                                        @input="form.codice_fiscale = form.codice_fiscale.toUpperCase()"
                                    />
                                    <p v-if="form.errors.codice_fiscale" id="errore-codice_fiscale" class="mt-1 text-sm text-red-700">{{ form.errors.codice_fiscale }}</p>
                                </div>
                            </div>

                            <!-- Billing Same as Shipping -->
                            <div class="mt-6 pt-6 border-t border-gray-100">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.billing_same_as_shipping" class="w-4 h-4 text-savino-blue border-gray-300 rounded" />
                                    <span class="text-sm text-gray-600">{{ $t('shop_checkout.billing_same_as_shipping') }}</span>
                                </label>
                            </div>

                            <!-- Billing Address (if different) -->
                            <div v-if="!form.billing_same_as_shipping" class="mt-6 pt-6 border-t border-gray-100">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $t('shop_checkout.label_billing_address') }}</h3>
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="billing-first-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_first_name') }} *</label>
                                        <input id="billing-first-name" v-model="form.billing_first_name"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.billing_first_name"
                                :aria-describedby="form.errors.billing_first_name ? 'errore-billing_first_name' : undefined" type="text" autocomplete="given-name" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm" :placeholder="$t('shop_checkout.placeholder_first_name')" />
                                        <p v-if="form.errors.billing_first_name" id="errore-billing_first_name" class="mt-1 text-sm text-red-700">{{ form.errors.billing_first_name }}</p>
                                    </div>
                                    <div>
                                        <label for="billing-last-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_last_name') }} *</label>
                                        <input id="billing-last-name" v-model="form.billing_last_name"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.billing_last_name"
                                :aria-describedby="form.errors.billing_last_name ? 'errore-billing_last_name' : undefined" type="text" autocomplete="family-name" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm" :placeholder="$t('shop_checkout.placeholder_last_name')" />
                                        <p v-if="form.errors.billing_last_name" id="errore-billing_last_name" class="mt-1 text-sm text-red-700">{{ form.errors.billing_last_name }}</p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="billing-street" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_street') }} *</label>
                                        <input id="billing-street" v-model="form.billing_street"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.billing_street"
                                :aria-describedby="form.errors.billing_street ? 'errore-billing_street' : undefined" type="text" autocomplete="address-line1" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm" :placeholder="$t('shop_checkout.placeholder_street')" />
                                        <p v-if="form.errors.billing_street" id="errore-billing_street" class="mt-1 text-sm text-red-700">{{ form.errors.billing_street }}</p>
                                    </div>
                                    <div>
                                        <label for="billing-city" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_city') }} *</label>
                                        <input id="billing-city" v-model="form.billing_city"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.billing_city"
                                :aria-describedby="form.errors.billing_city ? 'errore-billing_city' : undefined" type="text" autocomplete="address-level2" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm" :placeholder="$t('shop_checkout.placeholder_city')" />
                                        <p v-if="form.errors.billing_city" id="errore-billing_city" class="mt-1 text-sm text-red-700">{{ form.errors.billing_city }}</p>
                                    </div>
                                    <div>
                                        <label for="billing-zip" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_zip_code') }} *</label>
                                        <input id="billing-zip" v-model="form.billing_zip_code"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.billing_zip_code"
                                :aria-describedby="form.errors.billing_zip_code ? 'errore-billing_zip_code' : undefined" type="text" autocomplete="postal-code" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm" :placeholder="$t('shop_checkout.placeholder_zip_code')" />
                                        <p v-if="form.errors.billing_zip_code" id="errore-billing_zip_code" class="mt-1 text-sm text-red-700">{{ form.errors.billing_zip_code }}</p>
                                    </div>
                                    <div>
                                        <label for="billing-province" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_province') }}</label>
                                        <input id="billing-province" v-model="form.billing_province"
                                :aria-invalid="!!form.errors.billing_province"
                                :aria-describedby="form.errors.billing_province ? 'errore-billing_province' : undefined" type="text" autocomplete="address-level1" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm" :placeholder="$t('shop_checkout.placeholder_province')" />
                                        <p v-if="form.errors.billing_province" id="errore-billing_province" class="mt-1 text-sm text-red-700">{{ form.errors.billing_province }}</p>
                                    </div>
                                    <div>
                                        <label for="billing-country" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_billing_country') }}</label>
                                        <select id="billing-country" v-model="form.billing_country"
                                :aria-invalid="!!form.errors.billing_country"
                                :aria-describedby="form.errors.billing_country ? 'errore-billing_country' : undefined" autocomplete="country" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm">
                                            <option value="" disabled>{{ $t('shop_checkout.select_country') }}</option>
                                            <option v-for="c in availableCountries" :key="c.code" :value="c.code">
                                                {{ c.name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.billing_country" id="errore-billing_country" class="mt-1 text-sm text-red-700">{{ form.errors.billing_country }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Next Step Button -->
                        <div class="flex justify-end">
                            <button
                                type="button"
                                @click="goToStep2"
                                class="px-8 py-3 bg-savino-blue text-white font-bold uppercase tracking-wider text-sm rounded-lg hover:bg-savino-blue/90 transition-all duration-200 flex items-center gap-2"
                            >
                                {{ $t('shop_checkout.next_step') }}
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </template>

                    <!-- STEP 2: Payment -->
                    <template v-if="currentStep === 2">

                        <!-- Payment Gateway Selector -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="w-8 h-8 rounded-full bg-savino-blue text-white flex items-center justify-center text-sm font-bold">2</span>
                                <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">{{ $t('shop_checkout.payment_title') }}</h2>
                            </div>
                            <fieldset class="space-y-3" :aria-describedby="form.errors.payment_gateway ? 'errore-payment_gateway' : undefined">
                                <legend class="sr-only">{{ $t('shop_checkout.payment_title') }}</legend>
                                <label
                                    v-for="gateway in paymentGateways"
                                    :key="gateway.value"
                                    class="flex items-center gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200"
                                    :class="form.payment_gateway === gateway.value ? 'border-savino-blue bg-savino-blue/5' : 'border-gray-200 hover:border-gray-300'"
                                >
                                    <input
                                        type="radio"
                                        name="payment_gateway"
                                        :value="gateway.value"
                                        v-model="form.payment_gateway"
                                        :aria-invalid="!!form.errors.payment_gateway"
                                        class="w-5 h-5 text-savino-blue border-gray-300 focus:ring-savino-blue/20"
                                    />
                                    <div class="flex-1">
                                        <span class="font-bold text-gray-900">{{ gateway.label }}</span>
                                    </div>
                                </label>
                            </fieldset>
                            <p v-if="!paymentGateways.length" class="text-gray-400 text-sm text-center py-4">{{ $t('shop_checkout.no_gateways') }}</p>
                            <p v-if="form.errors.payment_gateway" id="errore-payment_gateway" class="mt-2 text-sm text-red-700">{{ form.errors.payment_gateway }}</p>
                        </div>

                        <!-- Coupon Code -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <div class="flex items-center gap-3 mb-4">
                                <svg class="w-5 h-5 text-savino-fucsia" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                                    :aria-label="$t('shop_checkout.coupon_label')"
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
                                    <span class="text-sm font-medium text-green-800" role="status">{{ couponMessage }}</span>
                                </div>
                                <button type="button" @click="removeCoupon" class="text-sm text-red-700 hover:text-red-800 underline-offset-2 hover:underline font-medium">
                                    {{ $t('shop_checkout.coupon_remove') }}
                                </button>
                            </div>
                            <p role="status" class="mt-2 text-sm text-red-700" :class="{ 'sr-only': couponStatus !== 'invalid' }">{{ couponStatus === 'invalid' ? couponMessage : '' }}</p>
                        </div>

                        <!-- Notes -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <label for="checkout-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_notes') }}</label>
                            <textarea
                                id="checkout-notes"
                                v-model="form.notes"
                                rows="3"
                                class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm resize-none"
                                :placeholder="$t('shop_checkout.placeholder_notes')"
                            ></textarea>
                        </div>

                        <!-- Condizioni di vendita, recesso e privacy -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <AccettazioneCondizioni v-model="form.privacy_accepted" :errore="form.errors.privacy_accepted" />
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="flex justify-between">
                            <button
                                type="button"
                                @click="goToStep1"
                                class="px-6 py-3 bg-gray-100 text-gray-700 font-bold uppercase tracking-wider text-sm rounded-lg hover:bg-gray-200 transition-all duration-200 flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                {{ $t('shop_checkout.prev_step') }}
                            </button>
                        </div>
                    </template>

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
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ item.product?.name || item.name }}</p>
                                    <p v-if="item.variant" class="text-xs text-gray-500 mb-0.5">
                                        {{ item.variant.size }}{{ item.variant.color ? ` - ${item.variant.color}` : '' }}
                                    </p>
                                    <p v-if="item.personalizzazione" class="text-xs text-savino-fucsia font-semibold mb-0.5">+ {{ item.personalizzazione }}</p>
                                    <p v-if="item.personalizzazione" class="text-xs text-gray-600 mb-0.5">{{ $t('shop.personalization_excluded_short') }}</p>
                                    <p class="text-xs text-gray-500">{{ item.quantity ?? 1 }} × {{ formatPrice(item.unit_price) }}</p>
                                </div>
                                <span class="text-sm font-bold text-gray-900 flex-shrink-0">
                                    {{ formatPrice(item.unit_price * (item.quantity ?? 1)) }}
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
                                <div class="text-right">
                                    <span class="text-gray-900 font-medium" :class="{ 'text-green-600': shippingCost === 0 }">
                                        {{ shippingCost === 0 ? $t('shop_checkout.free_shipping') : formatPrice(shippingCost) }}
                                    </span>
                                    <div v-if="selectedZone?.estimated_days_min" class="text-xs text-gray-500 mt-1">
                                        📦 {{ $t('shop_checkout.estimated_delivery') }}: {{ selectedZone.estimated_days_min }}-{{ selectedZone.estimated_days_max }} {{ $t('shop_checkout.business_days') }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="shippingCost > 0 && selectedZone?.free_threshold" class="text-xs text-savino-fucsia">
                                {{ $t('shop_checkout.free_shipping_over', { amount: formatPrice(Number(selectedZone.free_threshold)) }) }}
                            </div>
                            <div v-if="couponDiscount > 0" class="flex justify-between text-sm">
                                <span class="text-green-600">{{ $t('shop_checkout.discount') }}</span>
                                <span class="text-green-600 font-medium">-{{ formatPrice(couponDiscount) }}</span>
                            </div>
                            <div class="flex justify-between pt-3 border-t border-gray-200">
                                <span class="font-bold text-gray-900">{{ $t('shop_checkout.total') }}</span>
                                <span class="text-xl font-black text-savino-red">
                                    {{ formatPrice(orderTotal) }}
                                </span>
                            </div>
                        </div>

                        <!-- Errori senza un campo a cui appoggiarsi (merce esaurita,
                             carrello cambiato, coupon): prende il focus dopo
                             l'invio, altrimenti non li vedrebbe nessuno. -->
                        <div
                            v-if="currentStep === 2 && erroriGenerali.length"
                            ref="riquadroErrori"
                            role="alert"
                            tabindex="-1"
                            class="mt-8 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600"
                        >
                            <p class="font-bold">{{ $t('shop_checkout.order_errors_title') }}</p>
                            <ul class="mt-1 list-disc pl-5 space-y-1">
                                <li v-for="(messaggio, i) in erroriGenerali" :key="i">{{ messaggio }}</li>
                            </ul>
                        </div>

                        <!-- CTA (only on step 2) -->
                        <PulsanteOrdine
                            v-if="currentStep === 2"
                            :etichetta="$t('shop_checkout.confirm_order')"
                            :in-corso="form.processing"
                            :disabilitato="form.processing || cart.items.length === 0 || !form.payment_gateway || !form.privacy_accepted"
                            @click="submitOrder"
                        />

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
