<script setup>\nimport { useTranslations } from '@/Composables/useTranslations.js';
import { useLocale } from '@/Composables/useLocale.js';

const $t = useTranslations();
const { locale, alternateUrl, switchLocale } = useLocale();
</script>

<template>
    <div class="flex items-center gap-1">
        <!-- Current locale indicator -->
        <span
            class="text-sm font-bold uppercase tracking-wider px-2 py-1 rounded text-white/90"
            :title="locale === 'it' ? $t('lang.italian') : $t('lang.english')"
        >
            {{ locale === 'it' ? '🇮🇹' : '🇬🇧' }}
        </span>

        <span class="text-white/30 text-xs">|</span>

        <!-- Switch to other locale -->
        <a
            :href="alternateUrl"
            @click.prevent="switchLocale"
            class="text-sm px-2 py-1 rounded text-white/50 hover:text-white hover:bg-white/10 transition-all duration-200 cursor-pointer"
            :aria-label="$t('lang.switch_to', { lang: locale === 'it' ? $t('lang.english') : $t('lang.italian') })"
            :title="locale === 'it' ? $t('lang.english') : $t('lang.italian')"
        >
            {{ locale === 'it' ? '🇬🇧' : '🇮🇹' }}
        </a>
    </div>
</template>
