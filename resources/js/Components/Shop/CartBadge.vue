<script setup>
import { onMounted } from 'vue';
import { useCart } from '@/Composables/useCart.js';
import { useTranslations } from '@/Composables/useTranslations.js';

const { cartCount, fetchCartCount, toggleCart } = useCart();
const $t = useTranslations();

onMounted(() => {
    fetchCartCount();
});
</script>

<template>
    <button
        @click="toggleCart"
        class="relative p-2 text-gray-300 hover:text-savino-gold transition-colors duration-300 focus:outline-none"
        :aria-label="`${$t('shop.cart_title') || 'Carrello'} (${cartCount})`"
    >
        <!-- Shopping Bag Icon -->
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
        </svg>

        <!-- Count Badge -->
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="scale-0 opacity-0"
            enter-to-class="scale-100 opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="scale-100 opacity-100"
            leave-to-class="scale-0 opacity-0"
        >
            <span
                v-if="cartCount > 0"
                class="absolute -top-1 -right-1 bg-savino-red text-white text-[10px] font-black min-w-[18px] h-[18px] rounded-full flex items-center justify-center px-1 shadow-lg ring-2 ring-gray-900"
            >
                {{ cartCount > 99 ? '99+' : cartCount }}
            </span>
        </Transition>
    </button>
</template>
