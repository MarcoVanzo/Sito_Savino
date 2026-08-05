<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ripulisce le `description` delle voci di menu, che il mega-menu mostra al
 * pubblico come sottotitolo grigio sotto ogni voce di sottomenu
 * (`resources/js/Components/MegaMenu.vue`).
 *
 * Il seeder originale usava quel campo come blocco note: in dieci voci del menu
 * principale contiene il percorso della pagina ("/stagione/risultati/",
 * "/societa/contatti/") o un appunto redazionale ("- Vivaticket WL",
 * "-> E-Shop", "-> YouTube Channel"). Non è un problema di traduzione: il testo
 * è sbagliato in entrambe le lingue, perché è sbagliato l'italiano da cui la
 * traduzione è partita.
 *
 * Due interventi:
 *
 * 1. le dieci voci sporche del menu principale ricevono una descrizione vera,
 *    ricavata dal contenuto della pagina a cui puntano;
 * 2. le `description` che contengono solo valori vuoti (JSON `{"it":null}`,
 *    lasciato dal seeder sulle voci senza descrizione) tornano a NULL. Non sono
 *    visibili — il footer non stampa la descrizione — ma è il valore che il
 *    fallback `common.explore_section` del mega-menu si aspetta se un domani
 *    quella voce venisse spostata nel menu principale.
 *
 * Come nella migrazione delle traduzioni inglesi, le voci si cercano per
 * posizione + livello + URL e non per id (in produzione gli id non coincidono
 * con quelli del seeder), e si riscrive solo se il testo attuale è ancora
 * esattamente quello sporco: una descrizione già corretta dalla redazione
 * sopravvive, e rieseguire la migrazione non cambia nulla.
 */
return new class extends Migration
{
    /**
     * Nuove descrizioni, indicizzate per "<location>|<root|child>|<url senza
     * slash finale>".
     *
     * `dirty` elenca i testi da sostituire (case-insensitive, spazi esterni
     * ignorati); `value` è il nuovo contenuto tradotto, oppure null per
     * svuotare il campo e lasciare spazio al fallback del front-end.
     *
     * @return array<string, array{dirty: list<string>, value: array<string, string>|null}>
     */
    private function replacements(): array
    {
        return [
            // ── MAIN: Stagione ───────────────────────────────────────────
            'main|child|/stagione/risultati' => [
                'dirty' => ['/stagione/risultati/'],
                'value' => ['it' => 'Calendario e tabellini', 'en' => 'Fixtures and box scores'],
            ],
            'main|child|/stagione/news' => [
                'dirty' => ['/stagione/news/'],
                'value' => ['it' => 'Notizie e comunicati', 'en' => 'News and press releases'],
            ],

            // ── MAIN: Società ────────────────────────────────────────────
            'main|child|/societa/storia' => [
                'dirty' => ['/societa/storia/'],
                'value' => ['it' => 'Dal 1982 a oggi', 'en' => 'From 1982 to today'],
            ],
            'main|child|/societa/contatti' => [
                'dirty' => ['/societa/contatti/'],
                'value' => ['it' => 'Recapiti e sede', 'en' => 'Contact details and address'],
            ],

            // ── MAIN: Ticketing ──────────────────────────────────────────
            'main|child|/ticketing' => [
                'dirty' => ['- Vivaticket WL'],
                'value' => ['it' => 'Prezzi e punti vendita', 'en' => 'Prices and points of sale'],
            ],
            'main|child|/ticketing/abbonamenti' => [
                'dirty' => ['/ticketing/abbonamenti/'],
                'value' => ['it' => 'Formule e prezzi', 'en' => 'Packages and prices'],
            ],

            // ── MAIN: Summer Camp ────────────────────────────────────────
            'main|child|/summer-camp/info' => [
                'dirty' => ['/summer-camp/info/'],
                'value' => ['it' => 'Programma e informazioni', 'en' => 'Programme and information'],
            ],

            // ── MAIN: Sociale ────────────────────────────────────────────
            'main|child|/sociale/progetti' => [
                'dirty' => ['/sociale/progetti/'],
                'value' => ['it' => 'Inclusione e territorio', 'en' => 'Inclusion and community'],
            ],
            'main|child|/sociale/aste' => [
                'dirty' => ['-> E-Shop'],
                'value' => ['it' => 'Aste sull\'e-shop ufficiale', 'en' => 'Auctions on the official e-shop'],
            ],

            // ── MAIN: Comunicazione ──────────────────────────────────────
            'main|child|/comunicazione/double-face' => [
                'dirty' => ['-> YouTube Channel'],
                'value' => ['it' => 'Il magazine ufficiale', 'en' => 'The official magazine'],
            ],

            // ── FOOTER ───────────────────────────────────────────────────
            // Stesso appunto redazionale finito nella voce del footer, che la
            // descrizione non la mostra: qui si svuota e basta.
            'footer|child|/ticketing' => [
                'dirty' => ['- Vivaticket WL'],
                'value' => null,
            ],
        ];
    }

    public function up(): void
    {
        $replacements = $this->replacements();
        $touched = false;

        $rows = DB::table('menu_items')->get(['id', 'location', 'parent_id', 'url', 'description']);

        foreach ($rows as $row) {
            $values = $this->decode($row->description);
            $entry = $replacements[$this->keyFor($row)] ?? null;
            $new = null;

            if ($entry !== null && $this->isDirty($values, $entry['dirty'])) {
                $new = $entry['value'] === null
                    ? null
                    : json_encode($entry['value'], JSON_UNESCAPED_UNICODE);
            } elseif ($row->description !== null && ! $this->hasText($values)) {
                // {"it":null} e simili: nessun testo, ma nemmeno NULL.
                $new = null;
            } else {
                continue;
            }

            if ($new === $row->description) {
                continue;
            }

            DB::table('menu_items')->where('id', $row->id)->update([
                'description' => $new,
                'updated_at' => now(),
            ]);
            $touched = true;
        }

        if ($touched) {
            $this->flushMenuCache();
        }
    }

    /**
     * Non reversibile: il valore precedente era un percorso o un appunto di
     * lavorazione, non un contenuto da ripristinare.
     */
    public function down(): void
    {
        // no-op
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

    /**
     * Sporca se ogni traduzione presente è uno dei testi da sostituire: se la
     * redazione ne ha già riscritta una, la voce non si tocca.
     *
     * @param  array<string, string|null>  $values
     * @param  list<string>  $dirty
     */
    private function isDirty(array $values, array $dirty): bool
    {
        $found = false;

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $found = true;

            if (! $this->matchesAny($value, $dirty)) {
                return false;
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function matchesAny(string $value, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (mb_strtolower(trim($value)) === mb_strtolower($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function hasText(array $values): bool
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Il menu è cachato per posizione e lingua con TTL di un giorno: senza
     * questo svuotamento le descrizioni vecchie resterebbero visibili fino a
     * 24 ore dopo il deploy.
     */
    private function flushMenuCache(): void
    {
        foreach (config('app.supported_locales', ['it']) as $locale) {
            Cache::forget('menu_items_main_'.$locale);
            Cache::forget('menu_items_footer_'.$locale);
        }
    }
};
