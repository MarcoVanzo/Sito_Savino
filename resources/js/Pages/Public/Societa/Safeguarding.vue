<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSanitize } from '@/Composables/useSanitize';
import { useOgMeta } from '@/Composables/useOgMeta';

const { sanitize } = useSanitize();
const $t = useTranslations();

const props = defineProps({
    page: Object,
});

const safeContent = computed(() => sanitize(props.page?.content));

const ogMeta = useOgMeta({
    title: props.page?.title ?? 'Safeguarding',
    description: props.page?.meta_description,
});

const inertiaPage = usePage();
const contact = computed(() => inertiaPage.props.siteSettings?.contact ?? {});
const cd = computed(() => props.page?.content_data ?? {});

const reportTitle = computed(() => cd.value?.report_title || $t('societa.safeguarding_reporting_title'));
const reportDescription = computed(() => cd.value?.report_description || $t('societa.safeguarding_reporting_desc'));
const reportEmail = computed(() => cd.value?.report_email || contact.value.email || null);

// I documenti ufficiali arrivano dal CMS. Prima ne erano elencati due nel
// componente con il link a "#": online sembravano scaricabili e non lo erano.
// Lo stesso segnaposto è finito in tabella con i dati iniziali, quindi non
// basta controllare che il campo sia valorizzato.
const hasFile = (doc) => typeof doc?.file === 'string' && doc.file.trim() !== '' && doc.file.trim() !== '#';

const documents = computed(() => Array.isArray(cd.value?.documents) ? cd.value.documents : []);
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
        <!-- Formal Hero Section -->
        <!-- La curva in fondo e' alta fino a 80px e sta sopra al testo: senza
             margine sufficiente l'ultima riga finiva dentro l'onda. -->
        <div class="relative bg-savino-blue pt-32 pb-32 md:pb-44 overflow-hidden">
            <div class="absolute inset-0 opacity-10 bg-[url('/images/pattern.svg')]"></div>
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/10 text-white mb-6">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                </div>
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter mb-4">{{ page?.title }}</h1>
                <p class="text-xl text-blue-100 max-w-2xl mx-auto font-light">{{ page?.meta_description }}</p>
            </div>
            <!-- Curve bottom -->
            <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none">
                <!-- L'onda precedente era asimmetrica: a sinistra saliva quasi dritta e
                     a destra scendeva morbida, e la differenza si notava. Questa e' una
                     curva unica, speculare rispetto al centro. -->
                <svg class="relative block w-full h-[50px] md:h-[80px]" data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                    <path d="M0,120V64Q600,0,1200,64V120Z" fill="#F9FAFB"></path>
                </svg>
            </div>
        </div>

        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-4xl mx-auto">
                
                <!-- Intro Card -->
                <div class="bg-white rounded-3xl p-8 md:p-12 shadow-sm border border-gray-100 mb-12">
                    <div 
                        v-if="page?.content"
                        class="prose prose-lg max-w-none prose-headings:font-bold prose-headings:text-savino-blue prose-h2:text-2xl prose-p:text-gray-600 prose-p:leading-relaxed prose-a:text-savino-fucsia hover:prose-a:underline"
                        v-html="safeContent"
                    ></div>
                </div>

                <!-- Documents Section -->
                <h3 v-if="documents.length" class="text-2xl font-black text-savino-blue mb-6 uppercase tracking-tight">{{ $t('societa.safeguarding_documents_header') }}</h3>
                <div v-if="documents.length" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-16">
                    <div v-for="(doc, idx) in documents" :key="idx" class="bg-white rounded-2xl p-6 border border-gray-100 hover:border-savino-blue/30 hover:shadow-md transition-all group flex flex-col h-full">
                        <div class="flex items-start mb-4">
                            <div class="bg-blue-50 p-3 rounded-xl text-savino-blue group-hover:bg-savino-blue group-hover:text-white transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="doc.icon" />
                                </svg>
                            </div>
                            <h4 class="ml-4 font-bold text-lg text-savino-blue leading-tight">{{ doc.title }}</h4>
                        </div>
                        <p class="text-gray-500 text-sm flex-grow mb-6">{{ doc.description }}</p>
                        <a v-if="hasFile(doc)" :href="doc.file" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-sm font-bold text-savino-blue hover:text-savino-red transition-colors w-max">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            {{ $t('societa.safeguarding_download_pdf') }}
                        </a>
                    </div>
                </div>

                <!-- Report Section -->
                <div class="bg-gradient-to-br from-savino-blue to-[#001a36] rounded-3xl p-8 md:p-12 text-white relative overflow-hidden shadow-2xl">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3"></div>
                    <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
                        <div>
                            <h3 class="text-2xl font-black mb-3">{{ reportTitle }}</h3>
                            <p class="text-blue-100 font-medium max-w-lg leading-relaxed">
                                {{ reportDescription }}
                            </p>
                        </div>
                        <!-- Era un pulsante "Invia segnalazione" che apriva il programma di
                             posta: su un computer senza client configurato non succedeva
                             niente e sembrava rotto. L'indirizzo sta scritto, si legge e si
                             copia; resta cliccabile per chi la posta ce l'ha. -->
                        <div v-if="reportEmail" class="flex-shrink-0 w-full md:w-auto text-center md:text-right">
                            <span class="block text-blue-200 text-xs font-bold uppercase tracking-[0.2em] mb-2">{{ $t('societa.safeguarding_report_email_label') }}</span>
                            <a :href="'mailto:' + reportEmail" class="inline-block text-white text-lg md:text-xl font-black break-all hover:text-savino-fucsia transition-colors">
                                {{ reportEmail }}
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </PublicLayout>
</template>
