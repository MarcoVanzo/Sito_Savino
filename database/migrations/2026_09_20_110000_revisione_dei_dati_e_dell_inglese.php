<?php

use App\Http\Middleware\CachePublicResponse;
use App\Jobs\RicostruisciLaCacheDellaGallery;
use App\Models\MenuItem;
use App\Models\Player;
use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seconda parte della revisione del 20 settembre 2026, estesa a tutto il sito:
 * dati di prova rimasti online, impostazioni nel posto sbagliato, squadre
 * orfane di un import errato e — soprattutto — le pagine inglesi rimaste ai
 * testi del seeder, che raccontavano un club fondato nel 1982 e una storia
 * ferma alla finale Scudetto.
 *
 * Come la prima: ogni correzione ha una guardia sul valore letto in produzione
 * quel giorno, e rilanciarla non cambia nulla.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->impostazioni();
        $this->datiDiProva();
        $this->squadreRimasteDaUnImportSbagliato();
        $this->profiliInstagram();
        $this->descrizioniDelMenuInInglese();

        $this->testo('storia', fn (string $html, string $l): string => $this->testoStoria($html, $l));
        $this->dati('storia', fn (array $v, string $l): array => $this->timelineStoria($v, $l));
        $this->testo('organigramma', fn (string $html, string $l): string => $this->testoOrganigramma($html, $l));
        $this->testo('double-face', fn (string $html, string $l): string => $this->testoDoubleFace($html, $l));
        $this->dati('double-face', fn (array $v, string $l, array $t): array => $this->pulsanteInInglese($v, $l, $t, 'Watch now'));
        $this->dati('hospitality', fn (array $v, string $l, array $t): array => $this->pulsanteInInglese($v, $l, $t, 'Contact us'));
        $this->dati('diventa-sponsor', fn (array $v, string $l): array => $this->diventaSponsor($v, $l));
        $this->dati('settore-giovanile', fn (array $v, string $l): array => $this->settoreGiovanile($v, $l));
        $this->dati('palazzetto', fn (array $v, string $l, array $t): array => $this->palazzetto($v, $l, $t));
        $this->dati('abbonamenti', fn (array $v, string $l): array => $this->abbonamenti($v, $l));
        $this->riassuntoSettoreGiovanile();

        $this->dati('storia', fn (array $v, string $l): array => $this->refusiDellaTimeline($v, $l));
        $this->dati('talent-day', fn (array $v): array => $this->moduloTalentDay($v));
        $this->testo('accessibilita', fn (string $html, string $l): string => $this->testoAccessibilita($html, $l));
        $this->riassuntoConvenzioniInItaliano();
        $this->vociDiMenuESponsor();

        SiteSetting::clearCache();
        MenuItem::clearCache();
        CachePublicResponse::flush();
    }

    /**
     * Si tolgono dati di prova e testi falsi: tornare indietro li ripubblicherebbe.
     */
    public function down(): void {}

    // ------------------------------------------------------------------
    // Impostazioni
    // ------------------------------------------------------------------

    private function impostazioni(): void
    {
        // Il campo "Video di sfondo" del pannello scriveva nel gruppo 'general',
        // la home legge `settings.home.hero_video_url`: compilarlo non faceva nulla.
        DB::table('site_settings')->where('key', 'hero_video_url')->where('group', 'general')->update(['group' => 'home']);

        // Residuo di un repeater appiattito ("stats.0.value"…): dodici righe che
        // nessuno legge, con testi diversi dai numeri veri della home (`stats`).
        DB::table('site_settings')
            ->where('group', 'general')
            ->where('key', 'like', 'stats.%')
            ->get(['id', 'key'])
            ->filter(fn ($riga): bool => preg_match('/^stats\.\d+\.(value|label|icon)$/', (string) $riga->key) === 1)
            ->each(fn ($riga) => DB::table('site_settings')->where('id', $riga->id)->delete());

        // "Serie A1  " con due spazi in coda fra i numeri della home inglese.
        $numeri = DB::table('site_settings')->where('key', 'stats')->where('group', 'home')->first(['id', 'value']);
        $elenchi = is_object($numeri) ? json_decode((string) $numeri->value, true) : null;

        if (is_array($elenchi)) {
            $puliti = $elenchi;

            array_walk_recursive($puliti, function (&$valore): void {
                $valore = is_string($valore) ? trim($valore) : $valore;
            });

            if ($puliti !== $elenchi) {
                DB::table('site_settings')->where('id', $numeri->id)->update(['value' => json_encode($puliti)]);
            }
        }

        // Il copyright scritto a mano era la frase predefinita in italiano, e il
        // footer la preferisce alla traduzione: su /en usciva "Tutti i diritti
        // riservati". Vuota, vale la frase di sistema, tradotta in ogni lingua.
        DB::table('site_settings')
            ->where('key', 'footer_copyright')
            ->where('value', '© {year} Savino Del Bene Volley — Tutti i diritti riservati.')
            ->update(['value' => '']);
    }

    // ------------------------------------------------------------------
    // Dati di prova rimasti online
    // ------------------------------------------------------------------

    private function datiDiProva(): void
    {
        // Il tag "test" compariva come etichetta pubblica sotto una notizia.
        foreach (DB::table('tags')->whereIn('name', ['test', '{"it":"test"}'])->pluck('id') as $id) {
            DB::table('post_tag')->where('tag_id', $id)->delete();
            DB::table('tags')->where('id', $id)->delete();
        }

        // L'album "test9" (3 luglio 2026) era visibile nella gallery pubblica.
        // Si spegne, non si cancella: i file restano a chi vorrà buttarli dal pannello.
        $album = DB::table('gallery_events')
            ->where('is_active', true)
            ->whereIn('title', ['{"it":"test9"}', '{"it":"test9","en":"test9"}', 'test9'])
            ->pluck('id');

        if ($album->isNotEmpty()) {
            DB::table('gallery_events')->whereIn('id', $album)->update(['is_active' => false]);
            DB::table('gallery_images')->whereIn('gallery_event_id', $album)->update(['is_active' => false]);
        }

        // Una foto attiva senza file: in gallery è un tassello rotto, e l'analisi
        // dei volti la salta per sempre.
        DB::table('gallery_images')
            ->where('is_active', true)
            ->whereNotExists(fn ($media) => $media->selectRaw('1')->from('media')
                ->whereColumn('media.model_id', 'gallery_images.id')
                ->where('media.model_type', 'App\\Models\\GalleryImage'))
            ->update(['is_active' => false]);

        if ($album->isNotEmpty() && class_exists(RicostruisciLaCacheDellaGallery::class)) {
            // §12-bis: la cache della gallery non si butta, si rigenera.
            dispatch(new RicostruisciLaCacheDellaGallery);
        }
    }

    /**
     * Il 30 luglio un import ha portato dentro 17 squadre di A2; le loro gare
     * sono state cancellate il giorno dopo, le squadre no. Non hanno gare,
     * classifica, rose né loghi: restano solo a sporcare le tendine del pannello.
     * Le squadre della società non si toccano mai.
     */
    private function squadreRimasteDaUnImportSbagliato(): void
    {
        $orfane = DB::table('teams')
            ->where('is_internal', false)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('games')
                ->where(fn ($gare) => $gare->whereColumn('games.home_team_id', 'teams.id')->orWhereColumn('games.away_team_id', 'teams.id')))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('standings')->whereColumn('standings.team_id', 'teams.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('rosters')->whereColumn('rosters.team_id', 'teams.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('media')
                ->whereColumn('media.model_id', 'teams.id')->where('media.model_type', 'App\\Models\\Team'))
            ->pluck('id');

        if ($orfane->isEmpty()) {
            return;
        }

        if (Schema::hasTable('team_lvf_club_ids')) {
            DB::table('team_lvf_club_ids')->whereIn('team_id', $orfane)->delete();
        }

        DB::table('teams')->whereIn('id', $orfane)->delete();
    }

    /**
     * §14: il profilo si salva come nome utente. Tredici schede su quattordici
     * avevano il link intero, una con i parametri di tracciamento in coda.
     */
    private function profiliInstagram(): void
    {
        foreach (DB::table('players')->where('instagram_handle', 'like', '%instagram.com/%')->get(['id', 'instagram_handle']) as $atleta) {
            $nome = Player::instagramHandleDa($atleta->instagram_handle);

            if ($nome !== null) {
                DB::table('players')->where('id', $atleta->id)->update(['instagram_handle' => $nome]);
            }
        }
    }

    private function descrizioniDelMenuInInglese(): void
    {
        $correzioni = [
            'Benefits & LinkedIn' => 'Discover the benefits',
            'Service Description' => 'Discover all services',
            // In italiano queste due voci non hanno descrizione: in inglese
            // erano rimaste quelle del seeder.
            'Logos and Categories' => null,
            'Vision, Mission, Subsidiaries' => null,
        ];

        foreach (DB::table('menu_items')->whereNotNull('description')->get(['id', 'description']) as $voce) {
            $lingue = json_decode((string) $voce->description, true);

            if (! is_array($lingue) || ! array_key_exists((string) ($lingue['en'] ?? ''), $correzioni)) {
                continue;
            }

            $lingue['en'] = $correzioni[$lingue['en']];

            DB::table('menu_items')->where('id', $voce->id)->update(['description' => json_encode($lingue, JSON_UNESCAPED_UNICODE)]);
        }
    }

    // ------------------------------------------------------------------
    // Pagine
    // ------------------------------------------------------------------

    private function testoStoria(string $html, string $lingua): string
    {
        // In italiano era rimasto il titolo "Le Origini" senza testo sotto, e
        // "La Crescita" — il titolo successivo — declassato a paragrafo.
        if ($lingua === 'it') {
            return str_replace('<h2>Le Origini</h2><p>La Crescita</p><p>Con la partnership', '<h2>La Crescita</h2><p>Con la partnership', $html);
        }

        // In inglese il testo del seeder: "Founded in Scandicci in 1982", e una
        // storia che si ferma alla finale Scudetto. Il club nasce nel 2012 e ha
        // vinto Challenge Cup, CEV Cup e Mondiale per Club.
        if ($lingua === 'en' && str_contains($html, 'Founded in Scandicci in 1982')) {
            return '<h2>Growing up</h2><p>With the strategic partnership of the Savino Del Bene Group, the club has reached historic milestones: winning the European Challenge Cup and CEV Cup and, above all, the Club World Championship.</p>'
                .'<h2>Today</h2><p>Savino Del Bene Volley is now a model of sporting management, with an outstanding youth sector and a vision set firmly on the future.</p>';
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function timelineStoria(array $v, string $lingua): array
    {
        $prima = $v['timeline'][0]['year'] ?? null;

        if ($lingua !== 'en' || $prima !== '1982') {
            return $v;
        }

        $v['timeline'] = [
            ['year' => '2012', 'title' => 'The beginnings', 'description' => 'Thanks to the passion of Paolo Nocentini, President and CEO of Savino Del Bene SpA, the team started out in Serie B1 and ended its first season one step away from promotion to Serie A2.'],
            ['year' => '2014-15', 'title' => 'Into Serie A1', 'description' => 'Acquiring the sporting title of IHF Volleyball brought admission to the Italian top flight for the 2014-15 season.'],
            ['year' => '2015-16', 'title' => 'First time in the Play Offs', 'description' => 'The second year in Serie A1 brought the first qualification for the Scudetto Play Offs and the Italian Cup.'],
            ['year' => '2016-17', 'title' => 'The first Final Four', 'description' => 'In its third top-flight season the team played in the Italian Cup Final Four for the first time.'],
            ['year' => '2018-19', 'title' => 'The European debut', 'description' => 'Second place in the 2017-18 regular season earned the right to play in Europe for the first time — and through the front door, with an immediate debut in the CEV Champions League.'],
            ['year' => '2021-22', 'title' => 'The first trophy in club history', 'description' => 'Beating Spain’s CV Haris in the final, Savino Del Bene Volley won the Challenge Cup, the third most important continental competition.'],
            ['year' => '2022-23', 'title' => 'The second European trophy', 'description' => 'A second European title came with the win over Romania’s Alba Blaj in the final of the CEV Cup, the second most prestigious continental competition.'],
            ['year' => '2023-24', 'title' => 'The first Scudetto final', 'description' => 'After the European successes came a landmark at home: the team beat Allianz Vero Volley Milano in the semi-final and reached the Italian championship Finals for the first time.'],
            ['year' => '2024-25', 'title' => 'Among Europe’s top four', 'description' => 'Another historic milestone: the first CEV Champions League Final Four in club history, and a silver medal that earned a place at the Club World Championship.'],
            ['year' => '2025-26', 'title' => 'World Champions', 'description' => 'On 14 December 2025 Savino Del Bene Volley reached the top of the world, winning the FIVB Club World Championship final in São Paulo, Brazil.'],
            ['year' => '2025-26', 'title' => 'The first Italian Cup final', 'description' => 'The magical 2025-26 season added another milestone: coach Gaspari’s team played its first Italian Cup final and earned the right to play the 2026 Italian Super Cup.'],
        ];

        return $v;
    }

    private function testoOrganigramma(string $html, string $lingua): string
    {
        // "This page is managed from the CMS." era pubblicato così com'era.
        return $lingua === 'en' && str_contains($html, 'This page is managed from the CMS')
            ? '<p>The Savino Del Bene Volley organisation chart.</p>'
            : $html;
    }

    private function testoDoubleFace(string $html, string $lingua): string
    {
        return $lingua === 'en' && str_contains($html, 'tactical depth, behind the scenes')
            ? '<h2>Double Face</h2><p>The official Savino Del Bene Volley podcast. Exclusive interviews with the players, hosted by our Brand Ambassador Veronica Angeloni and Head of Communications Fabio Ferri.</p>'
            : $html;
    }

    /**
     * Il link del pulsante non ha lingua e ora ripiega da solo sull'italiano;
     * il testo no: senza, in inglese il pulsante non compariva.
     *
     * @param  array<string, mixed>  $v
     * @param  array<string, mixed>  $tutte
     * @return array<string, mixed>
     */
    private function pulsanteInInglese(array $v, string $lingua, array $tutte, string $testo): array
    {
        if ($lingua === 'en' && trim((string) ($v['button_text'] ?? '')) === '' && trim((string) ($tutte['it']['button_text'] ?? '')) !== '') {
            $v['button_text'] = $testo;
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function diventaSponsor(array $v, string $lingua): array
    {
        $url = (string) ($v['button_url'] ?? '');

        if ($lingua === 'en' && str_ends_with($url, 'Richiesta%20di%20sponsorizzazione')) {
            $v['button_url'] = str_replace('Richiesta%20di%20sponsorizzazione', 'Sponsorship%20enquiry', $url);
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function settoreGiovanile(array $v, string $lingua): array
    {
        // Numeri del seeder: in italiano la redazione ha scritto 3+ / 10 / 6.
        if ($lingua === 'en' && ($v['stat_years'] ?? null) === '15+' && ($v['stat_coaches'] ?? null) === '12' && ($v['stat_categories'] ?? null) === '4') {
            $v['stat_years'] = '3+';
            $v['stat_coaches'] = '10';
            $v['stat_categories'] = '6';
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $v
     * @param  array<string, mixed>  $tutte
     * @return array<string, mixed>
     */
    private function palazzetto(array $v, string $lingua, array $tutte): array
    {
        if ($lingua !== 'en') {
            return $v;
        }

        // Il link inglese alla mappa era quello del seeder e risponde 404.
        if (($v['maps_link'] ?? null) === 'https://maps.app.goo.gl/BXZz1R6Z3sX3Y3e97') {
            $v['maps_link'] = $tutte['it']['maps_link'] ?? null;
        }

        // Quattro servizi del seeder ("Disabled Access", "Ample Parking"…) contro i
        // due che la redazione ha lasciato in italiano.
        $nomi = array_map(fn ($s) => $s['name'] ?? null, is_array($v['services'] ?? null) ? $v['services'] : []);

        if ($nomi === ['Capacity 3500 Seats', 'Disabled Access', 'Ample Parking', 'Bar & Hospitality Area']) {
            $v['services'] = [
                ['icon' => $v['services'][0]['icon'] ?? null, 'name' => 'Capacity 3,500+ seats'],
                ['icon' => $v['services'][3]['icon'] ?? null, 'name' => 'Bar & Hospitality Area'],
            ];
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function abbonamenti(array $v, string $lingua): array
    {
        if ($lingua !== 'en') {
            return $v;
        }

        // Si paga su Vivaticket, non "with credit card or PayPal".
        if (str_contains((string) ($v['online_description'] ?? ''), 'credit card or PayPal')) {
            $v['online_description'] = 'Buy your season ticket conveniently online through our ticketing system. Secure payment through the Vivaticket portal.';
        }

        // "Più popolare" in italiano è la Tribuna Est e Sud, in inglese era la Ovest.
        if (is_array($v['plans'] ?? null)) {
            $evidenziati = array_keys(array_filter($v['plans'], fn ($p): bool => ! empty($p['highlight'])));
            $nomi = array_column($v['plans'], 'name');
            $giusto = array_search('East and South Stand', $nomi, true);

            if ($giusto !== false && $evidenziati === [array_search('West Stand', $nomi, true)]) {
                foreach ($v['plans'] as $i => $piano) {
                    $v['plans'][$i]['highlight'] = $i === $giusto;
                }
            }
        }

        // Un vantaggio generico che in italiano la redazione ha tolto.
        if (is_array($v['benefits'] ?? null)) {
            $v['benefits'] = array_values(array_filter(
                $v['benefits'],
                fn ($b): bool => ! str_starts_with((string) ($b['text'] ?? ''), 'Every season ticket holder gets discounts on merchandising'),
            ));
        }

        return $v;
    }

    private function riassuntoSettoreGiovanile(): void
    {
        $pagina = DB::table('pages')->where('slug', 'settore-giovanile')->first(['id', 'excerpt', 'meta_description']);

        if (! is_object($pagina)) {
            return;
        }

        $modifiche = [];

        foreach (['excerpt', 'meta_description'] as $colonna) {
            $lingue = json_decode((string) $pagina->{$colonna}, true);

            if (is_array($lingue) && str_contains((string) ($lingue['en'] ?? ''), 'Under 18, Under 16, Under 14 and Under 13')) {
                $lingue['en'] = str_replace('Under 18, Under 16, Under 14 and Under 13', 'Under 19, Under 17 and Under 15', $lingue['en']);
                $modifiche[$colonna] = json_encode($lingue, JSON_UNESCAPED_UNICODE);
            }
        }

        if ($modifiche !== []) {
            DB::table('pages')->where('id', $pagina->id)->update($modifiche + ['updated_at' => now()]);
            Cache::forget('public:page:settore-giovanile:en');
        }
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function refusiDellaTimeline(array $v, string $lingua): array
    {
        if ($lingua !== 'it' || ! is_array($v['timeline'] ?? null)) {
            return $v;
        }

        foreach ($v['timeline'] as $i => $tappa) {
            if (is_string($tappa['description'] ?? null)) {
                $v['timeline'][$i]['description'] = trim(str_replace('con la ragazze della', 'con le ragazze della', $tappa['description']));
            }

            // Una sola voce aveva il trattino lungo: "2024–25".
            if (is_string($tappa['year'] ?? null)) {
                $v['timeline'][$i]['year'] = str_replace('–', '-', $tappa['year']);
            }
        }

        return $v;
    }

    /**
     * Il modulo d'iscrizione del Talent Day (sito esterno) risponde 404: le
     * tappe sono concluse e la pagina è stata tolta.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function moduloTalentDay(array $v): array
    {
        if (($v['signup_url'] ?? null) === 'https://www.fusionteamvolley.it/ERP/talent-day/') {
            $v['signup_url'] = null;
        }

        return $v;
    }

    private function testoAccessibilita(string $html, string $lingua): string
    {
        // Il titolo della pagina è già l'H1: l'<h2> identico lo ripeteva subito
        // sotto. E cinque paragrafi vuoti facevano da spaziatura.
        $titoli = ['it' => 'Accessibilità', 'en' => 'Accessibility'];
        $titolo = $titoli[$lingua] ?? null;

        if ($titolo !== null && str_starts_with($html, "<h2>{$titolo}</h2>")) {
            $html = substr($html, strlen("<h2>{$titolo}</h2>"));
        }

        return str_replace('<p><br></p>', '', $html);
    }

    private function riassuntoConvenzioniInItaliano(): void
    {
        // La descrizione per Google parlava di "gruppi, scuole e associazioni":
        // le convenzioni sono gli sconti dei partner per gli abbonati.
        $pagina = DB::table('pages')->where('slug', 'convenzioni')->first(['id', 'meta_description']);
        $lingue = is_object($pagina) ? json_decode((string) $pagina->meta_description, true) : null;

        if (is_array($lingue) && str_contains((string) ($lingue['it'] ?? ''), 'per gruppi, scuole e associazioni')) {
            $lingue['it'] = 'Sconti e agevolazioni per gli abbonati della Savino Del Bene Volley presso i partner del club.';

            DB::table('pages')->where('id', $pagina->id)->update([
                'meta_description' => json_encode($lingue, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
            Cache::forget('public:page:convenzioni:it');
        }
    }

    private function vociDiMenuESponsor(): void
    {
        // Il partner di Volley 4 All è "Allenamente", come scrive la pagina.
        foreach (DB::table('menu_items')->where('description', 'like', '%AllunaMente%')->get(['id', 'description']) as $voce) {
            DB::table('menu_items')->where('id', $voce->id)->update([
                'description' => str_replace('AllunaMente', 'Allenamente', (string) $voce->description),
            ]);
        }

        // Il vecchio indirizzo di Erreà non risolve più.
        DB::table('sponsors')->where('url', 'http://it.errea.com/')->update(['url' => 'https://www.errea.com/']);
    }

    // ------------------------------------------------------------------
    // Attrezzi
    // ------------------------------------------------------------------

    /**
     * @param  callable(array<string, mixed>, string, array<string, mixed>): array<string, mixed>  $trasforma
     */
    private function dati(string $slug, callable $trasforma): void
    {
        $this->colonna($slug, 'content_data', function (array $lingue) use ($trasforma): array {
            foreach ($lingue as $lingua => $valori) {
                if (is_array($valori) && ! array_is_list($valori)) {
                    $lingue[$lingua] = $trasforma($valori, (string) $lingua, $lingue);
                }
            }

            return $lingue;
        });
    }

    /**
     * @param  callable(string, string): string  $trasforma
     */
    private function testo(string $slug, callable $trasforma): void
    {
        $this->colonna($slug, 'content', function (array $lingue) use ($trasforma): array {
            foreach ($lingue as $lingua => $html) {
                if (is_string($html) && $html !== '') {
                    $lingue[$lingua] = $trasforma($html, (string) $lingua);
                }
            }

            return $lingue;
        });
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $trasforma
     */
    private function colonna(string $slug, string $colonna, callable $trasforma): void
    {
        foreach (DB::table('pages')->where('slug', $slug)->get(['id', $colonna]) as $pagina) {
            $lingue = json_decode((string) $pagina->{$colonna}, true);

            if (! is_array($lingue)) {
                continue;
            }

            $nuove = $trasforma($lingue);

            if ($nuove === $lingue) {
                continue;
            }

            DB::table('pages')->where('id', $pagina->id)->update([
                $colonna => json_encode($nuove, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            foreach (array_keys($nuove) as $lingua) {
                Cache::forget('public:page:'.$slug.':'.$lingua);
            }
        }
    }
};
