import { describe, it, expect } from 'vitest';
import { installaHasOwnDiRipiego } from './hasOwnDiRipiego.js';

describe('installaHasOwnDiRipiego', () => {
    it('non tocca i browser che hanno gia\' Object.hasOwn', () => {
        const originale = () => true;
        const finto = { hasOwn: originale };

        expect(installaHasOwnDiRipiego(finto)).toBe(false);
        expect(finto.hasOwn).toBe(originale);
    });

    it('lo aggiunge dove manca (Chrome 87) e risponde come quello vero', () => {
        const finto = {};

        expect(installaHasOwnDiRipiego(finto)).toBe(true);

        const risultato = /a/.exec('a');
        expect(finto.hasOwn(risultato, 'index')).toBe(true);
        expect(finto.hasOwn(risultato, 'input')).toBe(true);
        expect(finto.hasOwn({}, 'toString')).toBe(false);
        expect(finto.hasOwn(Object.create(null), 'x')).toBe(false);
        expect(finto.hasOwn('abc', 'length')).toBe(true);
    });

    it('rifiuta null e undefined come fa il browser', () => {
        const finto = {};
        installaHasOwnDiRipiego(finto);

        expect(() => finto.hasOwn(null, 'x')).toThrow(TypeError);
        expect(() => finto.hasOwn(undefined, 'x')).toThrow(TypeError);
    });
});
