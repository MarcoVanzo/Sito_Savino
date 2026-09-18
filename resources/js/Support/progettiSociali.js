/**
 * I progetti sociali come li mostra la pagina: la scheda chiude con un solo
 * pulsante, che e' "Contattaci" quando il progetto ha un referente a cui
 * scrivere e "Scopri" quando invece si racconta in una pagina.
 *
 * L'indirizzo di posta vince sul link: per Volley4Kids e Volley4All la
 * redazione non ha una pagina da aprire, ha una persona da far contattare.
 *
 * @param {unknown} raw content_data.projects
 * @param {{safeUrl: (url: unknown) => string|null|undefined}} deps
 */
export function progettiSociali(raw, { safeUrl }) {
    if (!Array.isArray(raw)) {
        return []
    }

    return raw
        .filter(progetto => progetto && typeof progetto === 'object')
        .map(progetto => ({ ...progetto, cta: azioneDelProgetto(progetto, safeUrl) }))
}

/**
 * @param {Record<string, unknown>} progetto
 * @param {(url: unknown) => string|null|undefined} safeUrl
 */
function azioneDelProgetto(progetto, safeUrl) {
    const email = indirizzoDiPosta(progetto.contact_email)

    if (email !== null) {
        return { tipo: 'email', href: `mailto:${email}` }
    }

    const link = safeUrl(progetto.link) || null

    return link !== null ? { tipo: 'link', href: link } : null
}

/**
 * L'indirizzo scritto in redazione, se e' davvero un indirizzo: nel campo
 * "link" era finita una frase intera ("Per maggiori informazioni: ...") e un
 * mailto costruito su quella non apre niente.
 *
 * @param {unknown} valore
 */
function indirizzoDiPosta(valore) {
    if (typeof valore !== 'string') {
        return null
    }

    const pulito = valore.trim()

    return /^[^\s@,;:<>]+@[^\s@,;:<>]+\.[^\s@,;:<>]+$/.test(pulito) ? pulito : null
}
