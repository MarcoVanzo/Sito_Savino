/**
 * <dialog> per i browser che non lo conoscono (Safari prima di iOS 15.4).
 *
 * Lì `showModal()` non esiste e il clic su una foto della gallery andava in
 * errore (Sentry, 03/10/2026, iOS 14.3). Le finestre del sito usano
 * `showModal()`/`close()`/`open`, l'evento `close` e il clic sullo sfondo
 * (`@click.self`): qui li rifacciamo quanto basta.
 *
 * Non c'e' il top layer: la finestra sta sopra con `position: fixed` e
 * z-index (regole `dialog[data-ripiego]` in `app.css`). Al posto di
 * `::backdrop` c'e' un elemento vero, che blocca i clic sulla pagina sotto e
 * li gira alla finestra, come fa il browser col suo sfondo.
 *
 * Nei browser che hanno <dialog> non fa nulla.
 */
const FOCALIZZABILI = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function installaDialogDiRipiego(finestra = window) {
    const documento = finestra.document;
    const prova = documento?.createElement('dialog');

    if (!prova || typeof prova.showModal === 'function') {
        return false;
    }

    // Il prototipo vero dell'elemento (HTMLUnknownElement su Safari 14): cosi'
    // `open`, `show`, `close` non finiscono su tutti gli elementi della pagina.
    const proto = Object.getPrototypeOf(prova);
    const sfondi = new Map();
    const fuocoPrima = new Map();

    // Una finestra tolta dal DOM mentre era aperta (cambio di pagina, v-if)
    // non deve lasciare lo sfondo a coprire il sito.
    const sorvegliante = new finestra.MutationObserver(() => {
        for (const [dialogo, sfondo] of sfondi) {
            if (!dialogo.isConnected) {
                sfondo.remove();
                sfondi.delete(dialogo);
                fuocoPrima.delete(dialogo);
            }
        }
    });
    sorvegliante.observe(documento.documentElement, { childList: true, subtree: true });

    Object.defineProperty(proto, 'open', {
        configurable: true,
        get() {
            return this.hasAttribute('open');
        },
        set(valore) {
            this.toggleAttribute('open', Boolean(valore));
        },
    });

    proto.show = function show() {
        if (this.open) return;
        this.setAttribute('data-ripiego', '');
        this.open = true;
    };

    proto.showModal = function showModal() {
        if (this.open) return;

        const sfondo = documento.createElement('div');
        sfondo.className = 'dialog-ripiego-sfondo';
        sfondo.addEventListener('click', () => {
            this.dispatchEvent(new finestra.MouseEvent('click', { bubbles: true }));
        });
        documento.body.appendChild(sfondo);
        sfondi.set(this, sfondo);
        fuocoPrima.set(this, documento.activeElement);

        this.setAttribute('data-ripiego', 'modale');
        this.open = true;

        const primo = this.querySelector('[autofocus]') ?? this.querySelector(FOCALIZZABILI);
        if (primo) {
            primo.focus();
        } else {
            if (!this.hasAttribute('tabindex')) this.setAttribute('tabindex', '-1');
            this.focus();
        }
    };

    proto.close = function close() {
        if (!this.open) return;

        this.open = false;
        this.removeAttribute('data-ripiego');
        sfondi.get(this)?.remove();
        sfondi.delete(this);

        const precedente = fuocoPrima.get(this);
        fuocoPrima.delete(this);
        if (precedente?.isConnected) precedente.focus?.();

        this.dispatchEvent(new finestra.Event('close'));
    };

    // L'Esc chiude la finestra modale piu' recente, come fa il browser: prima
    // `cancel` (annullabile), poi `close`.
    documento.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Escape' && evento.key !== 'Esc') return;

        const aperte = documento.querySelectorAll('dialog[open][data-ripiego="modale"]');
        const ultima = aperte[aperte.length - 1];
        if (!ultima) return;

        const annulla = new finestra.Event('cancel', { cancelable: true });
        if (ultima.dispatchEvent(annulla)) {
            ultima.close();
        }
    });

    return true;
}
