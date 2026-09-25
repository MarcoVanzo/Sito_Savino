import { describe, expect, it } from 'vitest';

const { mancaIlCookieXsrf } = await import('./bootstrap.js');

describe('cookie XSRF', () => {
    it('riconosce il cookie solo col suo nome esatto', () => {
        expect(mancaIlCookieXsrf('')).toBe(true);
        expect(mancaIlCookieXsrf('sito_session=abc')).toBe(true);
        expect(mancaIlCookieXsrf('NOT-XSRF-TOKEN=x')).toBe(true);
        expect(mancaIlCookieXsrf('XSRF-TOKEN=abc')).toBe(false);
        expect(mancaIlCookieXsrf('a=1; XSRF-TOKEN=abc')).toBe(false);
    });
});
