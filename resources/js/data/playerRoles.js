/**
 * Mappa centralizzata dei ruoli giocatrici.
 *
 * Le chiavi corrispondono ai backing values dell'enum PHP PlayerPosition
 * (palleggiatrice, schiacciatrice, opposto, centrale, libero).
 *
 * roleLabels contiene le chiavi i18n per ogni ruolo. Usare getRoleLabels($t)
 * per ottenere le label tradotte, oppure displayRole(role, $t) per singolo ruolo.
 */

/**
 * Chiavi i18n per i ruoli. I valori sono le chiavi di traduzione.
 */
export const roleKeys = {
    palleggiatrice: 'roles.setter',
    schiacciatrice: 'roles.outside_hitter',
    centrale: 'roles.middle_blocker',
    opposto: 'roles.opposite',
    libero: 'roles.libero',
};

/**
 * Label italiane statiche (backward-compatible, per contesti senza $t).
 */
export const roleLabels = {
    palleggiatrice: 'Palleggiatrice',
    schiacciatrice: 'Schiacciatrice',
    centrale: 'Centrale',
    opposto: 'Opposto',
    libero: 'Libero',
};

/**
 * Restituisce le label tradotte per ogni ruolo.
 * @param {Function} $t - Funzione di traduzione
 * @returns {Object} Mappa ruolo → label tradotta
 */
export function getRoleLabels($t) {
    if (!$t) return roleLabels;
    const translated = {};
    for (const [key, i18nKey] of Object.entries(roleKeys)) {
        translated[key] = $t(i18nKey);
    }
    return translated;
}

/**
 * Colori CSS (classi Tailwind) per i badge ruolo.
 */
export const roleColors = {
    palleggiatrice: 'bg-savino-fucsia',
    schiacciatrice: 'bg-savino-red',
    centrale: 'bg-savino-blue',
    opposto: 'bg-purple-600',
    libero: 'bg-emerald-600',
};

/**
 * Restituisce la label leggibile per un backing value di ruolo.
 * Se viene passata una funzione $t, usa le chiavi i18n.
 * Fallback: restituisce il valore originale se non trovato.
 * @param {string} role
 * @param {Function} [$t] - Funzione di traduzione (opzionale)
 */
export function displayRole(role, $t) {
    if ($t && roleKeys[role]) {
        return $t(roleKeys[role]);
    }
    return roleLabels[role] ?? role;
}
