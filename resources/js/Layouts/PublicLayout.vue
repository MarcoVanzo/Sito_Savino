<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const isMobileMenuOpen = ref(false);

// Chiude il menu quando si cambia pagina
router.on('navigate', () => {
    isMobileMenuOpen.value = false;
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex flex-col font-sans">
        <!-- HEADER STICKY -->
        <header class="sticky top-0 z-50 bg-savino-blue text-white shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-20">
                    <!-- LOGO -->
                    <div class="flex-shrink-0 flex items-center z-50">
                        <Link :href="route('home')" class="font-serif text-2xl font-bold tracking-wider uppercase">
                            Savino Del Bene
                        </Link>
                    </div>

                    <!-- MEGA MENU (Desktop) -->
                    <nav class="hidden lg:flex space-x-6 text-sm font-semibold uppercase tracking-wide">
                        <Link :href="route('stagione')" class="hover:text-savino-red transition-colors py-7" :class="{'text-savino-red border-b-2 border-savino-red': $page.url.startsWith('/stagione')}">Stagione</Link>
                        <Link :href="route('societa')" class="hover:text-savino-red transition-colors py-7" :class="{'text-savino-red border-b-2 border-savino-red': $page.url.startsWith('/societa')}">Società</Link>
                        <Link :href="route('ticketing')" class="hover:text-savino-red transition-colors py-7" :class="{'text-savino-red border-b-2 border-savino-red': $page.url.startsWith('/ticketing')}">Ticketing</Link>
                        <Link :href="route('sponsor')" class="hover:text-savino-red transition-colors py-7" :class="{'text-savino-red border-b-2 border-savino-red': $page.url.startsWith('/sponsor')}">Sponsor</Link>
                        <Link :href="route('youth')" class="hover:text-savino-red transition-colors py-7" :class="{'text-savino-red border-b-2 border-savino-red': $page.url.startsWith('/youth')}">SDB Youth</Link>
                        <Link :href="route('summer-camp')" class="hover:text-savino-red transition-colors py-7" :class="{'text-savino-red border-b-2 border-savino-red': $page.url.startsWith('/summer-camp')}">Summer Camp</Link>
                        <Link :href="route('sociale')" class="hover:text-savino-red transition-colors py-7" :class="{'text-savino-red border-b-2 border-savino-red': $page.url.startsWith('/sociale')}">Sociale</Link>
                        <Link :href="route('comunicazione')" class="hover:text-savino-red transition-colors py-7" :class="{'text-savino-red border-b-2 border-savino-red': $page.url.startsWith('/comunicazione')}">Comunicazione</Link>
                        <Link :href="route('shop')" class="text-[#ED028C] hover:text-white transition-colors py-7" :class="{'border-b-2 border-[#ED028C]': $page.url.startsWith('/shop')}">Shop Ufficiale</Link>
                    </nav>

                    <!-- MOBILE MENU BUTTON -->
                    <div class="flex items-center lg:hidden z-50">
                        <button @click="isMobileMenuOpen = !isMobileMenuOpen" type="button" class="text-white hover:text-savino-red focus:outline-none">
                            <svg v-if="!isMobileMenuOpen" class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg v-else class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
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
                <div v-show="isMobileMenuOpen" class="lg:hidden absolute top-0 left-0 w-full bg-savino-blue border-t border-white/10 pt-20 pb-6 px-4 shadow-xl z-40 h-screen overflow-y-auto">
                    <nav class="flex flex-col space-y-4 text-center">
                        <Link :href="route('stagione')" class="text-xl font-bold uppercase tracking-wider py-2 hover:text-savino-red" :class="{'text-savino-red': $page.url.startsWith('/stagione')}">Stagione</Link>
                        <Link :href="route('societa')" class="text-xl font-bold uppercase tracking-wider py-2 hover:text-savino-red" :class="{'text-savino-red': $page.url.startsWith('/societa')}">Società</Link>
                        <Link :href="route('ticketing')" class="text-xl font-bold uppercase tracking-wider py-2 hover:text-savino-red" :class="{'text-savino-red': $page.url.startsWith('/ticketing')}">Ticketing</Link>
                        <Link :href="route('sponsor')" class="text-xl font-bold uppercase tracking-wider py-2 hover:text-savino-red" :class="{'text-savino-red': $page.url.startsWith('/sponsor')}">Sponsor</Link>
                        <Link :href="route('youth')" class="text-xl font-bold uppercase tracking-wider py-2 hover:text-savino-red" :class="{'text-savino-red': $page.url.startsWith('/youth')}">SDB Youth</Link>
                        <Link :href="route('summer-camp')" class="text-xl font-bold uppercase tracking-wider py-2 hover:text-savino-red" :class="{'text-savino-red': $page.url.startsWith('/summer-camp')}">Summer Camp</Link>
                        <Link :href="route('sociale')" class="text-xl font-bold uppercase tracking-wider py-2 hover:text-savino-red" :class="{'text-savino-red': $page.url.startsWith('/sociale')}">Sociale</Link>
                        <Link :href="route('comunicazione')" class="text-xl font-bold uppercase tracking-wider py-2 hover:text-savino-red" :class="{'text-savino-red': $page.url.startsWith('/comunicazione')}">Comunicazione</Link>
                        <Link :href="route('shop')" class="text-xl font-bold uppercase tracking-wider py-2 text-[#ED028C] hover:text-white mt-4 border-t border-white/20 pt-4">Shop Ufficiale</Link>
                    </nav>
                </div>
            </transition>
        </header>

        <!-- MAIN CONTENT -->
        <main class="flex-grow">
            <slot />
        </main>

        <!-- FOOTER -->
        <footer class="bg-gray-900 text-gray-400 py-12 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <span class="font-serif text-xl font-bold text-white">Savino Del Bene Volley</span>
                    <p class="text-sm mt-2">© 2026 Tutti i diritti riservati.</p>
                </div>
                <div class="flex space-x-6">
                    <a href="#" class="hover:text-white">Facebook</a>
                    <a href="#" class="hover:text-white">Instagram</a>
                    <a href="#" class="hover:text-white">YouTube</a>
                </div>
            </div>
        </footer>
    </div>
</template>
