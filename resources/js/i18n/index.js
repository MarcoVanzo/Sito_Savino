import it from './it.json';
import en from './en.json';

const messages = { it, en };

/**
 * Resolve a dot-notation key from a nested object.
 * @param {Object} obj
 * @param {string} key
 * @returns {*}
 */
export function resolve(obj, key) {
    return key.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : undefined), obj);
}

/**
 * Create a translation function for the given locale.
 * Fallback chain: requested locale → Italian → raw key.
 *
 * @param {string} locale - 'it' or 'en'
 * @returns {function(string, Object=): string}
 */
export function createTranslations(locale = 'it') {
    return function t(key, params = {}) {
        let value = resolve(messages[locale], key) ?? resolve(messages.it, key) ?? key;
        // Simple interpolation: replace {param} with value
        if (typeof value === 'string' && params) {
            Object.entries(params).forEach(([k, v]) => {
                value = value.replace(new RegExp(`\\{${k}\\}`, 'g'), v);
            });
        }
        return value;
    };
}

export { messages };
