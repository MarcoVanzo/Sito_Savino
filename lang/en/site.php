<?php

return [
    'default_description' => 'Savino Del Bene Volley - Official website of the women\'s volleyball team from Scandicci. Serie A1, roster, schedule, results and shop.',
    'default_og_description' => 'Savino Del Bene Volley - Official website of the women\'s volleyball team from Scandicci. Serie A1, roster, schedule, results and shop.',

    /*
     * Heading of the news RSS feed (NewsFeedBuilder).
     */
    // Avversaria di una gara di Champions non ancora nota ("la vincente del
    // 3° turno"): Team::nomePubblico().
    'avversaria_da_definire' => 'Opponent to be confirmed',

    // Testi alternativi generati per le immagini (Player::testoAlternativoDellaFoto).
    'testi_alternativi' => [
        'atleta_del_vivaio' => 'Youth team player, Savino Del Bene Volley',
    ],

    'feed' => [
        'title' => 'Savino Del Bene Volley — News',
        'description' => 'Official news from Savino Del Bene Volley: press releases, results and club life.',
    ],

    /*
     * Social previews (ServeSocialCrawlerMeta) for the pages that have no CMS
     * page to take title and description from. See the Italian file: these
     * strings live here so that an English URL gets an English preview.
     */
    // Il titolo dell'HTML servito con un 404 (bootstrap/app.php).
    'pagina_non_trovata' => 'Page not found | Savino Del Bene Volley',

    'social' => [
        'home' => [
            'title' => 'Savino Del Bene Volley — Official Website',
            'description' => 'Official website of Savino Del Bene Volley. Squad, schedule and results of the Serie A1 women\'s team from Scandicci.',
        ],
        'stagione' => [
            'title' => 'Season',
            'description' => 'Squad, coaching and medical staff for the current Savino Del Bene Volley season.',
        ],
        'vivaio' => [
            'title' => 'Youth Sector',
            'description' => 'The Savino Del Bene Volley youth teams: squads, staff and competitions.',
        ],
        'risultati' => [
            'title' => 'Schedule and Results',
            'description' => 'Schedule, results and box scores of the Savino Del Bene Volley matches.',
        ],
        'classifica' => [
            'title' => 'Standings',
            'description' => 'The current standings of the Serie A1 women\'s championship.',
        ],
        'cev' => [
            'title' => 'CEV Champions League',
            'description' => 'Savino Del Bene Volley schedule and results in the CEV Champions League.',
        ],
        'coppa-italia' => [
            'title' => 'Italian Cup',
            'description' => 'Savino Del Bene Volley schedule and results in the Italian Cup.',
        ],
        'playoff' => [
            'title' => 'Playoffs',
            'description' => 'Savino Del Bene Volley playoff schedule and results.',
        ],
        'foto-ufficiale' => [
            'title' => 'Official Team Photo',
            'description' => 'The official team photo of Savino Del Bene Volley.',
        ],
        'gallery' => [
            'title' => 'Gallery',
            'description' => 'The official photo gallery of Savino Del Bene Volley.',
        ],
        'diretta' => [
            'title' => 'Live stream',
            'description' => 'Watch Savino Del Bene Volley matches live.',
        ],
        'staff' => [
            'title' => 'Staff',
            'description' => 'Coaching and medical staff of Savino Del Bene Volley.',
        ],
        'sponsor' => [
            'title' => 'Sponsors',
            'description' => 'The official partners and sponsors of Savino Del Bene Volley.',
        ],
        'shop' => [
            'title' => 'Official Shop',
            'description' => 'Buy official Savino Del Bene Volley jerseys, merchandise and accessories.',
        ],
        'aste' => [
            'title' => 'Charity Auctions',
            'description' => 'Savino Del Bene Volley charity auctions: match-worn jerseys and signed memorabilia.',
        ],
        'news' => [
            'title' => 'News',
            'description' => 'All the latest news from Savino Del Bene Volley.',
        ],
        'contatti' => [
            'title' => 'Contacts',
            'description' => 'Get in touch with Savino Del Bene Volley. Information, offices and contact details.',
        ],
        'atleta' => [
            'title' => ':nome — :ruolo',
            'description' => ':nome, :ruolo at Savino Del Bene Volley in Serie A1: profile, season stats, career, honours, news and photos.',
            'description_senza_ruolo' => ':nome of Savino Del Bene Volley in Serie A1: profile, season stats, career, honours, news and photos.',
        ],
        'gallery-atleta' => [
            'title' => 'Photos of :nome',
            'description' => 'All photos of :nome in the Savino Del Bene Volley photo archive.',
        ],
        'gallery-album' => [
            'title' => ':nome',
            'description' => 'Photos of “:nome” in the official Savino Del Bene Volley gallery.',
        ],
        // Added by the SEO audit of 14/10/2026: one key per page, so no two
        // pages share a title. Existing entries above are unchanged.
        'vivaio-b1' => [
            'title' => 'Serie B1 and Under 19 — squad and staff',
            'description' => 'Squad and staff of the Savino Del Bene Volley Serie B1 and Under 19 youth team.',
        ],
        'vivaio-u17' => [
            'title' => 'Under 17 — squad and staff',
            'description' => 'Squad and staff of the Savino Del Bene Volley Under 17 youth team, playing Serie C and Under 17.',
        ],
        'vivaio-u15' => [
            'title' => 'Under 15 — squad and staff',
            'description' => 'Squad and staff of the Savino Del Bene Volley Under 15 youth team, playing Seconda Divisione and Under 15.',
        ],
        'partita' => [
            'title' => ':casa - :ospite :risultato, :competizione :data',
            'description' => ':casa - :ospite on :data (:competizione): result, set scores and box score.',
        ],
        'prodotto' => [
            'description' => ':nome: official Savino Del Bene Volley merchandise from the team online shop.',
        ],
        'shop-categoria' => [
            'description' => ':nome in the official Savino Del Bene Volley shop: every item in the category, with sizes and availability.',
        ],
        'shop-cerca' => ['title' => 'Search the shop', 'description' => 'Search jerseys, apparel and accessories in the official Savino Del Bene Volley shop.'],
        'shop-guida-taglie' => ['title' => 'Size guide', 'description' => 'Measurements of jerseys and apparel in the official Savino Del Bene Volley shop.'],
        'shop-contatti' => ['title' => 'Shop contacts', 'description' => 'Help with orders, shipping and returns in the official Savino Del Bene Volley shop.'],
        'asta' => ['description' => ':nome: Savino Del Bene Volley charity auction.'],
        'carrello' => ['title' => 'Cart', 'description' => 'Your cart in the official Savino Del Bene Volley shop.'],
        'checkout' => ['title' => 'Checkout', 'description' => 'Shipping and payment details for your Savino Del Bene Volley shop order.'],
        'checkout-conferma' => ['title' => 'Order received', 'description' => 'Confirmation of your Savino Del Bene Volley shop order.'],
        'checkout-annullato' => ['title' => 'Payment cancelled', 'description' => 'The payment for your Savino Del Bene Volley shop order was not completed.'],
        'account' => ['title' => 'My account', 'description' => 'Your Savino Del Bene Volley shop account.'],
        'ordini' => ['title' => 'My orders', 'description' => 'Your Savino Del Bene Volley shop orders.'],
        'ordine' => ['title' => 'Order details', 'description' => 'Status and details of a Savino Del Bene Volley shop order.'],
        'registrati' => ['title' => 'Sign up', 'description' => 'Create a Savino Del Bene Volley shop account.'],
        'recesso' => ['title' => 'Withdrawal', 'description' => 'Online withdrawal form for Savino Del Bene Volley shop purchases.'],
        'newsletter' => ['title' => 'Newsletter', 'description' => 'Manage your Savino Del Bene Volley newsletter subscription.'],
        'in-costruzione' => ['title' => 'Page under construction', 'description' => 'This section of the Savino Del Bene Volley website is being prepared.'],
        'pagina' => ['description' => ':nome: official information from Savino Del Bene Volley, Serie A1 women\'s volleyball team.'],
    ],

    'ruoli' => [
        'palleggiatrice' => 'setter',
        'schiacciatrice' => 'outside hitter',
        'opposto' => 'opposite',
        'centrale' => 'middle blocker',
        'libero' => 'libero',
    ],

    /*
     * Month names for dates written out in full (the match dropdown in the
     * press accreditation form). See `lang/it/site.php` for why they are here
     * and not in `Carbon::translatedFormat()`.
     */
    'months' => [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ],
];
