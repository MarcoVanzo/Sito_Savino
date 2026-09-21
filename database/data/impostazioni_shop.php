<?php

/**
 * Le impostazioni dello Shop e delle Aste con i loro valori di partenza.
 *
 * Sono dati, non logica, e servono a due padroni: il seeder che crea le righe
 * (ShopSettingsSeeder) e la pagina "Impostazioni Shop & Aste", che li usa come
 * valore di ripiego per le chiavi non ancora in archivio. Senza quel ripiego
 * un interruttore che in tabella non c'è si apre spento, e il primo
 * salvataggio lo spegne davvero: è così che lo shop è finito in manutenzione
 * mentre in redazione si accendevano le aste.
 *
 * `type` conta: `boolean` è ciò che rende un valore un interruttore, sia in
 * lettura (SiteSetting::getAllGrouped) sia nel modulo.
 */
return [
    // ─── Negozio ──────────────────────────────────────────────────────────
    [
        'key' => 'shop.enabled',
        'value' => '1',
        'type' => 'boolean',
        'group' => 'shop',
        'label' => 'Shop attivo',
        'description' => 'Abilita o disabilita completamente lo shop',
        'sort_order' => 1,
    ],
    [
        'key' => 'shop.maintenance_message',
        'value' => '',
        'type' => 'text',
        'group' => 'shop',
        'label' => 'Messaggio manutenzione',
        'description' => 'Banner mostrato quando lo shop è disabilitato',
        'sort_order' => 2,
    ],
    [
        'key' => 'shop.announcement_banner',
        'value' => '',
        'type' => 'text',
        'group' => 'shop',
        'label' => 'Banner promozionale',
        'description' => 'Banner in cima allo shop (es. "Saldi estivi -20%!")',
        'sort_order' => 3,
    ],
    [
        'key' => 'shop.max_qty_per_product',
        'value' => '10',
        'type' => 'number',
        'group' => 'shop',
        'label' => 'Quantità max per prodotto',
        'description' => 'Quantità massima acquistabile per singolo prodotto per ordine',
        'sort_order' => 5,
    ],
    [
        'key' => 'shop.cart_expiry_days',
        'value' => '7',
        'type' => 'number',
        'group' => 'shop',
        'label' => 'Scadenza carrello (giorni)',
        'description' => 'Giorni dopo cui un carrello inattivo viene eliminato',
        'sort_order' => 6,
    ],
    [
        'key' => 'shop.free_shipping_threshold',
        'value' => '50',
        'type' => 'number',
        'group' => 'shop',
        'label' => 'Soglia spedizione gratuita (€)',
        'description' => 'Importo minimo per la spedizione gratuita (soglia globale)',
        'sort_order' => 10,
    ],
    [
        'key' => 'shop.default_item_weight_kg',
        'value' => '0.5',
        'type' => 'number',
        'group' => 'shop',
        'label' => 'Peso di ripiego per articolo (kg)',
        'description' => 'Peso usato per i prodotti che non ne hanno uno in scheda, per le fasce di peso della spedizione',
        'sort_order' => 11,
    ],
    [
        'key' => 'shop.active_payment_gateways',
        'value' => 'stripe,paypal,bank_transfer',
        'type' => 'text',
        'group' => 'shop',
        'label' => 'Gateway di Pagamento Attivi',
        'description' => 'Metodi di pagamento abilitati (stripe, paypal, bank_transfer)',
        'sort_order' => 20,
    ],
    [
        'key' => 'shop.bank_transfer_iban',
        'value' => '',
        'type' => 'text',
        'group' => 'shop',
        'label' => 'IBAN per bonifico',
        'description' => 'IBAN mostrato al cliente per il pagamento con bonifico',
        'sort_order' => 23,
    ],
    [
        'key' => 'shop.bank_transfer_beneficiary',
        'value' => '',
        'type' => 'text',
        'group' => 'shop',
        'label' => 'Intestatario conto',
        'description' => 'Nome dell\'intestatario del conto per il bonifico',
        'sort_order' => 24,
    ],
    [
        'key' => 'shop.bank_transfer_expiry_days',
        'value' => '7',
        'type' => 'number',
        'group' => 'shop',
        'label' => 'Scadenza bonifico (giorni)',
        'description' => 'Giorni per effettuare il bonifico prima dell\'annullamento automatico',
        'sort_order' => 25,
    ],
    [
        'key' => 'shop.receipt_footer_text',
        'value' => 'Savino Del Bene Volley - Ricevuta non fiscale. Lo scontrino è incluso nel pacco.',
        'type' => 'text',
        'group' => 'shop',
        'label' => 'Footer ricevuta PDF',
        'description' => 'Testo in fondo alla ricevuta PDF inviata al cliente',
        'sort_order' => 31,
    ],

    // ─── Aste ─────────────────────────────────────────────────────────────
    [
        'key' => 'auctions.enabled',
        'value' => '1',
        'type' => 'boolean',
        'group' => 'auctions',
        'label' => 'Aste attive',
        'description' => 'Abilita o disabilita la sezione aste',
        'sort_order' => 1,
    ],
    [
        'key' => 'auctions.min_bid_increment',
        'value' => '5',
        'type' => 'number',
        'group' => 'auctions',
        'label' => 'Incremento minimo offerta (€)',
        'description' => 'Importo minimo in più rispetto all\'offerta corrente',
        'sort_order' => 2,
    ],
    [
        'key' => 'auctions.max_bid_jump',
        'value' => '300',
        'type' => 'number',
        'group' => 'auctions',
        'label' => 'Salto massimo offerta (€)',
        'description' => 'Importo massimo consentito sopra l\'offerta corrente',
        'sort_order' => 3,
    ],
    [
        'key' => 'auctions.payment_deadline_hours',
        'value' => '48',
        'type' => 'number',
        'group' => 'auctions',
        'label' => 'Scadenza pagamento vincitore (ore)',
        'description' => 'Ore concesse al vincitore per completare il pagamento',
        'sort_order' => 4,
    ],
    [
        'key' => 'auctions.anti_snipe_minutes',
        'value' => '5',
        'type' => 'number',
        'group' => 'auctions',
        'label' => 'Anti-sniping (minuti)',
        'description' => 'Minuti di estensione se arriva un\'offerta negli ultimi minuti',
        'sort_order' => 5,
    ],
    [
        'key' => 'auctions.rules_text',
        'value' => '',
        'type' => 'text',
        'group' => 'auctions',
        'label' => 'Regolamento aste',
        'description' => 'Testo completo del regolamento aste (mostrato nella pagina dedicata)',
        'sort_order' => 6,
    ],
];
