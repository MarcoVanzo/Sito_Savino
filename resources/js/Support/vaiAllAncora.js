/**
 * Porta la pagina all'ancora dell'indirizzo (`/privacy-policy#acquisti`).
 *
 * Il testo delle pagine CMS nasce da v-html: quando il browser cerca l'ancora
 * l'elemento non c'e' ancora, e la pagina restava in cima. Chi la chiama lo
 * fa quando il testo c'e' (e a caricamento finito, perche' immagini e font
 * spostano il punto); la testata sticky la scansa lo scroll-margin del CSS.
 *
 * @param {string} hash  `window.location.hash`
 * @param {Document} documento
 * @returns {boolean} se ha trovato l'ancora
 */
export function vaiAllAncora(hash, documento = document) {
    let id;
    try {
        id = decodeURIComponent((hash ?? '').replace(/^#/, ''));
    } catch {
        return false;
    }
    if (!id) return false;

    const elemento = documento.getElementById(id);
    if (!elemento) return false;

    elemento.scrollIntoView({ block: 'start' });

    return true;
}
