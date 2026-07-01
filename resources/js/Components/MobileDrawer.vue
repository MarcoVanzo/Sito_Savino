<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    navigation: {
        type: Array,
        required: true,
    },
    isOpen: {
        type: Boolean,
        default: false,
    },
    activeIndex: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['toggle', 'toggle-item']);
</script>

<template>
    <!-- MOBILE MENU BUTTON -->
    <div class="flex items-center xl:hidden z-50">
        <button @click="emit('toggle')" type="button" aria-label="Apri menu" :aria-expanded="isOpen" class="text-white hover:text-savino-red focus:outline-none p-2">
            <svg v-if="!isOpen" class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <svg v-else class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- DRAWER MOBILE -->
    <transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="-translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-full opacity-0"
    >
        <div v-show="isOpen" class="xl:hidden absolute top-0 left-0 w-full bg-savino-blue border-t border-white/10 pt-20 pb-6 px-4 shadow-xl z-40 h-[100dvh] overflow-y-auto">
            <nav role="navigation" aria-label="Menu mobile" class="flex flex-col space-y-2 text-center pb-10">
                <div v-for="(item, index) in navigation" :key="item.label" class="border-b border-white/10 last:border-0">
                    <button 
                        @click="emit('toggle-item', index)"
                        aria-haspopup="true"
                        :aria-expanded="activeIndex === index"
                        class="w-full flex items-center justify-between py-4 px-4 text-[14px] font-bold uppercase tracking-widest text-white focus:outline-none"
                        :class="{'text-savino-red': $page.url.startsWith(item.href) || activeIndex === index, 'text-[#ED028C]': item.isHighlight}"
                    >
                        <span>{{ item.label }}</span>
                        <svg class="w-4 h-4 transition-transform" :class="{'rotate-180': activeIndex === index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    
                    <!-- Sottomenu Mobile -->
                    <div v-show="activeIndex === index" class="bg-black/20 pb-4 pt-2">
                        <Link 
                            v-for="sub in item.children" 
                            :key="sub.label"
                            :href="sub.href"
                            class="block py-2 text-[12px] font-semibold uppercase tracking-widest text-gray-300 hover:text-white"
                        >
                            {{ sub.label }}
                        </Link>
                    </div>
                </div>
            </nav>
        </div>
    </transition>
</template>
