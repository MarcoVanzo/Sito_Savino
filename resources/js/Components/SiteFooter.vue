<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import { useSanitize } from '@/Composables/useSanitize.js';
import { useSafeUrl } from '@/Composables/useSafeUrl.js';
import { isExternalLink } from '@/Support/menuLinks.js';
import LOGOS from '@/Constants/logos.js';
import NewsletterForm from '@/Components/NewsletterForm.vue';

const $t = useTranslations();
const { sanitize } = useSanitize();
const { safeUrl } = useSafeUrl();

const { onImgError } = useImageFallback();

const currentYear = new Date().getFullYear();
const page = usePage();

// Dati condivisi dal backend via Inertia
const settings = computed(() => page.props.siteSettings ?? {});
const general = computed(() => settings.value.general ?? {});
const social = computed(() => settings.value.social ?? {});
const footerSettings = computed(() => settings.value.footer ?? {});
const legalDocs = computed(() => settings.value.legal ?? {});

// Footer menu dal backend (struttura gerarchica: parent → children)
const footerMenuItems = computed(() => page.props.footerMenu ?? []);

// Il footer menu è già organizzato come parent items con children
// Ogni parent è un "titolo colonna" con i suoi link figli
const displayedLinks = computed(() => {
    if (footerMenuItems.value.length > 0) {
        const groups = {};
        footerMenuItems.value.forEach(parent => {
            // I documenti legali li risolve il backend (`documento:<chiave>`
            // sulla voce di menu). Qui si abbinavano confrontando l'etichetta
            // italiana: sul sito inglese non ne corrispondeva nessuna e tutti i
            // link di Corporate Governance portavano alla pagina Safeguarding.
            groups[parent.label] = (parent.children || []).map(child => ({
                label: child.label,
                // Gli URL arrivano dal CMS: validare lo schema prima dell'href.
                url: safeUrl(child.href, '#'),
                target: isExternalLink(child.href) ? '_blank' : '_self',
            }));
        });
        return groups;
    }
    // Nessun elenco di riserva: il menu del footer si compone in
    // Menu → Footer, e quello che non c'è lì non deve comparire online.
    return {};
});

// Logo e testi dal backend con fallback
const footerLogo = computed(() => general.value.site_logo || LOGOS.VOLLEY);
// Nessun ripiego: il payoff compare solo se la redazione lo scrive.
const footerTagline = computed(() => footerSettings.value.footer_tagline || '');
// Il testo può arrivare dal CMS e finisce in v-html: va sanificato.
const copyrightText = computed(() => sanitize(
    (footerSettings.value.footer_copyright || `© ${currentYear} <span class="whitespace-nowrap">Savino Del Bene</span> Volley — ${$t('footer.all_rights_reserved')}.`)
        .replace('{year}', currentYear)
        .replace('Savino Del Bene', '<span class="whitespace-nowrap">Savino Del Bene</span>')
));
const footerPiva = computed(() => footerSettings.value.footer_piva || '');

// Mappa icone SVG per i social (mantenute le stesse SVG originali)
const socialIconPaths = {
    instagram: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z',
    facebook: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
    youtube: 'M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
    tiktok: 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
    x: 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
    linkedin: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
    whatsapp: 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.825 9.825 0 016.993 2.898 9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z',
};

// Social links dal backend con fallback
const socialLinks = computed(() => {
    const platforms = [
        { key: 'social_instagram', name: 'Instagram' },
        { key: 'social_facebook', name: 'Facebook' },
        { key: 'social_youtube', name: 'YouTube' },
        { key: 'social_tiktok', name: 'TikTok' },
        { key: 'social_x', name: 'X' },
        { key: 'social_linkedin', name: 'LinkedIn' },
        { key: 'social_whatsapp', name: 'WhatsApp' },
    ];
    const links = platforms
        .map(p => ({
            name: p.name,
            href: safeUrl(social.value[p.key]),
            icon: socialIconPaths[p.name.toLowerCase()] || '',
        }))
        .filter(p => p.href);
    return links;
});
</script>

<template>
    <footer role="contentinfo" class="bg-gray-900 mt-auto">
        <!-- Main Footer -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-12 lg:gap-8">

                <!-- Brand Column -->
                <div class="lg:col-span-2">
                    <!-- I due marchi affiancati misurano oltre quattrocento pixel: su un
                         telefono da 375 sfondavano il contenitore, e siccome la pagina ha
                         `overflow-x-hidden` non si vedeva una barra di scorrimento ma il
                         contenuto si poteva spostare di lato senza modo di tornare indietro.
                         Sotto il primo breakpoint si rimpiccioliscono e vanno a capo. -->
                    <div class="flex flex-wrap items-center gap-3 sm:gap-5 mb-6">
                        <img
                            :src="footerLogo"
                            alt="Savino Del Bene Volley"
                            class="h-14 sm:h-20 w-auto object-contain"
                            @error="onImgError"
                        />
                        <span class="hidden sm:block h-14 w-px bg-white/20"></span>
                        <!-- `mx-auto` dentro una riga flex spingeva il marchio corporate
                             fuori asse rispetto al volley: i due vanno letti come una coppia. -->
                        <img
                            :src="LOGOS.CORPORATE_LEFT_WHITE"
                            alt="Savino Del Bene Corporate"
                            class="h-11 sm:h-16 max-w-full w-auto object-contain"
                            @error="onImgError"
                        />
                    </div>
                    <!-- Il payoff "Dal 1982..." era un testo di ripiego cablato nelle
                         traduzioni: la redazione non lo trovava da nessuna parte e la data
                         contraddiceva i tredici anni di Savino Del Bene Volley. Resta solo
                         quello che la redazione scrive davvero in Impostazioni -> Footer. -->
                    <p v-if="footerTagline" class="text-gray-400 text-sm leading-relaxed max-w-sm mb-6 whitespace-pre-line">{{ footerTagline }}</p>
                    <!-- Social Icons -->
                    <div class="flex items-center gap-4">
                        <a
                            v-for="social in socialLinks"
                            :key="social.name"
                            :href="social.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            :aria-label="social.name"
                            class="w-11 h-11 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:bg-savino-fucsia hover:border-savino-fucsia transition-all duration-300 group"
                        >
                            <svg class="w-5 h-5 text-gray-400 group-hover:text-white transition-colors" fill="currentColor" viewBox="0 0 24 24">
                                <path :d="social.icon" />
                            </svg>
                        </a>
                    </div>
                    <!-- Newsletter -->
                    <div class="mt-8">
                        <NewsletterForm variant="footer" />
                    </div>
                </div>

                <!-- Link Columns (dinamiche dal backend) -->
                <div v-for="(links, groupName) in displayedLinks" :key="groupName">
                    <h3 class="text-white text-xs font-bold uppercase tracking-[0.2em] mb-5">{{ groupName }}</h3>
                    <ul class="space-y-3">
                        <li v-for="link in links" :key="link.url || link.href">
                            <component 
                                :is="link.target === '_blank' ? 'a' : Link"
                                :href="safeUrl(link.url || link.href, '#')"
                                :target="link.target"
                                :rel="link.target === '_blank' ? 'noopener noreferrer' : null"
                                class="text-gray-400 text-sm hover:text-savino-fucsia transition-colors duration-200"
                            >
                                {{ link.label }}
                            </component>
                        </li>
                    </ul>
                </div>

            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-t border-white/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-gray-400 text-xs">
                    <span v-html="copyrightText"></span>
                    <span v-if="footerPiva" class="block sm:inline sm:ml-2">P.IVA {{ footerPiva }}</span>
                </div>
                <div class="flex items-center gap-6">
                    <a :href="safeUrl(legalDocs.privacy_policy, '/privacy-policy')" target="_blank" rel="noopener noreferrer" class="text-gray-400 text-xs hover:text-savino-fucsia transition-colors">{{ $t('footer.privacy_policy') }}</a>
                    <a :href="safeUrl(legalDocs.cookie_policy, '/cookie-policy')" target="_blank" rel="noopener noreferrer" class="text-gray-400 text-xs hover:text-savino-fucsia transition-colors">{{ $t('footer.cookie_policy') }}</a>
                    <a :href="safeUrl(legalDocs.informativa_fornitori, '/informativa-fornitori')" target="_blank" rel="noopener noreferrer" class="text-gray-400 text-xs hover:text-savino-fucsia transition-colors">{{ $t('footer.supplier_policy') }}</a>
                </div>
            </div>
        </div>
    </footer>
</template>
