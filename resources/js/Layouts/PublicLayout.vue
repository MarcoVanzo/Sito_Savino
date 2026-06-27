<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import MegaMenu from '@/Components/MegaMenu.vue';
import MobileDrawer from '@/Components/MobileDrawer.vue';
import SiteFooter from '@/Components/SiteFooter.vue';
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
            { name: 'Staff Tecnico', href: '/staff' },
            { name: 'Risultati e Classifica', href: '/risultati' },
            { name: 'Gallery', href: '/gallery' },
        ]
    },
    {
        name: 'Società', route: 'societa', path: '/societa',
        mottoTitle: 'La Nostra<br/>Storia.',
        mottoSubtitle: 'Le radici, l\'organizzazione e la casa della Savino Del Bene Volley.',
        items: [
            { name: 'Storia', href: '/storia' },
            { name: 'Organigramma', href: '/organigramma' },
            { name: 'Palazzetto', href: '/palazzetto' },
            { name: 'Contatti', href: '/contatti' },
        ]
    },
    {
        name: 'Ticketing', route: 'ticketing', path: '/ticketing',
        mottoTitle: 'Vivi<br/>l\'Emozione.',
        mottoSubtitle: 'Acquista i tuoi biglietti e unisciti ai tifosi al palazzetto.',
        items: [
            { name: 'Biglietteria', href: '/biglietteria' },
            { name: 'Abbonamenti', href: '/abbonamenti' },
            { name: 'Convenzioni', href: '/convenzioni' },
            { name: 'Accessibilità', href: '/accessibilita' },
        ]
    },
    {
        name: 'Sponsor', route: 'sponsor', path: '/sponsor',
        mottoTitle: 'I Nostri<br/>Partner.',
        mottoSubtitle: 'Eccellenza e business: le aziende che credono nel nostro progetto.',
        items: [
            { name: 'I Nostri Sponsor', href: '/sponsor' },
            { name: 'Title Sponsor', href: '/title-sponsor' },
            { name: 'Diventa Sponsor', href: '/diventa-sponsor' },
            { name: 'Hospitality', href: '/hospitality' },
            { name: 'Affiliazioni', href: '/affiliazioni' },
        ]
    },
    {
        name: 'SDB Youth', route: 'youth', path: '/youth',
        mottoTitle: 'Il Futuro<br/>in Campo.',
        mottoSubtitle: 'Le giovani promesse e le squadre del nostro settore giovanile.',
        items: [
            { name: 'Roster B1', href: '/stagione/b1' },
            { name: 'Settore Giovanile', href: '/settore-giovanile' },
            { name: 'Talent Day', href: '/talent-day' },
            { name: 'Progetto Scuola', href: '/progetto-scuola' },
        ]
    },
    {
        name: 'Camp', route: 'summer-camp', path: '/summer-camp',
        mottoTitle: 'Estate<br/>di Sport.',
        mottoSubtitle: 'Divertimento, crescita e pallavolo nei nostri Summer Camp.',
        items: [
            { name: 'Summer Camp', href: '/summer-camp' },
            { name: 'Iscrizioni', href: '/summer-camp' },
        ]
    },
    {
        name: 'Sociale', route: 'sociale', path: '/sociale',
        mottoTitle: 'Volley<br/>For All.',
        mottoSubtitle: 'L\'impegno della società per il territorio e l\'inclusione.',
        items: [
            { name: 'Volley4All', href: '/volley-4-all' },
            { name: 'Progetti Sociali', href: '/progetti-sociali' },
            { name: 'Sostenibilità', href: '/sostenibilita' },
        ]
    },
    {
        name: 'Media', route: 'comunicazione', path: '/comunicazione',
        mottoTitle: 'La Nostra<br/>Voce.',
        mottoSubtitle: 'Ultime news, gallery fotografiche e area stampa ufficiale.',
        items: [
            { name: 'News', href: '/news' },
            { name: 'Accrediti Stampa', href: '/accrediti-stampa' },
            { name: 'Cartelle Stampa', href: '/cartelle-stampa' },
            { name: 'Double Face', href: '/double-face' },
            { name: 'Foto Gallery', href: '/gallery' },
        ]
    },
    {
        name: 'Shop', route: 'shop', path: '/shop', isHighlight: true,
        mottoTitle: 'I Colori<br/>Addosso.',
        mottoSubtitle: 'Vesti la tua passione con il merchandising ufficiale del club.',
        items: [
            { name: 'Catalogo', href: '/shop' },
            { name: 'Carrello', href: '/shop/checkout' },
        ]
    },
];

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

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
                                <a href="https://www.savinodelbene.com/it/home/" target="_blank" rel="noopener noreferrer" class="block">
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
                                            <a href="https://www.savinodelbene.com/it/home/" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center px-4 py-2 bg-[#0B1521] text-white text-[10px] font-bold uppercase rounded-md hover:bg-savino-gold transition-colors w-full cursor-pointer">
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
                    <MegaMenu :navigation="navigation" />

                    <!-- MOBILE MENU -->
                    <MobileDrawer
                        :navigation="navigation"
                        :is-open="isMobileMenuOpen"
                        :active-index="activeMobileIndex"
                        @toggle="toggleMobileMenu"
                        @toggle-item="toggleMobileItem"
                    />
                </div>
            </div>
        </header>

        <!-- MAIN CONTENT -->
        <main class="flex-grow">
            <slot />
        </main>

        <!-- FOOTER -->
        <SiteFooter />

        <!-- GDPR Cookie Consent -->
        <CookieConsent />
    </div>
</template>
