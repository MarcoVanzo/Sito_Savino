<?php

/**
 * Le voci del menu del pannello che portano a pagine non ancora pronte.
 *
 * Sono un elenco, non logica: stavano dentro AdminPanelProvider come due array
 * lunghi una settantina di righe, e il rilevatore di cloni li segnalava come
 * codice duplicato perche' la forma di ogni riga e' identica — pur essendo
 * tutte diverse fra loro. Qui stanno accanto agli altri contenuti iniziali
 * (`database/data/`), dove la redazione e chi mantiene il pannello li cercano.
 *
 * `slug` e' la pagina CMS a cui la voce punta quando esiste: se non c'e', la
 * voce porta alla pagina "in costruzione".
 * `gruppo` e `ordine` sono la collocazione nel menu del pannello.
 */
return [
    ['etichetta' => 'CEV Champions League', 'slug' => null, 'gruppo' => 'Stagione', 'ordine' => 4],
    ['etichetta' => 'Coppa Italia & Playoff', 'slug' => null, 'gruppo' => 'Stagione', 'ordine' => 5],

    ['etichetta' => 'Organigramma', 'slug' => 'organigramma', 'gruppo' => 'Società', 'ordine' => 1],
    ['etichetta' => 'Storia', 'slug' => 'storia', 'gruppo' => 'Società', 'ordine' => 2],
    ['etichetta' => 'Safeguarding', 'slug' => 'safeguarding', 'gruppo' => 'Società', 'ordine' => 3],
    ['etichetta' => 'Contatti', 'slug' => 'contatti', 'gruppo' => 'Società', 'ordine' => 4],
    ['etichetta' => 'Palazzetto & Google Maps', 'slug' => 'palazzetto', 'gruppo' => 'Società', 'ordine' => 6],

    ['etichetta' => 'Biglietteria', 'slug' => 'biglietteria', 'gruppo' => 'Ticketing', 'ordine' => 1],
    ['etichetta' => 'Campagna Abbonamenti', 'slug' => 'abbonamenti', 'gruppo' => 'Ticketing', 'ordine' => 2],
    ['etichetta' => 'Accessibilità & Info', 'slug' => 'accessibilita', 'gruppo' => 'Ticketing', 'ordine' => 3],
    ['etichetta' => 'Convenzioni', 'slug' => 'convenzioni', 'gruppo' => 'Ticketing', 'ordine' => 4],

    ['etichetta' => 'Diventa Sponsor', 'slug' => 'diventa-sponsor', 'gruppo' => 'Sponsor', 'ordine' => 2],
    ['etichetta' => 'Title Sponsor (SDB)', 'slug' => 'title-sponsor', 'gruppo' => 'Sponsor', 'ordine' => 3],
    ['etichetta' => 'Hospitality', 'slug' => 'hospitality', 'gruppo' => 'Sponsor', 'ordine' => 4],

    ['etichetta' => 'Settore Giovanile', 'slug' => 'settore-giovanile', 'gruppo' => 'SDB Youth', 'ordine' => 3],
    ['etichetta' => 'Talent Day & Recruiting', 'slug' => 'talent-day', 'gruppo' => 'SDB Youth', 'ordine' => 4],
    ['etichetta' => 'Progetto Affiliazioni', 'slug' => 'affiliazioni', 'gruppo' => 'SDB Youth', 'ordine' => 5],

    ['etichetta' => 'Tutte le Info', 'slug' => 'summer-camp', 'gruppo' => 'Summer Camp', 'ordine' => 1],
    ['etichetta' => 'Iscrizione (Experience)', 'slug' => 'iscrizione-experience', 'gruppo' => 'Summer Camp', 'ordine' => 2],

    ['etichetta' => 'Progetti Sociali', 'slug' => 'progetti-sociali', 'gruppo' => 'Sociale', 'ordine' => 1],
    ['etichetta' => 'Volley 4 All', 'slug' => 'volley-4-all', 'gruppo' => 'Sociale', 'ordine' => 2],
    ['etichetta' => 'Bilancio Sostenibilità', 'slug' => 'sostenibilita', 'gruppo' => 'Sociale', 'ordine' => 3],
    ['etichetta' => 'Progetto Scuola', 'slug' => 'progetto-scuola', 'gruppo' => 'Sociale', 'ordine' => 4],

    ['etichetta' => 'Accrediti Stampa', 'slug' => 'accrediti-stampa', 'gruppo' => 'Comunicazione', 'ordine' => 1],
    ['etichetta' => 'Cartelle Stampa', 'slug' => 'cartelle-stampa', 'gruppo' => 'Comunicazione', 'ordine' => 2],
    ['etichetta' => 'Magazine', 'slug' => 'magazine', 'gruppo' => 'Comunicazione', 'ordine' => 3],
    ['etichetta' => 'Double Face', 'slug' => 'double-face', 'gruppo' => 'Comunicazione', 'ordine' => 4],
];
