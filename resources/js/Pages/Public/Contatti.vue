<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useOgMeta } from '@/Composables/useOgMeta'

const $t = useTranslations();

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    }
})

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
    honeypot: '', // Anti-spam
})

const inertiaPage = usePage();
const settings = computed(() => inertiaPage.props.siteSettings ?? {});
const contact = computed(() => settings.value.contact ?? {});
const cd = computed(() => props.page?.content_data ?? {});

const flashSuccess = computed(() => inertiaPage.props.flash?.success)

function handleSubmit() {
    form.post(route('contatti.submit'), {
        preserveScroll: true,
        onSuccess: () => {
            // Form reset automatico dopo successo
            form.reset()
        },
    })
}

function resetForm() {
    form.reset()
}

const contactInfo = computed(() => [
    {
        icon: 'email',
        title: 'Email',
        value: contact.value.email || 'info@savinodelbenevolley.it',
        link: 'mailto:' + (contact.value.email || 'info@savinodelbenevolley.it'),
        color: 'savino-blue'
    },
    {
        icon: 'phone',
        title: $t('contatti.phone') || 'Telefono',
        value: contact.value.phone || '055 721503',
        link: 'tel:' + (contact.value.phone || '055721503').replace(/\s/g, ''),
        color: 'savino-gold'
    },
    {
        icon: 'location',
        title: $t('contatti.headquarters') || 'Sede Amministrativa',
        value: contact.value.address || 'Via Benozzo Gozzoli, 5/6 50018 Scandicci – Firenze',
        link: 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(contact.value.address || 'Via Benozzo Gozzoli, 5/6 Scandicci Firenze'),
        color: 'savino-red'
    }
])

// --- DIRECTORY DYNAMICS ---
const searchQuery = ref('');
const selectedCategory = ref('Tutti');

const contactsByCategory = computed(() => {
    const list = cd.value.contacts_list || [];
    const grouped = {};
    list.forEach(c => {
        const cat = c.category || 'Altri Contatti';
        if (!grouped[cat]) {
            grouped[cat] = [];
        }
        grouped[cat].push(c);
    });
    return grouped;
});

const allCategories = computed(() => {
    const keys = Object.keys(contactsByCategory.value);
    const sorted = keys.sort((a, b) => {
        const standardA = a.toUpperCase();
        const standardB = b.toUpperCase();
        if (standardA === 'SERIE A1') return -1;
        if (standardB === 'SERIE A1') return 1;
        if (standardA === 'SDB VOLLEY YOUTH') return -1;
        if (standardB === 'SDB VOLLEY YOUTH') return 1;
        return a.localeCompare(b);
    });
    return ['Tutti', ...sorted];
});

const filteredContactsByCategory = computed(() => {
    const list = cd.value.contacts_list || [];
    const filteredList = list.filter(c => {
        const matchesCategory = selectedCategory.value === 'Tutti' || (c.category || 'Altri Contatti') === selectedCategory.value;
        const searchLower = searchQuery.value.toLowerCase();
        const matchesSearch = !searchQuery.value || 
                              (c.name && c.name.toLowerCase().includes(searchLower)) || 
                              (c.role && c.role.toLowerCase().includes(searchLower)) ||
                              (c.email && c.email.toLowerCase().includes(searchLower));
        return matchesCategory && matchesSearch;
    });

    const grouped = {};
    filteredList.forEach(c => {
        const cat = c.category || 'Altri Contatti';
        if (!grouped[cat]) {
            grouped[cat] = [];
        }
        grouped[cat].push(c);
    });
    
    // Sort keys logically to preserve the standard order even when searching
    const sortedKeys = Object.keys(grouped).sort((a, b) => {
        const standardA = a.toUpperCase();
        const standardB = b.toUpperCase();
        if (standardA === 'SERIE A1') return -1;
        if (standardB === 'SERIE A1') return 1;
        if (standardA === 'SDB VOLLEY YOUTH') return -1;
        if (standardB === 'SDB VOLLEY YOUTH') return 1;
        return a.localeCompare(b);
    });
    
    const sortedGrouped = {};
    sortedKeys.forEach(k => {
        sortedGrouped[k] = grouped[k];
    });
    
    return sortedGrouped;
});

// --- CLIPBOARD DYNAMICS ---
const copiedField = ref(null);
const copyToClipboard = async (text, fieldName) => {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = fieldName;
        setTimeout(() => {
            if (copiedField.value === fieldName) {
                copiedField.value = null;
            }
        }, 2000);
    } catch (err) {
        console.error('Failed to copy', err);
    }
};

// --- SMART FORM TIPS ---
const subjectTopics = computed(() => [
    { value: 'Informazioni Generali', label: $t('contatti.topic_general') || 'Informazioni Generali' },
    { value: 'Ticketing / Biglietti', label: $t('contatti.topic_ticketing') || 'Ticketing / Biglietti' },
    { value: 'Sponsor / Commerciale', label: $t('contatti.topic_sponsor') || 'Sponsor / Commerciale' },
    { value: 'Settore Giovanile', label: $t('contatti.topic_youth') || 'Settore Giovanile' },
    { value: 'Stampa / Media', label: $t('contatti.topic_press') || 'Stampa / Media' }
]);

const smartTip = computed(() => {
    switch (form.subject) {
        case 'Ticketing / Biglietti':
            return {
                title: 'Info Ticketing',
                text: $t('contatti.tip_ticketing') || 'Sapevi che puoi acquistare i biglietti direttamente dalla nostra biglietteria online?',
                linkText: 'Vai al Ticketing',
                linkUrl: '/ticketing'
            };
        case 'Settore Giovanile':
            return {
                title: 'SDB Youth',
                text: $t('contatti.tip_youth') || 'Scopri tutto sul nostro settore giovanile e le accademie.',
                linkText: null,
                linkUrl: null
            };
        case 'Sponsor / Commerciale':
            return {
                title: 'Diventa Partner',
                text: $t('contatti.tip_sponsor') || 'Vuoi diventare nostro partner? Visita la sezione dedicata agli sponsor.',
                linkText: 'I nostri Sponsor',
                linkUrl: route('sponsor')
            };
        default:
            return null;
    }
});

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('contatti.og_title'),
    description: cd.value?.meta_description || $t('contatti.og_description'),
})
</script>

<template>
    <Head>
      <title>{{ ogMeta.title }}</title>
      <meta name="description" :content="ogMeta.description" />
      <meta property="og:title" :content="ogMeta.title" />
      <meta property="og:description" :content="ogMeta.description" />
      <meta property="og:image" :content="ogMeta.image" />
      <meta property="og:url" :content="ogMeta.url" />
      <meta property="og:type" :content="ogMeta.type" />
    </Head>

    <PublicLayout>
        <!-- Hero Premium -->
        <section class="relative min-h-[45vh] flex items-center justify-center overflow-hidden bg-savino-blue">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900/90 via-savino-blue/90 to-gray-900/90 mix-blend-multiply z-0"></div>
            <!-- Optional subtle background pattern -->
            <div class="absolute inset-0 opacity-10 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-white via-transparent to-transparent z-0"></div>
            
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-24 animate-fade-in-up">
                <span class="text-savino-gold text-sm font-black uppercase tracking-[0.4em] mb-4 block drop-shadow-sm">{{ cd.hero_subtitle || $t('contatti.hero_subtitle') || 'Siamo a tua disposizione' }}</span>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-black text-white uppercase tracking-tighter drop-shadow-lg">{{ page?.title ?? $t('contatti.og_title') ?? 'Contatti' }}</h1>
                <div class="w-24 h-1.5 bg-savino-gold mx-auto mt-6 mb-8 rounded-full shadow-[0_0_15px_rgba(201,168,76,0.5)]"></div>
                <p class="text-white/90 text-xl font-medium max-w-2xl mx-auto leading-relaxed drop-shadow-md">{{ cd.hero_description || $t('contatti.hero_description') || 'Inviaci un messaggio o trova il reparto giusto nella nostra rubrica.' }}</p>
            </div>
        </section>

        <!-- Main Content Layout -->
        <section class="py-16 md:py-24 bg-gray-50 relative">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Quick Info Cards (Elevated slightly over background) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8 -mt-32 mb-20 relative z-20">
                    <div
                        v-for="info in contactInfo"
                        :key="info.title"
                        class="bg-white rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-500 p-8 text-center border-t-4 border-gray-100 hover:-translate-y-2 group"
                        :class="{
                            'hover:border-t-savino-blue': info.color === 'savino-blue',
                            'hover:border-t-savino-gold': info.color === 'savino-gold',
                            'hover:border-t-savino-red': info.color === 'savino-red'
                        }"
                    >
                        <div
                            class="w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center transition-transform duration-500 group-hover:scale-110 shadow-inner"
                            :class="{
                                'bg-savino-blue/10': info.color === 'savino-blue',
                                'bg-savino-gold/10': info.color === 'savino-gold',
                                'bg-savino-red/10': info.color === 'savino-red'
                            }"
                        >
                            <!-- Icons SVG -->
                            <svg v-if="info.icon === 'email'" class="w-8 h-8" :class="{ 'text-savino-blue': info.color === 'savino-blue' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <svg v-else-if="info.icon === 'phone'" class="w-8 h-8" :class="{ 'text-savino-gold': info.color === 'savino-gold' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <svg v-else class="w-8 h-8" :class="{ 'text-savino-red': info.color === 'savino-red' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-3">{{ info.title }}</h3>
                        <a
                            v-if="info.link"
                            :href="info.link"
                            class="text-gray-600 font-bold hover:text-savino-blue transition-colors text-base"
                            target="_blank"
                            rel="noopener noreferrer"
                        >{{ info.value }}</a>
                        <p v-else class="text-gray-600 font-bold text-base">{{ info.value }}</p>
                    </div>
                </div>

                <!-- HQ & Corporate Info (with Clipboard) -->
                <div class="mb-24 bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gray-900 px-8 py-6 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-savino-blue flex items-center justify-center flex-shrink-0 shadow-inner">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <h3 class="text-xl md:text-2xl font-black text-white uppercase tracking-wider">
                            {{ $t('contatti.corporate_title') || 'Dati Societari & Fatturazione' }}
                        </h3>
                    </div>
                    
                    <div class="p-8">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- Helper for Clipboard Items -->
                            <template v-for="field in [
                                { id: 'piva', label: $t('contatti.legal_piva_label') || 'Partita IVA', val: contact.legal_piva || '06271460484' },
                                { id: 'cf', label: $t('contatti.legal_cf_label') || 'Codice Fiscale', val: contact.legal_cf || '94217750481' },
                                { id: 'fipav', label: $t('contatti.legal_fipav_label') || 'Codice FIPAV', val: contact.legal_fipav || '100470331' },
                                { id: 'sdi', label: $t('contatti.legal_sdi_label') || 'Codice SDI', val: contact.legal_sdi || 'KRRH6B9' }
                            ]" :key="field.id">
                                <div 
                                    @click="copyToClipboard(field.val, field.id)"
                                    class="group p-5 bg-gray-50 rounded-2xl border border-gray-200 hover:border-savino-blue/50 hover:bg-savino-blue/5 transition-all duration-300 cursor-pointer relative"
                                    title="Clicca per copiare"
                                >
                                    <span class="block text-xs font-bold text-gray-500 uppercase tracking-widest">{{ field.label }}</span>
                                    <span class="block text-lg font-black text-gray-900 mt-2">{{ field.val }}</span>
                                    
                                    <!-- Copy Icon -->
                                    <div class="absolute top-4 right-4 text-gray-300 group-hover:text-savino-blue transition-colors">
                                        <svg v-if="copiedField !== field.id" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg v-else class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <!-- Copied Toast Micro-interaction -->
                                    <div v-if="copiedField === field.id" class="absolute -top-10 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-xs font-bold px-3 py-1 rounded shadow-lg whitespace-nowrap animate-fade-in-up z-50">
                                        Copiato!
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8 border-t border-gray-100 pt-8">
                            <div class="flex items-start gap-5 p-5 rounded-2xl hover:bg-gray-50 transition-colors">
                                <div class="w-12 h-12 rounded-full bg-savino-blue/10 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-savino-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="block text-xs font-bold text-gray-500 uppercase tracking-widest">{{ $t('contatti.legal_pec_label') || 'PEC Ufficiale' }}</span>
                                    <a :href="'mailto:' + (contact.pec || 'pallavoloscandicci@legalmail.it')" class="block text-base font-black text-savino-blue hover:text-savino-blue/80 mt-1 transition-colors">{{ contact.pec || 'pallavoloscandicci@legalmail.it' }}</a>
                                </div>
                            </div>
                            <div class="flex items-start gap-5 p-5 rounded-2xl hover:bg-gray-50 transition-colors">
                                <div class="w-12 h-12 rounded-full bg-savino-gold/10 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-12 0 9 9 0 0112 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="block text-xs font-bold text-gray-500 uppercase tracking-widest">{{ $t('contatti.office_hours_label') || 'Orari Ufficio Sede' }}</span>
                                    <span class="block text-base font-black text-gray-900 mt-1 whitespace-pre-line">{{ settings.contact?.office_hours || 'Lun-Ven: 09:00 - 13:00\n14:00 - 18:00' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Contact Directory / Rubrica -->
                <div v-if="cd.contacts_list && cd.contacts_list.length > 0" class="mb-24">
                    <div class="text-center mb-12">
                        <span class="text-savino-pink text-xs font-black uppercase tracking-[0.3em] bg-savino-pink/10 px-4 py-2 rounded-full">{{ $t('contatti.directory_badge') || 'Directory' }}</span>
                        <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-6">{{ $t('contatti.directory_title') || 'Rubrica Contatti' }}</h2>
                        <div class="w-16 h-1.5 bg-savino-pink mx-auto mt-4 rounded-full"></div>
                    </div>

                    <!-- Search and Filters -->
                    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100 mb-10">
                        <div class="flex flex-col md:flex-row gap-6">
                            <div class="relative flex-grow">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input 
                                    v-model="searchQuery"
                                    type="text" 
                                    class="w-full pl-11 pr-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-savino-blue/20 focus:border-savino-blue focus:bg-white transition-all font-medium text-gray-900 placeholder-gray-400"
                                    :placeholder="$t('contatti.search_placeholder') || 'Cerca per nome, ruolo o email...'"
                                >
                            </div>
                            <!-- Chips / Filter -->
                            <div class="flex flex-wrap gap-2 items-center">
                                <button
                                    v-for="cat in allCategories"
                                    :key="cat"
                                    @click="selectedCategory = cat"
                                    class="px-4 py-2 rounded-full text-xs font-bold tracking-widest uppercase transition-all duration-300"
                                    :class="selectedCategory === cat 
                                        ? 'bg-gray-900 text-white shadow-md' 
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900'"
                                >
                                    {{ cat }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="Object.keys(filteredContactsByCategory).length === 0" class="text-center py-16 bg-white rounded-2xl border border-gray-100 shadow-sm animate-fade-in-up">
                        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <p class="text-gray-500 font-medium text-lg">Nessun contatto trovato per questa ricerca.</p>
                        <button @click="searchQuery = ''; selectedCategory = 'Tutti'" class="mt-4 text-savino-blue font-bold hover:underline">Resetta filtri</button>
                    </div>

                    <div v-else class="space-y-12">
                        <div v-for="category in Object.keys(filteredContactsByCategory)" :key="category" class="space-y-6 animate-fade-in-up">
                            <!-- Category Header -->
                            <div class="flex items-center gap-4">
                                <h3 class="text-base font-black text-savino-blue tracking-widest uppercase bg-savino-blue/5 px-5 py-2.5 rounded-xl border-l-4 border-savino-blue shadow-sm">
                                    {{ category }}
                                </h3>
                                <div class="flex-grow h-px bg-gray-200"></div>
                            </div>

                            <!-- Contacts Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                <div
                                    v-for="(item, index) in filteredContactsByCategory[category]"
                                    :key="index"
                                    class="bg-white rounded-2xl p-6 border border-gray-100 hover:border-savino-blue/30 hover:shadow-xl transition-all duration-300 flex flex-col justify-between group"
                                >
                                    <div>
                                        <span class="inline-block text-[10px] font-black text-savino-gold uppercase tracking-[0.2em] mb-3 bg-savino-gold/10 px-2.5 py-1 rounded">{{ item.role }}</span>
                                        <h4 v-if="item.name" class="text-lg font-black text-gray-900 uppercase tracking-tight mb-4 group-hover:text-savino-blue transition-colors">
                                            {{ item.name }}
                                        </h4>
                                        <div v-else class="h-4"></div>
                                    </div>
                                    
                                    <div class="space-y-3 pt-4 border-t border-gray-100 text-sm">
                                        <!-- Email -->
                                        <div v-if="item.email" class="flex items-center gap-3 text-gray-600 hover:text-savino-blue transition-colors">
                                            <div class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center flex-shrink-0 group-hover:bg-savino-blue/10 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <a :href="'mailto:' + item.email" class="font-bold break-all">{{ item.email }}</a>
                                        </div>
                                        <!-- Phone -->
                                        <div v-if="item.phone" class="flex items-center gap-3 text-gray-600 hover:text-savino-gold transition-colors">
                                            <div class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center flex-shrink-0 group-hover:bg-savino-gold/10 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                </svg>
                                            </div>
                                            <a :href="'tel:' + item.phone.replace(/\s/g, '')" class="font-bold">{{ item.phone }}</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Form Layout -->
                <div class="max-w-4xl mx-auto">
                    
                    <!-- Smart Form -->
                    <div class="bg-white rounded-3xl shadow-2xl p-8 md:p-12 border border-gray-100">
                        <div class="mb-8">
                            <span class="text-savino-blue text-xs font-black uppercase tracking-[0.2em] block mb-2">{{ cd.form_subtitle || 'Scrivici direttamente' }}</span>
                            <h2 class="text-3xl font-black text-gray-900 uppercase tracking-tight mb-4">{{ cd.form_title || $t('contatti.form_title') || 'Compila il Modulo' }}</h2>
                            <div class="w-12 h-1.5 bg-savino-blue rounded-full"></div>
                        </div>

                        <!-- Success State -->
                        <div v-if="flashSuccess" class="text-center py-16 animate-fade-in-up">
                            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-green-100 flex items-center justify-center shadow-inner">
                                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-black text-gray-900 mb-3">{{ flashSuccess }}</h3>
                            <p class="text-gray-500 font-medium mb-8 text-lg">{{ cd.form_success_message || $t('contatti.form_success_message') || 'Il tuo messaggio è stato inviato con successo.' }}</p>
                            <button @click="resetForm" class="inline-block px-8 py-3.5 bg-gray-100 text-gray-900 rounded-xl font-bold uppercase tracking-wider hover:bg-gray-200 transition-colors">{{ cd.form_reset_label || $t('contatti.form_reset_label') || 'Invia un altro messaggio' }}</button>
                        </div>

                        <!-- Form -->
                        <form v-else @submit.prevent="handleSubmit" class="space-y-6">
                            <div v-if="Object.keys(form.errors).length > 0" class="bg-savino-red/10 border-l-4 border-savino-red rounded-r-xl p-4 text-savino-red text-sm font-bold shadow-sm">
                                <p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="contact-name" class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">{{ cd.form_label_name || $t('contatti.form_label_name') || 'Nome e Cognome' }} *</label>
                                    <input
                                        id="contact-name"
                                        v-model="form.name"
                                        type="text"
                                        class="w-full px-5 py-4 bg-gray-50 rounded-xl border border-gray-200 focus:bg-white focus:border-savino-blue focus:ring-4 focus:ring-savino-blue/10 outline-none transition-all font-medium"
                                        :placeholder="cd.form_placeholder_name || $t('contatti.form_placeholder_name') || 'Mario Rossi'"
                                        required
                                    />
                                </div>
                                <div>
                                    <label for="contact-email" class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">{{ cd.form_label_email || 'Email' }} *</label>
                                    <input
                                        id="contact-email"
                                        v-model="form.email"
                                        type="email"
                                        class="w-full px-5 py-4 bg-gray-50 rounded-xl border border-gray-200 focus:bg-white focus:border-savino-blue focus:ring-4 focus:ring-savino-blue/10 outline-none transition-all font-medium"
                                        :placeholder="cd.form_placeholder_email || $t('contatti.form_placeholder_email') || 'mario@example.com'"
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <label for="contact-subject" class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">{{ cd.form_label_subject || $t('contatti.form_label_subject') || 'Argomento' }}</label>
                                <!-- Using a select for smart tips -->
                                <div class="relative">
                                    <select
                                        id="contact-subject"
                                        v-model="form.subject"
                                        class="w-full px-5 py-4 bg-gray-50 rounded-xl border border-gray-200 focus:bg-white focus:border-savino-blue focus:ring-4 focus:ring-savino-blue/10 outline-none transition-all font-medium appearance-none cursor-pointer"
                                    >
                                        <option value="" disabled selected>Seleziona l'argomento della richiesta...</option>
                                        <option v-for="topic in subjectTopics" :key="topic.value" :value="topic.value">{{ topic.label }}</option>
                                        <option value="Altro">Altro</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-500">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Smart Tip Box -->
                            <div v-if="smartTip" class="bg-blue-50 border border-blue-100 rounded-xl p-5 flex items-start gap-4 animate-fade-in-up">
                                <div class="text-blue-500 mt-0.5">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 mb-1">{{ smartTip.title }}</h4>
                                    <p class="text-sm text-gray-600 mb-2">{{ smartTip.text }}</p>
                                    <a v-if="smartTip.linkUrl" :href="smartTip.linkUrl" class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 uppercase tracking-widest hover:text-blue-800 transition-colors">
                                        {{ smartTip.linkText }}
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            </div>

                            <div>
                                <label for="contact-message" class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">{{ cd.form_label_message || $t('contatti.form_label_message') || 'Messaggio' }} *</label>
                                <textarea
                                    id="contact-message"
                                    v-model="form.message"
                                    rows="5"
                                    class="w-full px-5 py-4 bg-gray-50 rounded-xl border border-gray-200 focus:bg-white focus:border-savino-blue focus:ring-4 focus:ring-savino-blue/10 outline-none transition-all resize-none font-medium"
                                    :placeholder="cd.form_placeholder_message || $t('contatti.form_placeholder_message') || 'Scrivi qui il tuo messaggio...'"
                                    required
                                ></textarea>
                            </div>
                            
                            <div class="hidden" aria-hidden="true">
                                <input type="text" v-model="form.honeypot" tabindex="-1" autocomplete="off" />
                            </div>
                            
                            <div class="pt-4 flex justify-center">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full sm:w-auto px-10 py-4 bg-gray-900 text-white rounded-xl font-black uppercase tracking-widest hover:bg-savino-blue transition-all duration-300 shadow-xl shadow-gray-900/20 hover:shadow-savino-blue/30 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-3 group"
                                >
                                    <span v-if="form.processing">{{ cd.form_sending_label || $t('contatti.form_sending') || 'Invio in corso...' }}</span>
                                    <span v-else>{{ cd.form_submit_label || $t('contatti.form_submit') || 'Invia Messaggio' }}</span>
                                    <svg v-if="!form.processing" class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
.animate-fade-in-up {
    animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
