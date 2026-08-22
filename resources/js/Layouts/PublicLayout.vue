<script setup>
import { ref, computed, watch, onMounted, onUnmounted, defineAsyncComponent } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import MegaMenu from '@/Components/MegaMenu.vue';
import SiteFooter from '@/Components/SiteFooter.vue';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import LOGOS from '@/Constants/logos.js';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import CartBadge from '@/Components/Shop/CartBadge.vue';
import CartDrawer from '@/Components/Shop/CartDrawer.vue';
import ShopCtaButton from '@/Components/Shop/ShopCtaButton.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import UserMenu from '@/Components/Shop/UserMenu.vue';
import PasswordExpiryBanner from '@/Components/PasswordExpiryBanner.vue';
import { useHeaderNavFit, SHOP_BUTTON_FONT_SIZE } from '@/Composables/useHeaderNavFit.js';

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

// Testi e indirizzi arrivano da Impostazioni → Generali. Restano nel codice
// solo i loghi: sono file di brand, centralizzati in Constants/logos.js, e
// servono comunque un'immagine quando il pannello non ne carica una.
const corporateUrl = computed(() => general.value.corporate_url ?? '');
const corporateLogo = computed(() => general.value.corporate_logo || LOGOS.CORPORATE_LEFT);
const siteLogo = computed(() => general.value.site_logo || LOGOS.VOLLEY);
const corporateName = computed(() => general.value.corporate_name ?? '');
const corporateDescription = computed(() => general.value.corporate_description ?? '');
const corporateDomain = computed(() => {
    try { return new URL(corporateUrl.value).hostname; } catch { return ''; }
});

// La voce marcata "Evidenziata" nel pannello non entra nella barra: diventa il
// pulsante pieno in testata (oggi il negozio). Etichetta e indirizzo restano quelli
// della voce, così la redazione continua a governarli dal menu.
const shopLink = computed(() => nav.value.find((item) => item.isHighlight) ?? null);
const mainNav = computed(() => nav.value.filter((item) => !item.isHighlight));

// Navigazione orizzontale o drawer: la decisione è misurata sullo spazio reale
// dell'header, non su un breakpoint fisso.
const logoBlock = ref(null);
const navLabels = computed(() => mainNav.value.map((item) => item.label ?? ''));
const shopLabel = computed(() => shopLink.value?.label ?? null);
const { desktopNav, navFontSize } = useHeaderNavFit(navLabels, logoBlock, shopLabel);

// Allargando la finestra con il drawer aperto resterebbe il pannello a tutto schermo
// sopra un header che ha già la sua navigazione.
watch(desktopNav, (isDesktop) => {
    if (isDesktop) {
        isMobileMenuOpen.value = false;
        activeMobileIndex.value = null;
    }
});
</script>

<template>
    <div class="site-shell min-h-screen bg-gray-900 flex flex-col font-sans overflow-x-hidden">
        <PasswordExpiryBanner />

        <!-- HEADER STICKY -->
        <header 
            class="sticky top-0 z-50 text-gray-200 transition-all duration-500 ease-out"
            :class="headerScrolled 
                ? 'bg-[#0B1521]/90 backdrop-blur-lg shadow-[0_10px_30px_rgba(0,0,0,0.5)]' 
                : 'bg-gradient-to-b from-[#0B1521]/90 via-[#0B1521]/55 to-transparent backdrop-blur-[2px] shadow-none'"
        >
            <!-- La riga usa tutta la larghezza dello schermo: con il contenitore a
                 1280px il menu restava schiacciato fra i loghi e le icone anche su
                 monitor larghi il doppio. -->
            <div class="w-full px-4 h-[var(--header-h)] relative">
                <div class="flex justify-between items-center h-full">
                    <!-- LOGHI CHE SBORDANO (Layout Originale) -->
                    <!-- I due marchi stanno affiancati sulla stessa linea, non sovrapposti:
                         la larghezza riservata qui sotto e' la somma delle due piu' il gap.
                         Deve restare sotto lo spazio disponibile: sui telefoni il blocco
                         icone (menu, carrello, account) occupa ~212px e una larghezza fissa
                         lo spingerebbe fuori dal viewport, per questo li' sono 76px. -->
                    <div ref="logoBlock" class="flex-shrink-0 flex items-center z-[60] w-[76px] sm:w-[calc(var(--corporate-logo-w)_+_var(--volley-logo-h)_+_12px)] h-full relative">
                        <!-- Tutto e' centrato sulla stessa orizzontale: il centro del logo
                             volley e' il centro della riga, e quindi il centro del menu, del
                             pulsante Shop e delle icone. Per questo la riga e' alta quanto il
                             logo (piu' l'aria attorno) invece di lasciarlo sbordare in basso:
                             lo sbordo spostava il suo centro 28px sotto quello del menu. -->
                        <div class="absolute inset-y-0 left-0 z-[60] flex items-center gap-3">

                            <!-- Marchio della Spa — nascosto sui telefoni: a quella dimensione il
                                 payoff è illeggibile e ruberebbe spazio alla navigazione.
                                 Il brandbook della Savino Del Bene Spa vieta la toppa bianca
                                 dietro al marchio: su fondo pieno o su una foto va il marchio
                                 bianco, su fondo chiaro quello blu. Qui l'header è sempre scuro
                                 (#0B1521, o il degradé sopra la foto), quindi bianco. Non si usa
                                 il logo caricato dal pannello perché quello è la versione a
                                 colori, giusta sul chiaro e sbagliata qui. -->
                            <div class="relative group hidden sm:block">
                                <a :href="corporateUrl || undefined" :target="corporateUrl ? '_blank' : undefined" :rel="corporateUrl ? 'noopener noreferrer' : undefined" class="flex items-center justify-center z-0 transition-transform duration-300 group-hover:scale-105 w-[var(--corporate-logo-w)]">
                                    <!-- La larghezza discende da quella del logo volley
                                         (--corporate-logo-w), cosi' il rapporto fra i due
                                         marchi non cambia col viewport: il marchio della Spa
                                         occupa l'80% dell'ingombro del volley, che resta il
                                         segno principale della testata. Con l'SVG a 4,55:1
                                         la larghezza minima resta sopra i 40 mm (151 px),
                                         quindi e' ancora la versione con payoff prescritta
                                         dal brandbook. -->
                                    <img :src="LOGOS.CORPORATE_LEFT_WHITE" :alt="corporateName" fetchpriority="high" decoding="sync" class="w-full h-auto object-contain drop-shadow-[0_6px_16px_rgba(0,0,0,0.7)]" @error="(e) => onImgError(e, LOGOS.CORPORATE_LEFT_WHITE)" />
                                </a>
                                
                                <!-- Finestrella Preview Card -->
                                <div class="absolute top-[calc(var(--header-h)_+_10px)] left-0 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-[100]">
                                    <div class="w-[280px] bg-white rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.7)] border border-gray-200 overflow-hidden relative">
                                        <div class="bg-gray-100 px-3 py-2 border-b border-gray-200 flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-700">{{ corporateDomain }}</span>
                                            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        </div>
                                        <div class="p-4 flex flex-col items-center justify-center text-center bg-gradient-to-b from-white to-gray-50">
                                            <!-- Qui il fondo è bianco: ci va il marchio a colori,
                                                 come prescrive il brandbook per i fondi chiari. -->
                                            <img :src="corporateLogo" :alt="corporateName" class="w-48 h-16 object-contain mb-3" @error="(e) => onImgError(e, LOGOS.CORPORATE_LEFT)" />
                                            <h4 class="text-sm font-black text-[#0B1521] uppercase tracking-wider mb-1 whitespace-nowrap">{{ corporateName }}</h4>
                                            <p class="text-[10px] text-gray-500 mb-4">{{ corporateDescription }}</p>
                                            <a :href="corporateUrl || undefined" :target="corporateUrl ? '_blank' : undefined" :rel="corporateUrl ? 'noopener noreferrer' : undefined" class="inline-flex items-center justify-center px-4 py-2 bg-[#0B1521] text-white text-[10px] font-bold uppercase rounded-md hover:bg-savino-fucsia transition-colors w-full cursor-pointer">
                                                {{ $t('common.visit_official_site') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Volley Logo -->
                            <Link :href="route('home')" class="block z-10 transition-transform duration-300 hover:scale-105">
                                <img :src="siteLogo" alt="Savino Del Bene Volley" fetchpriority="high" decoding="sync" class="h-[var(--volley-logo-h)] w-auto object-contain drop-shadow-[0_10px_20px_rgba(0,0,0,0.6)]" @error="onImgError" />
                            </Link>
                        </div>
                    </div>

                    <!-- MEGA MENU (Desktop) -->
                    <MegaMenu v-show="desktopNav" :navigation="mainNav" :font-size="navFontSize" />

                    <!-- PULSANTE SHOP (Desktop) -->
                    <div v-show="desktopNav && shopLink" class="flex items-center ml-3">
                        <ShopCtaButton v-if="shopLink" :href="shopLink.href" :label="shopLink.label" :font-size="SHOP_BUTTON_FONT_SIZE" />
                    </div>

                    <!-- LANGUAGE SWITCHER (Desktop) -->
                    <div v-show="desktopNav" class="flex items-center ml-2">
                        <LanguageSwitcher />
                    </div>

                    <!-- USER MENU (Desktop) -->
                    <div v-show="desktopNav" class="flex items-center ml-1">
                        <UserMenu />
                    </div>

                    <!-- CART BADGE -->
                    <div v-show="desktopNav" class="flex items-center ml-1">
                        <CartBadge />
                    </div>

                    <!-- MOBILE MENU -->
                    <MobileDrawer
                        :navigation="nav"
                        :visible="!desktopNav"
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

<style scoped>
/* Il logo volley e' il centro della testata. La riga e' alta quanto lui piu' l'aria
   attorno: cosi' il suo centro coincide con il centro della riga, e quindi con quello
   del menu, del pulsante Shop e delle icone — tutto sulla stessa orizzontale. Prima
   il logo sbordava sotto l'header e il suo centro cadeva 28px piu' in basso.
   Le altre misure discendono da lui; --header-h serve anche all'hero della home, che
   sale sotto l'header (Home.vue). */
.site-shell {
    --volley-logo-h: 64px;
    --header-h: 85px;
    --corporate-logo-w: 122px;
}

@media (min-width: 640px) {
    .site-shell {
        /* Cresce col viewport invece che a scatti: fra 1280 e 1536 px Tailwind non ha
           un gradino, e un logo dimensionato per un monitor da 1512 lasciava a 1280
           troppo poco spazio al menu. Il tetto e' l'altezza storica del logo. */
        --volley-logo-h: clamp(84px, 8.3vw, 125px);
        --header-h: calc(var(--volley-logo-h) + 16px);
        /* 1,9 e' il rapporto che da' al marchio della Spa l'80% dell'ingombro del
           volley: con l'SVG a 4,55:1 sono 238x52 px contro 125x125. */
        --corporate-logo-w: calc(var(--volley-logo-h) * 1.9);
    }
}
</style>
