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

        $this->command->info('✅ ' . count($pages) . ' pagine CMS create/aggiornate con successo.');
    }

    /**
     * Genera una entry CMS in formato compatto.
     */
    private function page(string $slug, string $title, string $template, string $meta, string $content = ''): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'template' => $template,
            'meta_description' => $meta,
            'content' => $content,
        ];
    }

    /**
     * Definisce le 22 pagine CMS raggruppate per area tematica.
     *
     * @return array<array<string, string>>
     */
    private function buildPages(): array
    {
        return [
            // === SOCIETÀ ===
            $this->page('organigramma', 'Organigramma', 'Public/Societa',
                'L\'organigramma ufficiale della Savino Del Bene Volley. Scopri il team dirigenziale e lo staff del club.',
                '<p>L\'organigramma della Savino Del Bene Volley. Questa pagina è gestita dal CMS.</p>'),
            $this->page('storia', 'Storia del Club', 'Public/ContentPage',
                'La storia della Savino Del Bene Volley: dal 1982 ad oggi, un percorso di crescita e successi nella pallavolo femminile italiana.',
                '<h2>Le Origini</h2><p>Fondata nel 1982 a Scandicci, la Savino Del Bene Volley è diventata una delle realtà più importanti della pallavolo femminile italiana.</p><h2>La Crescita</h2><p>Con la partnership strategica del Gruppo Savino Del Bene, il club ha raggiunto traguardi storici: la Finale Scudetto, la partecipazione alla CEV Champions League.</p><h2>Il Presente</h2><p>Oggi la Savino Del Bene Volley rappresenta un modello di gestione sportiva, con un settore giovanile d\'eccellenza e una visione proiettata verso il futuro.</p>'),
            $this->page('contatti', 'Contatti', 'Public/Contatti',
                'Contatta la Savino Del Bene Volley. Trova i nostri recapiti, l\'indirizzo della sede e il form di contatto.'),
            $this->page('palazzetto', 'Il Palazzetto', 'Public/ContentPage',
                'Palazzo Wanny, la casa della Savino Del Bene Volley a Firenze. Capienza, come arrivare e servizi dell\'impianto.',
                '<h2>Palazzo Wanny</h2><p>Il Palazzo Wanny di Firenze è la casa della Savino Del Bene Volley. Con una capienza di oltre 4.000 posti, l\'impianto offre un\'esperienza unica per tifosi e appassionati di pallavolo.</p><h2>Come Arrivare</h2><p>Via del Tridente, 5 — 50127 Firenze (FI). Facilmente raggiungibile con i mezzi pubblici e con ampio parcheggio disponibile.</p><h2>Servizi</h2><p>Bar, area hospitality, accesso disabili, parcheggio custodito.</p>'),

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
            $this->page('title-sponsor', 'Title Sponsor', 'Public/Sponsor',
                'Savino Del Bene S.p.A., title sponsor della Savino Del Bene Volley. Scopri la partnership.',
                '<h2>Savino Del Bene S.p.A.</h2><p>Il Gruppo Savino Del Bene, leader mondiale nella logistica e nelle spedizioni internazionali, è il title sponsor del club sin dalla sua fondazione. Una partnership che unisce eccellenza imprenditoriale e passione sportiva.</p>'),
            $this->page('diventa-sponsor', 'Diventa Sponsor', 'Public/Sponsor',
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
                '<h2>Double Face</h2><p>Il magazine ufficiale della Savino Del Bene Volley. Interviste esclusive con le atlete, approfondimenti tattici, dietro le quinte e storie dal settore giovanile. Disponibile in formato digitale.</p>'),
        ];
    }
}
