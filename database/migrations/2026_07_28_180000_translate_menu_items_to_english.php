<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Completa le traduzioni inglesi delle voci di menu (posizioni `main` e `footer`).
 *
 * Il menu vive nel database e quasi tutte le voci hanno solo la traduzione
 * italiana: il sito in inglese mostra quindi una navigazione italiana, resa
 * ancora più incoerente dalle poche voci già tradotte (Shop, Risultati,
 * Classifica).
 *
 * Regole di prudenza, perché in produzione questi dati sono già stati toccati
 * dalla redazione:
 *
 * - si scrive SOLO dove la traduzione inglese manca (assente, null o stringa
 *   vuota): un valore già presente non viene mai sovrascritto, quindi la
 *   migrazione è idempotente e rieseguibile;
 * - le voci si cercano per URL (con e senza slash finale) dentro la loro
 *   posizione e al loro livello (radice o figlia), non per id: in produzione
 *   gli id possono differire da quelli del seeder;
 * - la traduzione si applica solo se anche l'italiano corrisponde a quello
 *   atteso. Se la redazione ha rinominato una voce, il testo inglese qui
 *   scritto non le corrisponderebbe più: meglio lasciarla non tradotta che
 *   sbagliata;
 * - alcune `description` del seeder contengono in realtà un URL segnaposto
 *   (es. "/stagione/news/"): non hanno una traduzione e non sono in tabella.
 *
 * Si lavora con il query builder e non con il modello: una migrazione deve
 * restare valida anche se un domani `MenuItem` cambia. Le colonne tradotte sono
 * `text` e contengono un JSON {"it":…,"en":…}.
 */
return new class extends Migration
{
    private const TRANSLATABLE_COLUMNS = ['label', 'description', 'motto_title', 'motto_subtitle'];

    /**
     * Traduzioni indicizzate per "<location>|<root|child>|<url senza slash finale>".
     *
     * Ogni voce elenca l'italiano atteso (`it`) e l'inglese da scrivere (`en`)
     * per ciascuna colonna tradotta.
     *
     * @return array<string, array<string, array{it: string, en: string}>>
     */
    private function translations(): array
    {
        return [
            // ── MAIN: voci di primo livello ──────────────────────────────
            'main|root|/stagione' => [
                'label' => ['it' => 'Stagione', 'en' => 'Season'],
                'motto_title' => ['it' => 'La Squadra', 'en' => 'The Team'],
                'motto_subtitle' => [
                    'it' => 'Roster, staff e risultati della stagione in corso',
                    'en' => 'Roster, staff and results from the current season',
                ],
            ],
            'main|root|/societa' => [
                'label' => ['it' => 'Società', 'en' => 'Club'],
                'motto_title' => ['it' => 'Il Club', 'en' => 'The Club'],
                'motto_subtitle' => [
                    'it' => 'Storia, organigramma e strutture della Savino Del Bene',
                    'en' => 'History, leadership and facilities of Savino Del Bene',
                ],
            ],
            'main|root|/ticketing' => [
                'label' => ['it' => 'Ticketing', 'en' => 'Tickets'],
                'motto_title' => ['it' => "Vivi l'Emozione", 'en' => 'Feel the Thrill'],
                'motto_subtitle' => [
                    'it' => 'Biglietti, abbonamenti e informazioni per le partite',
                    'en' => 'Tickets, season passes and match information',
                ],
            ],
            'main|root|/sponsor' => [
                'label' => ['it' => 'Sponsor', 'en' => 'Sponsors'],
                'motto_title' => ['it' => 'I Partner', 'en' => 'Our Partners'],
                'motto_subtitle' => [
                    'it' => 'Un network di eccellenza al fianco della squadra',
                    'en' => 'A network of excellence alongside the team',
                ],
            ],
            'main|root|/youth' => [
                'label' => ['it' => 'SDB Youth', 'en' => 'SDB Youth'],
                'motto_title' => ['it' => 'Il Futuro', 'en' => 'The Future'],
                'motto_subtitle' => [
                    'it' => 'Settore giovanile, B1 e talent scouting',
                    'en' => 'Youth programme, Serie B1 and talent scouting',
                ],
            ],
            'main|root|/summer-camp' => [
                'label' => ['it' => 'Summer Camp', 'en' => 'Summer Camp'],
                'motto_title' => ['it' => 'Estate di Sport', 'en' => 'A Summer of Sport'],
                'motto_subtitle' => [
                    'it' => 'Summer camp e esperienze sportive per ragazzi',
                    'en' => 'Summer camps and sporting experiences for young players',
                ],
            ],
            'main|root|/sociale' => [
                'label' => ['it' => 'Sociale', 'en' => 'Community'],
                'motto_title' => ['it' => 'Sport e Società', 'en' => 'Sport and Society'],
                'motto_subtitle' => [
                    'it' => 'Progetti sociali, inclusione e sostenibilità',
                    'en' => 'Community projects, inclusion and sustainability',
                ],
            ],
            'main|root|/comunicazione' => [
                'label' => ['it' => 'Comunicazione', 'en' => 'Media'],
                'motto_title' => ['it' => 'Area Stampa', 'en' => 'Press Area'],
                'motto_subtitle' => [
                    'it' => 'News, comunicati, accrediti e materiale media',
                    'en' => 'News, press releases, accreditation and media resources',
                ],
            ],
            'main|root|/shop' => [
                'label' => ['it' => 'Shop Ufficiale', 'en' => 'Official Shop'],
                'motto_title' => ['it' => 'Shop Ufficiale', 'en' => 'Official Shop'],
                'motto_subtitle' => [
                    'it' => 'Maglie, merchandise e accessori della squadra',
                    'en' => 'Team shirts, merchandise and accessories',
                ],
            ],

            // ── MAIN: Stagione ───────────────────────────────────────────
            'main|child|/stagione' => [
                'label' => ['it' => 'Serie A1', 'en' => 'Serie A1'],
                'description' => ['it' => 'Roster e Staff', 'en' => 'Roster and staff'],
            ],
            'main|child|/stagione/foto-ufficiale' => [
                'label' => ['it' => 'Foto Ufficiale', 'en' => 'Official Team Photo'],
                'description' => ['it' => 'Download PDF', 'en' => 'PDF download'],
            ],
            'main|child|/stagione/risultati' => [
                'label' => ['it' => 'Risultati', 'en' => 'Results'],
            ],
            'main|child|/stagione/classifica' => [
                'label' => ['it' => 'Classifica', 'en' => 'Standings'],
            ],
            'main|child|/stagione/cev' => [
                'label' => ['it' => 'CEV Champions League', 'en' => 'CEV Champions League'],
                'description' => ['it' => 'Classifiche e Girone', 'en' => 'Standings and pool'],
            ],
            'main|child|/stagione/coppa-italia' => [
                'label' => ['it' => 'Coppa Italia & Playoff', 'en' => 'Coppa Italia & Playoffs'],
                'description' => ['it' => 'Tabellone Risultati', 'en' => 'Results bracket'],
            ],
            'main|child|/stagione/news' => [
                'label' => ['it' => 'News & Comunicati', 'en' => 'News & Press Releases'],
            ],
            'main|child|/gallery' => [
                'label' => ['it' => 'Foto Gallery', 'en' => 'Photo Gallery'],
                'description' => ['it' => 'Galleria Fotografica', 'en' => 'Photo gallery'],
            ],

            // ── MAIN: Società ────────────────────────────────────────────
            'main|child|/societa/organigramma' => [
                'label' => ['it' => 'Organigramma', 'en' => 'Leadership'],
                'description' => ['it' => 'Cariche e nominativi', 'en' => 'Roles and names'],
            ],
            'main|child|/societa/storia' => [
                'label' => ['it' => 'Storia', 'en' => 'History'],
            ],
            'main|child|/societa/safeguarding' => [
                'label' => ['it' => 'Safeguarding', 'en' => 'Safeguarding'],
                'description' => ['it' => 'PDF Scaricabile', 'en' => 'Downloadable PDF'],
            ],
            'main|child|/societa/contatti' => [
                'label' => ['it' => 'Contatti', 'en' => 'Contact'],
            ],
            'main|child|/societa/palazzetto' => [
                'label' => ['it' => 'Palazzetto & Google Maps', 'en' => 'Arena & Google Maps'],
                'description' => ['it' => 'Come raggiungerci', 'en' => 'How to reach us'],
            ],

            // ── MAIN: Ticketing ──────────────────────────────────────────
            'main|child|/ticketing' => [
                'label' => ['it' => 'Biglietteria', 'en' => 'Box Office'],
            ],
            'main|child|/ticketing/abbonamenti' => [
                'label' => ['it' => 'Campagna Abbonamenti', 'en' => 'Season Ticket Campaign'],
            ],
            'main|child|/ticketing/accessibilita' => [
                'label' => ['it' => 'Accessibilità & Info', 'en' => 'Accessibility & Info'],
                'description' => [
                    'it' => 'Disabili, Cani, Ospiti',
                    'en' => 'Accessible seating, dogs, away fans',
                ],
            ],
            'main|child|/ticketing/convenzioni' => [
                'label' => ['it' => 'Convenzioni', 'en' => 'Discounts'],
                'description' => ['it' => 'Abbonati e possessori', 'en' => 'Season ticket and card holders'],
            ],

            // ── MAIN: Sponsor ────────────────────────────────────────────
            'main|child|/sponsor/nostri-sponsor' => [
                'label' => ['it' => 'I Nostri Sponsor', 'en' => 'Our Sponsors'],
                'description' => ['it' => 'Loghi e Categorie', 'en' => 'Logos and categories'],
            ],
            'main|child|/sponsor/diventa-sponsor' => [
                'label' => ['it' => 'Diventa Sponsor', 'en' => 'Become a Sponsor'],
                'description' => ['it' => 'Vantaggi & LinkedIn', 'en' => 'Benefits & LinkedIn'],
            ],
            'main|child|/sponsor/title-sponsor' => [
                'label' => ['it' => 'Title Sponsor (SDB)', 'en' => 'Title Sponsor (SDB)'],
                'description' => [
                    'it' => 'Vision, Mission, Consociate',
                    'en' => 'Vision, mission, group companies',
                ],
            ],
            'main|child|/sponsor/hospitality' => [
                'label' => ['it' => 'Hospitality', 'en' => 'Hospitality'],
                'description' => ['it' => 'Descrizione Servizio', 'en' => 'Service details'],
            ],

            // ── MAIN: SDB Youth ──────────────────────────────────────────
            'main|child|/youth/b1-u19' => [
                'label' => ['it' => 'Serie B1 / U19', 'en' => 'Serie B1 / U19'],
                'description' => ['it' => 'Roster e Staff', 'en' => 'Roster and staff'],
            ],
            'main|child|/youth/u17-u15' => [
                'label' => ['it' => 'Serie U17 & U15', 'en' => 'U17 & U15'],
                'description' => ['it' => 'Roster e Staff', 'en' => 'Roster and staff'],
            ],
            'main|child|/youth/settore-giovanile' => [
                'label' => ['it' => 'Settore Giovanile', 'en' => 'Youth Programme'],
                'description' => ['it' => 'Foto squadre di base', 'en' => 'Grassroots team photos'],
            ],
            'main|child|/youth/talent-day' => [
                'label' => ['it' => 'Talent Day & Recruiting', 'en' => 'Talent Day & Recruiting'],
                'description' => ['it' => 'Calendario e Form', 'en' => 'Dates and entry form'],
            ],
            'main|child|/youth/affiliazioni' => [
                'label' => ['it' => 'Progetto Affiliazioni', 'en' => 'Affiliated Clubs Project'],
                'description' => ['it' => 'Loghi Società', 'en' => 'Club logos'],
            ],

            // ── MAIN: Summer Camp ────────────────────────────────────────
            'main|child|/summer-camp/info' => [
                'label' => ['it' => 'Tutte le Info', 'en' => 'All the Info'],
            ],
            'main|child|/summer-camp/iscrizione' => [
                'label' => ['it' => 'Iscrizione (Experience)', 'en' => 'Registration (Experience)'],
                'description' => ['it' => 'Form multi-step', 'en' => 'Multi-step form'],
            ],

            // ── MAIN: Sociale ────────────────────────────────────────────
            'main|child|/sociale/progetti' => [
                'label' => ['it' => 'Progetti Sociali', 'en' => 'Community Projects'],
            ],
            'main|child|/sociale/volley-4-all' => [
                'label' => ['it' => 'Volley 4 All', 'en' => 'Volley 4 All'],
                'description' => ['it' => 'Partner AllunaMente', 'en' => 'AllunaMente partnership'],
            ],
            'main|child|/sociale/sostenibilita' => [
                'label' => ['it' => 'Bilancio Sostenibilità', 'en' => 'Sustainability Report'],
                'description' => ['it' => 'PDF Stagioni', 'en' => 'PDFs by season'],
            ],
            'main|child|/sociale/progetto-scuola' => [
                'label' => ['it' => 'Progetto Scuola', 'en' => 'Schools Project'],
                'description' => ['it' => 'Istituti e Partner', 'en' => 'Schools and partners'],
            ],
            'main|child|/sociale/aste' => [
                'label' => ['it' => 'Aste Benefiche', 'en' => 'Charity Auctions'],
            ],

            // ── MAIN: Comunicazione ──────────────────────────────────────
            'main|child|/comunicazione/accrediti' => [
                'label' => ['it' => 'Accrediti Stampa', 'en' => 'Press Accreditation'],
                'description' => ['it' => 'Form Richiesta', 'en' => 'Request form'],
            ],
            'main|child|/comunicazione/cartelle' => [
                'label' => ['it' => 'Cartelle Stampa', 'en' => 'Press Kits'],
                'description' => ['it' => 'Materiali', 'en' => 'Downloads'],
            ],
            'main|child|/comunicazione/magazine' => [
                'label' => ['it' => 'Magazine', 'en' => 'Magazine'],
                'description' => ['it' => 'PDF Online', 'en' => 'Online PDF'],
            ],
            'main|child|/comunicazione/double-face' => [
                'label' => ['it' => 'Double Face', 'en' => 'Double Face'],
            ],

            // ── MAIN: Shop ───────────────────────────────────────────────
            'main|child|/shop/categoria/kit-gara-25-26' => [
                'label' => ['it' => 'Kit Gara', 'en' => 'Match Kits'],
                'description' => ['it' => 'Home, Away, Champions', 'en' => 'Home, Away, Champions'],
            ],
            'main|child|/shop/categoria/abbigliamento' => [
                'label' => ['it' => 'Abbigliamento & Accessori', 'en' => 'Apparel & Accessories'],
                'description' => ['it' => 'Catalogo', 'en' => 'Catalogue'],
            ],
            'main|child|/shop/aste' => [
                'label' => ['it' => 'Aste & Outlet', 'en' => 'Auctions & Outlet'],
                'description' => ['it' => 'Scadenze e Rilanci', 'en' => 'Deadlines and Bids'],
            ],
            'main|child|/shop/guida-taglie' => [
                'label' => ['it' => 'Guida Taglie', 'en' => 'Size Guide'],
                'description' => ['it' => 'File Errea', 'en' => 'Errea File'],
            ],
            'main|child|/shop/contatti' => [
                'label' => ['it' => 'Contatti Shop', 'en' => 'Shop Contacts'],
                'description' => ['it' => 'Assistenza Clienti', 'en' => 'Customer Support'],
            ],

            // ── FOOTER: colonne ──────────────────────────────────────────
            'footer|root|/stagione' => [
                'label' => ['it' => 'Stagione', 'en' => 'Season'],
            ],
            'footer|root|/societa' => [
                'label' => ['it' => 'Il Club', 'en' => 'The Club'],
            ],
            'footer|root|/ticketing' => [
                'label' => ['it' => 'Servizi', 'en' => 'Services'],
            ],

            // ── FOOTER: voci ─────────────────────────────────────────────
            'footer|child|/stagione' => [
                'label' => ['it' => 'Roster A1', 'en' => 'A1 Roster'],
            ],
            'footer|child|/risultati' => [
                'label' => ['it' => 'Risultati', 'en' => 'Results'],
            ],
            'footer|child|/gallery' => [
                'label' => ['it' => 'Gallery', 'en' => 'Gallery'],
            ],
            'footer|child|/staff' => [
                'label' => ['it' => 'Staff Tecnico', 'en' => 'Coaching Staff'],
            ],
            'footer|child|/societa' => [
                'label' => ['it' => 'La Società', 'en' => 'The Club'],
            ],
            'footer|child|/youth' => [
                'label' => ['it' => 'Settore Giovanile', 'en' => 'Youth Programme'],
            ],
            'footer|child|/sponsor' => [
                'label' => ['it' => 'Sponsor', 'en' => 'Sponsors'],
            ],
            'footer|child|/news' => [
                'label' => ['it' => 'News', 'en' => 'News'],
            ],
            'footer|child|/ticketing' => [
                'label' => ['it' => 'Biglietteria', 'en' => 'Box Office'],
            ],
            'footer|child|/shop' => [
                'label' => ['it' => 'Shop Ufficiale', 'en' => 'Official Shop'],
            ],
            'footer|child|/contatti' => [
                'label' => ['it' => 'Contatti', 'en' => 'Contact'],
            ],
            'footer|child|/comunicazione' => [
                'label' => ['it' => 'Comunicazione', 'en' => 'Media'],
            ],
        ];
    }

    public function up(): void
    {
        $translations = $this->translations();
        $touched = false;

        $rows = DB::table('menu_items')
            ->whereIn('location', ['main', 'footer'])
            ->get(['id', 'location', 'parent_id', 'url', ...self::TRANSLATABLE_COLUMNS]);

        foreach ($rows as $row) {
            $entry = $translations[$this->keyFor($row)] ?? null;

            if ($entry === null) {
                // Voce aggiunta dalla redazione o URL cambiato: non si inventa
                // una traduzione.
                continue;
            }

            $update = [];

            foreach (self::TRANSLATABLE_COLUMNS as $column) {
                if (! isset($entry[$column])) {
                    continue;
                }

                $values = $this->decode($row->{$column} ?? null);

                // L'inglese c'è già (redazione o migrazione precedente): mai
                // sovrascriverlo. È questo che rende la migrazione idempotente.
                if ($this->present($values['en'] ?? null)) {
                    continue;
                }

                // L'italiano deve essere quello atteso, altrimenti la voce è
                // stata rinominata e la traduzione non le corrisponderebbe.
                if (! $this->matches($values['it'] ?? null, $entry[$column]['it'])) {
                    continue;
                }

                $values['en'] = $entry[$column]['en'];
                $update[$column] = json_encode($values, JSON_UNESCAPED_UNICODE);
            }

            if ($update === []) {
                continue;
            }

            $update['updated_at'] = now();
            DB::table('menu_items')->where('id', $row->id)->update($update);
            $touched = true;
        }

        if ($touched) {
            $this->flushMenuCache();
        }
    }

    /**
     * Rimuove solo le traduzioni inglesi scritte da questa migrazione, cioè
     * quelle ancora identiche al testo qui definito. Un ritocco successivo
     * della redazione sopravvive al rollback.
     */
    public function down(): void
    {
        $translations = $this->translations();
        $touched = false;

        $rows = DB::table('menu_items')
            ->whereIn('location', ['main', 'footer'])
            ->get(['id', 'location', 'parent_id', 'url', ...self::TRANSLATABLE_COLUMNS]);

        foreach ($rows as $row) {
            $entry = $translations[$this->keyFor($row)] ?? null;

            if ($entry === null) {
                continue;
            }

            $update = [];

            foreach (self::TRANSLATABLE_COLUMNS as $column) {
                if (! isset($entry[$column])) {
                    continue;
                }

                $values = $this->decode($row->{$column} ?? null);

                if (! array_key_exists('en', $values) || $values['en'] !== $entry[$column]['en']) {
                    continue;
                }

                unset($values['en']);
                $update[$column] = json_encode($values, JSON_UNESCAPED_UNICODE);
            }

            if ($update === []) {
                continue;
            }

            $update['updated_at'] = now();
            DB::table('menu_items')->where('id', $row->id)->update($update);
            $touched = true;
        }

        if ($touched) {
            $this->flushMenuCache();
        }
    }

    /**
     * Chiave "<location>|<root|child>|<url>": identifica la voce senza usare
     * l'id. Lo slash finale è ininfluente e alcuni URL si ripetono fra menu
     * principale e footer, o fra una colonna e la sua prima voce.
     */
    private function keyFor(object $row): string
    {
        $url = rtrim((string) ($row->url ?? ''), '/');

        return $row->location.'|'.($row->parent_id === null ? 'root' : 'child').'|'.($url === '' ? '/' : $url);
    }

    /**
     * @return array<string, string|null>
     */
    private function decode(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Riga legacy in testo semplice: è l'italiano storico.
        return ['it' => $raw];
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function matches(mixed $actual, string $expected): bool
    {
        return is_string($actual)
            && mb_strtolower(trim($actual)) === mb_strtolower($expected);
    }

    /**
     * Il menu è cachato per posizione e lingua con TTL di un giorno: senza
     * questo svuotamento le traduzioni comparirebbero fino a 24 ore dopo il deploy.
     */
    private function flushMenuCache(): void
    {
        foreach (config('app.supported_locales', ['it']) as $locale) {
            Cache::forget('menu_items_main_'.$locale);
            Cache::forget('menu_items_footer_'.$locale);
        }
    }
};
