/**
 * La classifica della SDB Volley Club Race, pronta da mostrare.
 *
 * Arriva dal pannello come elenco di societa' con un punteggio, nell'ordine
 * in cui la redazione le ha inserite: qui si ordina per punti, a parita' per
 * nome, e si assegna la posizione. Chi non ha nome viene scartato; un
 * punteggio vuoto o non numerico vale zero, cosi' una riga incompleta non
 * fa sparire la tabella.
 *
 * @param {unknown} raw contenuto di content_data.standings
 * @returns {Array<{position: number, club: string, points: number}>}
 */
export function classificaClubRace(raw) {
    if (!Array.isArray(raw)) {
        return []
    }

    return raw
        .filter(riga => riga && typeof riga.club === 'string' && riga.club.trim() !== '')
        .map(riga => ({ club: riga.club.trim(), points: punti(riga.points) }))
        .sort((a, b) => b.points - a.points || a.club.localeCompare(b.club, 'it'))
        .map((riga, indice) => ({ position: indice + 1, ...riga }))
}

function punti(valore) {
    const numero = typeof valore === 'number' ? valore : Number.parseFloat(String(valore ?? '').replace(',', '.'))

    return Number.isFinite(numero) ? numero : 0
}
