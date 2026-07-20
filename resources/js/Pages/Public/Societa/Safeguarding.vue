<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
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
    description: props.page?.meta_description || 'Policy di Safeguarding della Savino Del Bene Volley',
});

const cd = computed(() => props.page?.content_data ?? {});

const reportTitle = computed(() => cd.value?.report_title || $t('societa.safeguarding_reporting_title') || 'Segnalazioni');
const reportDescription = computed(() => cd.value?.report_description || $t('societa.safeguarding_reporting_desc') || 'Per segnalare comportamenti non conformi al Codice di Condotta o presunti abusi, è possibile contattare il Responsabile Safeguarding in totale riservatezza.');
const reportEmail = computed(() => cd.value?.report_email || 'safeguarding@savinodelbenevolley.it');

const documents = computed(() => {
    if (cd.value?.documents && Array.isArray(cd.value.documents) && cd.value.documents.length > 0) {
        return cd.value.documents;
    }
    return [
        { title: $t('societa.safeguarding_fallback_doc1_title') || 'Modello Organizzativo e di Controllo', description: $t('societa.safeguarding_fallback_doc1_desc') || 'Documento che stabilisce i principi di comportamento e le misure di prevenzione.', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', file: '#' },
        { title: $t('societa.safeguarding_fallback_doc2_title') || 'Codice di Condotta a Tutela dei Minori', description: $t('societa.safeguarding_fallback_doc2_desc') || 'Linee guida specifiche per garantire un ambiente sportivo sicuro per i più giovani.', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', file: '#' }
    ];
});
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
        <div class="relative bg-savino-blue pt-32 pb-20 overflow-hidden">
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
                <svg class="relative block w-full h-[50px] md:h-[80px]" data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                    <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V120H0V95.8C59.71,118.08,130.83,115.93,198.71,105.1,241.13,98.24,281.86,83.5,321.39,56.44Z" fill="#F9FAFB"></path>
                </svg>
            </div>
        </div>

        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-4xl mx-auto">
                
                <!-- Intro Card -->
                <div class="bg-white rounded-3xl p-8 md:p-12 shadow-sm border border-gray-100 mb-12">
                    <div 
                        v-if="page?.content"
                        class="prose prose-lg max-w-none prose-headings:font-bold prose-headings:text-savino-blue prose-h2:text-2xl prose-p:text-gray-600 prose-p:leading-relaxed prose-a:text-savino-gold hover:prose-a:underline"
                        v-html="safeContent"
                    ></div>
                </div>

                <!-- Documents Section -->
                <h3 class="text-2xl font-black text-savino-blue mb-6 uppercase tracking-tight">{{ $t('societa.safeguarding_documents_header') || 'Documenti Ufficiali' }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-16">
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
                        <a :href="doc.file" class="inline-flex items-center text-sm font-bold text-savino-blue hover:text-savino-red transition-colors w-max">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            {{ $t('societa.safeguarding_download_pdf') || 'Scarica PDF' }}
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
                        <a :href="'mailto:' + reportEmail" class="flex-shrink-0 bg-savino-gold hover:bg-white hover:text-savino-blue text-white text-lg font-bold px-8 py-4 rounded-xl transition-all shadow-lg hover:shadow-xl w-full md:w-auto text-center">
                            {{ $t('societa.safeguarding_send_report') || 'Invia Segnalazione' }}
                        </a>
                    </div>
                </div>

            </div>
        </section>
    </PublicLayout>
</template>
