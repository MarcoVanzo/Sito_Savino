<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Revisione dei contenuti dopo le prime settimane di lavoro della redazione
 * (20 settembre 2026): link rotti, doppioni, date impossibili e versioni
 * inglesi rimaste ai testi di esempio del seeder, che online affermavano cose
 * false (fondi raccolti, sitting volley, convenzioni per i gruppi).
 *
 * Ogni correzione ha una guardia: tocca un valore solo se è ancora quello
 * letto in produzione il giorno della revisione. Se nel frattempo la redazione
 * lo ha cambiato, vale il suo. Rilanciarla non cambia nulla.
 */
return new class extends Migration
{
    private const MISSIONE_SEED = [
        'it' => 'La Savino Del Bene crede fermamente nel potere trasformativo dello sport.',
        'en' => 'Savino Del Bene firmly believes in the transformative power of sport.',
    ];

    /**
     * I due protocolli che il footer pubblica e la pagina Safeguarding no.
     * Il percorso si legge dalle impostazioni (Documenti Legali), dove il PDF è
     * già caricato: [titolo, descrizione] per lingua.
     */
    private const PROTOCOLLI = [
        'legal.protocollo_bullismo' => [
            'parole' => ['bullismo', 'bullying'],
            'it' => ['Protocollo Bullismo e Cyberbullismo', 'Misure di prevenzione e di intervento contro bullismo e cyberbullismo.'],
            'en' => ['Anti-Bullying and Cyberbullying Protocol', 'Prevention and response measures against bullying and cyberbullying.'],
        ],
        'legal.protocollo_razzismo' => [
            'parole' => ['razzismo', 'racism'],
            'it' => ['Protocollo Razzismo e Xenofobia', 'Misure di prevenzione e di contrasto di ogni forma di razzismo e xenofobia.'],
            'en' => ['Anti-Racism and Xenophobia Protocol', 'Measures to prevent and counter every form of racism and xenophobia.'],
        ],
    ];

    private const ICONA_PROTOCOLLO = 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z';

    /** Pagine con il titolo nell'hero: un <h2> uguale in cima al testo lo ripete. */
    private const PAGINE_CON_TITOLO_NELL_HERO = ['volley-4-all', 'progetto-scuola', 'convenzioni', 'biglietteria'];

    public function up(): void
    {
        $this->dati('hospitality', fn (array $v): array => $this->hospitality($v));
        $this->dati('club-race', fn (array $v, string $l): array => $this->clubRace($v, $l));
        $this->dati('abbonamenti', fn (array $v, string $l, array $tutte): array => $this->abbonamenti($v, $l, $tutte));
        $this->dati('double-face', fn (array $v): array => $this->doubleFace($v));
        $this->dati('volley-4-all', fn (array $v, string $l): array => $this->volley4All($v, $l));
        $this->dati('progetto-scuola', fn (array $v, string $l): array => $this->progettoScuola($v, $l));
        $this->dati('progetti-sociali', fn (array $v, string $l): array => $this->progettiSociali($v, $l));
        $this->dati('contatti', fn (array $v, string $l): array => $this->contatti($v, $l));
        $this->dati('cartelle-stampa', fn (array $v, string $l): array => $this->cartelleStampa($v, $l));
        $this->dati('talent-day', fn (array $v): array => $this->talentDay($v));
        $this->dati('safeguarding', fn (array $v, string $l, array $tutte): array => $this->safeguarding($v, $l, $tutte));

        $this->testo('club-race', fn (string $html, string $l): string => $this->regolamentoClubRace($html, $l));
        $this->testo('volley-4-all', fn (string $html, string $l): string => $this->testoVolley4All($html, $l));
        $this->testo('progetto-scuola', fn (string $html, string $l): string => $this->testoProgettoScuola($html, $l));
        $this->testo('convenzioni', fn (string $html, string $l): string => $this->testoConvenzioni($html, $l));
        $this->testo('magazine', fn (string $html, string $l): string => $this->testoMagazine($html, $l));
        $this->testo('settore-giovanile', fn (string $html, string $l): string => $this->testoSettoreGiovanile($html, $l));

        foreach (self::PAGINE_CON_TITOLO_NELL_HERO as $slug) {
            $this->togliIlTitoloRipetuto($slug);
        }

        $this->riassuntoConvenzioni();

        CachePublicResponse::flush();
    }

    /**
     * Correzioni di testi sbagliati: tornare indietro li ripubblicherebbe.
     */
    public function down(): void {}

    // ------------------------------------------------------------------
    // content_data
    // ------------------------------------------------------------------

    /**
     * Un'email senza `mailto:` in un href è un percorso relativo: il pulsante
     * "Contattaci" portava a /sponsor/marketing@… e quindi a un 404.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function hospitality(array $v): array
    {
        $url = is_string($v['button_url'] ?? null) ? trim($v['button_url']) : '';

        if ($url !== '' && ! str_contains($url, ':') && ! str_contains($url, '/') && filter_var($url, FILTER_VALIDATE_EMAIL)) {
            $v['button_url'] = 'mailto:'.$url;
        }

        return $v;
    }

    /**
     * "Aggiornata al 4 ottobre 2026" con tutti a zero punti: il 4 ottobre è la
     * prima gara in casa, non un aggiornamento. E l'occhiello "Società
     * territorio" non dice niente: la pagina sta sotto Ticketing.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function clubRace(array $v, string $lingua): array
    {
        if ($lingua !== 'it') {
            return $v;
        }

        if (($v['hero_label'] ?? null) === 'Società territorio') {
            $v['hero_label'] = 'Ticketing';
        }

        $classifica = is_array($v['standings'] ?? null) ? $v['standings'] : [];
        $tuttiAZero = $classifica !== [] && array_filter($classifica, fn ($r): bool => (int) ($r['points'] ?? 0) !== 0) === [];

        if (($v['standings_updated'] ?? null) === '4 ottobre 2026' && $tuttiAZero) {
            $v['standings_updated'] = null;

            if (trim((string) ($v['standings_note'] ?? '')) === '') {
                $v['standings_note'] = 'La classifica parte dalla prima gara casalinga, domenica 4 ottobre 2026.';
            }
        }

        return $v;
    }

    /**
     * Gli abbonamenti non si vendono al botteghino e il riquadro era stato
     * riempito con un secondo "Vantaggi", doppione del blocco che lo precede.
     * Ora il riquadro vuoto non compare. In inglese mancava il link d'acquisto.
     *
     * @param  array<string, mixed>  $v
     * @param  array<string, mixed>  $tutte
     * @return array<string, mixed>
     */
    private function abbonamenti(array $v, string $lingua, array $tutte): array
    {
        if ($lingua === 'it' && ($v['boxoffice_title'] ?? null) === 'Vantaggi' && ($v['benefits_heading'] ?? null) === 'Vantaggi') {
            $v['boxoffice_title'] = null;
            $v['boxoffice_description'] = null;
        }

        if ($lingua === 'en') {
            if (($v['boxoffice_title'] ?? null) === 'At the Box Office' && str_starts_with((string) ($v['boxoffice_description'] ?? ''), 'The Pala BigMat box office opens')) {
                $v['boxoffice_title'] = null;
                $v['boxoffice_description'] = null;
            }

            $linkItaliano = $tutte['it']['tickets_url'] ?? null;

            if (trim((string) ($v['tickets_url'] ?? '')) === '' && is_string($linkItaliano) && $linkItaliano !== '') {
                $v['tickets_url'] = $linkItaliano;

                if (in_array($v['tickets_button_text'] ?? null, [null, '', 'Buy tickets'], true)) {
                    $v['tickets_button_text'] = 'Buy your season ticket';
                }
            }
        }

        return $v;
    }

    /**
     * Nel campo del video c'era l'indirizzo del canale YouTube, lo stesso del
     * pulsante "Guarda ora": un canale non si incorpora, e in pagina uscivano
     * due pulsanti verso lo stesso posto.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function doubleFace(array $v): array
    {
        $video = (string) ($v['video_url'] ?? '');

        if ($video !== '' && $video === ($v['button_url'] ?? null) && str_contains($video, 'youtube.com/@')) {
            $v['video_url'] = null;
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function volley4All(array $v, string $lingua): array
    {
        $v = $this->senzaLaMissioneDelSeeder($v, $lingua);

        // Il link del reel copiato da Instagram porta con sé i parametri di
        // tracciamento di chi lo ha copiato.
        $video = (string) ($v['video_url'] ?? '');

        if (str_contains($video, 'instagram.com/') && str_contains($video, '?')) {
            $v['video_url'] = strtok($video, '?');
        }

        // In italiano progetti e numeri sono stati tolti; in inglese restavano
        // quelli di esempio (fondi raccolti, sitting volley).
        if ($lingua === 'en' && $this->haINumeriDelSeeder($v)) {
            $v['projects'] = [];
            $v['impact_stats'] = [];
            $v['impact_title'] = null;
            $v['results_badge'] = null;
            $v['initiatives_badge'] = null;
            $v['initiatives_title'] = null;
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function progettoScuola(array $v, string $lingua): array
    {
        $v = $this->senzaLaMissioneDelSeeder($v, $lingua);

        if ($lingua === 'it' && is_array($v['projects'] ?? null)) {
            foreach ($v['projects'] as $i => $progetto) {
                if (($progetto['title'] ?? null) === 'orientamento allo sport') {
                    $v['projects'][$i]['title'] = 'Orientamento allo sport';
                }

                if (is_string($progetto['description'] ?? null)) {
                    $v['projects'][$i]['description'] = str_replace('Nella classi seconde', 'Nelle classi seconde', $progetto['description']);
                }
            }
        }

        if ($lingua === 'en' && $this->haINumeriDelSeeder($v)) {
            $email = 'federico.latanza@savinodelbenevolley.it';

            $v['projects'] = [
                ['tag' => 'Primary schools', 'icon' => '🎒', 'link' => null, 'color' => 'savino-red', 'title' => 'Savino Del Bene Volley School', 'contact_email' => $email,
                    'description' => 'The activity is introduced in class with Savino, our mascot, who hands out comics and gadgets. It continues with two practical lessons in the school gym and ends with a drawing contest, exhibited at the arena during one of the first team’s matches.'],
                ['tag' => 'Middle schools', 'icon' => '📣', 'link' => null, 'color' => 'savino-blue', 'title' => 'School of supporting', 'contact_email' => $email,
                    'description' => "The project promotes a positive way of supporting in volleyball, with the help of professionals such as Nuova Mente and the Biancoblues, our official supporters’ group.\nFirst-year classes start with technical and practical lessons.\nSecond-year classes follow the theory course of the School of supporting.\nThird-year classes play the Savino Del Bene Volley school and inter-school tournament."],
                ['tag' => 'Sports high schools', 'icon' => '👩‍🏫', 'link' => null, 'color' => 'savino-blue', 'title' => 'Careers in sport', 'contact_email' => $email,
                    'description' => 'A series of meetings in sports-oriented classes, showing students what working in the world of sport can look like.'],
                ['tag' => 'High schools', 'icon' => '🏆', 'link' => null, 'color' => 'savino-fucsia', 'title' => 'Savino Del Bene Volley School Cup', 'contact_email' => $email,
                    'description' => 'Inter-school tournament in the Florence area.'],
            ];

            $v['impact_stats'] = [
                ['label' => 'School projects', 'value' => '4'],
                ['label' => 'Schools involved', 'value' => '37'],
                ['label' => 'Pupils involved', 'value' => '5000+'],
                ['label' => 'Pupils at the arena', 'value' => '2300+'],
            ];
        }

        return $v;
    }

    /**
     * Le schede dei progetti non portavano da nessuna parte, mentre tre di loro
     * hanno una pagina propria nella stessa sezione del menu.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function progettiSociali(array $v, string $lingua): array
    {
        $prefisso = $lingua === 'it' ? '' : '/'.$lingua;

        if ($lingua === 'en' && $this->haINumeriDelSeeder($v)) {
            $v['projects'] = [
                ['tag' => 'Inclusive sport', 'icon' => '🏐', 'link' => null, 'color' => 'savino-blue', 'title' => 'Volley 4 All', 'contact_email' => null,
                    'description' => 'Inclusive volleyball for children aged 7 to 12 with neurodevelopmental disorders'],
                ['tag' => 'Education', 'icon' => '📚', 'link' => null, 'color' => 'savino-fucsia', 'title' => 'School & Sport', 'contact_email' => null,
                    'description' => 'Projects for schools, from primary to high school'],
                ['tag' => 'First steps in sport', 'icon' => '🐥', 'link' => null, 'color' => 'savino-red', 'title' => 'Volley4kids', 'contact_email' => null,
                    'description' => 'Movement workshop for children born in 2021 and 2022'],
                ['tag' => 'Environment', 'icon' => '🌱', 'link' => null, 'color' => 'savino-blue', 'title' => 'Sustainability', 'contact_email' => null,
                    'description' => 'Green initiatives to reduce the environmental impact of sporting events and raise awareness in the community'],
            ];

            $v['impact_stats'] = [
                ['label' => 'Young people involved', 'value' => '5000+'],
                ['label' => 'Partner schools', 'value' => '30+'],
                ['label' => 'Social events', 'value' => '10+'],
                ['label' => 'Sustainability reports', 'value' => '1'],
            ];

            // In italiano il secondo paragrafo (sitting volley, mai esistito) è
            // stato tolto dalla redazione.
            $v['mission_text_2'] = null;
        }

        $pagine = [
            'volley4all' => '/sociale/volley-4-all',
            'volley 4 all' => '/sociale/volley-4-all',
            'scuola & sport' => '/sociale/progetto-scuola',
            'school & sport' => '/sociale/progetto-scuola',
            'sostenibilità' => '/sociale/sostenibilita',
            'sustainability' => '/sociale/sostenibilita',
        ];

        if (is_array($v['projects'] ?? null)) {
            foreach ($v['projects'] as $i => $progetto) {
                $titolo = mb_strtolower(trim((string) ($progetto['title'] ?? '')));

                if (isset($pagine[$titolo]) && trim((string) ($progetto['link'] ?? '')) === '' && trim((string) ($progetto['contact_email'] ?? '')) === '') {
                    $v['projects'][$i]['link'] = $prefisso.$pagine[$titolo];
                }

                // La pagina e la voce di menu si chiamano "Volley 4 All".
                if (($progetto['title'] ?? null) === 'Volley4All') {
                    $v['projects'][$i]['title'] = 'Volley 4 All';
                }
            }
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function contatti(array $v, string $lingua): array
    {
        // Il suggerimento porta a "Diventa Sponsor" ma si chiamava "I nostri Sponsor".
        if ($lingua === 'it' && is_array($v['form_topics'] ?? null)) {
            foreach ($v['form_topics'] as $i => $argomento) {
                if (($argomento['tip_link_url'] ?? null) === '/diventa-sponsor' && ($argomento['tip_link_text'] ?? null) === 'I nostri Sponsor') {
                    $v['form_topics'][$i]['tip_link_text'] = 'Diventa Sponsor';
                }
            }
        }

        // Occhiello e titolo del modulo dicevano la stessa cosa.
        if (($v['form_title'] ?? null) === 'Scrivici' && ($v['form_subtitle'] ?? null) === 'Scrivici direttamente') {
            $v['form_subtitle'] = 'Modulo di contatto';
        }

        if (($v['form_title'] ?? null) === 'Write to Us' && ($v['form_subtitle'] ?? null) === 'Write to us') {
            $v['form_subtitle'] = 'Contact form';
        }

        return $v;
    }

    /**
     * "Logo / Logo SDB Volley / Logo": etichetta, titolo e descrizione uguali.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function cartelleStampa(array $v, string $lingua): array
    {
        if ($lingua !== 'it' || ! is_array($v['press_kits'] ?? null)) {
            return $v;
        }

        foreach ($v['press_kits'] as $i => $cartella) {
            if (($cartella['title'] ?? null) === 'Logo SDB Volley' && ($cartella['description'] ?? null) === 'Logo') {
                $v['press_kits'][$i]['description'] = 'Il logo ufficiale della Savino Del Bene Volley.';
            }
        }

        return $v;
    }

    /**
     * Due tappe di giugno risultavano ancora "Disponibile" a settembre.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function talentDay(array $v): array
    {
        if (! is_array($v['stages'] ?? null)) {
            return $v;
        }

        $passate = ['5 giugno' => 'Conclusa', '29 giugno' => 'Conclusa', '5 June' => 'Closed', '29 June' => 'Closed'];

        foreach ($v['stages'] as $i => $tappa) {
            $data = (string) ($tappa['date'] ?? '');

            if (isset($passate[$data]) && in_array($tappa['status'] ?? null, ['Disponibile', 'Available'], true)) {
                $v['stages'][$i]['status'] = $passate[$data];
                $v['stages'][$i]['sold_out'] = true;
            }
        }

        return $v;
    }

    /**
     * Il footer pubblica quattro documenti, la pagina due: mancavano i
     * protocolli su bullismo e razzismo. Si aggiungono puntando al PDF già
     * caricato nei Documenti Legali — togliere il documento dalla pagina non
     * cancella il file, quindi il link del footer non rischia nulla.
     *
     * In inglese i due documenti già presenti non avevano il file: la scheda
     * usciva senza "Scarica PDF". Prendono quello della scheda italiana.
     *
     * @param  array<string, mixed>  $v
     * @param  array<string, mixed>  $tutte
     * @return array<string, mixed>
     */
    private function safeguarding(array $v, string $lingua, array $tutte): array
    {
        $documenti = is_array($v['documents'] ?? null) ? array_values($v['documents']) : [];
        $italiani = is_array($tutte['it']['documents'] ?? null) ? array_values($tutte['it']['documents']) : [];

        if ($lingua !== 'it') {
            foreach ($documenti as $i => $documento) {
                $file = $italiani[$i]['file'] ?? null;

                if (is_array($documento) && trim((string) ($documento['file'] ?? '')) === '' && is_string($file) && $file !== '') {
                    $documenti[$i]['file'] = $file;
                }
            }
        }

        foreach (self::PROTOCOLLI as $chiave => $protocollo) {
            $percorso = $this->documentoLegale($chiave);
            [$titolo, $descrizione] = $protocollo[$lingua] ?? $protocollo['it'];

            if ($percorso === null || $this->giaInElenco($documenti, $percorso, $protocollo['parole'])) {
                continue;
            }

            $documenti[] = [
                'file' => $percorso,
                'icon' => self::ICONA_PROTOCOLLO,
                'title' => $titolo,
                'description' => $descrizione,
            ];
        }

        if ($documenti !== []) {
            $v['documents'] = $documenti;
        }

        return $v;
    }

    private function documentoLegale(string $chiave): ?string
    {
        $valore = DB::table('site_settings')->where('key', $chiave)->value('value');
        $valore = is_string($valore) ? trim($valore) : '';

        // Le impostazioni tradotte tengono un JSON per lingua.
        if (str_starts_with($valore, '{')) {
            $lingue = json_decode($valore, true);
            $valore = is_array($lingue) ? trim((string) ($lingue['it'] ?? '')) : '';
        }

        return $valore === '' ? null : $valore;
    }

    /**
     * @param  list<mixed>  $documenti
     * @param  list<string>  $parole
     */
    private function giaInElenco(array $documenti, string $percorso, array $parole): bool
    {
        foreach ($documenti as $documento) {
            if (! is_array($documento)) {
                continue;
            }

            if (basename((string) ($documento['file'] ?? '')) === basename($percorso)) {
                return true;
            }

            $titolo = mb_strtolower((string) ($documento['title'] ?? ''));

            foreach ($parole as $parola) {
                if (str_contains($titolo, $parola)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Le tre pagine della sezione Sociale partivano con la stessa "missione"
     * del seeder. Resta su Progetti Sociali, che è la pagina d'insieme; sulle
     * pagine dei singoli progetti era un doppione che parlava d'altro (sitting
     * volley, iniziative ambientali) prima del racconto del progetto.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function senzaLaMissioneDelSeeder(array $v, string $lingua): array
    {
        $seed = self::MISSIONE_SEED[$lingua] ?? null;

        if ($seed !== null && str_starts_with((string) ($v['mission_text_1'] ?? ''), $seed)) {
            $v['mission_badge'] = null;
            $v['mission_title'] = null;
            $v['mission_text_1'] = null;
            $v['mission_text_2'] = null;
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private function haINumeriDelSeeder(array $v): bool
    {
        return ($v['impact_stats'][3]['value'] ?? null) === '€50K';
    }

    // ------------------------------------------------------------------
    // content (testo dell'editor)
    // ------------------------------------------------------------------

    private function regolamentoClubRace(string $html, string $lingua): string
    {
        if ($lingua === 'it') {
            // I premi erano tre <h2> allo stesso livello di "Regolamento".
            if (! str_contains($html, '<h2>Premi</h2>')) {
                $html = str_replace('<h2><strong>1° Classificato</strong></h2>', '<h2>Premi</h2><h3>1° Classificato</h3>', $html);
            }

            return str_replace(
                ['<h2><strong>2° Classificato</strong></h2>', '<h2><strong>3° Classificato</strong></h2>', 'playoff<strong> </strong>presso', '<p><br></p>', 'x1&nbsp;</p>'],
                ['<h3>2° Classificato</h3>', '<h3>3° Classificato</h3>', 'playoff presso', '', 'x1</p>'],
                $html,
            );
        }

        if ($lingua === 'en' && trim($html) === '<h2>Rules</h2><p>How points are awarded and what the prizes are.</p>') {
            return '<h2>Rules</h2>'
                .'<p>An initiative for all the volleyball teams that support Savino Del Bene Volley, rewarding the most loyal one.</p>'
                .'<p>At every home match we count the spectators present at Pala BigMat for each team and award points according to the criteria below. After each match we publish the overall standings, so every team can see where it stands.</p>'
                .'<p>For every match, one contact person per team must email ticketing@savinodelbenevolley.it with the tickets requested, split into athletes, companions and companions under 16. The race runs until the home match against Cuneo, at the end of the Regular Season.</p>'
                .'<h2>Prizes</h2>'
                .'<h3>1st place</h3><p>A <strong>training session at the Scandicci sports hall</strong> (day and time to be agreed; up to 15 athletes and 2 coaches) with coaches <strong>Gaspari and Kantor</strong> + attendance at a <strong>pre-match training session</strong> during the playoffs at Pala BigMat (up to 30 people) with a short photo and autograph session + <strong>1 ball signed</strong> by the whole team and <strong>1 match jersey</strong> with the club’s name</p>'
                .'<h3>2nd place</h3><p>Attendance at a <strong>pre-match training session</strong> during the playoffs at Pala BigMat (up to 30 people) with a short photo and autograph session + <strong>1 ball signed</strong> by the whole team</p>'
                .'<h3>3rd place</h3><p>A dedicated online session with <strong>nutritionist</strong> Alessandra Simone (day and time to be agreed; up to 15 people) + a <strong>video greeting from a player</strong></p>'
                .'<h2>How points are awarded</h2>'
                .'<p><em>Affiliation</em>: non-affiliated club x0.5 / affiliated club x1</p>'
                .'<p><em>Distance</em> of the club’s registered office from Pala BigMat: 0-20 km x0.5 / 21-50 km x1 / 51-100 km x1.5 / 101-200 km x2 / over 200 km x2.5</p>'
                .'<p><em>Match day</em>: weekend x1 / midweek match x2</p>'
                .'<p><em>Loyalty</em>: attending 3 consecutive matches multiplies the number of participants x1.5 / 4 consecutive matches x2 / 5 consecutive matches x2.5 / 6 consecutive matches x3</p>';
        }

        return $html;
    }

    private function testoVolley4All(string $html, string $lingua): string
    {
        if ($lingua === 'it') {
            return preg_replace('#(?:<p><br></p>)+$#', '', $html) ?? $html;
        }

        // Il testo inglese del seeder descriveva un altro progetto (disabilità,
        // disagio sociale): Volley 4 All è per bambine con disturbo del neurosviluppo.
        if ($lingua === 'en' && str_contains($html, 'A project that breaks down barriers')) {
            return '<p>Volley 4 All was born from the partnership between Savino Del Bene and the Allenamente learning and research centre. It gives girls aged 7 to 12 with neurodevelopmental disorders — autism spectrum in particular — the chance to experience volleyball as inclusion, growth and fun, together with typically developing teammates from the Savino Del Bene Volley beginners’ groups.</p>'
                .'<p>The adapted activities foster socialisation, cooperation, concentration and communication, building social and cognitive skills in a safe, structured setting. Playing with the ball becomes a bridge for communication: giving and receiving, acceptance, relating to others.</p>'
                .'<p>Volley 4 All shows that volleyball can be much more than a game: a place to grow in autonomy, develop social skills and improve physical and mental well-being.</p>'
                .'<h3>Main benefits</h3>'
                .'<ul><li>Social inclusion: integration in a playful team setting.</li>'
                .'<li>Cognitive development: better attention, cooperation and ability to respond appropriately to requests.</li>'
                .'<li>Managing emotions and communication: the game becomes a physical and emotional dialogue.</li>'
                .'<li>Routine and structure: predictability, safety and regularity, which are essential for well-being.</li></ul>';
        }

        return $html;
    }

    private function testoProgettoScuola(string $html, string $lingua): string
    {
        if ($lingua === 'it') {
            return str_replace('aumentando&nbsp; la conoscenza', 'aumentando la conoscenza', $html);
        }

        if ($lingua === 'en' && str_contains($html, 'Through lessons, demonstrations, and interschool tournaments')) {
            return '<p>Savino Del Bene Volley brings volleyball and the values of sport into local schools. Through our school projects we want to create positive, formative experiences that build trust, belonging and recognition of our club, supporting young people as they grow and making our activities better known among pupils and families.</p>';
        }

        return $html;
    }

    private function testoConvenzioni(string $html, string $lingua): string
    {
        // Il testo inglese del seeder parlava di tariffe per gruppi e scuole:
        // le convenzioni sono gli sconti dei partner per gli abbonati.
        if ($lingua === 'en' && str_contains($html, 'discounted rates for organized groups')) {
            return '<p>Savino Del Bene Volley season ticket holders get discounts and special offers at our partners. Find out more!</p>';
        }

        return $html;
    }

    private function testoMagazine(string $html, string $lingua): string
    {
        // "Double Face" è il podcast, non il magazine.
        if ($lingua === 'en') {
            return str_replace('every issue of "Double Face", the official Savino Del Bene Volley magazine,', 'every issue of the official Savino Del Bene Volley magazine', $html);
        }

        return $html;
    }

    private function testoSettoreGiovanile(string $html, string $lingua): string
    {
        // Paragrafo del seeder in fondo alla pagina, dopo l'invito a scrivere:
        // ripete l'introduzione "Il vivaio". In inglese era già vuoto.
        $seed = '<p>SDB Volley Youth rappresenta il progetto giovanile della società. Attraverso un programma di formazione strutturato, le nostre giovani atlete crescono seguendo i valori del club.</p>';

        return $lingua === 'it' && trim($html) === $seed ? '' : $html;
    }

    /**
     * L'hero mostra già il titolo della pagina: un <h2> identico in cima al
     * testo dell'editor lo ripeteva subito sotto.
     */
    private function togliIlTitoloRipetuto(string $slug): void
    {
        $pagina = DB::table('pages')->where('slug', $slug)->first(['title']);
        $titoli = is_object($pagina) ? json_decode((string) $pagina->title, true) : null;

        if (! is_array($titoli)) {
            return;
        }

        $this->testo($slug, function (string $html, string $lingua) use ($titoli): string {
            $titolo = $this->normalizza((string) ($titoli[$lingua] ?? ''));

            if ($titolo === '' || ! preg_match('#^\s*<h2>(.*?)</h2>#su', $html, $trovato)) {
                return $html;
            }

            return $this->normalizza(strip_tags($trovato[1])) === $titolo
                ? ltrim(substr($html, strlen($trovato[0])))
                : $html;
        });
    }

    private function riassuntoConvenzioni(): void
    {
        $seed = 'Agreements and benefits for groups, schools, and associations to attend Savino Del Bene Volley matches.';
        $nuovo = 'Discounts and benefits for Savino Del Bene Volley season ticket holders at our partners.';

        $pagina = DB::table('pages')->where('slug', 'convenzioni')->first(['id', 'excerpt', 'meta_description']);

        if (! is_object($pagina)) {
            return;
        }

        $modifiche = [];

        foreach (['excerpt', 'meta_description'] as $colonna) {
            $valori = json_decode((string) $pagina->{$colonna}, true);

            if (is_array($valori) && str_starts_with((string) ($valori['en'] ?? ''), $seed)) {
                $valori['en'] = $nuovo;
                $modifiche[$colonna] = json_encode($valori, JSON_UNESCAPED_UNICODE);
            }
        }

        if ($modifiche !== []) {
            DB::table('pages')->where('id', $pagina->id)->update($modifiche + ['updated_at' => now()]);
            Cache::forget('public:page:convenzioni:en');
        }
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

    private function normalizza(string $testo): string
    {
        return mb_strtolower(trim(html_entity_decode($testo, ENT_QUOTES | ENT_HTML5)));
    }
};
