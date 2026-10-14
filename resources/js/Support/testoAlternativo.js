/**
 * Il testo alternativo di un'immagine: il primo candidato non vuoto, oppure
 * una stringa vuota.
 *
 * Mai `undefined` o `null`: con `:alt="post.title"` e il titolo mancante Vue
 * toglie l'attributo, e un'immagine senza `alt` lo screen reader la legge col
 * nome del file e l'audit SEO la conta fra quelle senza testo (14/10/2026:
 * 92 su 179 in home). Una stringa vuota, invece, dice "decorativa".
 *
 * @param {...unknown} candidati in ordine di preferenza (es. alt scritto dalla redazione, poi il titolo)
 * @returns {string}
 */
export function testoAlternativo(...candidati) {
    for (const candidato of candidati) {
        if (typeof candidato === 'string' && candidato.trim() !== '') {
            return candidato.trim();
        }

        if (typeof candidato === 'number') {
            return String(candidato);
        }
    }

    return '';
}
