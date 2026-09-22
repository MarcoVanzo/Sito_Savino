<?php

return [
    'default_description' => 'Savino Del Bene Volley - Sito ufficiale della squadra di pallavolo femminile di Scandicci. Serie A1, roster, calendario, risultati e shop.',
    'default_og_description' => 'Savino Del Bene Volley - Sito ufficiale della squadra di pallavolo femminile di Scandicci. Serie A1, roster, calendario, risultati e shop.',

    /*
     * Intestazione del feed RSS delle notizie (NewsFeedBuilder): la legge
     * chi si abbona, a partire dalla Lega, quindi è in lingua come tutto
     * il resto.
     */
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
        'vivaio' => [
            'title' => 'Settore Giovanile',
            'description' => 'Le squadre del vivaio della Savino Del Bene Volley: rose, staff e campionati.',
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
            'title' => ':nome',
            'description' => ':nome nella rosa della Savino Del Bene Volley: numeri, foto e palmarès.',
        ],
        'gallery-atleta' => [
            'title' => 'Le foto di :nome',
            'description' => 'Tutte le foto di :nome nell\'archivio fotografico della Savino Del Bene Volley.',
        ],
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
