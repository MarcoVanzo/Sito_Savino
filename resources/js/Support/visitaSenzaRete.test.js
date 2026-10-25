import { describe, it, expect, vi } from 'vitest';
import { apriSenzaInertiaSeCadeLaRete } from './visitaSenzaRete.js';

const PAGINA = 'https://savinodelbenevolley.it/stagione/atleta/1-maja-ognjenovic';

function ambiente(destinazione) {
    const documento = new EventTarget();
    const finestra = { location: { href: 'https://savinodelbenevolley.it/stagione', assign: vi.fn() } };
    apriSenzaInertiaSeCadeLaRete(documento, finestra, destinazione);

    const cadeLaRete = (url) => {
        const evento = new CustomEvent('inertia:networkError', { cancelable: true, detail: { error: { url } } });
        documento.dispatchEvent(evento);

        return evento;
    };

    return { finestra, cadeLaRete };
}

describe('apriSenzaInertiaSeCadeLaRete', () => {
    it('apre con una navigazione normale la pagina che la rete non ha portato', () => {
        const { finestra, cadeLaRete } = ambiente(() => PAGINA);

        const evento = cadeLaRete(PAGINA);

        expect(finestra.location.assign).toHaveBeenCalledWith(PAGINA);
        expect(evento.defaultPrevented).toBe(true);
    });

    it('riconosce la visita anche con l\'indirizzo relativo', () => {
        const { finestra, cadeLaRete } = ambiente(() => PAGINA);

        cadeLaRete('/stagione/atleta/1-maja-ognjenovic');

        expect(finestra.location.assign).toHaveBeenCalledWith(PAGINA);
    });

    it('lascia stare un precaricamento fallito mentre non c\'e\' una visita in corso', () => {
        const { finestra, cadeLaRete } = ambiente(() => null);

        const evento = cadeLaRete('https://savinodelbenevolley.it/shop');

        expect(finestra.location.assign).not.toHaveBeenCalled();
        expect(evento.defaultPrevented).toBe(false);
    });

    it('non apre un indirizzo diverso da quello della visita in corso', () => {
        const { finestra, cadeLaRete } = ambiente(() => PAGINA);

        cadeLaRete('https://savinodelbenevolley.it/shop');

        expect(finestra.location.assign).not.toHaveBeenCalled();
    });
});
