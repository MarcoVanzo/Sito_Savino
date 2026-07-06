<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import PageHero from '@/Components/PageHero.vue';
import AuctionCard from '@/Components/Shop/Auction/AuctionCard.vue';

const $t = useTranslations();

const props = defineProps({
    auctions: { type: Array, default: () => [] },
    rulesText: { type: String, default: null },
});

const ogMeta = useOgMeta({
    title: 'Aste — Savino Del Bene Volley',
    description: 'Partecipa alle aste esclusive della Savino Del Bene Volley. Maglie autografate, esperienze uniche e tanto altro per una buona causa.',
});

// Filters
const activeFilter = ref('all');

const filters = [
    { key: 'all', label: 'Tutte' },
    { key: 'active', label: '🔴 Attive' },
    { key: 'scheduled', label: '⏰ In arrivo' },
    { key: 'ended', label: '✅ Concluse' },
];

const filteredAuctions = computed(() => {
    if (activeFilter.value === 'all') return props.auctions;
    return props.auctions.filter(a => a.status === activeFilter.value);
});

const countByStatus = (status) => {
    if (status === 'all') return props.auctions.length;
    return props.auctions.filter(a => a.status === status).length;
};
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
        <meta property="og:title" :content="ogMeta.title" />
        <meta property="og:description" :content="ogMeta.description" />
        <meta property="og:image" :content="ogMeta.image" />
        <meta property="og:url" :content="ogMeta.url" />
        <meta property="og:type" content="website" />
    </Head>
    <PublicLayout>
        <!-- Hero -->
        <PageHero
            subtitle="Shop"
            title="Aste"
            description="Partecipa alle nostre aste esclusive. Maglie autografate, esperienze uniche e oggetti da collezione per sostenere la squadra."
            :pattern="true"
        />

        <!-- Content -->
        <section class="bg-gray-950 py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Filter Tabs -->
                <div class="flex flex-wrap gap-2 mb-10 justify-center">
                    <button
                        v-for="filter in filters"
                        :key="filter.key"
                        @click="activeFilter = filter.key"
                        :class="[
                            'px-5 py-2.5 rounded-full text-sm font-bold uppercase tracking-wider transition-all duration-300',
                            activeFilter === filter.key
                                ? 'bg-savino-gold text-gray-900'
                                : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white',
                        ]"
                    >
                        {{ filter.label }}
                        <span
                            v-if="countByStatus(filter.key) > 0"
                            :class="[
                                'ml-1.5 px-2 py-0.5 rounded-full text-xs',
                                activeFilter === filter.key
                                    ? 'bg-gray-900/30 text-gray-900'
                                    : 'bg-gray-600/50 text-gray-300',
                            ]"
                        >
                            {{ countByStatus(filter.key) }}
                        </span>
                    </button>
                </div>

                <!-- Auctions Grid -->
                <div
                    v-if="filteredAuctions.length > 0"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8"
                >
                    <AuctionCard
                        v-for="auction in filteredAuctions"
                        :key="auction.id"
                        :auction="auction"
                    />
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-20">
                    <svg class="w-16 h-16 mx-auto text-gray-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <h3 class="text-white font-bold text-lg mb-2">Nessuna asta disponibile</h3>
                    <p class="text-gray-500 text-sm">
                        Al momento non ci sono aste
                        {{ activeFilter !== 'all' ? 'in questa categoria' : '' }}.
                        Torna presto per nuove opportunità!
                    </p>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
