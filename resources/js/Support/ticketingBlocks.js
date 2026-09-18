/**
 * I blocchi facoltativi delle pagine Biglietteria e Campagna Abbonamenti:
 * lo spazio in evidenza (testo, grafica, pulsante), i vantaggi per gli
 * abbonati, le fasi della campagna e la Gift Card.
 *
 * La biglietteria non ha un listino: i prezzi dei biglietti cambiano di
 * partita in partita e stanno su Vivaticket. Quello che mostra sono questi
 * blocchi, e ognuno compare solo se la redazione lo ha compilato: un titolo
 * senza niente sotto, o un pulsante senza destinazione, non si mostrano.
 *
 * Le immagini arrivano gia' come indirizzi pubblici (CmsFile lato server).
 *
 * @param {Record<string, unknown>} cd content_data della pagina
 * @param {{t: (key: string) => string, safeUrl: (url: unknown) => string|null|undefined}} deps
 */
export function blocchiTicketing(cd, { t, safeUrl }) {
    const dati = cd && typeof cd === 'object' ? cd : {}

    return {
        feature: spazioInEvidenza(dati, { t, safeUrl }),
        benefits: elencoDiTesti(dati.benefits, 'text'),
        phases: fasi(dati.phases),
        giftCard: giftCard(dati, { t, safeUrl }),
    }
}

function testo(valore) {
    return typeof valore === 'string' ? valore.trim() : ''
}

function spazioInEvidenza(cd, { t, safeUrl }) {
    const title = testo(cd.feature_title)
    const text = testo(cd.feature_text)
    const image = testo(cd.feature_image) || null
    const buttonUrl = safeUrl(cd.feature_button_url) || null
    const hasText = title !== '' || text !== ''

    if (!hasText && !image) {
        return null
    }

    return {
        title,
        text,
        image,
        hasText,
        buttonUrl,
        buttonText: testo(cd.feature_button_text) || t('ticketing.feature_cta'),
    }
}

function elencoDiTesti(raw, chiave) {
    if (!Array.isArray(raw)) {
        return []
    }

    return raw
        .map(voce => (typeof voce === 'string' ? voce : testo(voce?.[chiave])))
        .filter(voce => haContenuto(voce))
}

/**
 * I vantaggi si scrivono con l'editor, quindi arrivano come HTML: una voce
 * svuotata in redazione resta `<p></p>` e la stringa non e' vuota. Si guarda
 * il testo, non il markup.
 *
 * @param {string} voce
 */
function haContenuto(voce) {
    return voce
        // `[^<>]` e non `[^>]`: senza escludere anche l'apertura, una sfilza
        // di `<` fa ripercorrere al motore la stessa stringa molte volte.
        .replace(/<[^<>]*>/g, '')
        .replace(/&nbsp;/gi, ' ')
        .trim() !== ''
}

function fasi(raw) {
    if (!Array.isArray(raw)) {
        return []
    }

    return raw
        .filter(fase => fase && typeof fase === 'object')
        .map(fase => ({
            title: testo(fase.title),
            period: testo(fase.period),
            description: testo(fase.description),
        }))
        .filter(fase => fase.title !== '')
}

function giftCard(cd, { t, safeUrl }) {
    const title = testo(cd.gift_card_title)
    const text = testo(cd.gift_card_text)
    const image = testo(cd.gift_card_image) || null
    const url = safeUrl(cd.gift_card_url) || null

    // Titolo, testo e immagine vuoti: non c'e' niente da mostrare, nemmeno
    // se resta un link.
    if (title === '' && text === '' && !image) {
        return null
    }

    return {
        title: title || t('ticketing.gift_card_label'),
        text,
        image,
        url,
        buttonText: testo(cd.gift_card_button_text) || t('ticketing.gift_card_cta'),
    }
}
