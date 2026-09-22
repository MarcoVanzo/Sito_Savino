<?php

return [
    'default_description' => 'Savino Del Bene Volley - Official website of the women\'s volleyball team from Scandicci. Serie A1, roster, schedule, results and shop.',
    'default_og_description' => 'Savino Del Bene Volley - Official website of the women\'s volleyball team from Scandicci. Serie A1, roster, schedule, results and shop.',

    /*
     * Heading of the news RSS feed (NewsFeedBuilder).
     */
    'feed' => [
        'title' => 'Savino Del Bene Volley — News',
        'description' => 'Official news from Savino Del Bene Volley: press releases, results and club life.',
    ],

    /*
     * Social previews (ServeSocialCrawlerMeta) for the pages that have no CMS
     * page to take title and description from. See the Italian file: these
     * strings live here so that an English URL gets an English preview.
     */
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
            'title' => ':nome',
            'description' => ':nome in the Savino Del Bene Volley squad: stats, photos and honours.',
        ],
        'gallery-atleta' => [
            'title' => 'Photos of :nome',
            'description' => 'All photos of :nome in the Savino Del Bene Volley photo archive.',
        ],
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
