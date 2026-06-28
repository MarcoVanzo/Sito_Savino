/**
 * Mappa centralizzata dei ruoli giocatrici.
 *
 * Le chiavi corrispondono ai backing values dell'enum PHP PlayerPosition
 * (palleggiatrice, schiacciatrice, opposto, centrale, libero).
 * I valori sono le label italiane da mostrare in UI.
 */
export const roleLabels = {
    palleggiatrice: 'Palleggiatrice',
    schiacciatrice: 'Schiacciatrice',
    centrale: 'Centrale',
    opposto: 'Opposto',
    libero: 'Libero',
};

/**
 * Colori CSS (classi Tailwind) per i badge ruolo.
 */
export const roleColors = {
    palleggiatrice: 'bg-savino-gold',
    schiacciatrice: 'bg-savino-red',
    centrale: 'bg-savino-blue',
    opposto: 'bg-purple-600',
    libero: 'bg-emerald-600',
};

/**
 * Restituisce la label leggibile per un backing value di ruolo.
 * Fallback: restituisce il valore originale se non trovato.
 */
export function displayRole(role) {
    return roleLabels[role] ?? role;
}
