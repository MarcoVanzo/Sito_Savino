/**
 * Gli eventi del funnel, verso GA4 e il pixel di Meta insieme.
 *
 * I componenti chiamano questo modulo e non i due tag: cosi' un evento nuovo
 * non arriva a uno solo dei due, e il consenso lo controlla ciascun tag con le
 * sue regole (statistiche per GA4, marketing per il pixel). Senza consenso le
 * chiamate non mandano nulla.
 */
import * as ga4 from './analytics.js';
import * as pixel from './meta-pixel.js';

/**
 * Scheda prodotto aperta.
 *
 * @param {{ id: number, name: string, price: number }} prodotto il prezzo che si paga, non il barrato
 */
export function prodottoVisto({ id, name, price }) {
    pixel.trackViewContent({ id, name, value: price });
    ga4.trackViewItem({ id, name, price });
}

/**
 * Prodotto aggiunto al carrello.
 *
 * @param {{ id: number, name: string, price: number, quantity?: number }} riga `price` di un pezzo
 */
export function aggiuntoAlCarrello({ id, name, price, quantity = 1 }) {
    pixel.trackAddToCart({ id, name, value: (Number(price) || 0) * quantity, quantity });
    ga4.trackAddToCart({ id, name, price, quantity });
}

/**
 * Checkout aperto.
 *
 * @param {{ value: number, items: Array<{ id: number, name: string, price: number, quantity: number }> }} carrello
 */
export function checkoutIniziato({ value, items = [] }) {
    pixel.trackInitiateCheckout({
        value,
        numItems: items.reduce((totale, riga) => totale + (Number(riga.quantity) || 0), 0),
    });
    ga4.trackBeginCheckout({ value, items });
}

/**
 * Ordine concluso: una volta sola per ordine anche se la pagina si ricarica.
 *
 * @param {{ orderNumber: string, value: number, items: Array<{ id: number, name: string, price: number, quantity: number }> }} ordine
 */
export function acquistoConcluso({ orderNumber, value, items = [] }) {
    pixel.trackPurchase({ orderNumber, value, items });
    ga4.trackPurchase({ orderNumber, value, items });
}
