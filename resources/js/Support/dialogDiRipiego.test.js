import { describe, it, expect, vi } from 'vitest';
import { JSDOM } from 'jsdom';
import { installaDialogDiRipiego } from './dialogDiRipiego.js';

// Un browser senza <dialog>, come Safari prima di iOS 15.4.
function finestraSenzaDialog() {
    const { window } = new JSDOM(
        '<!doctype html><body><button id="apri">apri</button>'
        + '<dialog><p>testo</p><button id="dentro">x</button></dialog><div></div></body>',
    );
    for (const nome of ['showModal', 'show', 'close']) {
        delete window.HTMLDialogElement.prototype[nome];
    }
    return window;
}

const attendiIlDom = () => new Promise((fatto) => setTimeout(fatto, 0));

describe('installaDialogDiRipiego', () => {
    it('non tocca i browser che hanno <dialog>', () => {
        const { window } = new JSDOM('');
        window.HTMLDialogElement.prototype.showModal ??= function showModal() {};
        expect(installaDialogDiRipiego(window)).toBe(false);
    });

    it('apre e chiude con showModal/close, sposta e rende il fuoco, manda close', () => {
        const w = finestraSenzaDialog();
        expect(installaDialogDiRipiego(w)).toBe(true);
        const d = w.document.querySelector('dialog');
        const chiusa = vi.fn();
        d.addEventListener('close', chiusa);
        w.document.getElementById('apri').focus();

        d.showModal();
        expect(d.open).toBe(true);
        expect(d.getAttribute('data-ripiego')).toBe('modale');
        expect(w.document.querySelectorAll('.dialog-ripiego-sfondo')).toHaveLength(1);
        expect(w.document.activeElement.id).toBe('dentro');

        d.close();
        expect(d.open).toBe(false);
        expect(d.hasAttribute('data-ripiego')).toBe(false);
        expect(w.document.querySelectorAll('.dialog-ripiego-sfondo')).toHaveLength(0);
        expect(w.document.activeElement.id).toBe('apri');
        expect(chiusa).toHaveBeenCalledOnce();
    });

    it('il clic sullo sfondo arriva alla finestra (per @click.self)', () => {
        const w = finestraSenzaDialog();
        installaDialogDiRipiego(w);
        const d = w.document.querySelector('dialog');
        const clic = vi.fn((e) => e.target === d);
        d.addEventListener('click', clic);

        d.showModal();
        w.document.querySelector('.dialog-ripiego-sfondo').click();
        expect(clic).toHaveReturnedWith(true);
    });

    it('Esc chiude la modale, a meno che cancel sia annullato', () => {
        const w = finestraSenzaDialog();
        installaDialogDiRipiego(w);
        const d = w.document.querySelector('dialog');
        const esc = () => w.document.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Escape' }));

        d.showModal();
        const blocca = (e) => e.preventDefault();
        d.addEventListener('cancel', blocca);
        esc();
        expect(d.open).toBe(true);

        d.removeEventListener('cancel', blocca);
        esc();
        expect(d.open).toBe(false);
    });

    it('una finestra tolta dal DOM mentre era aperta non lascia lo sfondo', async () => {
        const w = finestraSenzaDialog();
        installaDialogDiRipiego(w);
        const d = w.document.querySelector('dialog');

        d.showModal();
        d.remove();
        await attendiIlDom();
        expect(w.document.querySelectorAll('.dialog-ripiego-sfondo')).toHaveLength(0);
    });

    it('gli altri elementi restano senza showModal e open', () => {
        const w = finestraSenzaDialog();
        installaDialogDiRipiego(w);
        const div = w.document.querySelector('div');
        expect(div.showModal).toBeUndefined();
        expect(div.open).toBeUndefined();
    });
});
