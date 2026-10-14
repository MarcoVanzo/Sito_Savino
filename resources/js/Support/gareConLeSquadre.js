/**
 * Ricompone le gare della pagina Risultati con i dati delle loro squadre.
 *
 * Il server manda nome, logo e appartenenza di ogni squadra una volta sola
 * (`teams`, per id) e sulle gare solo `homeTeam`/`awayTeam`: ripetere su 182
 * gare gli indirizzi assoluti dei due loghi era un terzo del JSON della
 * pagina. Qui le gare tornano nella forma che il template legge.
 *
 * Una squadra assente (gara senza squadra, o id sconosciuto) vale come nome
 * vuoto e nessun logo, come quando il server mandava i campi per gara.
 */
const SENZA_SQUADRA = Object.freeze({ name: '', logo: null, isOwn: false })

export function gareConLeSquadre(gare, squadre) {
    const squadra = (id) => (id != null && squadre?.[id]) || SENZA_SQUADRA

    return (gare ?? []).map((gara) => {
        const casa = squadra(gara.homeTeam)
        const ospite = squadra(gara.awayTeam)

        return {
            ...gara,
            home: casa.name ?? '',
            away: ospite.name ?? '',
            homeLogo: casa.logo ?? null,
            awayLogo: ospite.logo ?? null,
            homeIsOwn: casa.isOwn === true,
            awayIsOwn: ospite.isOwn === true,
        }
    })
}
