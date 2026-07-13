<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

class CmsPagesSeeder extends Seeder
{
    /**
     * Le 22 pagine CMS del progetto con slug, template e contenuto base.
     * Esegui con: php artisan db:seed --class=CmsPagesSeeder
     */
    public function run(): void
    {
        $pages = $this->buildPages();

        foreach ($pages as $pageData) {
            Page::updateOrCreate(
                ['slug' => $pageData['slug']],
                array_merge($pageData, [
                    'status' => PostStatus::Published->value,
                    'excerpt' => $pageData['meta_description'],
                ])
            );
        }

        $this->command->info('✅ '.count($pages).' pagine CMS create/aggiornate con successo.');
    }

    /**
     * Genera una entry CMS in formato compatto.
     */
    private function page(string $slug, string $title, string $template, string $meta, string $content = '', array $contentData = []): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'template' => $template,
            'meta_description' => $meta,
            'content' => $content,
            'content_data' => $contentData,
        ];
    }

    /**
     * Definisce le 22 pagine CMS raggruppate per area tematica.
     *
     * @return array<array<string, mixed>>
     */
    private function buildPages(): array
    {
        return [
            // === SOCIETÀ ===
            $this->page('organigramma', 'Organigramma', 'Public/Societa/Organigramma',
                'L\'organigramma ufficiale della Savino Del Bene Volley. Scopri il team dirigenziale e lo staff del club.',
                '<p>L\'organigramma della Savino Del Bene Volley. Questa pagina è gestita dal CMS.</p>'),
            $this->page('storia', 'Storia del Club', 'Public/Societa/Storia',
                'La storia della Savino Del Bene Volley: dal 1982 ad oggi, un percorso di crescita e successi nella pallavolo femminile italiana.',
                '<h2>Le Origini</h2><p>Fondata nel 1982 a Scandicci, la Savino Del Bene Volley è diventata una delle realtà più importanti della pallavolo femminile italiana.</p><h2>La Crescita</h2><p>Con la partnership strategica del Gruppo Savino Del Bene, il club ha raggiunto traguardi storici: la Finale Scudetto, la partecipazione alla CEV Champions League.</p><h2>Il Presente</h2><p>Oggi la Savino Del Bene Volley rappresenta un modello di gestione sportiva, con un settore giovanile d\'eccellenza e una visiose proiettata verso il futuro.</p>',
                [
                    'it' => [
                        'timeline' => [
                            [
                                'year' => '1982',
                                'title' => 'Le Origini',
                                'description' => 'La Pallavolo Scandicci nasce nel cuore del territorio toscano. I primi passi nei campionati locali pongono le basi per un progetto ambizioso.',
                            ],
                            [
                                'year' => '2012',
                                'title' => 'La Svolta Savino Del Bene',
                                'description' => 'L\'ingresso del Gruppo Savino Del Bene come title sponsor trasforma le ambizioni del club. Inizia la scalata verso l\'élite del volley nazionale.',
                            ],
                            [
                                'year' => '2014',
                                'title' => 'Promozione in Serie A1',
                                'description' => 'Un traguardo storico: la squadra conquista l\'accesso alla massima serie italiana, portando Scandicci nel panorama nazionale.',
                            ],
                            [
                                'year' => '2018',
                                'title' => 'Debutto Europeo',
                                'description' => 'Prima qualificazione in CEV Champions League. La Savino Del Bene Volley si affaccia sul palcoscenico internazionale sfidando le big europee.',
                            ],
                            [
                                'year' => '2022',
                                'title' => 'CEV Challenge Cup',
                                'description' => 'Il primo storico trofeo europeo. Una cavalcata trionfale che arricchisce la bacheca del club e consacra il progetto a livello internazionale.',
                            ],
                            [
                                'year' => '2023',
                                'title' => 'CEV Cup',
                                'description' => 'Ancora un successo continentale. La Savino Del Bene solleva la CEV Cup, dimostrando continuità di risultati e mentalità vincente.',
                            ],
                            [
                                'year' => '2024',
                                'title' => 'Finale Scudetto',
                                'description' => 'Per la prima volta nella storia, il club raggiunge la Finale Scudetto, lottando punto a punto per il tricolore e riempiendo il Palazzo Wanny.',
                            ],
                        ],
                    ],
                    'en' => [
                        'timeline' => [
                            [
                                'year' => '1982',
                                'title' => 'The Origins',
                                'description' => 'Pallavolo Scandicci was born in the heart of the Tuscan region. The first steps in the local leagues laid the foundations for an ambitious project.',
                            ],
                            [
                                'year' => '2012',
                                'title' => 'The Savino Del Bene Turning Point',
                                'description' => 'The entry of the Savino Del Bene Group as title sponsor transformed the club\'s ambitions. The ascent to the elite of Italian national volleyball began.',
                            ],
                            [
                                'year' => '2014',
                                'title' => 'Promotion to Serie A1',
                                'description' => 'A historic achievement: the team won promotion to the top Italian division, bringing Scandicci into the national spotlight.',
                            ],
                            [
                                'year' => '2018',
                                'title' => 'European Debut',
                                'description' => 'First qualification for the CEV Champions League. Savino Del Bene Volley entered the international stage, challenging Europe\'s giants.',
                            ],
                            [
                                'year' => '2022',
                                'title' => 'CEV Challenge Cup',
                                'description' => 'The first historic European trophy. A triumphant journey that enriched the club\'s cabinet and established the project internationally.',
                            ],
                            [
                                'year' => '2023',
                                'title' => 'CEV Cup',
                                'description' => 'Another continental success. Savino Del Bene lifted the CEV Cup, demonstrating consistency and a winning mentality.',
                            ],
                            [
                                'year' => '2024',
                                'title' => 'Scudetto Finals',
                                'description' => 'For the first time in history, the club reached the Scudetto Finals, fighting point-by-point for the championship and filling Palazzo Wanny.',
                            ],
                        ],
                    ],
                ]),
            $this->page('safeguarding', 'Safeguarding', 'Public/Societa/Safeguarding',
                'Policy di Safeguarding della Savino Del Bene Volley. Tutela e protezione dei minori e prevenzione delle molestie.',
                '<h2>Policy di Safeguarding</h2><p>La Savino Del Bene Volley si impegna a garantire un ambiente sicuro e protetto per tutti i tesserati, in particolare per i minori, adottando misure di prevenzione contro ogni forma di abuso, molestia e discriminazione.</p>',
                [
                    'it' => [
                        'report_title' => 'Segnalazioni',
                        'report_description' => 'Per segnalare comportamenti non conformi al Codice di Condotta o presunti abusi, è possibile contattare il Responsabile Safeguarding in totale riservatezza.',
                        'report_email' => 'safeguarding@savinodelbenevolley.it',
                        'documents' => [
                            [
                                'title' => 'Modello Organizzativo e di Controllo',
                                'description' => 'Documento che stabilisce i principi di comportamento e le misure di prevenzione.',
                                'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                'file' => '#',
                            ],
                            [
                                'title' => 'Codice di Condotta a Tutela dei Minori',
                                'description' => 'Linee guida specifiche per garantire un ambiente sportivo sicuro per i più giovani.',
                                'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                                'file' => '#',
                            ],
                        ],
                    ],
                    'en' => [
                        'report_title' => 'Reports',
                        'report_description' => 'To report behaviors not in compliance with the Code of Conduct or alleged abuses, you can contact the Safeguarding Officer in total confidentiality.',
                        'report_email' => 'safeguarding@savinodelbenevolley.it',
                        'documents' => [
                            [
                                'title' => 'Organizational and Control Model',
                                'description' => 'Document establishing conduct principles and preventive measures.',
                                'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                'file' => '#',
                            ],
                            [
                                'title' => 'Code of Conduct for Minor Protection',
                                'description' => 'Specific guidelines to ensure a safe sports environment for younger members.',
                                'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                                'file' => '#',
                            ],
                        ],
                    ],
                ]),
            $this->page('contatti', 'Contatti', 'Public/Contatti',
                'Contatta la Savino Del Bene Volley. Trova i nostri recapiti, l\'indirizzo della sede e il form di contatto.',
                '',
                [
                    'it' => [
                        'hero_subtitle' => 'Contatti e Sede',
                        'hero_description' => 'Uffici, direzioni e contatti ufficiali della Savino Del Bene Volley.',
                        'form_title' => 'Invia un Messaggio',
                        'form_success_message' => 'Il tuo messaggio è stato inviato correttamente! Ti risponderemo al più presto.',
                        'map_title' => 'Sede Legale',
                        'map_address' => 'Via Benozzo Gozzoli, 5/6, Scandicci FI',
                        'address' => 'Via Benozzo Gozzoli, 5/6 50018 Scandicci – Firenze',
                        'phone' => '055 721503',
                        'email' => 'info@savinodelbenevolley.it',
                        'pec' => 'pallavoloscandicci@legalmail.it',
                        'legal_sdi' => 'KRRH6B9',
                        'legal_piva' => '06271460484',
                        'legal_cf' => '94217750481',
                        'legal_fipav' => '100470331',
                        'contacts_list' => [
                            [
                                'category' => 'SERIE A1',
                                'role' => 'DIRETTORE GENERALE',
                                'name' => 'Francesco Paoletti',
                                'email' => 'francesco.paoletti@savinodelbenevolley.it',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SERIE A1',
                                'role' => 'UFFICIO STAMPA',
                                'name' => '',
                                'email' => 'press@savinodelbenevolley.it',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SERIE A1',
                                'role' => 'RESPONSABILE MARKETING',
                                'name' => '',
                                'email' => 'marketing@savinodelbenevolley.it',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SERIE A1',
                                'role' => 'SAFEGUARDING OFFICE',
                                'name' => '',
                                'email' => 'safeguarding@savinodelbenevolley.it',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SERIE A1',
                                'role' => 'SETTORE GIOVANILE',
                                'name' => '',
                                'email' => 'settoregiovanile@savinodelbenevolley.it',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SDB VOLLEY YOUTH',
                                'role' => 'RESPONSABILE SDB VOLLEY YOUTH',
                                'name' => 'Massimo Toccafondi',
                                'email' => '',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SDB VOLLEY YOUTH',
                                'role' => 'REFERENTE SQUADRE SDB VOLLEY YOUTH',
                                'name' => 'Lucrezia Grandini',
                                'email' => '',
                                'phone' => '333 88 24 951',
                            ],
                        ],
                    ],
                    'en' => [
                        'hero_subtitle' => 'Contacts & Head Office',
                        'hero_description' => 'Offices, management, and official contacts of Savino Del Bene Volley.',
                        'form_title' => 'Send a Message',
                        'form_success_message' => 'Your message has been sent successfully! We will get back to you soon.',
                        'map_title' => 'Registered Office',
                        'map_address' => 'Via Benozzo Gozzoli, 5/6, Scandicci FI',
                        'address' => 'Via Benozzo Gozzoli, 5/6 50018 Scandicci – Florence',
                        'phone' => '055 721503',
                        'email' => 'info@savinodelbenevolley.it',
                        'pec' => 'pallavoloscandicci@legalmail.it',
                        'legal_sdi' => 'KRRH6B9',
                        'legal_piva' => '06271460484',
                        'legal_cf' => '94217750481',
                        'legal_fipav' => '100470331',
                        'contacts_list' => [
                            [
                                'category' => 'SERIE A1',
                                'role' => 'GENERAL MANAGER',
                                'name' => 'Francesco Paoletti',
                                'email' => 'francesco.paoletti@savinodelbenevolley.it',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SERIE A1',
                                'role' => 'PRESS OFFICE',
                                'name' => '',
                                'email' => 'press@savinodelbenevolley.it',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SERIE A1',
                                'role' => 'MARKETING MANAGER',
                                'name' => '',
                                'email' => 'marketing@savinodelbenevolley.it',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SERIE A1',
                                'role' => 'SAFEGUARDING OFFICE',
                                'name' => '',
                                'email' => 'safeguarding@savinodelbenevolley.it',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SERIE A1',
                                'role' => 'YOUTH SECTOR',
                                'name' => '',
                                'email' => 'settoregiovanile@savinodelbenevolley.it',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SDB VOLLEY YOUTH',
                                'role' => 'HEAD OF SDB VOLLEY YOUTH',
                                'name' => 'Massimo Toccafondi',
                                'email' => '',
                                'phone' => '',
                            ],
                            [
                                'category' => 'SDB VOLLEY YOUTH',
                                'role' => 'SDB VOLLEY YOUTH TEAM CONTACT',
                                'name' => 'Lucrezia Grandini',
                                'email' => '',
                                'phone' => '333 88 24 951',
                            ],
                        ],
                    ],
                ]),
            $this->page('palazzetto', 'Il Palazzetto', 'Public/Societa/Palazzetto',
                'Palazzo Wanny, la casa della Savino Del Bene Volley a Firenze. Capienza, come arrivare e servizi dell\'impianto.',
                '<h2>Palazzo Wanny</h2><p>Il Palazzo Wanny di Firenze è la casa della Savino Del Bene Volley. Con una capienza di oltre 4.000 posti, l\'impianto offre un\'esperienza unica per tifosi e appassionati di pallavolo.</p><h2>Come Arrivare</h2><p>Via del Cavallaccio, 18/20/22/24 — 50142 Firenze (FI). Facilmente raggiungibile con i mezzi pubblici e con ampio parcheggio disponibile.</p><h2>Servizi</h2><p>Bar, area hospitality, accesso disabili, parcheggio custodito.</p>',
                [
                    'it' => [
                        'venue_name' => 'Palazzo Wanny',
                        'venue_address' => 'Via del Cavallaccio, 18/20/22/24 — 50142 Firenze (FI)',
                        'maps_link' => 'https://www.google.com/maps/place/Palazzo+Wanny/@43.7725946,11.1989035,17z/data=!3m1!4b1!4m6!3m5!1s0x132a514d3f32c3f9:0x6b4a2e5d5225c5d0!8m2!3d43.7725946!4d11.1989035!16s%2Fg%2F11q26v9v3g',
                        'maps_iframe_src' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2880.088656114169!2d11.2001!3d43.7917!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x132a514d3f32c3f9%3A0x6b4a2e5d5225c5d0!2sPalazzo%20Wanny!5e0!3m2!1sit!2sit!4v1700000000000!5m2!1sit!2sit',
                        'services' => [
                            [
                                'name' => 'Capienza 4000 Posti',
                                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                            ],
                            [
                                'name' => 'Accesso Disabili',
                                'icon' => 'M19 14l-7 7m0 0l-7-7m7 7V3',
                            ],
                            [
                                'name' => 'Parcheggio Ampio',
                                'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
                            ],
                            [
                                'name' => 'Bar e Area Hospitality',
                                'icon' => 'M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M6.75 21A3.75 3.75 0 013 17.25V11.5A2.25 2.25 0 015.25 9.25h13.5A2.25 2.25 0 0121 11.5v5.75A3.75 3.75 0 0117.25 21H6.75z',
                            ],
                        ],
                    ],
                    'en' => [
                        'venue_name' => 'Palazzo Wanny',
                        'venue_address' => 'Via del Cavallaccio, 18/20/22/24 — 50142 Florence (FI)',
                        'maps_link' => 'https://www.google.com/maps/place/Palazzo+Wanny/@43.7725946,11.1989035,17z/data=!3m1!4b1!4m6!3m5!1s0x132a514d3f32c3f9:0x6b4a2e5d5225c5d0!8m2!3d43.7725946!4d11.1989035!16s%2Fg%2F11q26v9v3g',
                        'maps_iframe_src' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2880.088656114169!2d11.2001!3d43.7917!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x132a514d3f32c3f9%3A0x6b4a2e5d5225c5d0!2sPalazzo%20Wanny!5e0!3m2!1sit!2sit!4v1700000000000!5m2!1sit!2sit',
                        'services' => [
                            [
                                'name' => 'Capacity 4000 Seats',
                                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                            ],
                            [
                                'name' => 'Disabled Access',
                                'icon' => 'M19 14l-7 7m0 0l-7-7m7 7V3',
                            ],
                            [
                                'name' => 'Ample Parking',
                                'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
                            ],
                            [
                                'name' => 'Bar & Hospitality Area',
                                'icon' => 'M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M6.75 21A3.75 3.75 0 013 17.25V11.5A2.25 2.25 0 015.25 9.25h13.5A2.25 2.25 0 0121 11.5v5.75A3.75 3.75 0 0117.25 21H6.75z',
                            ],
                        ],
                    ],
                ]),

            // === TICKETING ===
            $this->page('abbonamenti', 'Campagna Abbonamenti', 'Public/Ticketing',
                'Abbonamenti Savino Del Bene Volley 2026/2027. Scopri le formule e i prezzi per la nuova stagione.',
                '<h2>Campagna Abbonamenti 2026/2027</h2><p>Vivi tutte le emozioni della Serie A1 e della Champions League con l\'abbonamento stagionale. Scegli la formula più adatta a te e assicurati il tuo posto al Palazzo Wanny.</p>'),
            $this->page('biglietteria', 'Biglietteria', 'Public/Ticketing',
                'Acquista i biglietti per le partite della Savino Del Bene Volley. Prezzi, punti vendita e modalità di acquisto.',
                '<h2>Biglietteria</h2><p>I biglietti per le partite casalinghe della Savino Del Bene Volley sono acquistabili online e presso i punti vendita autorizzati.</p>'),
            $this->page('convenzioni', 'Convenzioni', 'Public/ContentPage',
                'Convenzioni e agevolazioni per gruppi, scuole e associazioni per assistere alle partite della Savino Del Bene Volley.',
                '<h2>Convenzioni</h2><p>La Savino Del Bene Volley offre tariffe agevolate per gruppi organizzati, scuole, associazioni sportive e aziende partner. Contattaci per ricevere un preventivo personalizzato.</p>'),
            $this->page('accessibilita', 'Accessibilità', 'Public/ContentPage',
                'Informazioni sull\'accessibilità del Palazzo Wanny per persone con disabilità. Posti riservati e servizi dedicati.',
                '<h2>Accessibilità</h2><p>Il Palazzo Wanny è dotato di posti riservati per persone con disabilità motoria, accesso facilitato, servizi igienici dedicati e personale formato per l\'assistenza. Per informazioni e prenotazioni, contatta la segreteria.</p>'),

            // === YOUTH / ACADEMY ===
            $this->page('settore-giovanile', 'Settore Giovanile', 'Public/Youth',
                'Il settore giovanile della Savino Del Bene Volley. Under 18, Under 16, Under 14 e Under 13.',
                '<p>Il settore giovanile rappresenta il cuore pulsante del progetto sportivo. Attraverso un programma di formazione strutturato, le nostre giovani atlete crescono seguendo i valori del club.</p>'),
            $this->page('talent-day', 'Talent Day & Recruiting', 'Public/SummerCamp',
                'Talent Day della Savino Del Bene Volley. Partecipa alle selezioni per entrare nel settore giovanile.',
                '<h2>Talent Day</h2><p>Ogni anno la Savino Del Bene Volley organizza giornate di selezione aperte a tutte le giovani atlete che desiderano entrare a far parte del settore giovanile. Monitora questa pagina per le prossime date.</p>'),
            $this->page('summer-camp', 'Summer Camp & Experience', 'Public/SummerCamp',
                'Summer Camp Savino Del Bene Volley. Settimane di sport, divertimento e formazione con le atlete della Serie A1.',
                '<h2>Summer Camp 2026</h2><p>Un\'esperienza unica per le giovani pallavoliste: allenamenti con lo staff tecnico della prima squadra, tornei, attività ricreative e la possibilità di incontrare le atlete della Serie A1.</p>'),
            $this->page('progetto-scuola', 'Progetto Scuola', 'Public/ContentPage',
                'Il progetto scuola della Savino Del Bene Volley. Promuoviamo la pallavolo e i valori dello sport nelle scuole.',
                '<h2>Progetto Scuola</h2><p>La Savino Del Bene Volley porta la pallavolo e i valori dello sport nelle scuole del territorio. Attraverso lezioni, dimostrazioni e tornei interscolastici, avviciniamo i giovani alla pratica sportiva.</p>'),

            // === SPONSOR / B2B ===
            $this->page('title-sponsor', 'Title Sponsor', 'Public/ContentPage',
                'Savino Del Bene S.p.A., title sponsor della Savino Del Bene Volley. Scopri la partnership.',
                '<h2>Savino Del Bene S.p.A.</h2><p>Il Gruppo Savino Del Bene, leader mondiale nella logistica e nelle spedizioni internazionali, è il title sponsor del club sin dalla sua fondazione. Una partnership che unisce eccellenza imprenditoriale e passione sportiva.</p>'),
            $this->page('diventa-sponsor', 'Diventa Sponsor', 'Public/ContentPage',
                'Diventa sponsor della Savino Del Bene Volley. Scopri i pacchetti di sponsorizzazione e i vantaggi per la tua azienda.',
                '<h2>Perché Sponsorizzare la Savino Del Bene Volley?</h2><p>Visibilità nazionale e internazionale, accesso a un network B2B esclusivo, hospitality premium e attivazioni di marketing dedicate. Contattaci per un preventivo personalizzato.</p>'),
            $this->page('hospitality', 'Hospitality', 'Public/ContentPage',
                'Hospitality e servizi premium per le partite della Savino Del Bene Volley al Palazzo Wanny.',
                '<h2>Esperienza Hospitality</h2><p>Vivi le partite della Savino Del Bene Volley da una prospettiva esclusiva. Il nostro programma Hospitality offre posti premium, catering dedicato, meet & greet con le atlete e networking con i partner del club.</p>'),
            $this->page('affiliazioni', 'Progetto Affiliazioni', 'Public/ContentPage',
                'Programma di affiliazione della Savino Del Bene Volley per società sportive e scuole di pallavolo.',
                '<h2>Programma Affiliazioni</h2><p>La Savino Del Bene Volley offre un programma di affiliazione per società sportive e scuole di pallavolo del territorio. Formazione tecnica, condivisione di metodologie e partecipazione a eventi esclusivi.</p>'),

            // === SOCIALE ===
            $this->page('volley-4-all', 'Volley 4 All', 'Public/Sociale',
                'Volley 4 All: il progetto di inclusione sociale della Savino Del Bene Volley. Sport per tutti, senza barriere.',
                '<h2>Volley 4 All</h2><p>Un progetto che abbatte le barriere e porta la pallavolo a tutti: persone con disabilità, ragazzi in situazioni di disagio sociale e comunità svantaggiate. Perché lo sport è un diritto, non un privilegio.</p>'),
            $this->page('progetti-sociali', 'Progetti Sociali', 'Public/Sociale',
                'I progetti sociali della Savino Del Bene Volley. Inclusione, territorio e responsabilità sociale.',
                '<p>La Savino Del Bene Volley è impegnata attivamente nel tessuto sociale del territorio con iniziative di inclusione, formazione e sostegno alle comunità locali.</p>'),
            $this->page('sostenibilita', 'Bilancio di Sostenibilità', 'Public/ContentPage',
                'Il bilancio di sostenibilità della Savino Del Bene Volley. Impegno ambientale, sociale e di governance.',
                '<h2>Sostenibilità</h2><p>La Savino Del Bene Volley pubblica annualmente il proprio bilancio di sostenibilità, documentando l\'impegno del club in ambito ambientale (riduzione impatto eventi), sociale (progetti inclusivi) e di governance (trasparenza gestionale).</p>'),

            // === COMUNICAZIONE / MEDIA ===
            $this->page('accrediti-stampa', 'Accrediti Stampa', 'Public/Comunicazione',
                'Richiedi l\'accredito stampa per le partite della Savino Del Bene Volley. Informazioni per giornalisti e fotografi.',
                '<p>Per richiedere l\'accredito stampa, compila il modulo dedicato almeno 48 ore prima dell\'evento. L\'ufficio stampa valuterà la richiesta e invierà conferma via email.</p>'),
            $this->page('cartelle-stampa', 'Cartelle Stampa', 'Public/Comunicazione',
                'Scarica le cartelle stampa ufficiali della Savino Del Bene Volley. Logo, foto ufficiali e materiali per la stampa.',
                '<h2>Materiale Stampa</h2><p>In questa sezione sono disponibili i materiali ufficiali per la stampa: logo del club in vari formati, foto ufficiali delle atlete, cartelle stampa pre-partita e comunicati ufficiali.</p>'),
            $this->page('double-face', 'Double Face — Il Magazine', 'Public/ContentPage',
                'Double Face, il magazine ufficiale della Savino Del Bene Volley. Interviste, approfondimenti e dietro le quinte.',
                '<h2>Double Face</h2><p>Il magazine ufficiale della Savino Del Bene Volley. Interviste esclusive con le atlete, approfondimenti tattici, dietro le quinte e storie dal settore giovanile. Disponibile in formato digitale.</p>', [
                    'youtube_videos' => [
                        [
                            'title' => 'Video di Presentazione Double Face',
                            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                            'description' => 'Un video speciale sul dietro le quinte del magazine.',
                        ],
                    ],
                ]),
            $this->page('magazine', 'Magazine', 'Public/ContentPage',
                'Sfoglia l\'archivio del magazine ufficiale della Savino Del Bene Volley. Scarica le edizioni in formato PDF.',
                '<h2>Archivio Magazine</h2><p>In questa sezione puoi consultare e scaricare in formato PDF tutti i numeri di "Double Face", il magazine ufficiale della Savino Del Bene Volley.</p>', [
                    'magazines' => [
                        [
                            'title' => 'Double Face — Numero 1',
                            'publish_date' => 'Ottobre 2026',
                            'file_url' => '',
                            'cover_image_url' => '',
                        ],
                    ],
                ]),
            $this->page('iscrizione-experience', 'Iscrizione (Experience)', 'Public/ContentPage',
                'Iscrizione al Summer Camp Savino Del Bene Volley. Accedi al portale iscrizioni del partner organizzativo.',
                '<h2>Iscrizione Online</h2><p>Le iscrizioni per l\'edizione 2026 del Summer Camp sono gestite direttamente dal nostro partner organizzativo. Clicca sul pulsante sottostante per accedere al portale di iscrizione esterno.</p>', [
                    'button_text' => 'Accedi al portale iscrizioni',
                    'button_url' => 'https://www.example.com/iscrizione-camp',
                ]),
        ];
    }
}
