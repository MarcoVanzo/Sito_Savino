import { describe, expect, it } from 'vitest'
import { progettiSociali } from './progettiSociali.js'

const deps = {
    safeUrl: (url) => (typeof url === 'string' && url.startsWith('/') ? url : null),
}

describe('progettiSociali', () => {
    it('senza elenco dal CMS non c\'e\' niente da mostrare', () => {
        expect(progettiSociali(undefined, deps)).toEqual([])
        expect(progettiSociali({ 'uuid-1': { title: 'Volley4Kids' } }, deps)).toEqual([])
    })

    it('con l\'email la scheda chiude con "Contattaci"', () => {
        const [progetto] = progettiSociali(
            [{ title: 'Volley4Kids', contact_email: 'federico.latanza@savinodelbenevolley.it' }],
            deps,
        )

        expect(progetto.cta).toEqual({ tipo: 'email', href: 'mailto:federico.latanza@savinodelbenevolley.it' })
    })

    it('l\'email vince sul link alla pagina', () => {
        const [progetto] = progettiSociali(
            [{ title: 'Volley 4 All', contact_email: 'sociale@savinodelbenevolley.it', link: '/sociale/volley-4-all' }],
            deps,
        )

        expect(progetto.cta.tipo).toBe('email')
    })

    it('una frase al posto dell\'indirizzo non diventa un mailto', () => {
        const [progetto] = progettiSociali(
            [{ title: 'Volley4Kids', contact_email: 'Per maggiori informazioni: federico.latanza@savinodelbenevolley.it' }],
            deps,
        )

        expect(progetto.cta).toBeNull()
    })

    it('senza email resta "Scopri" verso la pagina, e un link non sicuro non passa', () => {
        const [conLink] = progettiSociali([{ title: 'Progetto Scuola', link: '/sociale/progetto-scuola' }], deps)
        expect(conLink.cta).toEqual({ tipo: 'link', href: '/sociale/progetto-scuola' })

        const [senzaNiente] = progettiSociali([{ title: 'Progetto Scuola', link: 'javascript:alert(1)' }], deps)
        expect(senzaNiente.cta).toBeNull()
    })
})
