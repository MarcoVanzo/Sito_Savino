<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTranslations } from '@/Composables/useTranslations.js';

const $t = useTranslations();

/**
 * Navigazione fra le sezioni di stagione (risultati, classifica, coppe).
 *
 * Il mega-menu principale è alimentato dal CMS: questa barra serve a spostarsi
 * fra pagine strettamente collegate senza tornare al menu.
 */
const props = defineProps({
    active: {
        type: String,
        required: true, // 'risultati' | 'classifica' | 'cev' | 'coppa-italia' | 'playoff'
    },
});

// `route()` è registrato come proprietà globale in app.js e risolve da solo il
// prefisso di lingua (en.stagione.*), quindi non serve comporlo qui.
const items = computed(() => [
    { key: 'risultati', label: $t('risultati.nav_results'), routeName: 'stagione.risultati' },
    { key: 'classifica', label: $t('risultati.nav_standings'), routeName: 'stagione.classifica' },
    { key: 'cev', label: $t('risultati.nav_cev'), routeName: 'stagione.cev' },
    { key: 'coppa-italia', label: $t('risultati.nav_coppa_italia'), routeName: 'stagione.coppa-italia' },
    { key: 'playoff', label: $t('risultati.nav_playoff'), routeName: 'stagione.playoff' },
]);

const isActive = (key) => key === props.active;
</script>

<template>
    <nav :aria-label="$t('risultati.nav_label')" class="border-b border-gray-200 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <ul class="flex gap-1 overflow-x-auto -mb-px">
                <li v-for="item in items" :key="item.key" class="flex-shrink-0">
                    <Link
                        :href="route(item.routeName)"
                        class="block px-4 py-4 text-xs sm:text-sm font-bold uppercase tracking-wider border-b-2 transition-colors duration-200"
                        :class="isActive(item.key)
                            ? 'border-savino-fucsia text-savino-blue'
                            : 'border-transparent text-gray-500 hover:text-savino-blue hover:border-gray-300'"
                        :aria-current="isActive(item.key) ? 'page' : undefined"
                    >{{ item.label }}</Link>
                </li>
            </ul>
        </div>
    </nav>
</template>
