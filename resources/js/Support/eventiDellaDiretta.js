/**
 * Manda un contatore del report sponsor (pre-roll visti, saltati, cornici,
 * clic) senza rallentare nulla: `sendBeacon` parte anche se la finestra si
 * chiude, e se non c'è si ripiega su un `fetch` che sopravvive alla pagina.
 * Solo l'evento e la creatività: nessun dato del visitatore.
 */
export function registraEvento(url, { evento, creativita }) {
    if (!url || !evento || creativita == null) {
        return false;
    }

    const corpo = JSON.stringify({ evento, creativita });

    try {
        if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
            return navigator.sendBeacon(url, new Blob([corpo], { type: 'application/json' }));
        }

        fetch(url, { method: 'POST', body: corpo, headers: { 'Content-Type': 'application/json' }, keepalive: true, credentials: 'omit' }).catch(() => {});

        return true;
    } catch {
        // Un contatore perso non deve mai disturbare la diretta.
        return false;
    }
}
