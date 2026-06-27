<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: PublicLayout })

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

const shippingForm = ref({
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    province: '',
    cap: '',
    notes: ''
})

const shippingCost = computed(() => {
    return props.cart.total >= 50 ? 0 : 5.90
})

const orderTotal = computed(() => {
    return (props.cart.total + shippingCost.value).toFixed(2)
})

const formatPrice = (price) => {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(price)
}

const submitOrder = () => {
    // Placeholder per l'invio dell'ordine
    alert('Ordine inviato! (placeholder)')
}
</script>

<template>
    <Head :title="page?.title ?? 'Checkout'" />

    <!-- Hero -->
    <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
        <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
            <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Il Tuo Ordine</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                {{ page?.title ?? 'Checkout' }}
            </h1>
            <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
            <p class="text-white/70 text-lg max-w-2xl mx-auto">
                Completa il tuo ordine in pochi semplici passaggi.
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
                            <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">Dati di Spedizione</h2>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label for="checkout-firstname" class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                                <input
                                    id="checkout-firstname"
                                    v-model="shippingForm.firstName"
                                    type="text"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                   
                                    placeholder="Mario"
                                />
                            </div>
                            <div>
                                <label for="checkout-lastname" class="block text-sm font-medium text-gray-700 mb-1">Cognome *</label>
                                <input
                                    id="checkout-lastname"
                                    v-model="shippingForm.lastName"
                                    type="text"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                   
                                    placeholder="Rossi"
                                />
                            </div>
                            <div>
                                <label for="checkout-email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                <input
                                    id="checkout-email"
                                    v-model="shippingForm.email"
                                    type="email"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                   
                                    placeholder="mario@esempio.it"
                                />
                            </div>
                            <div>
                                <label for="checkout-phone" class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                                <input
                                    id="checkout-phone"
                                    v-model="shippingForm.phone"
                                    type="tel"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                   
                                    placeholder="+39 333 000 0000"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label for="checkout-address" class="block text-sm font-medium text-gray-700 mb-1">Indirizzo *</label>
                                <input
                                    id="checkout-address"
                                    v-model="shippingForm.address"
                                    type="text"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                   
                                    placeholder="Via Roma 1"
                                />
                            </div>
                            <div>
                                <label for="checkout-city" class="block text-sm font-medium text-gray-700 mb-1">Città *</label>
                                <input
                                    id="checkout-city"
                                    v-model="shippingForm.city"
                                    type="text"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                   
                                    placeholder="Firenze"
                                />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="checkout-province" class="block text-sm font-medium text-gray-700 mb-1">Prov.</label>
                                    <input
                                        id="checkout-province"
                                        v-model="shippingForm.province"
                                        type="text"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                       
                                        placeholder="FI"
                                        maxlength="2"
                                    />
                                </div>
                                <div>
                                    <label for="checkout-cap" class="block text-sm font-medium text-gray-700 mb-1">CAP *</label>
                                    <input
                                        id="checkout-cap"
                                        v-model="shippingForm.cap"
                                        type="text"
                                        class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
                                       
                                        placeholder="50100"
                                        maxlength="5"
                                    />
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="checkout-notes" class="block text-sm font-medium text-gray-700 mb-1">Note (opzionale)</label>
                                <textarea
                                    id="checkout-notes"
                                    v-model="shippingForm.notes"
                                    rows="3"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm resize-none"
                                   
                                    placeholder="Istruzioni per la consegna..."
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Placeholder -->
                    <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="w-8 h-8 rounded-full bg-savino-blue text-white flex items-center justify-center text-sm font-bold">2</span>
                            <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">Metodo di Pagamento</h2>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-8 border border-dashed border-gray-300 text-center">
                            <span class="text-4xl block mb-3">💳</span>
                            <p class="text-gray-500 font-medium">Integrazione pagamento</p>
                            <p class="text-gray-400 text-sm mt-1">Stripe / PayPal — prossimamente disponibile</p>
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
                            Riepilogo Ordine
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
                                    <p class="text-xs text-gray-500">Qtà: {{ item.quantity ?? 1 }}</p>
                                </div>
                                <span class="text-sm font-bold text-gray-900 flex-shrink-0">
                                    {{ formatPrice(item.price) }}
                                </span>
                            </div>
                        </div>

                        <!-- Empty Cart -->
                        <div v-else class="text-center py-6 mb-6">
                            <span class="text-4xl block mb-2">🛒</span>
                            <p class="text-gray-400 text-sm">Il tuo carrello è vuoto</p>
                        </div>

                        <!-- Totals -->
                        <div class="space-y-3 pt-4 border-t border-gray-100">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Subtotale</span>
                                <span class="text-gray-900 font-medium">{{ formatPrice(cart.total) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Spedizione</span>
                                <span class="text-gray-900 font-medium" :class="{ 'text-green-600': shippingCost === 0 }">
                                    {{ shippingCost === 0 ? 'Gratuita' : formatPrice(shippingCost) }}
                                </span>
                            </div>
                            <div v-if="cart.total < 50 && cart.items.length > 0" class="text-xs text-savino-gold">
                                Spedizione gratuita per ordini sopra i €50
                            </div>
                            <div class="flex justify-between pt-3 border-t border-gray-200">
                                <span class="font-bold text-gray-900">Totale</span>
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
                            Conferma Ordine
                        </button>

                        <p class="text-xs text-gray-400 text-center mt-4">
                            Pagamento sicuro e protetto. I tuoi dati sono al sicuro.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>
</template>
