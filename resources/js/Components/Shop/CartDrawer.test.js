import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { ref } from 'vue';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: '/', props: { locale: 'it', auth: { user: null } } }),
    Link: { template: '<a href="#"><slot /></a>' },
    router: { post: vi.fn() },
}));

const updateQuantity = vi.fn();
vi.mock('@/Composables/useCart.js', () => ({
    useCart: () => ({
        isCartOpen: ref(true),
        closeCart: vi.fn(),
        updateQuantity,
        removeItem: vi.fn(),
        cartVersion: ref(0),
    }),
}));

globalThis.route = (nome) => `/${nome}`;

const riga = { id: 7, name: 'Maglia gara', quantity: 1, disponibili: 1, price: 59, total: 59 };
globalThis.fetch = vi.fn(async () => ({ json: async () => ({ items: [riga], total: 59 }) }));

const { default: CartDrawer } = await import('./CartDrawer.vue');

let wrapper;
afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

/**
 * Al minimo e al massimo "−" e "+" restano focalizzabili: con `disabled`
 * il focus cadeva sul body appena la quantità arrivava a 1 o alla giacenza.
 */
describe('Carrello: i pulsanti della quantità ai limiti', () => {
    it('non sono disattivati, lo dicono con aria-disabled e non chiamano il server', async () => {
        wrapper = mount(CartDrawer, { attachTo: document.body, global: { mocks: { route: globalThis.route } } });
        await flushPromises();

        const pulsanti = [...document.body.querySelectorAll('button[aria-label]')]
            .filter((b) => ['−', '+'].includes(b.textContent.trim()));

        expect(pulsanti).toHaveLength(2);
        for (const pulsante of pulsanti) {
            expect(pulsante.hasAttribute('disabled')).toBe(false);
            expect(pulsante.getAttribute('aria-disabled')).toBe('true');
            pulsante.click();
        }
        expect(updateQuantity).not.toHaveBeenCalled();
    });
});
