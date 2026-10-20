import { describe, it, expect, beforeEach } from 'vitest';
import { dimenticaIResiduiDelVecchioSito } from './residuiDelVecchioSito.js';

describe('dimenticaIResiduiDelVecchioSito', () => {
    beforeEach(() => localStorage.clear());

    it('toglie reCAPTCHA e il modulo ActiveCampaign del vecchio WordPress', () => {
        localStorage.setItem('_grecaptcha', 'x');
        localStorage.setItem('_form_1_', '{}');
        localStorage.setItem('acf', '1');

        expect(dimenticaIResiduiDelVecchioSito().sort()).toEqual(['_form_1_', '_grecaptcha', 'acf']);
        expect(localStorage.length).toBe(0);
    });

    it('lascia stare la scelta sui cookie e le chiavi del sito nuovo', () => {
        localStorage.setItem('cookie-consent-v2', '{}');
        localStorage.setItem('isOpen', 'true');

        dimenticaIResiduiDelVecchioSito();

        expect(localStorage.getItem('cookie-consent-v2')).toBe('{}');
        expect(localStorage.getItem('isOpen')).toBe('true');
    });

    it('non rompe niente se il browser non lascia leggere l\'archivio', () => {
        const bloccato = { get length() { throw new Error('SecurityError'); } };

        expect(dimenticaIResiduiDelVecchioSito(bloccato)).toEqual([]);
    });
});
