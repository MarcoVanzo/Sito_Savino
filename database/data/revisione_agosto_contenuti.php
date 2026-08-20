<?php

/**
 * Contenuti recuperati dal sito precedente durante la revisione di agosto.
 *
 * Le pagine Talent Day, Campagna Abbonamenti e Progetto Affiliazioni esistevano
 * ma erano vuote: Talent Day mostrava per questo i contenuti del Summer Camp,
 * con cui condivideva il modello. Questi sono i testi veri, da cui la redazione
 * riparte per la stagione in corso.
 *
 * Il file è usato dalla migrazione che li inserisce e resta come traccia della
 * provenienza: non è un valore di ripiego letto a runtime dai componenti.
 */
return [
    'talent-day' => [
        'it' => [
            'hero_label' => 'Talent Scouting',
            'hero_subtitle' => 'Insegui il Tuo Sogno',
            'stages_title' => 'Le Tappe',
            'stages' => [
                ['date' => '19 maggio', 'place' => 'Palasport, via Rialdoli — Scandicci (FI)', 'status' => 'Esaurita', 'sold_out' => true],
                ['date' => '26 maggio', 'place' => 'Palasport, via Rialdoli — Scandicci (FI)', 'status' => 'Esaurita', 'sold_out' => true],
                ['date' => '28 maggio', 'place' => 'Roma (RM)', 'status' => 'Esaurita', 'sold_out' => true],
                ['date' => '3 giugno', 'place' => 'Venezia (VE)', 'status' => 'Esaurita', 'sold_out' => true],
                ['date' => '4 giugno', 'place' => 'Trieste (TS)', 'status' => 'Esaurita', 'sold_out' => true],
                ['date' => '5 giugno', 'place' => 'Udine (UD)', 'status' => 'Disponibile', 'sold_out' => false],
                ['date' => '10 giugno', 'place' => 'Torino (TO)', 'status' => 'Esaurita', 'sold_out' => true],
                ['date' => '11 giugno', 'place' => 'Lecce (LE)', 'status' => 'Esaurita', 'sold_out' => true],
                ['date' => '19 giugno', 'place' => 'Catania (CT)', 'status' => 'Esaurita', 'sold_out' => true],
                ['date' => '29 giugno', 'place' => 'Cagliari (CA)', 'status' => 'Disponibile', 'sold_out' => false],
            ],
            'slots_title' => 'Orari e Categorie',
            'slots' => [
                ['time' => '16:00 — 17:30', 'years' => 'Atlete nate dal 2012 al 2014'],
                ['time' => '17:30 — 19:00', 'years' => 'Atlete nate dal 2008 al 2011'],
            ],
            'signup_title' => 'Come Iscriversi',
            'signup_description' => "L'iscrizione si effettua compilando il modulo online. I posti di ogni tappa sono limitati.",
            'signup_url' => 'https://www.fusionteamvolley.it/ERP/talent-day/',
            'signup_cta' => 'Vai al modulo di iscrizione',
            'partners' => 'In collaborazione con Civitavecchia Volley, Fusion Team Volley, Azzurra Volley R.d.R Trieste, VolaValley, Volley Melendugno e Ciclope Volley Bronte.',
        ],
        'en' => [
            'hero_label' => 'Talent Scouting',
            'hero_subtitle' => 'Chase Your Dream',
            'stages_title' => 'The Stages',
            'stages' => [
                ['date' => '19 May', 'place' => 'Palasport, via Rialdoli — Scandicci (FI)', 'status' => 'Sold out', 'sold_out' => true],
                ['date' => '26 May', 'place' => 'Palasport, via Rialdoli — Scandicci (FI)', 'status' => 'Sold out', 'sold_out' => true],
                ['date' => '28 May', 'place' => 'Rome (RM)', 'status' => 'Sold out', 'sold_out' => true],
                ['date' => '3 June', 'place' => 'Venice (VE)', 'status' => 'Sold out', 'sold_out' => true],
                ['date' => '4 June', 'place' => 'Trieste (TS)', 'status' => 'Sold out', 'sold_out' => true],
                ['date' => '5 June', 'place' => 'Udine (UD)', 'status' => 'Available', 'sold_out' => false],
                ['date' => '10 June', 'place' => 'Turin (TO)', 'status' => 'Sold out', 'sold_out' => true],
                ['date' => '11 June', 'place' => 'Lecce (LE)', 'status' => 'Sold out', 'sold_out' => true],
                ['date' => '19 June', 'place' => 'Catania (CT)', 'status' => 'Sold out', 'sold_out' => true],
                ['date' => '29 June', 'place' => 'Cagliari (CA)', 'status' => 'Available', 'sold_out' => false],
            ],
            'slots_title' => 'Times and Age Groups',
            'slots' => [
                ['time' => '16:00 — 17:30', 'years' => 'Players born between 2012 and 2014'],
                ['time' => '17:30 — 19:00', 'years' => 'Players born between 2008 and 2011'],
            ],
            'signup_title' => 'How to Sign Up',
            'signup_description' => 'Registration is through the online form. Places at each stage are limited.',
            'signup_url' => 'https://www.fusionteamvolley.it/ERP/talent-day/',
            'signup_cta' => 'Go to the registration form',
            'partners' => 'In partnership with Civitavecchia Volley, Fusion Team Volley, Azzurra Volley R.d.R Trieste, VolaValley, Volley Melendugno and Ciclope Volley Bronte.',
        ],
    ],

    /**
     * Listino abbonamenti 2026/2027. Ogni settore ha tre tariffe: intero,
     * riconferma per chi era già abbonato, e under 16.
     */
    'abbonamenti' => [
        'it' => [
            'plans_heading' => 'Abbonamenti 2026/2027',
            'plans' => [
                ['name' => 'Tribuna Ovest', 'price' => '460', 'price_returning' => '390', 'price_under16' => '290', 'period' => 'stagione', 'highlight' => true, 'features' => []],
                ['name' => 'Tribuna Est e Sud', 'price' => '380', 'price_returning' => '330', 'price_under16' => '230', 'period' => 'stagione', 'highlight' => false, 'features' => []],
                ['name' => 'Tribuna Nord', 'price' => '310', 'price_returning' => '260', 'price_under16' => '190', 'period' => 'stagione', 'highlight' => false, 'features' => []],
                ['name' => 'Est Rialzata', 'price' => '230', 'price_returning' => '180', 'price_under16' => '130', 'period' => 'stagione', 'highlight' => false, 'features' => []],
            ],
        ],
        'en' => [
            'plans_heading' => 'Season Tickets 2026/2027',
            'plans' => [
                ['name' => 'West Stand', 'price' => '460', 'price_returning' => '390', 'price_under16' => '290', 'period' => 'season', 'highlight' => true, 'features' => []],
                ['name' => 'East and South Stand', 'price' => '380', 'price_returning' => '330', 'price_under16' => '230', 'period' => 'season', 'highlight' => false, 'features' => []],
                ['name' => 'North Stand', 'price' => '310', 'price_returning' => '260', 'price_under16' => '190', 'period' => 'season', 'highlight' => false, 'features' => []],
                ['name' => 'Raised East', 'price' => '230', 'price_returning' => '180', 'price_under16' => '130', 'period' => 'season', 'highlight' => false, 'features' => []],
            ],
        ],
    ],

    'affiliazioni' => [
        'it' => "<h2>Un progetto per crescere insieme</h2>\n<p>Il programma di affiliazione nasce nella stagione 2024/2025 con l'obiettivo di allargare la famiglia Savino Del Bene Volley a chi vuole sentirsi più vicino alla nostra realtà. Alle società affiliate offriamo promozioni su biglietteria, merchandising, Summer Camp e Talent Day, e percorsi di formazione online e in presenza per le figure tecniche, di comunicazione, marketing e organizzazione.</p>\n<h2>Perché affiliarsi</h2>\n<ul><li><strong>Ticketing</strong> — promozioni dedicate sui biglietti</li><li><strong>Merchandising</strong> — sconti sui prodotti ufficiali</li><li><strong>Eventi</strong> — accesso alle iniziative del club</li><li><strong>Summer Camp</strong> — condizioni riservate sui camp estivi</li></ul>\n<h2>Main Partner</h2>\n<p>Fusion Team Volley, Vola Valley.</p>\n<h2>Partner Ufficiali</h2>\n<p>Nottolini Volley, La Spezia, Lupi Santa Croce, Pallavolo Monsummano.</p>\n<h2>Come aderire</h2>\n<p>Per informazioni sul progetto: <strong>334 6085983</strong> — <a href=\"mailto:info@savinodelbenevolley.it\">info@savinodelbenevolley.it</a></p>",
        'en' => "<h2>A project to grow together</h2>\n<p>The affiliation programme began in the 2024/2025 season, with the aim of opening the Savino Del Bene Volley family to anyone who wants to be closer to the club. Affiliated clubs receive promotions on tickets, merchandise, Summer Camp and Talent Day, along with online and in-person training for their coaching, communications, marketing and organisational staff.</p>\n<h2>Why join</h2>\n<ul><li><strong>Ticketing</strong> — dedicated ticket offers</li><li><strong>Merchandise</strong> — discounts on official products</li><li><strong>Events</strong> — access to club initiatives</li><li><strong>Summer Camp</strong> — reserved rates on summer camps</li></ul>\n<h2>Main Partners</h2>\n<p>Fusion Team Volley, Vola Valley.</p>\n<h2>Official Partners</h2>\n<p>Nottolini Volley, La Spezia, Lupi Santa Croce, Pallavolo Monsummano.</p>\n<h2>How to join</h2>\n<p>For information about the programme: <strong>+39 334 6085983</strong> — <a href=\"mailto:info@savinodelbenevolley.it\">info@savinodelbenevolley.it</a></p>",
    ],
];
