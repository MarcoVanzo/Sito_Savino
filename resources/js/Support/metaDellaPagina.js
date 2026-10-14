/**
 * I meta della pagina calcolati dal server (prop condivisa `seo`, da
 * ServeSocialCrawlerMeta). Sono gli stessi dell'HTML servito: il client li
 * usa così come sono invece di ricomporli, altrimenti il titolo della scheda
 * cambiava appena partiva il JavaScript («Savino Del Bene Volley — Sito
 * Ufficiale» diventava «Sito Ufficiale — Savino Del Bene Volley»).
 */

/**
 * Il titolo completo dato dal server, o null se la pagina non ne ha uno
 * (errori, pagine che il server non descrive).
 *
 * @param {object|undefined} props
 * @returns {string|null}
 */
export function titoloDellaPagina(props) {
    const titolo = props?.seo?.title;

    return typeof titolo === 'string' && titolo.trim() !== '' ? titolo : null;
}

/**
 * I meta da mettere nel <Head>: quelli del server dove ci sono, quelli
 * calcolati dalla pagina come ripiego.
 *
 * @param {object|undefined} props
 * @param {{title: string, description: string, image: string, type: string}} ripiego
 */
export function metaDellaPagina(props, ripiego) {
    const seo = props?.seo;

    if (!seo || !titoloDellaPagina(props)) {
        return ripiego;
    }

    return {
        ...ripiego,
        title: seo.title,
        description: seo.description || ripiego.description,
        image: seo.image || ripiego.image,
        type: seo.type || ripiego.type,
    };
}
