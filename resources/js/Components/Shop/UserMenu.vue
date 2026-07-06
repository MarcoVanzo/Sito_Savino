<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';

const $t = useTranslations();
const page = usePage();
const isOpen = ref(false);
const menuRef = ref(null);

const user = () => page.props.auth?.user;

const toggleMenu = () => {
    isOpen.value = !isOpen.value;
};

const closeMenu = () => {
    isOpen.value = false;
};

const handleLogout = () => {
    closeMenu();
    router.post(route('logout'));
};

// Click-outside
const handleClickOutside = (e) => {
    if (menuRef.value && !menuRef.value.contains(e.target)) {
        closeMenu();
    }
};

// Close on Escape
const handleKeydown = (e) => {
    if (e.key === 'Escape') closeMenu();
};

// Close on navigation
const removeNavigateListener = router.on('navigate', closeMenu);

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeydown);
    removeNavigateListener();
});
</script>

<template>
    <div ref="menuRef" class="relative">
        <!-- Trigger Button -->
        <button
            @click="toggleMenu"
            class="relative p-2 text-gray-300 hover:text-savino-gold transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-savino-gold/50 rounded-lg"
            :aria-label="user() ? $t('shop.my_account') : $t('shop.login')"
            :aria-expanded="isOpen"
            aria-haspopup="true"
        >
            <!-- User Icon -->
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>

            <!-- Online indicator (authenticated) -->
            <span
                v-if="user()"
                class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-emerald-400 rounded-full ring-2 ring-gray-900"
            ></span>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-2 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-2 scale-95"
        >
            <div
                v-show="isOpen"
                class="absolute right-0 top-full mt-3 w-56 bg-white rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] border border-gray-100 overflow-hidden z-50"
                role="menu"
            >
                <!-- GUEST STATE -->
                <template v-if="!user()">
                    <div class="p-2">
                        <Link
                            :href="route('login')"
                            @click="closeMenu"
                            role="menuitem"
                            class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-savino-blue hover:bg-savino-blue/5 rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                            </svg>
                            {{ $t('shop.login') }}
                        </Link>
                        <Link
                            :href="route('shop.register')"
                            @click="closeMenu"
                            role="menuitem"
                            class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-savino-blue hover:bg-savino-blue/5 rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                            </svg>
                            {{ $t('shop.register_link') }}
                        </Link>
                    </div>
                </template>

                <!-- AUTHENTICATED STATE -->
                <template v-else>
                    <!-- User info header -->
                    <div class="px-4 py-3 bg-gradient-to-r from-savino-blue to-gray-900">
                        <p class="text-white text-sm font-bold truncate">{{ user().name }}</p>
                        <p class="text-white/50 text-xs truncate">{{ user().email }}</p>
                    </div>

                    <div class="p-2">
                        <Link
                            :href="route('shop.orders')"
                            @click="closeMenu"
                            role="menuitem"
                            class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-savino-blue hover:bg-savino-blue/5 rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                            </svg>
                            {{ $t('shop.my_orders') }}
                        </Link>

                        <!-- Divider -->
                        <div class="my-1 border-t border-gray-100"></div>

                        <button
                            @click="handleLogout"
                            role="menuitem"
                            class="flex items-center gap-3 w-full px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                            {{ $t('shop.logout') }}
                        </button>
                    </div>
                </template>
            </div>
        </Transition>
    </div>
</template>
