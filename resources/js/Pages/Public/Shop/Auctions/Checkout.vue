<script setup>
import { vaiAlPrimoErrore } from '@/Support/primoErrore.js';
import { computed, nextTick, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AddressAutocomplete from '@/Components/Shop/AddressAutocomplete.vue';
import CountdownTimer from '@/Components/Shop/Auction/CountdownTimer.vue';
import AccettazioneCondizioni from '@/Components/Shop/AccettazioneCondizioni.vue';
import PulsanteOrdine from '@/Components/Shop/PulsanteOrdine.vue';
import { useTranslations } from '@/Composables/useTranslations.js';
import { useFormatPrice } from '@/Composables/useFormatPrice.js';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useOgMeta } from '@/Composables/useOgMeta';
import { useAuctionCheckout } from '@/Composables/useAuctionCheckout.js';
import { costoDiSpedizione } from '@/Support/spedizione.js';
import { metodoPredefinito } from '@/Support/metodiDiPagamento.js';

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
    // Il peso del pezzo battuto, ripiego compreso: decide la fascia tariffaria
    // della zona (Product::pesoPerLaSpedizione).
    pesoDelCollo: { type: [Number, String], default: 0 },
    checkoutDeadline: { type: String, default: null },
    winningBid: { type: [Number, String], default: 0 },
    token: { type: String, default: null },
    // Solo i metodi con le credenziali e attivi dal pannello
    // (PaymentGateway::offertiAlleAste): Stripe e/o PayPal.
    paymentGateways: { type: Array, default: () => [] },
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
    payment_gateway: metodoPredefinito(props.paymentGateways),
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

// Lo stesso conto del server (ShippingZone::calculateShippingCost) e dello
// shop: qui era rimasta la sola tariffa base, e appena una zona prende le
// fasce di peso il totale mostrato al vincitore non è quello che gli viene
// addebitato.
const shippingCost = computed(() => costoDiSpedizione(selectedZone.value, {
    subtotale: bidAmount.value,
    peso: props.pesoDelCollo,
}));

const orderTotal = computed(() => bidAmount.value + shippingCost.value);

// I campi da compilare prima di pagare. Il pulsante resta attivo: spento non
// diceva perche', e chi usa la tastiera o uno screen reader non aveva modo di
// scoprirlo. Al clic ogni campo vuoto riceve il suo errore e il focus va al
// primo (WCAG 3.3.1).
const campiObbligatori = () => {
    const campi = ['phone', 'shipping_first_name', 'shipping_last_name', 'shipping_street',
        'shipping_city', 'shipping_zip_code', 'shipping_province'];
    if (form.country === 'IT') campi.push('codice_fiscale');
    if (!form.billing_same_as_shipping) {
        campi.push('billing_first_name', 'billing_last_name', 'billing_street', 'billing_city', 'billing_zip_code');
    }
    return campi;
};

const validaIlModulo = () => {
    const campi = campiObbligatori();
    form.clearErrors(...campi, 'payment_gateway', 'privacy_accepted');
    const mancanti = campi.filter((campo) => !form[campo]?.toString().trim());
    mancanti.forEach((campo) => form.setError(campo, $t('shop_checkout.field_required')));
    if (!form.payment_gateway) {
        form.setError('payment_gateway', $t('shop_checkout.payment_required'));
    }
    if (!form.privacy_accepted) {
        form.setError('privacy_accepted', $t('shop_checkout.terms_required'));
    }
    return mancanti.length === 0 && !!form.payment_gateway && form.privacy_accepted;
};

// Gli errori che il server non lega a un campo del modulo (`general` e simili)
// stanno nel riquadro sopra il pulsante, che prende il focus dopo l'invio.
const campiDelModulo = ['phone', 'shipping_first_name', 'shipping_last_name', 'shipping_street',
    'shipping_city', 'shipping_zip_code', 'shipping_province', 'country', 'codice_fiscale',
    'billing_first_name', 'billing_last_name', 'billing_street', 'billing_city', 'billing_zip_code',
    'billing_province', 'notes', 'payment_gateway', 'privacy_accepted'];
const riquadroErrori = ref(null);
const erroriGenerali = computed(() => Object.entries(form.errors)
    .filter(([campo]) => !campiDelModulo.includes(campo))
    .map(([, messaggio]) => messaggio));

const submitOrder = () => {
    if (!checkoutToken.value || form.processing) return;
    if (!validaIlModulo()) {
        vaiAlPrimoErrore();
        return;
    }
    form.post(route('shop.auction-checkout.store', { token: checkoutToken.value }), {
        preserveScroll: true,
        onError: (errori) => {
            if (Object.keys(errori).some((campo) => campiDelModulo.includes(campo))) {
                vaiAlPrimoErrore();
            } else {
                nextTick(() => riquadroErrori.value?.focus());
            }
        },
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
                <span class="text-savino-fucsia-chiaro text-sm font-bold uppercase tracking-[0.3em]">
                    {{ $t('auction_checkout.hero_label') }}
                </span>
                <h1 class="text-3xl md:text-5xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ $t('auction_checkout.title') }}
                </h1>
                <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
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
                                required aria-required="true"
                                :aria-invalid="!!form.errors.phone"
                                :aria-describedby="form.errors.phone ? 'errore-phone' : undefined"
                                type="tel"
                                autocomplete="tel"
                                :class="inputClass"
                                :placeholder="$t('shop_checkout.placeholder_phone')"
                            />
                            <p v-if="form.errors.phone" id="errore-phone" class="mt-1 text-sm text-red-700">{{ form.errors.phone }}</p>
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
                                    <input id="auction-first-name" v-model="form.shipping_first_name"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.shipping_first_name"
                                :aria-describedby="form.errors.shipping_first_name ? 'errore-shipping_first_name' : undefined" type="text" autocomplete="given-name" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_first_name')" />
                                    <p v-if="form.errors.shipping_first_name" id="errore-shipping_first_name" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_first_name }}</p>
                                </div>
                                <div>
                                    <label for="auction-last-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_last_name') }} *</label>
                                    <input id="auction-last-name" v-model="form.shipping_last_name"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.shipping_last_name"
                                :aria-describedby="form.errors.shipping_last_name ? 'errore-shipping_last_name' : undefined" type="text" autocomplete="family-name" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_last_name')" />
                                    <p v-if="form.errors.shipping_last_name" id="errore-shipping_last_name" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_last_name }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="auction-street" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_street') }} *</label>
                                    <AddressAutocomplete
                                        id="auction-street"
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
                                <div>
                                    <label for="auction-city" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_city') }} *</label>
                                    <input id="auction-city" v-model="form.shipping_city"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.shipping_city"
                                :aria-describedby="form.errors.shipping_city ? 'errore-shipping_city' : undefined" type="text" autocomplete="address-level2" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_city')" />
                                    <p v-if="form.errors.shipping_city" id="errore-shipping_city" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_city }}</p>
                                </div>
                                <div>
                                    <label for="auction-zip" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_zip_code') }} *</label>
                                    <input id="auction-zip" v-model="form.shipping_zip_code"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.shipping_zip_code"
                                :aria-describedby="form.errors.shipping_zip_code ? 'errore-shipping_zip_code' : undefined" type="text" autocomplete="postal-code" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_zip_code')" />
                                    <p v-if="form.errors.shipping_zip_code" id="errore-shipping_zip_code" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_zip_code }}</p>
                                </div>
                                <div>
                                    <label for="auction-province" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_province') }} *</label>
                                    <input id="auction-province" v-model="form.shipping_province"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.shipping_province"
                                :aria-describedby="form.errors.shipping_province ? 'errore-shipping_province' : undefined" type="text" autocomplete="address-level1" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_province')" />
                                    <p v-if="form.errors.shipping_province" id="errore-shipping_province" class="mt-1 text-sm text-red-700">{{ form.errors.shipping_province }}</p>
                                </div>
                                <div>
                                    <label for="auction-country" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_country') }}</label>
                                    <select id="auction-country" v-model="form.country"
                                :aria-invalid="!!form.errors.country"
                                :aria-describedby="form.errors.country ? 'errore-country' : undefined" autocomplete="country" :class="inputClass">
                                        <option value="" disabled>{{ $t('shop_checkout.select_country') }}</option>
                                        <option v-for="c in availableCountries" :key="c.code" :value="c.code">{{ c.name }}</option>
                                    </select>
                                    <p v-if="form.errors.country" id="errore-country" class="mt-1 text-sm text-red-700">{{ form.errors.country }}</p>
                                </div>
                                <div v-if="form.country === 'IT'" class="sm:col-span-2">
                                    <label for="auction-cf" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_codice_fiscale') }} *</label>
                                    <input
                                        id="auction-cf"
                                        v-model="form.codice_fiscale"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.codice_fiscale"
                                :aria-describedby="form.errors.codice_fiscale ? 'errore-codice_fiscale' : undefined"
                                        type="text"
                                        maxlength="16"
                                        :class="[inputClass, 'uppercase']"
                                        :placeholder="$t('shop_checkout.placeholder_codice_fiscale')"
                                        @input="form.codice_fiscale = form.codice_fiscale.toUpperCase()"
                                    />
                                    <p v-if="form.errors.codice_fiscale" id="errore-codice_fiscale" class="mt-1 text-sm text-red-700">{{ form.errors.codice_fiscale }}</p>
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
                                        <input id="auction-billing-first-name" v-model="form.billing_first_name"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.billing_first_name"
                                :aria-describedby="form.errors.billing_first_name ? 'errore-billing_first_name' : undefined" type="text" autocomplete="given-name" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_first_name')" />
                                        <p v-if="form.errors.billing_first_name" id="errore-billing_first_name" class="mt-1 text-sm text-red-700">{{ form.errors.billing_first_name }}</p>
                                    </div>
                                    <div>
                                        <label for="auction-billing-last-name" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_last_name') }} *</label>
                                        <input id="auction-billing-last-name" v-model="form.billing_last_name"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.billing_last_name"
                                :aria-describedby="form.errors.billing_last_name ? 'errore-billing_last_name' : undefined" type="text" autocomplete="family-name" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_last_name')" />
                                        <p v-if="form.errors.billing_last_name" id="errore-billing_last_name" class="mt-1 text-sm text-red-700">{{ form.errors.billing_last_name }}</p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="auction-billing-street" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_street') }} *</label>
                                        <input id="auction-billing-street" v-model="form.billing_street"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.billing_street"
                                :aria-describedby="form.errors.billing_street ? 'errore-billing_street' : undefined" type="text" autocomplete="address-line1" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_street')" />
                                        <p v-if="form.errors.billing_street" id="errore-billing_street" class="mt-1 text-sm text-red-700">{{ form.errors.billing_street }}</p>
                                    </div>
                                    <div>
                                        <label for="auction-billing-city" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_city') }} *</label>
                                        <input id="auction-billing-city" v-model="form.billing_city"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.billing_city"
                                :aria-describedby="form.errors.billing_city ? 'errore-billing_city' : undefined" type="text" autocomplete="address-level2" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_city')" />
                                        <p v-if="form.errors.billing_city" id="errore-billing_city" class="mt-1 text-sm text-red-700">{{ form.errors.billing_city }}</p>
                                    </div>
                                    <div>
                                        <label for="auction-billing-zip" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_zip_code') }} *</label>
                                        <input id="auction-billing-zip" v-model="form.billing_zip_code"
                                required aria-required="true"
                                :aria-invalid="!!form.errors.billing_zip_code"
                                :aria-describedby="form.errors.billing_zip_code ? 'errore-billing_zip_code' : undefined" type="text" autocomplete="postal-code" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_zip_code')" />
                                        <p v-if="form.errors.billing_zip_code" id="errore-billing_zip_code" class="mt-1 text-sm text-red-700">{{ form.errors.billing_zip_code }}</p>
                                    </div>
                                    <div>
                                        <label for="auction-billing-province" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('shop_checkout.label_province') }} *</label>
                                        <input id="auction-billing-province" v-model="form.billing_province"
                                :aria-invalid="!!form.errors.billing_province"
                                :aria-describedby="form.errors.billing_province ? 'errore-billing_province' : undefined" type="text" autocomplete="address-level1" :class="inputClass" :placeholder="$t('shop_checkout.placeholder_province')" />
                                        <p v-if="form.errors.billing_province" id="errore-billing_province" class="mt-1 text-sm text-red-700">{{ form.errors.billing_province }}</p>
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
                                :aria-invalid="!!form.errors.notes"
                                :aria-describedby="form.errors.notes ? 'errore-notes' : undefined"
                                rows="3"
                                maxlength="1000"
                                :class="[inputClass, 'resize-none']"
                                :placeholder="$t('shop_checkout.placeholder_notes')"
                            ></textarea>
                            <p v-if="form.errors.notes" id="errore-notes" class="mt-1 text-sm text-red-700">{{ form.errors.notes }}</p>
                        </div>

                        <!-- Metodo di pagamento -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <fieldset class="space-y-3" :aria-describedby="form.errors.payment_gateway ? 'errore-payment_gateway' : undefined">
                                <legend class="text-xl font-black text-gray-900 uppercase tracking-tight mb-6">{{ $t('shop_checkout.payment_title') }}</legend>
                                <label
                                    v-for="gateway in paymentGateways"
                                    :key="gateway.value"
                                    class="flex items-center gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200"
                                    :class="form.payment_gateway === gateway.value ? 'border-savino-blue bg-savino-blue/5' : 'border-gray-200 hover:border-gray-300'"
                                >
                                    <input
                                        v-model="form.payment_gateway"
                                        type="radio"
                                        name="payment_gateway"
                                        :value="gateway.value"
                                        :aria-invalid="!!form.errors.payment_gateway"
                                        class="w-5 h-5 text-savino-blue border-gray-300 focus:ring-savino-blue/20"
                                    />
                                    <span class="flex-1 font-bold text-gray-900">{{ gateway.label }}</span>
                                </label>
                            </fieldset>
                            <p v-if="!paymentGateways.length" class="text-gray-500 text-sm text-center py-4">{{ $t('shop_checkout.no_gateways') }}</p>
                            <p v-if="form.errors.payment_gateway" id="errore-payment_gateway" class="mt-2 text-sm text-red-700">{{ form.errors.payment_gateway }}</p>
                        </div>

                        <!-- Condizioni di vendita, recesso e privacy -->
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                            <AccettazioneCondizioni v-model="form.privacy_accepted" :errore="form.errors.privacy_accepted" />
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
                                    <span class="inline-block text-[10px] font-bold uppercase tracking-widest text-savino-fucsia mb-0.5">
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

                            <div
                                v-if="erroriGenerali.length"
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

                            <PulsanteOrdine
                                :etichetta="$t('auction_checkout.pay_now')"
                                :in-corso="form.processing"
                                :disabilitato="form.processing"
                                @click="submitOrder"
                            />

                            <p class="text-xs text-gray-400 text-center mt-4">
                                {{ $t('auction_checkout.gateway_note') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
