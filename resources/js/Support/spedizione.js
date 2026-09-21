/**
 * Il costo di spedizione di una zona, come lo calcola il server.
 *
 * Il conto è scritto due volte — qui e in ShippingZone::calculateShippingCost —
 * perché il totale va mostrato mentre il cliente compila il modulo, senza un
 * giro sul server a ogni carattere. Due copie sono già una di troppo: quando
 * era scritto una terza volta dentro il checkout delle aste, quella terza è
 * rimasta indietro alle fasce di peso e al vincitore veniva mostrata la tariffa
 * base mentre l'ordine ne addebitava un'altra. Se cambia una regola, cambiano
 * questo file e il model, e i test li confrontano.
 *
 * Le regole, nell'ordine: la soglia della spedizione gratuita viene prima di
 * tutto, è una promessa fatta nel carrello e vale qualunque sia il collo; poi
 * la prima fascia che contiene il peso, prese in ordine di peso crescente
 * (l'ultima può non avere limite e prende tutto il resto); senza fasce, o se
 * nessuna copre il peso, la tariffa base.
 *
 * @param {{flat_rate?: unknown, free_threshold?: unknown, weight_rates?: unknown}|null|undefined} zona
 * @param {{subtotale?: unknown, peso?: unknown}} carrello
 * @returns {number} il costo in euro
 */
export function costoDiSpedizione(zona, { subtotale = 0, peso = 0 } = {}) {
    if (!zona) {
        return 0
    }

    const soglia = Number(zona.free_threshold ?? 0)

    if (soglia > 0 && Number(subtotale) >= soglia) {
        return 0
    }

    const tariffa = fasciaPerIlPeso(zona.weight_rates, peso)

    return tariffa !== null ? tariffa : Number(zona.flat_rate ?? 0) || 0
}

/**
 * La tariffa della prima fascia che contiene questo peso, o null se non ce n'è.
 *
 * Le fasce arrivano già ordinate dal server (ShippingZone::fasceOrdinate), ma
 * si riordinano comunque: sono un elenco compilato a mano nel pannello e una
 * fascia fuori posto farebbe pagare la tariffa sbagliata.
 *
 * @param {unknown} fasce
 * @param {unknown} peso
 * @returns {number|null}
 */
function fasciaPerIlPeso(fasce, peso) {
    if (!Array.isArray(fasce)) {
        return null
    }

    const valide = fasce
        .filter(f => f && Number.isFinite(Number(f.rate)))
        .map(f => ({
            limite: limiteDellaFascia(f.max_weight),
            tariffa: Number(f.rate),
        }))
        .sort((a, b) => {
            if (a.limite === null) return 1
            if (b.limite === null) return -1
            return a.limite - b.limite
        })

    const kg = Number(peso) || 0

    for (const fascia of valide) {
        if (fascia.limite === null || kg <= fascia.limite) {
            return fascia.tariffa
        }
    }

    return null
}

/**
 * Il limite di una fascia: un numero, oppure null per "da qui in su".
 *
 * @param {unknown} valore
 * @returns {number|null}
 */
function limiteDellaFascia(valore) {
    if (valore === null || valore === undefined || valore === '') {
        return null
    }

    const limite = Number(valore)

    return Number.isFinite(limite) ? limite : null
}
