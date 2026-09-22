<?php

/**
 * Le impostazioni dello Shop e delle Aste con i loro valori di partenza.
 *
 * Sono dati, non logica, e servono a più padroni: il seeder che crea le righe
 * (ShopSettingsSeeder), la pagina "Impostazioni Shop & Aste", che li usa come
 * valore di ripiego per le chiavi non ancora in archivio, la migrazione che ha
 * rimesso a posto quelle svuotate e il comando `shop:stato`. Senza quel ripiego
 * un interruttore che in tabella non c'è si apre spento, e il primo
 * salvataggio lo spegne davvero: è così che lo shop è finito in manutenzione
 * mentre in redazione si accendevano le aste.
 *
 * Una riga per impostazione, nell'ordine:
 *
 *     chiave, tipo, valore di partenza, etichetta, descrizione, ordinamento
 *
 * `type` conta: `boolean` è ciò che rende un valore un interruttore, sia in
 * lettura (SiteSetting::getAllGrouped) sia nel modulo. Il gruppo non si scrive
 * perché è già il prefisso della chiave, come lo legge
 * SiteSetting::collocazione().
 */
$impostazione = static fn (
    string $chiave,
    string $tipo,
    string $valore,
    string $etichetta,
    string $descrizione,
    int $ordinamento,
): array => [
    'key' => $chiave,
    'value' => $valore,
    'type' => $tipo,
    'group' => explode('.', $chiave, 2)[0],
    'label' => $etichetta,
    'description' => $descrizione,
    'sort_order' => $ordinamento,
];

return [
    // ─── Negozio ──────────────────────────────────────────────────────────
    $impostazione('shop.enabled', 'boolean', '1',
        'Shop attivo', 'Abilita o disabilita completamente lo shop', 1),
    $impostazione('shop.maintenance_message', 'text', '',
        'Messaggio manutenzione', 'Banner mostrato quando lo shop è disabilitato', 2),
    $impostazione('shop.announcement_banner', 'text', '',
        'Banner promozionale', 'Banner in cima allo shop (es. "Saldi estivi -20%!")', 3),
    $impostazione('shop.max_qty_per_product', 'number', '10',
        'Quantità max per prodotto', 'Quantità massima acquistabile per singolo prodotto per ordine', 5),
    $impostazione('shop.cart_expiry_days', 'number', '7',
        'Scadenza carrello (giorni)', 'Giorni dopo cui un carrello inattivo viene eliminato', 6),
    $impostazione('shop.free_shipping_threshold', 'number', '50',
        'Soglia spedizione gratuita (€)', 'Importo minimo per la spedizione gratuita (soglia globale)', 10),
    $impostazione('shop.default_item_weight_kg', 'number', '0.5',
        'Peso di ripiego per articolo (kg)', 'Peso usato per i prodotti che non ne hanno uno in scheda, per le fasce di peso della spedizione', 11),
    $impostazione('shop.active_payment_gateways', 'text', 'stripe,paypal,bank_transfer',
        'Gateway di Pagamento Attivi', 'Metodi di pagamento abilitati (stripe, paypal, bank_transfer)', 20),
    $impostazione('shop.bank_transfer_iban', 'text', '',
        'IBAN per bonifico', 'IBAN mostrato al cliente per il pagamento con bonifico', 23),
    $impostazione('shop.bank_transfer_beneficiary', 'text', '',
        'Intestatario conto', 'Nome dell\'intestatario del conto per il bonifico', 24),
    $impostazione('shop.bank_transfer_expiry_days', 'number', '7',
        'Scadenza bonifico (giorni)', 'Giorni per effettuare il bonifico prima dell\'annullamento automatico', 25),
    $impostazione('shop.receipt_footer_text', 'text', 'Savino Del Bene Volley - Ricevuta non fiscale. Lo scontrino è incluso nel pacco.',
        'Footer ricevuta PDF', 'Testo in fondo alla ricevuta PDF inviata al cliente', 31),

    // ─── Aste ─────────────────────────────────────────────────────────────
    $impostazione('auctions.enabled', 'boolean', '1',
        'Aste attive', 'Abilita o disabilita la sezione aste', 1),
    $impostazione('auctions.min_bid_increment', 'number', '5',
        'Incremento minimo offerta (€)', 'Importo minimo in più rispetto all\'offerta corrente', 2),
    $impostazione('auctions.max_bid_jump', 'number', '300',
        'Salto massimo offerta (€)', 'Importo massimo consentito sopra l\'offerta corrente', 3),
    $impostazione('auctions.payment_deadline_hours', 'number', '48',
        'Scadenza pagamento vincitore (ore)', 'Ore concesse al vincitore per completare il pagamento', 4),
    $impostazione('auctions.anti_snipe_minutes', 'number', '5',
        'Anti-sniping (minuti)', 'Minuti di estensione se arriva un\'offerta negli ultimi minuti', 5),
    $impostazione('auctions.rules_text', 'text', '',
        'Regolamento aste', 'Testo completo del regolamento aste (mostrato nella pagina dedicata)', 6),
];
