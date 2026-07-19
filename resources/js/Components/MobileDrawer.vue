<script setup>
import { watch, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';
import CartBadge from '@/Components/Shop/CartBadge.vue';
import UserMenu from '@/Components/Shop/UserMenu.vue';

const $t = useTranslations();
const page = usePage();

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

const user = () => page.props.auth?.user;

const handleLogout = () => {
    router.post(route('logout'));
};

// Body scroll lock when drawer is open
watch(() => props.isOpen, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
});

// Escape key to close drawer
const handleEscape = (e) => {
    if (e.key === 'Escape') emit('toggle');
};
watch(() => props.isOpen, (val) => {
    if (val) document.addEventListener('keydown', handleEscape);
    else document.removeEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
    <!-- MOBILE MENU BUTTON -->
    <div class="flex items-center xl:hidden z-50 gap-1">
        <!-- Language Switcher slot (mobile) -->
        <slot name="language-switcher" />

        <!-- User Menu (mobile) -->
        <UserMenu />

        <!-- Cart Badge (mobile) -->
        <CartBadge />

        <button @click="emit('toggle')" type="button" :aria-label="$t('common.open_menu')" :aria-expanded="isOpen" class="text-white hover:text-savino-red focus:outline-none p-3 min-w-[44px] min-h-[44px] flex items-center justify-center">
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
        <div v-show="isOpen" class="xl:hidden absolute top-0 left-0 w-full bg-savino-blue border-t border-white/10 pt-36 pb-6 px-4 shadow-xl z-40 h-[100dvh] overflow-y-auto">
            <nav role="navigation" :aria-label="$t('nav.mobile_menu')" class="flex flex-col space-y-2 text-center pb-10">
                <div v-for="(item, index) in navigation" :key="item.label" class="border-b border-white/10 last:border-0">
                    <button type="button" 
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
                            class="block py-3 text-sm font-semibold uppercase tracking-widest text-gray-300 hover:text-white min-h-[44px] flex items-center justify-center"
                        >
                            {{ sub.label }}
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- SEZIONE ACCOUNT -->
            <div class="border-t border-white/10 pt-6 mt-2 px-2">
                <!-- Guest -->
                <template v-if="!user()">
                    <Link
                        :href="route('login')"
                        class="flex items-center justify-center gap-3 w-full py-4 px-4 text-sm font-bold uppercase tracking-widest text-savino-gold hover:text-white transition-colors min-h-[44px]"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        {{ $t('shop.login') }}
                    </Link>
                    <Link
                        :href="route('shop.register')"
                        class="flex items-center justify-center gap-3 w-full py-4 px-4 text-sm font-bold uppercase tracking-widest text-white/70 hover:text-white transition-colors min-h-[44px]"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                        </svg>
                        {{ $t('shop.register_link') }}
                    </Link>
                </template>

                <!-- Autenticato -->
                <template v-else>
                    <p class="text-center text-white/40 text-xs uppercase tracking-widest mb-3">
                        {{ $t('shop.welcome_user', { name: user().name }) }}
                    </p>
                    <Link
                        :href="route('shop.orders')"
                        class="flex items-center justify-center gap-3 w-full py-4 px-4 text-sm font-bold uppercase tracking-widest text-savino-gold hover:text-white transition-colors min-h-[44px]"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                        </svg>
                        {{ $t('shop.my_orders') }}
                    </Link>
                    <button type="button"
                        @click="handleLogout"
                        class="flex items-center justify-center gap-3 w-full py-4 px-4 text-sm font-bold uppercase tracking-widest text-red-400 hover:text-red-300 transition-colors min-h-[44px]"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                        {{ $t('shop.logout') }}
                    </button>
                </template>
            </div>
        </div>
    </transition>
</template>
