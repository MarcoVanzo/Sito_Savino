import { describe, it, expect, vi, beforeEach } from 'vitest';

const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { post: (...args) => post(...args) },
    usePage: () => ({ props: {} }),
}));

globalThis.route = (nome) => `/${nome}`;

const { useCart } = await import('./useCart.js');

// La card del prodotto chiamava addToCart(id, { onFinish, onError }): il
// secondo argomento veniva letto come quantita', il server riceveva
// `quantity: {}` e lo rifiutava, e il pulsante restava in attesa per sempre
// perche' le callback non arrivavano a Inertia.
describe('useCart.addToCart', () => {
    beforeEach(() => {
        post.mockReset();
    });

    it('con un oggetto di opzioni manda la quantita richiesta', () => {
        useCart().addToCart({ product_id: 7, quantity: 1 }, {});

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0][1]).toEqual({
            product_id: 7,
            quantity: 1,
            variant_id: null,
            personalizzazione: false,
        });
    });

    it('con un id e un oggetto legge il secondo argomento come callback', () => {
        const onFinish = vi.fn();
        const onError = vi.fn();

        useCart().addToCart(7, { onFinish, onError });

        const [, payload, opzioni] = post.mock.calls[0];
        expect(payload.quantity).toBe(1);
        expect(payload.product_id).toBe(7);

        opzioni.onFinish({});
        opzioni.onError({ quantity: 'errore' });
        expect(onFinish).toHaveBeenCalledOnce();
        expect(onError).toHaveBeenCalledWith({ quantity: 'errore' });
    });

    it('con la forma posizionale tiene quantita e variante', () => {
        useCart().addToCart(7, 3, 12);

        expect(post.mock.calls[0][1]).toMatchObject({ product_id: 7, quantity: 3, variant_id: 12 });
    });
});
