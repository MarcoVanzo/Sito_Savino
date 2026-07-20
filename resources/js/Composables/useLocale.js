import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Composable for locale-aware operations.
 * Provides the current locale, locale switching, and date/number formatting.
 */
export function useLocale() {
    const page = usePage();

    const locale = computed(() => page.props.locale ?? 'it');
    const isItalian = computed(() => locale.value === 'it');
    const isEnglish = computed(() => locale.value === 'en');

    /**
     * Get the alternate URL for the other locale.
     */
    const alternateUrl = computed(() => page.props.alternateUrl ?? '/');

    /**
     * Switch to the other locale via full page reload.
     * A full reload is required because:
     * 1. The backend must set the new locale in session/middleware
     * 2. SSR must render in the new language
     * 3. createTranslations() in app.js only runs once during setup()
     */
    function switchLocale() {
        window.location.href = alternateUrl.value;
    }

    /**
     * Format a date string according to the current locale.
     * @param {string} dateStr
     * @param {Intl.DateTimeFormatOptions} options
     * @returns {string}
     */
    function formatDate(dateStr, options = {}) {
        if (!dateStr) return '';
        const defaults = { day: 'numeric', month: 'long', year: 'numeric' };
        const localeStr = locale.value === 'en' ? 'en-GB' : 'it-IT';
        return new Date(dateStr).toLocaleDateString(localeStr, { ...defaults, ...options });
    }

    /**
     * Format a number according to the current locale.
     * @param {number} num
     * @returns {string}
     */
    function formatNumber(num) {
        const localeStr = locale.value === 'en' ? 'en-GB' : 'it-IT';
        return num.toLocaleString(localeStr);
    }

    return {
        locale,
        isItalian,
        isEnglish,
        alternateUrl,
        switchLocale,
        formatDate,
        formatNumber,
    };
}
