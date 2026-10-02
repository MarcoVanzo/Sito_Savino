import { describe, expect, it, vi } from 'vitest';
import { vaiAllAncora } from './vaiAllAncora.js';

describe('vaiAllAncora', () => {
    it("scorre fino all'ancora dell'indirizzo", () => {
        document.body.innerHTML = '<h3 id="acquisti">Acquisti</h3>';
        const sezione = document.getElementById('acquisti');
        sezione.scrollIntoView = vi.fn();

        expect(vaiAllAncora('#acquisti')).toBe(true);
        expect(sezione.scrollIntoView).toHaveBeenCalledWith({ block: 'start' });
    });

    it('legge le ancore codificate', () => {
        document.body.innerHTML = '<h3 id="dirittì">Diritti</h3>';
        document.getElementById('dirittì').scrollIntoView = vi.fn();

        expect(vaiAllAncora('#diritt%C3%AC')).toBe(true);
    });

    it("senza ancora, o con un'ancora che non c'e', non fa niente", () => {
        document.body.innerHTML = '<p>testo</p>';

        expect(vaiAllAncora('')).toBe(false);
        expect(vaiAllAncora('#manca')).toBe(false);
        expect(vaiAllAncora('#%E0%A4%A')).toBe(false);
    });
});
