/**
 * Un indirizzo di menu porta fuori dal sito?
 *
 * Le voci del menu si disegnavano tutte con `<Link>` di Inertia, che chiede la
 * pagina via XHR e si aspetta una risposta del sito. Mettendo nel pannello
 * l'indirizzo della Savino Del Bene Spa o del canale YouTube, la voce smetteva
 * di funzionare: Inertia riceveva una pagina altrui e finiva in errore.
 *
 * Chi sta fuori si apre con un `<a>` normale, in una scheda nuova.
 *
 * @param {unknown} href
 * @returns {boolean}
 */
export function isExternalLink(href) {
    if (typeof href !== 'string') return false

    const pulito = href.trim()

    // Gli indirizzi interni cominciano con "/". Restano fuori anche i mailto:
    // e i tel:, che non sono navigazioni ma azioni del sistema operativo.
    return /^(https?:)?\/\//i.test(pulito) || /^(mailto|tel):/i.test(pulito)
}

/**
 * Attributi da mettere su una voce di menu che porta fuori dal sito.
 *
 * `noopener` evita che la pagina aperta possa toccare quella di partenza;
 * `noreferrer` non le dice da dove arriva.
 *
 * @param {unknown} href
 * @returns {{target?: string, rel?: string}}
 */
export function externalLinkAttrs(href) {
    if (!isExternalLink(href) || typeof href !== 'string') return {}

    // mailto: e tel: non aprono una scheda: aprirla lascerebbe una pagina bianca.
    if (/^(mailto|tel):/i.test(href.trim())) return {}

    return { target: '_blank', rel: 'noopener noreferrer' }
}
