<script setup>
import { ref, computed, onMounted, onUnmounted, defineAsyncComponent } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import MegaMenu from '@/Components/MegaMenu.vue';
import SiteFooter from '@/Components/SiteFooter.vue';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import LOGOS from '@/Constants/logos.js';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import CartBadge from '@/Components/Shop/CartBadge.vue';
import CartDrawer from '@/Components/Shop/CartDrawer.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import UserMenu from '@/Components/Shop/UserMenu.vue';
import PasswordExpiryBanner from '@/Components/PasswordExpiryBanner.vue';

const MobileDrawer = defineAsyncComponent(() => import('@/Components/MobileDrawer.vue'));
const CookieConsent = defineAsyncComponent(() => import('@/Components/CookieConsent.vue'));

const isMobileMenuOpen = ref(false);
const activeMobileIndex = ref(null);
const headerScrolled = ref(false);
const { onImgError } = useImageFallback();

let headerScrollTicking = false;
const handleHeaderScroll = () => {
    if (!headerScrollTicking) {
        requestAnimationFrame(() => {
            headerScrolled.value = window.scrollY > 40;
            headerScrollTicking = false;
        });
        headerScrollTicking = true;
    }
};

// Chiude il menu quando si cambia pagina.
// router.on() restituisce la funzione di rimozione: va chiamata allo unmount,
// altrimenti ogni istanza del layout lascia un listener attivo per sempre.
const removeNavigateListener = router.on('navigate', () => {
    isMobileMenuOpen.value = false;
    activeMobileIndex.value = null;
});

onMounted(() => {
    window.addEventListener('scroll', handleHeaderScroll, { passive: true });
    handleHeaderScroll(); // Check initial state
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleHeaderScroll);
    removeNavigateListener?.();
});

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

const toggleMobileItem = (index) => {
    activeMobileIndex.value = activeMobileIndex.value === index ? null : index;
};

const page = usePage();

// Dati condivisi dal backend via Inertia
const nav = computed(() => page.props.navigation ?? []);
const settings = computed(() => page.props.siteSettings ?? {});
const general = computed(() => settings.value.general ?? {});

// Valori derivati con fallback
const corporateUrl = computed(() => general.value.corporate_url || 'https://www.savinodelbene.com/it/home/');
const corporateLogo = computed(() => general.value.corporate_logo || LOGOS.CORPORATE_LEFT);
const siteLogo = computed(() => general.value.site_logo || LOGOS.VOLLEY);
const corporateName = computed(() => general.value.corporate_name || 'Savino Del Bene');
const corporateDescription = computed(() => general.value.corporate_description || 'Global Logistics and Forwarding Company');
const corporateDomain = computed(() => {
    try { return new URL(corporateUrl.value).hostname; } catch { return 'savinodelbene.com'; }
});
</script>

<template>
    <div class="min-h-screen bg-gray-900 flex flex-col font-sans overflow-x-hidden">
        <PasswordExpiryBanner />

        <!-- HEADER STICKY -->
        <header 
            class="sticky top-0 z-50 text-gray-200 transition-all duration-500 ease-out"
            :class="headerScrolled 
                ? 'bg-[#0B1521]/90 backdrop-blur-lg shadow-[0_10px_30px_rgba(0,0,0,0.5)]' 
                : 'bg-gradient-to-b from-[#0B1521]/90 via-[#0B1521]/55 to-transparent backdrop-blur-[2px] shadow-none'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-[85px] relative">
                <div class="flex justify-between items-center h-full">
                    <!-- LOGHI CHE SBORDANO (Layout Originale) -->
                    <!-- La larghezza riservata deve restare sotto lo spazio disponibile:
                         sui telefoni il blocco icone (menu, carrello, account) occupa ~212px
                         e una larghezza fissa lo spingerebbe fuori dal viewport. -->
                    <div class="flex-shrink-0 flex items-center z-[60] w-[76px] sm:w-[210px] md:w-[280px] xl:w-[380px] h-full relative">
                        <div class="absolute top-2 left-0 z-[60] flex items-end">

                            <!-- Corporate Logo Block (Sfondo Bianco) — nascosto sui telefoni: a quella
                                 dimensione il payoff è illeggibile e ruberebbe spazio alla navigazione -->
                            <div class="relative group hidden sm:block">
                                <a :href="corporateUrl" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center bg-white rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] z-0 transition-transform duration-300 group-hover:scale-105 pl-3 pr-8 xl:pr-10 py-1.5 mb-2 w-[150px] md:w-[190px] xl:w-[230px] overflow-hidden">
                                    <img :src="corporateLogo" :alt="corporateName" fetchpriority="high" decoding="sync" class="w-full h-auto object-contain" @error="(e) => onImgError(e, LOGOS.CORPORATE_LEFT)" />
                                </a>
                                
                                <!-- Finestrella Preview Card -->
                                <div class="absolute top-[95px] left-0 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-[100]">
                                    <div class="w-[280px] bg-white rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.7)] border border-gray-200 overflow-hidden relative">
                                        <div class="bg-gray-100 px-3 py-2 border-b border-gray-200 flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-700">{{ corporateDomain }}</span>
                                            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        </div>
                                        <div class="p-4 flex flex-col items-center justify-center text-center bg-gradient-to-b from-white to-gray-50">
                                            <img :src="corporateLogo" :alt="corporateName" class="w-48 h-16 object-contain rounded-lg shadow-sm mb-3" @error="(e) => onImgError(e, LOGOS.CORPORATE_LEFT)" />
                                            <h4 class="text-sm font-black text-[#0B1521] uppercase tracking-wider mb-1 whitespace-nowrap">{{ corporateName }}</h4>
                                            <p class="text-[10px] text-gray-500 mb-4">{{ corporateDescription }}</p>
                                            <a :href="corporateUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center px-4 py-2 bg-[#0B1521] text-white text-[10px] font-bold uppercase rounded-md hover:bg-savino-gold transition-colors w-full cursor-pointer">
                                                {{ $t('common.visit_official_site') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Volley Logo -->
                            <Link :href="route('home')" class="block z-10 sm:-ml-6 xl:-ml-8 transition-transform duration-300 hover:scale-105 pb-1">
                                <img :src="siteLogo" alt="Savino Del Bene Volley" fetchpriority="high" decoding="sync" class="h-[64px] sm:h-[84px] md:h-[100px] xl:h-[125px] w-auto object-contain drop-shadow-[0_10px_20px_rgba(0,0,0,0.6)]" @error="onImgError" />
                            </Link>
                        </div>
                    </div>

                    <!-- MEGA MENU (Desktop) -->
                    <MegaMenu :navigation="nav" />

                    <!-- LANGUAGE SWITCHER (Desktop) -->
                    <div class="hidden xl:flex items-center ml-3">
                        <LanguageSwitcher />
                    </div>

                    <!-- USER MENU (Desktop) -->
                    <div class="hidden xl:flex items-center ml-2">
                        <UserMenu />
                    </div>

                    <!-- CART BADGE -->
                    <div class="hidden xl:flex items-center ml-2">
                        <CartBadge />
                    </div>

                    <!-- MOBILE MENU -->
                    <MobileDrawer
                        :navigation="nav"
                        :is-open="isMobileMenuOpen"
                        :active-index="activeMobileIndex"
                        @toggle="toggleMobileMenu"
                        @toggle-item="toggleMobileItem"
                    >
                        <template #language-switcher>
                            <LanguageSwitcher />
                        </template>
                    </MobileDrawer>
                </div>
            </div>
        </header>

        <!-- MAIN CONTENT -->
        <main class="flex-grow bg-gray-50">
            <slot />
        </main>

        <!-- FOOTER -->
        <SiteFooter />

        <!-- GDPR Cookie Consent -->
        <CookieConsent />

        <!-- Flash Messages -->
        <FlashMessages />

        <!-- Cart Drawer -->
        <CartDrawer />
    </div>
</template>
