import { getCurrentInstance } from 'vue';
import { createTranslations } from '@/i18n/index.js';
import { usePage } from '@inertiajs/vue3';

/**
 * SSR-safe composable to access the $t translation function.
 *
 * In the browser, returns the global $t registered via app.config.globalProperties.
 * During SSR, creates a fresh $t from the page locale (no window needed).
 *
 * Usage in <script setup>:
 *   import { useTranslations } from '@/Composables/useTranslations.js';
 *   const $t = useTranslations();
 *
 * Must be called during component setup() (not inside callbacks or async).
 *
 * @returns {function(string, Object=): string}
 */
export function useTranslations() {
    // 1. Try Vue's globalProperties (works in both client & SSR)
    const instance = getCurrentInstance();
    const global$t = instance?.appContext?.config?.globalProperties?.$t;
    if (global$t) return global$t;

    // 2. Fallback: create from page locale (SSR without global registration)
    try {
        const page = usePage();
        return createTranslations(page.props.locale || 'it');
    } catch {
        // Ultimate fallback (should never happen in normal flow)
        return createTranslations('it');
    }
}
