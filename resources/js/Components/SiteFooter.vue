<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useImageFallback } from '@/Composables/useImageFallback.js';

const { onImgError } = useImageFallback();

const currentYear = new Date().getFullYear();
const page = usePage();

// Dati condivisi dal backend via Inertia
const settings = computed(() => page.props.siteSettings ?? {});
const general = computed(() => settings.value.general ?? {});
const social = computed(() => settings.value.social ?? {});
const footerSettings = computed(() => settings.value.footer ?? {});

// Footer menu dal backend (struttura gerarchica: parent → children)
const footerMenuItems = computed(() => page.props.footerMenu ?? []);

// Il footer menu è già organizzato come parent items con children
// Ogni parent è un "titolo colonna" con i suoi link figli
const displayedLinks = computed(() => {
    if (footerMenuItems.value.length > 0) {
        const groups = {};
        footerMenuItems.value.forEach(parent => {
            groups[parent.label] = (parent.children || []).map(child => ({
                label: child.label,
                url: child.href,
            }));
        });
        return groups;
    }
    // Fallback ai link hardcoded
    return {
        Stagione: [
            { label: 'Roster Serie A1', url: '/stagione' },
            { label: 'Risultati', url: '/risultati' },
            { label: 'Gallery', url: '/gallery' },
            { label: 'Staff Tecnico', url: '/staff' },
        ],
        'Il Club': [
            { label: 'La Società', url: '/societa' },
            { label: 'Settore Giovanile', url: '/youth' },
            { label: 'Sponsor', url: '/sponsor' },
            { label: 'News', url: '/news' },
        ],
        Servizi: [
            { label: 'Biglietteria', url: '/ticketing' },
            { label: 'Shop Ufficiale', url: '/shop' },
            { label: 'Contatti', url: '/contatti' },
            { label: 'Comunicazione', url: '/comunicazione' },
        ],
    };
});

// Logo e testi dal backend con fallback
const footerLogo = computed(() => general.value.site_logo || '/images/logo.png');
const footerBrandName = computed(() => general.value.corporate_name || 'Savino Del Bene');
const footerTagline = computed(() => footerSettings.value.footer_tagline || 'Dal 1982, una tradizione di eccellenza nella pallavolo femminile italiana.\nSerie A1 — Palazzo Wanny, Firenze.');
const copyrightText = computed(() => (footerSettings.value.footer_copyright || `© ${currentYear} <span class="whitespace-nowrap">Savino Del Bene</span> Volley — Tutti i diritti riservati.`).replace('{year}', currentYear).replace('Savino Del Bene', '<span class="whitespace-nowrap">Savino Del Bene</span>'));

// Mappa icone SVG per i social (mantenute le stesse SVG originali)
const socialIconPaths = {
    instagram: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z',
    facebook: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
    youtube: 'M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
    tiktok: 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
    x: 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
};

// Social links dal backend con fallback
const socialLinks = computed(() => {
    const platforms = [
        { key: 'social_instagram', name: 'Instagram' },
        { key: 'social_facebook', name: 'Facebook' },
        { key: 'social_youtube', name: 'YouTube' },
        { key: 'social_tiktok', name: 'TikTok' },
        { key: 'social_x', name: 'X' },
    ];
    const links = platforms
        .filter(p => social.value[p.key])
        .map(p => ({
            name: p.name,
            href: social.value[p.key],
            icon: socialIconPaths[p.name.toLowerCase()] || '',
        }));
    // Fallback se backend non fornisce social
    if (links.length === 0) {
        return [
            { name: 'Instagram', href: 'https://www.instagram.com/savinodelbenevolley/', icon: socialIconPaths.instagram },
            { name: 'Facebook', href: 'https://www.facebook.com/savinodelbenevolley', icon: socialIconPaths.facebook },
            { name: 'YouTube', href: 'https://www.youtube.com/@savinodelbenevolley1771', icon: socialIconPaths.youtube },
        ];
    }
    return links;
});
</script>

<template>
    <footer role="contentinfo" class="bg-gray-900 mt-auto">
        <!-- Main Footer -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-12 lg:gap-8">

                <!-- Brand Column -->
                <div class="lg:col-span-2">
                    <div class="mb-6">
                        <img
                            :src="footerLogo"
                            alt="Savino Del Bene Volley"
                            class="h-20 w-auto object-contain"
                            @error="onImgError"
                        />
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed max-w-sm mb-6 whitespace-pre-line">{{ footerTagline }}</p>
                    <!-- Social Icons -->
                    <div class="flex items-center gap-4">
                        <a
                            v-for="social in socialLinks"
                            :key="social.name"
                            :href="social.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            :aria-label="social.name + ' di Savino Del Bene Volley'"
                            class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:bg-savino-gold hover:border-savino-gold transition-all duration-300 group"
                        >
                            <svg class="w-4 h-4 text-gray-400 group-hover:text-white transition-colors" fill="currentColor" viewBox="0 0 24 24">
                                <path :d="social.icon" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Link Columns (dinamiche dal backend) -->
                <div v-for="(links, groupName) in displayedLinks" :key="groupName">
                    <h3 class="text-white text-xs font-bold uppercase tracking-[0.2em] mb-5">{{ groupName }}</h3>
                    <ul class="space-y-3">
                        <li v-for="link in links" :key="link.url || link.href">
                            <Link :href="link.url || link.href" class="text-gray-400 text-sm hover:text-savino-gold transition-colors duration-200">
                                {{ link.label }}
                            </Link>
                        </li>
                    </ul>
                </div>

            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-t border-white/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-gray-500 text-xs">
                    {{ copyrightText }}
                </div>
                <div class="flex items-center gap-6">
                    <Link href="/privacy-policy" class="text-gray-500 text-xs hover:text-savino-gold transition-colors">Privacy Policy</Link>
                    <Link href="/cookie-policy" class="text-gray-500 text-xs hover:text-savino-gold transition-colors">Cookie Policy</Link>
                </div>
            </div>
        </div>
    </footer>
</template>
