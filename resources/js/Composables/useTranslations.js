import { getCurrentInstance } from 'vue';
import { messages, resolve } from '@/i18n/index.js';
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
    let page;
    try {
        page = usePage();
    } catch {
        page = null;
    }

    return function t(key, params = {}) {
        let locale = 'it';
        if (page?.props?.locale) {
            locale = page.props.locale;
        } else {
            const instance = getCurrentInstance();
            if (instance?.proxy?.$page?.props) {
                locale = instance.proxy.$page.props.locale;
            }
        }
        
        let value = resolve(messages[locale], key) ?? resolve(messages.it, key) ?? key;
        if (typeof value === 'string' && params) {
            Object.entries(params).forEach(([k, v]) => {
                value = value.replace(new RegExp(String.raw`\{${k}\}`, 'g'), v);
            });
        }
        return value;
    };
}
