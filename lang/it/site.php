<?php

return [
    'default_description' => 'Savino Del Bene Volley - Sito ufficiale della squadra di pallavolo femminile di Scandicci. Serie A1, roster, calendario, risultati e shop.',
    'default_og_description' => 'Savino Del Bene Volley - Sito ufficiale della squadra di pallavolo femminile di Scandicci. Serie A1, roster, calendario, risultati e shop.',

    /*
     * Intestazione del feed RSS delle notizie (NewsFeedBuilder): la legge
     * chi si abbona, a partire dalla Lega, quindi è in lingua come tutto
     * il resto.
     */
    // Avversaria di una gara di Champions non ancora nota ("la vincente del
    // 3° turno"): Team::nomePubblico().
    'avversaria_da_definire' => 'Avversaria da definire',

    // Testi alternativi generati per le immagini (Player::testoAlternativoDellaFoto).
    'testi_alternativi' => [
        'atleta_del_vivaio' => 'Atleta del settore giovanile, Savino Del Bene Volley',
    ],

    'feed' => [
        'title' => 'Savino Del Bene Volley — News',
        'description' => 'Le notizie ufficiali della Savino Del Bene Volley: comunicati, risultati e vita del club.',
    ],

    /*
     * Anteprime social (ServeSocialCrawlerMeta) delle pagine che non hanno una
     * pagina del CMS da cui prendere titolo e descrizione. Stanno qui e non
     * nel middleware perché l'anteprima di una pagina inglese deve essere in
     * inglese: con le stringhe cablate nel codice `/en/season` annunciava
     * "Stagione — Savino Del Bene Volley".
     */
    'social' => [
        'home' => [
            'title' => 'Savino Del Bene Volley — Sito Ufficiale',
            'description' => 'Sito ufficiale della Savino Del Bene Volley. Scopri il roster, il calendario e i risultati della Serie A1 femminile di Scandicci.',
        ],
        'stagione' => [
            'title' => 'Stagione',
            'description' => 'Roster, staff tecnico e medico della stagione corrente della Savino Del Bene Volley.',
        ],
        // Una chiave per squadra: con una sola le tre pagine del vivaio
        // avevano titolo e descrizione identici.
        'vivaio-b1' => [
            'title' => 'Serie B1 e Under 19 — rosa e staff',
            'description' => 'La rosa e lo staff della squadra di Serie B1 e Under 19 del settore giovanile Savino Del Bene Volley: campionato nazionale di Serie B1 femminile e Under 19.',
        ],
        'vivaio-u17' => [
            'title' => 'Under 17 — rosa e staff',
            'description' => 'La rosa e lo staff della squadra Under 17 del settore giovanile Savino Del Bene Volley, al via nei campionati di Serie C e Under 17.',
        ],
        'vivaio-u15' => [
            'title' => 'Under 15 — rosa e staff',
            'description' => 'La rosa e lo staff della squadra Under 15 del settore giovanile Savino Del Bene Volley, al via nei campionati di Seconda Divisione e Under 15.',
        ],
        'risultati' => [
            'title' => 'Calendario e Risultati',
            'description' => 'Calendario, risultati e tabellini delle partite della Savino Del Bene Volley.',
        ],
        'classifica' => [
            'title' => 'Classifica',
            'description' => 'La classifica aggiornata del campionato di Serie A1 femminile.',
        ],
        'cev' => [
            'title' => 'CEV Champions League',
            'description' => 'Calendario e risultati della Savino Del Bene Volley in CEV Champions League.',
        ],
        'coppa-italia' => [
            'title' => 'Coppa Italia',
            'description' => 'Calendario e risultati della Savino Del Bene Volley in Coppa Italia.',
        ],
        'playoff' => [
            'title' => 'Playoff',
            'description' => 'Calendario e risultati dei playoff della Savino Del Bene Volley.',
        ],
        'foto-ufficiale' => [
            'title' => 'Foto Ufficiale',
            'description' => 'La foto ufficiale della squadra della Savino Del Bene Volley.',
        ],
        'gallery' => [
            'title' => 'Gallery',
            'description' => 'Galleria fotografica ufficiale della Savino Del Bene Volley.',
        ],
        'staff' => [
            'title' => 'Staff',
            'description' => 'Staff tecnico e medico della Savino Del Bene Volley.',
        ],
        'sponsor' => [
            'title' => 'Sponsor',
            'description' => 'I partner e gli sponsor ufficiali della Savino Del Bene Volley.',
        ],
        'shop' => [
            'title' => 'Shop Ufficiale',
            'description' => 'Acquista maglie, merchandise e accessori ufficiali della Savino Del Bene Volley.',
        ],
        'aste' => [
            'title' => 'Aste Benefiche',
            'description' => 'Le aste benefiche della Savino Del Bene Volley: maglie da gara e cimeli autografati.',
        ],
        'news' => [
            'title' => 'News',
            'description' => 'Tutte le ultime notizie dalla Savino Del Bene Volley.',
        ],
        'contatti' => [
            'title' => 'Contatti',
            'description' => 'Contatta la Savino Del Bene Volley. Informazioni, sede e recapiti.',
        ],
        'atleta' => [
            'title' => ':nome — :ruolo',
            'description' => ':nome, :ruolo della Savino Del Bene Volley in Serie A1: scheda, statistiche della stagione, carriera, palmarès, notizie e foto.',
            'description_senza_ruolo' => ':nome della Savino Del Bene Volley in Serie A1: scheda, statistiche della stagione, carriera, palmarès, notizie e foto.',
        ],
        'partita' => [
            'title' => ':casa - :ospite :risultato, :competizione :data',
            'description' => 'Partita :casa - :ospite del :data (:competizione): risultato, parziali dei set e tabellino con le statistiche delle atlete.',
        ],
        'prodotto' => [
            'description' => ':nome: articolo ufficiale della Savino Del Bene Volley nello shop online della squadra.',
        ],
        'shop-categoria' => [
            'description' => ':nome nello shop ufficiale della Savino Del Bene Volley: tutti gli articoli della categoria, con taglie e disponibilità.',
        ],
        'shop-cerca' => [
            'title' => 'Cerca nello shop',
            'description' => 'Cerca maglie, abbigliamento e accessori nello shop ufficiale della Savino Del Bene Volley.',
        ],
        'shop-guida-taglie' => [
            'title' => 'Guida alle taglie',
            'description' => 'Le misure di maglie e abbigliamento dello shop ufficiale della Savino Del Bene Volley, per scegliere la taglia giusta.',
        ],
        'shop-contatti' => [
            'title' => 'Contatti dello shop',
            'description' => 'Assistenza su ordini, spedizioni e resi dello shop ufficiale della Savino Del Bene Volley.',
        ],
        'asta' => [
            'description' => ':nome: asta benefica della Savino Del Bene Volley.',
        ],
        'carrello' => [
            'title' => 'Carrello',
            'description' => 'Il carrello dello shop ufficiale della Savino Del Bene Volley.',
        ],
        'checkout' => [
            'title' => 'Checkout',
            'description' => 'Dati di spedizione e pagamento dell\'ordine nello shop della Savino Del Bene Volley.',
        ],
        'checkout-conferma' => [
            'title' => 'Ordine ricevuto',
            'description' => 'Conferma dell\'ordine nello shop della Savino Del Bene Volley.',
        ],
        'checkout-annullato' => [
            'title' => 'Pagamento annullato',
            'description' => 'Il pagamento dell\'ordine nello shop della Savino Del Bene Volley non è stato completato.',
        ],
        'account' => [
            'title' => 'Il mio account',
            'description' => 'I dati del tuo account nello shop della Savino Del Bene Volley.',
        ],
        'ordini' => [
            'title' => 'I miei ordini',
            'description' => 'Gli ordini fatti nello shop della Savino Del Bene Volley.',
        ],
        'ordine' => [
            'title' => 'Dettaglio ordine',
            'description' => 'Stato e dettaglio di un ordine nello shop della Savino Del Bene Volley.',
        ],
        'registrati' => [
            'title' => 'Registrati',
            'description' => 'Crea un account nello shop della Savino Del Bene Volley.',
        ],
        'recesso' => [
            'title' => 'Recesso',
            'description' => 'Modulo di recesso online per gli acquisti nello shop della Savino Del Bene Volley.',
        ],
        'newsletter' => [
            'title' => 'Newsletter',
            'description' => 'Gestione dell\'iscrizione alla newsletter della Savino Del Bene Volley.',
        ],
        'in-costruzione' => [
            'title' => 'Pagina in costruzione',
            'description' => 'Questa sezione del sito della Savino Del Bene Volley è in preparazione.',
        ],
        'pagina' => [
            'description' => ':nome: informazioni ufficiali della Savino Del Bene Volley, squadra di pallavolo femminile di Serie A1.',
        ],
        'gallery-atleta' => [
            'title' => 'Le foto di :nome',
            'description' => 'Tutte le foto di :nome nell\'archivio fotografico della Savino Del Bene Volley.',
        ],
        'gallery-album' => [
            'title' => ':nome',
            'description' => 'Le foto di «:nome» nella gallery ufficiale della Savino Del Bene Volley.',
        ],
    ],

    /*
     * I ruoli delle atlete nei titoli delle schede (PlayerPosition).
     */
    'ruoli' => [
        'palleggiatrice' => 'palleggiatrice',
        'schiacciatrice' => 'schiacciatrice',
        'opposto' => 'opposto',
        'centrale' => 'centrale',
        'libero' => 'libero',
    ],

    /*
     * I nomi dei mesi per le date scritte per esteso (la tendina della gara nel
     * modulo accrediti). Prima li dava `Carbon::translatedFormat()`, che sotto
     * php-fpm faceva morire il processo di segmentation fault: vedi
     * `PageController::dataEstesa()`.
     */
    'months' => [
        1 => 'gennaio',
        2 => 'febbraio',
        3 => 'marzo',
        4 => 'aprile',
        5 => 'maggio',
        6 => 'giugno',
        7 => 'luglio',
        8 => 'agosto',
        9 => 'settembre',
        10 => 'ottobre',
        11 => 'novembre',
        12 => 'dicembre',
    ],
];
