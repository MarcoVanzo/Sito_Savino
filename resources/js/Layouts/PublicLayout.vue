<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CookieConsent from '@/Components/CookieConsent.vue';

const isMobileMenuOpen = ref(false);
const activeMobileIndex = ref(null);

// Chiude il menu quando si cambia pagina
router.on('navigate', () => {
    isMobileMenuOpen.value = false;
    activeMobileIndex.value = null;
});

const navigation = [
    {
        name: 'Stagione', route: 'stagione', path: '/stagione',
        mottoTitle: 'Nel Cuore<br/>della Sfida.',
        mottoSubtitle: 'Scopri il roster, il calendario e i risultati della squadra in Serie A1.',
        items: [
            { name: 'Roster A1', href: '/stagione' },
            { name: 'Staff Tecnico', href: '#' },
            { name: 'Calendario', href: '#' },
            { name: 'Classifica', href: '#' },
        ]
    },
    {
        name: 'Società', route: 'societa', path: '/societa',
        mottoTitle: 'La Nostra<br/>Storia.',
        mottoSubtitle: 'Le radici, l\'organizzazione e la casa della Savino Del Bene Volley.',
        items: [
            { name: 'Storia', href: '#' },
            { name: 'Organigramma', href: '#' },
            { name: 'Palazzetto', href: '#' },
            { name: 'Contatti', href: '#' },
        ]
    },
    {
        name: 'Ticketing', route: 'ticketing', path: '/ticketing',
        mottoTitle: 'Vivi<br/>l\'Emozione.',
        mottoSubtitle: 'Acquista i tuoi biglietti e unisciti ai tifosi al palazzetto.',
        items: [
            { name: 'Acquista Biglietti', href: '#' },
            { name: 'Abbonamenti', href: '#' },
            { name: 'Accrediti', href: '#' },
            { name: 'Convenzioni', href: '#' },
        ]
    },
    {
        name: 'Sponsor', route: 'sponsor', path: '/sponsor',
        mottoTitle: 'I Nostri<br/>Partner.',
        mottoSubtitle: 'Eccellenza e business: le aziende che credono nel nostro progetto.',
        items: [
            { name: 'Main Sponsor', href: '#' },
            { name: 'Diventa Sponsor', href: '#' },
            { name: 'B2B Hub', href: '#' },
        ]
    },
    {
        name: 'SDB Youth', route: 'youth', path: '/youth',
        mottoTitle: 'Il Futuro<br/>in Campo.',
        mottoSubtitle: 'Le giovani promesse e le squadre del nostro settore giovanile.',
        items: [
            { name: 'Roster B1', href: '#' },
            { name: 'Settore Giovanile', href: '#' },
        ]
    },
    {
        name: 'Camp', route: 'summer-camp', path: '/summer-camp',
        mottoTitle: 'Estate<br/>di Sport.',
        mottoSubtitle: 'Divertimento, crescita e pallavolo nei nostri Summer Camp.',
        items: [
            { name: 'Info Generali', href: '#' },
            { name: 'Iscrizioni', href: '#' },
        ]
    },
    {
        name: 'Sociale', route: 'sociale', path: '/sociale',
        mottoTitle: 'Volley<br/>For All.',
        mottoSubtitle: 'L\'impegno della società per il territorio e l\'inclusione.',
        items: [
            { name: 'Volley4All', href: '#' },
            { name: 'Progetti', href: '#' },
        ]
    },
    {
        name: 'Media', route: 'comunicazione', path: '/comunicazione',
        mottoTitle: 'La Nostra<br/>Voce.',
        mottoSubtitle: 'Ultime news, gallery fotografiche e area stampa ufficiale.',
        items: [
            { name: 'News', href: '#' },
            { name: 'Press Area', href: '#' },
            { name: 'Foto Gallery', href: '#' },
        ]
    },
    {
        name: 'Shop', route: 'shop', path: '/shop', isHighlight: true,
        mottoTitle: 'I Colori<br/>Addosso.',
        mottoSubtitle: 'Vesti la tua passione con il merchandising ufficiale del club.',
        items: [
            { name: 'Abbigliamento', href: '#' },
            { name: 'Merchandising', href: '#' },
            { name: 'Carrello', href: '#' },
        ]
    },
];

const toggleMobileItem = (index) => {
    activeMobileIndex.value = activeMobileIndex.value === index ? null : index;
};

const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex flex-col font-sans">
        <!-- HEADER STICKY -->
        <header class="sticky top-0 z-50 bg-[#0B1521]/85 backdrop-blur-md text-gray-200 shadow-[0_10px_30px_rgba(0,0,0,0.5)] border-none">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-[85px] relative">
                <div class="flex justify-between items-center h-full">
                    <!-- LOGHI CHE SBORDANO -->
                    <div class="flex-shrink-0 flex items-center z-[60] w-[280px] h-full relative">
                        <div class="absolute top-2 left-0 z-[60] flex items-end">
                            
                            <!-- Corporate Logo with Preview -->
                            <div class="relative group">
                                <a href="https://www.savinodelbene.com/it/home/" target="_blank" class="block">
                                    <img src="/images/Logo_Savino.jpeg" alt="Savino Del Bene" class="h-[85px] w-[85px] object-cover rounded-2xl shadow-xl z-0 transition-transform duration-300 group-hover:scale-105 bg-white mb-2" />
                                </a>
                                
                                <!-- Finestrella Preview Card -->
                                <div class="absolute top-[95px] left-0 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-[100]">
                                    <div class="w-[280px] bg-white rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.7)] border border-gray-200 overflow-hidden relative">
                                        <div class="bg-gray-100 px-3 py-2 border-b border-gray-200 flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-700">savinodelbene.com</span>
                                            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        </div>
                                        <div class="p-4 flex flex-col items-center justify-center text-center bg-gradient-to-b from-white to-gray-50">
                                            <img src="/images/Logo_Savino.jpeg" alt="SDB" class="w-12 h-12 rounded-lg shadow-sm mb-3" />
                                            <h4 class="text-sm font-black text-[#0B1521] uppercase tracking-wider mb-1">Savino Del Bene</h4>
                                            <p class="text-[10px] text-gray-500 mb-4">Global Logistics and Forwarding Company</p>
                                            <a href="https://www.savinodelbene.com/it/home/" target="_blank" class="inline-flex items-center justify-center px-4 py-2 bg-[#0B1521] text-white text-[10px] font-bold uppercase rounded-md hover:bg-savino-gold transition-colors w-full cursor-pointer">
                                                Visita il Sito Ufficiale
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Volley Logo -->
                            <Link :href="route('home')" class="block z-10 -ml-6 transition-transform duration-300 hover:scale-105">
                                <img src="/images/logo.png" alt="Savino Del Bene Volley" class="h-[125px] w-auto object-contain drop-shadow-[0_10px_20px_rgba(0,0,0,0.6)]" />
                            </Link>
                        </div>
                    </div>

                    <!-- MEGA MENU (Desktop) -->
                    <nav class="hidden lg:flex flex-1 justify-end items-center h-full">
                        <div class="flex items-center h-full">
                            <template v-for="(item, index) in navigation" :key="item.name">
                                <div class="group static h-full flex items-center">
                                    <Link 
                                        :href="route(item.route)" 
                                        class="text-[12px] xl:text-[14px] font-black tracking-wider uppercase transition-colors flex items-center h-full px-2 lg:px-3 whitespace-nowrap"
                                        :class="[
                                            $page.url.startsWith(item.path) ? 'text-white border-b-[3px] border-savino-gold pt-[3px]' : 'text-gray-400 hover:text-white border-b-[3px] border-transparent pt-[3px]',
                                            item.isHighlight ? 'text-[#ED028C] hover:text-[#ff30a6]' : ''
                                        ]"
                                    >
                                        {{ item.name }}
                                        <svg v-if="item.items && item.items.length > 0" class="w-3 h-3 ml-1.5 opacity-70 transition-transform duration-300 group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                                    </Link>

                                    <!-- Sottomenu Mega Dropdown Glass -->
                                    <div v-if="item.items && item.items.length > 0" class="absolute top-[85px] left-0 w-full pt-4 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50 px-4 sm:px-6 lg:px-8">
                                        <div class="flex bg-white/95 backdrop-blur-xl shadow-[0_30px_60px_rgba(0,0,0,0.4)] border border-white/20 rounded-2xl overflow-hidden translate-y-4 group-hover:translate-y-0 transition-transform duration-300 ease-out max-w-5xl mx-auto min-h-[350px]">
                                            <!-- Left side with links -->
                                            <div class="w-3/5 p-10 flex flex-col">
                                                <div class="mb-8">
                                                    <h3 class="text-2xl font-black text-savino-blue uppercase tracking-tighter mb-2" style="font-family: 'Montserrat', sans-serif;">{{ item.name }}</h3>
                                                    <div class="w-16 h-1 bg-savino-gold"></div>
                                                </div>
                                                <div class="grid grid-cols-2 gap-4 flex-1 content-start">
                                                    <Link 
                                                        v-for="sub in item.items" 
                                                        :key="sub.name"
                                                        :href="sub.href"
                                                        class="flex flex-col p-4 rounded-xl border border-gray-100 hover:border-savino-gold/50 bg-gray-50 hover:bg-white hover:shadow-xl transition-all duration-300 group/link"
                                                    >
                                                        <span class="text-[14px] font-black text-savino-blue uppercase tracking-wider mb-1 flex justify-between items-center">
                                                            {{ sub.name }}
                                                            <svg class="w-5 h-5 text-savino-gold opacity-0 -translate-x-2 group-hover/link:opacity-100 group-hover/link:translate-x-0 transition-all duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                                                        </span>
                                                        <span class="text-xs text-gray-500 font-medium">Esplora la sezione dedicata</span>
                                                    </Link>
                                                </div>
                                            </div>
                                            <!-- Right side branding/visual -->
                                            <div class="w-2/5 bg-gradient-to-br from-savino-blue to-[#001a40] relative p-10 flex flex-col justify-between overflow-hidden">
                                                <div class="absolute inset-0 bg-[url('/images/logo.png')] opacity-[0.07] bg-no-repeat bg-center bg-contain scale-150 transform transition-transform duration-[10s] group-hover:scale-[1.8]"></div>
                                                
                                                <!-- Decorative elements -->
                                                <div class="relative z-10 flex justify-end">
                                                    <svg class="w-12 h-12 text-savino-gold opacity-50" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13h-13L12 6.5z"/></svg>
                                                </div>

                                                <div class="relative z-10">
                                                    <div class="w-12 h-1 bg-savino-red mb-4"></div>
                                                    <p class="text-white text-3xl font-serif font-bold italic leading-tight mb-2" v-html="item.mottoTitle || 'Eccellenza<br/>nello Sport.'"></p>
                                                    <p class="text-gray-300 text-sm font-medium">{{ item.mottoSubtitle || 'Passione, determinazione e orgoglio in ogni partita.' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Vertical separator | -->
                                <div v-if="index < navigation.length - 1" class="text-white/20 select-none text-[10px] mx-1">|</div>
                            </template>
                        </div>
                        
                        <!-- Right Icons -->
                        <div class="flex items-center gap-5 ml-8 border-l border-white/10 pl-8 h-[40px]">
                            <!-- Globe Icon -->
                            <button class="text-gray-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </button>
                            <!-- Search Icon -->
                            <button class="text-gray-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </button>
                            <!-- Hamburger Icon -->
                            <button class="text-gray-400 hover:text-white transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                            </button>
                        </div>
                    </nav>

                    <!-- MOBILE MENU BUTTON -->
                    <div class="flex items-center lg:hidden z-50">
                        <button @click="isMobileMenuOpen = !isMobileMenuOpen" type="button" class="text-white hover:text-savino-red focus:outline-none p-2">
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
                <div v-show="isMobileMenuOpen" class="lg:hidden absolute top-0 left-0 w-full bg-savino-blue border-t border-white/10 pt-20 pb-6 px-4 shadow-xl z-40 h-[100dvh] overflow-y-auto">
                    <nav class="flex flex-col space-y-2 text-center pb-10">
                        <div v-for="(item, index) in navigation" :key="item.name" class="border-b border-white/10 last:border-0">
                            <button 
                                @click="toggleMobileItem(index)"
                                class="w-full flex items-center justify-between py-4 px-4 text-[14px] font-bold uppercase tracking-widest text-white focus:outline-none"
                                :class="{'text-savino-red': $page.url.startsWith(item.path) || activeMobileIndex === index, 'text-[#ED028C]': item.isHighlight}"
                            >
                                <span>{{ item.name }}</span>
                                <svg class="w-4 h-4 transition-transform" :class="{'rotate-180': activeMobileIndex === index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            
                            <!-- Sottomenu Mobile -->
                            <div v-show="activeMobileIndex === index" class="bg-black/20 pb-4 pt-2">
                                <Link 
                                    v-for="sub in item.items" 
                                    :key="sub.name"
                                    :href="sub.href"
                                    class="block py-2 text-[12px] font-semibold uppercase tracking-widest text-gray-300 hover:text-white"
                                >
                                    {{ sub.name }}
                                </Link>
                            </div>
                        </div>
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
                <div class="flex items-center space-x-6">
                    <a href="#" class="hover:text-white">Facebook</a>
                    <a href="#" class="hover:text-white">Instagram</a>
                    <a href="#" class="hover:text-white">YouTube</a>
                    <span class="text-gray-700">|</span>
                    <a href="/privacy-policy" class="text-xs hover:text-white">Privacy Policy</a>
                    <a href="/cookie-policy" class="text-xs hover:text-white">Cookie Policy</a>
                </div>
            </div>
        </footer>

        <!-- GDPR Cookie Consent -->
        <CookieConsent />
    </div>
</template>
