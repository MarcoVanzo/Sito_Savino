<?php

/**
 * Dizionario italiano → inglese per il palmarès importato da it.wikipedia.
 *
 * Il palmarès si legge da Wikipedia in italiano perché è lì che i template
 * (`Pallavolopalm`, `MedaglieOro`) sono strutturati e completi; la versione
 * inglese della voce, quando esiste, ha una struttura diversa e appaiare le
 * due liste per anno e medaglia si rompe al primo torneo con due edizioni
 * nello stesso anno.
 *
 * Quindi si traduce qui, sui nomi ricorrenti. Le chiavi sono normalizzate
 * (minuscole, senza accenti, spazi singoli): la corrispondenza la fa
 * App\Services\Wikipedia\CompetitionTranslator. Quello che non è in elenco
 * resta in italiano anche in inglese, ed è corretto: meglio "Coppa Italia"
 * che una traduzione inventata. La redazione può sempre sistemare la riga.
 */
return [

    'competitions' => [
        // Nazionale — competizioni maggiori
        'giochi olimpici' => 'Olympic Games',
        'campionato mondiale' => 'World Championship',
        'campionato europeo' => 'European Championship',
        'campionato sudamericano' => 'South American Championship',
        'campionato nordamericano' => 'North American Championship',
        'campionato asiatico' => 'Asian Championship',
        'coppa del mondo' => 'World Cup',
        'world grand prix' => 'World Grand Prix',
        'grand champions cup' => 'Grand Champions Cup',
        'volleyball nations league' => 'Volleyball Nations League',
        'european league' => 'European League',
        'european golden league' => 'European Golden League',
        'universiade' => 'Summer Universiade',
        'giochi mondiali universitari' => 'World University Games',
        'giochi del mediterraneo' => 'Mediterranean Games',
        'giochi europei' => 'European Games',
        'wevza cup' => 'WEVZA Cup',
        'montreux volley masters' => 'Montreux Volley Masters',

        // Club — Italia
        'campionato italiano' => 'Italian Championship',
        'serie a1' => 'Serie A1',
        'coppa italia' => 'Italian Cup',
        'coppa italia di serie a2' => 'Italian Serie A2 Cup',
        'supercoppa italiana' => 'Italian Super Cup',

        // Club — Europa e mondo
        'champions league' => 'Champions League',
        'cev champions league' => 'CEV Champions League',
        'coppa cev' => 'CEV Cup',
        'challenge cup' => 'CEV Challenge Cup',
        'supercoppa europea' => 'European Super Cup',
        'mondiale per club' => 'FIVB Club World Championship',
        'campionato mondiale per club' => 'FIVB Club World Championship',

        // Club — altri campionati nazionali
        'campionato turco' => 'Turkish Championship',
        'campionato polacco' => 'Polish Championship',
        'campionato russo' => 'Russian Championship',
        'campionato rumeno' => 'Romanian Championship',
        'campionato serbo' => 'Serbian Championship',
        'campionato serbo-montenegrino' => 'Serbia and Montenegro Championship',
        'campionato greco' => 'Greek Championship',
        'campionato brasiliano' => 'Brazilian Championship',
        'campionato francese' => 'French Championship',
        'campionato tedesco' => 'German Championship',
        'campionato giapponese' => 'Japanese Championship',
        'campionato cinese' => 'Chinese Championship',
        'campionato statunitense' => 'US Championship',
        'campionato azero' => 'Azerbaijani Championship',
        'campionato paulista' => 'Paulista Championship',
        'superlega' => 'SuperLega',
        'sultanlar ligi' => 'Sultanlar Ligi',

        // Club — coppe nazionali
        'coppa di turchia' => 'Turkish Cup',
        'coppa di polonia' => 'Polish Cup',
        'coppa di russia' => 'Russian Cup',
        'coppa di grecia' => 'Greek Cup',
        'coppa di romania' => 'Romanian Cup',
        'coppa di germania' => 'German Cup',
        'coppa di francia' => 'French Cup',
        'coppa del brasile' => 'Brazilian Cup',
        'coppa di azerbaigian' => 'Azerbaijani Cup',
        'supercoppa turca' => 'Turkish Super Cup',
        'supercoppa polacca' => 'Polish Super Cup',
        'supercoppa russa' => 'Russian Super Cup',
        'supercoppa brasiliana' => 'Brazilian Super Cup',
    ],

    /**
     * Premi individuali: la formula ricorrente è "Miglior <ruolo/fondamentale>".
     */
    'awards' => [
        'mvp' => 'MVP',
        'miglior giocatrice' => 'Best Player',
        'miglior palleggiatrice' => 'Best Setter',
        'miglior schiacciatrice' => 'Best Outside Hitter',
        'miglior opposto' => 'Best Opposite',
        'miglior centrale' => 'Best Middle Blocker',
        'miglior libero' => 'Best Libero',
        'miglior ricezione' => 'Best Receiver',
        'miglior ricettrice' => 'Best Receiver',
        'miglior servizio' => 'Best Server',
        'miglior battuta' => 'Best Server',
        'miglior muro' => 'Best Blocker',
        'miglior attacco' => 'Best Spiker',
        'miglior realizzatrice' => 'Top Scorer',
        'miglior marcatrice' => 'Top Scorer',
        'miglior esordiente' => 'Best Newcomer',
        'miglior giovane' => 'Best Young Player',
    ],
];
