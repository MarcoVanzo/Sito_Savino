import { describe, it, expect, vi, beforeAll, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: '/', props: { auth: { user: null } } }),
    Link: { template: '<a href="#"><slot /></a>' },
    router: { post: vi.fn() },
}));
vi.mock('@/Components/Shop/CartBadge.vue', () => ({ default: { template: '<button type="button">carrello</button>' } }));
vi.mock('@/Components/Shop/UserMenu.vue', () => ({ default: { template: '<button type="button">account</button>' } }));

globalThis.route = (nome) => `/${nome}`;

// jsdom non calcola il layout: "visibile" vuol dire "non dentro display:none".
beforeAll(() => {
    Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
        configurable: true,
        get() {
            for (let el = this; el; el = el.parentElement) {
                if (el.style?.display === 'none') return null;
            }
            return document.body;
        },
    });
});

const { default: MobileDrawer } = await import('./MobileDrawer.vue');

let wrapper;
afterEach(() => wrapper?.unmount());

// Con aria-modal sul solo elenco delle voci, il pulsante di chiusura (che sta
// nella barra, fratello dell'elenco) usciva dall'albero accessibile.
describe('MobileDrawer come finestra modale', () => {
    it('a menu aperto la finestra comprende il pulsante di chiusura e le voci', async () => {
        wrapper = mount(MobileDrawer, {
            attachTo: document.body,
            props: { navigation: [{ label: 'Società', href: '/societa' }], isOpen: false },
            global: { mocks: { $t: (chiave) => chiave, route: (nome) => `/${nome}` } },
        });
        await wrapper.setProps({ isOpen: true });
        await flushPromises();

        const finestre = wrapper.findAll('[role="dialog"]');
        expect(finestre).toHaveLength(1);
        const finestra = finestre[0];
        expect(finestra.attributes('aria-modal')).toBe('true');
        expect(finestra.find('button[aria-expanded="true"]').exists()).toBe(true);
        expect(finestra.find('nav').exists()).toBe(true);

        // La trappola del focus funziona ancora: all'apertura il focus va
        // sulla prima voce, dentro la finestra.
        expect(finestra.element.contains(document.activeElement)).toBe(true);
        expect(document.activeElement.textContent).toContain('Società');
    });

    it('a menu chiuso la barra non e una finestra', () => {
        wrapper = mount(MobileDrawer, {
            attachTo: document.body,
            props: { navigation: [], isOpen: false },
            global: { mocks: { $t: (chiave) => chiave, route: (nome) => `/${nome}` } },
        });

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.find('[aria-modal]').exists()).toBe(false);
    });
});
