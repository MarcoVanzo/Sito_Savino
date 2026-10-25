/**
 * `Object.hasOwn` per i browser che non lo conoscono (Chrome < 93,
 * Safari < 15.4, Smart TV).
 *
 * Inertia lo usa quando copia le props della pagina nella cronologia: dove
 * manca anche `structuredClone` (Chrome < 98) ripiega su una copia a mano
 * che chiama `Object.hasOwn`, e la navigazione andava in errore (Sentry
 * SITO-SAVINO-BROWSER-M, 25/10/2026, Chrome Mobile 87 su Android 11).
 *
 * Nei browser che lo hanno non fa nulla.
 */
export function installaHasOwnDiRipiego(oggetto = Object) {
    if (typeof oggetto.hasOwn === 'function') {
        return false;
    }

    Object.defineProperty(oggetto, 'hasOwn', {
        configurable: true,
        writable: true,
        value: function hasOwn(valore, chiave) {
            if (valore === null || valore === undefined) {
                throw new TypeError('Cannot convert undefined or null to object');
            }

            return Object.prototype.hasOwnProperty.call(Object(valore), chiave);
        },
    });

    return true;
}
