/**
 * Preparazione dei dati della pagina di un'atleta (Pages/Public/Atleta.vue).
 */

/**
 * Nome e cognome, senza spazi in più quando manca uno dei due.
 *
 * @param {{first_name?: string, last_name?: string}|null} atleta
 */
export function nomeCompleto(atleta) {
    return [atleta?.first_name, atleta?.last_name]
        .filter((parte) => typeof parte === 'string' && parte.trim() !== '')
        .join(' ')
        .trim();
}

/**
 * Le tre caselle del palmarès in testata: solo quelle con un numero dentro.
 *
 * @param {{totals?: Record<string, number>}|null} palmares
 * @returns {{key: string, value: number}[]}
 */
export function contatoriDelPalmares(palmares) {
    const totali = palmares?.totals ?? {};

    return ['club', 'national', 'individual']
        .map((key) => ({ key, value: totali[key] ?? 0 }))
        .filter((contatore) => contatore.value > 0);
}
