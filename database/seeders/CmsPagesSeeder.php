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
        $pages = [
            // === SOCIETÀ ===
            [
                'title' => 'Organigramma',
                'slug' => 'organigramma',
                'template' => 'Public/Societa',
                'meta_description' => 'L\'organigramma ufficiale della Savino Del Bene Volley. Scopri il team dirigenziale e lo staff del club.',
                'content' => '<p>L\'organigramma della Savino Del Bene Volley. Questa pagina è gestita dal CMS.</p>',
            ],
            [
                'title' => 'Storia del Club',
                'slug' => 'storia',
                'template' => 'Public/ContentPage',
                'meta_description' => 'La storia della Savino Del Bene Volley: dal 1982 ad oggi, un percorso di crescita e successi nella pallavolo femminile italiana.',
                'content' => '<h2>Le Origini</h2><p>Fondata nel 1982 a Scandicci, la Savino Del Bene Volley è diventata una delle realtà più importanti della pallavolo femminile italiana.</p><h2>La Crescita</h2><p>Con la partnership strategica del Gruppo Savino Del Bene, il club ha raggiunto traguardi storici: la Finale Scudetto, la partecipazione alla CEV Champions League.</p><h2>Il Presente</h2><p>Oggi la Savino Del Bene Volley rappresenta un modello di gestione sportiva, con un settore giovanile d\'eccellenza e una visione proiettata verso il futuro.</p>',
            ],
            [
                'title' => 'Contatti',
                'slug' => 'contatti',
                'template' => 'Public/Contatti',
                'meta_description' => 'Contatta la Savino Del Bene Volley. Trova i nostri recapiti, l\'indirizzo della sede e il form di contatto.',
                'content' => '',
            ],
            [
                'title' => 'Il Palazzetto',
                'slug' => 'palazzetto',
                'template' => 'Public/ContentPage',
                'meta_description' => 'Palazzo Wanny, la casa della Savino Del Bene Volley a Firenze. Capienza, come arrivare e servizi dell\'impianto.',
                'content' => '<h2>Palazzo Wanny</h2><p>Il Palazzo Wanny di Firenze è la casa della Savino Del Bene Volley. Con una capienza di oltre 4.000 posti, l\'impianto offre un\'esperienza unica per tifosi e appassionati di pallavolo.</p><h2>Come Arrivare</h2><p>Via del Tridente, 5 — 50127 Firenze (FI). Facilmente raggiungibile con i mezzi pubblici e con ampio parcheggio disponibile.</p><h2>Servizi</h2><p>Bar, area hospitality, accesso disabili, parcheggio custodito.</p>',
            ],

            // === TICKETING ===
            [
                'title' => 'Campagna Abbonamenti',
                'slug' => 'abbonamenti',
                'template' => 'Public/Ticketing',
                'meta_description' => 'Abbonamenti Savino Del Bene Volley 2026/2027. Scopri le formule e i prezzi per la nuova stagione.',
                'content' => '<h2>Campagna Abbonamenti 2026/2027</h2><p>Vivi tutte le emozioni della Serie A1 e della Champions League con l\'abbonamento stagionale. Scegli la formula più adatta a te e assicurati il tuo posto al Palazzo Wanny.</p>',
            ],
            [
                'title' => 'Biglietteria',
                'slug' => 'biglietteria',
                'template' => 'Public/Ticketing',
                'meta_description' => 'Acquista i biglietti per le partite della Savino Del Bene Volley. Prezzi, punti vendita e modalità di acquisto.',
                'content' => '<h2>Biglietteria</h2><p>I biglietti per le partite casalinghe della Savino Del Bene Volley sono acquistabili online e presso i punti vendita autorizzati.</p>',
            ],
            [
                'title' => 'Convenzioni',
                'slug' => 'convenzioni',
                'template' => 'Public/ContentPage',
                'meta_description' => 'Convenzioni e agevolazioni per gruppi, scuole e associazioni per assistere alle partite della Savino Del Bene Volley.',
                'content' => '<h2>Convenzioni</h2><p>La Savino Del Bene Volley offre tariffe agevolate per gruppi organizzati, scuole, associazioni sportive e aziende partner. Contattaci per ricevere un preventivo personalizzato.</p>',
            ],
            [
                'title' => 'Accessibilità',
                'slug' => 'accessibilita',
                'template' => 'Public/ContentPage',
                'meta_description' => 'Informazioni sull\'accessibilità del Palazzo Wanny per persone con disabilità. Posti riservati e servizi dedicati.',
                'content' => '<h2>Accessibilità</h2><p>Il Palazzo Wanny è dotato di posti riservati per persone con disabilità motoria, accesso facilitato, servizi igienici dedicati e personale formato per l\'assistenza. Per informazioni e prenotazioni, contatta la segreteria.</p>',
            ],

            // === YOUTH / ACADEMY ===
            [
                'title' => 'Settore Giovanile',
                'slug' => 'settore-giovanile',
                'template' => 'Public/Youth',
                'meta_description' => 'Il settore giovanile della Savino Del Bene Volley. Under 18, Under 16, Under 14 e Under 13.',
                'content' => '<p>Il settore giovanile rappresenta il cuore pulsante del progetto sportivo. Attraverso un programma di formazione strutturato, le nostre giovani atlete crescono seguendo i valori del club.</p>',
            ],
            [
                'title' => 'Talent Day & Recruiting',
                'slug' => 'talent-day',
                'template' => 'Public/SummerCamp',
                'meta_description' => 'Talent Day della Savino Del Bene Volley. Partecipa alle selezioni per entrare nel settore giovanile.',
                'content' => '<h2>Talent Day</h2><p>Ogni anno la Savino Del Bene Volley organizza giornate di selezione aperte a tutte le giovani atlete che desiderano entrare a far parte del settore giovanile. Monitora questa pagina per le prossime date.</p>',
            ],
            [
                'title' => 'Summer Camp & Experience',
                'slug' => 'summer-camp',
                'template' => 'Public/SummerCamp',
                'meta_description' => 'Summer Camp Savino Del Bene Volley. Settimane di sport, divertimento e formazione con le atlete della Serie A1.',
                'content' => '<h2>Summer Camp 2026</h2><p>Un\'esperienza unica per le giovani pallavoliste: allenamenti con lo staff tecnico della prima squadra, tornei, attività ricreative e la possibilità di incontrare le atlete della Serie A1.</p>',
            ],
            [
                'title' => 'Progetto Scuola',
                'slug' => 'progetto-scuola',
                'template' => 'Public/ContentPage',
                'meta_description' => 'Il progetto scuola della Savino Del Bene Volley. Promuoviamo la pallavolo e i valori dello sport nelle scuole.',
                'content' => '<h2>Progetto Scuola</h2><p>La Savino Del Bene Volley porta la pallavolo e i valori dello sport nelle scuole del territorio. Attraverso lezioni, dimostrazioni e tornei interscolastici, avviciniamo i giovani alla pratica sportiva.</p>',
            ],

            // === SPONSOR / B2B ===
            [
                'title' => 'Title Sponsor',
                'slug' => 'title-sponsor',
                'template' => 'Public/Sponsor',
                'meta_description' => 'Savino Del Bene S.p.A., title sponsor della Savino Del Bene Volley. Scopri la partnership.',
                'content' => '<h2>Savino Del Bene S.p.A.</h2><p>Il Gruppo Savino Del Bene, leader mondiale nella logistica e nelle spedizioni internazionali, è il title sponsor del club sin dalla sua fondazione. Una partnership che unisce eccellenza imprenditoriale e passione sportiva.</p>',
            ],
            [
                'title' => 'Diventa Sponsor',
                'slug' => 'diventa-sponsor',
                'template' => 'Public/Sponsor',
                'meta_description' => 'Diventa sponsor della Savino Del Bene Volley. Scopri i pacchetti di sponsorizzazione e i vantaggi per la tua azienda.',
                'content' => '<h2>Perché Sponsorizzare la Savino Del Bene Volley?</h2><p>Visibilità nazionale e internazionale, accesso a un network B2B esclusivo, hospitality premium e attivazioni di marketing dedicate. Contattaci per un preventivo personalizzato.</p>',
            ],
            [
                'title' => 'Hospitality',
                'slug' => 'hospitality',
                'template' => 'Public/ContentPage',
                'meta_description' => 'Hospitality e servizi premium per le partite della Savino Del Bene Volley al Palazzo Wanny.',
                'content' => '<h2>Esperienza Hospitality</h2><p>Vivi le partite della Savino Del Bene Volley da una prospettiva esclusiva. Il nostro programma Hospitality offre posti premium, catering dedicato, meet & greet con le atlete e networking con i partner del club.</p>',
            ],
            [
                'title' => 'Progetto Affiliazioni',
                'slug' => 'affiliazioni',
                'template' => 'Public/ContentPage',
                'meta_description' => 'Programma di affiliazione della Savino Del Bene Volley per società sportive e scuole di pallavolo.',
                'content' => '<h2>Programma Affiliazioni</h2><p>La Savino Del Bene Volley offre un programma di affiliazione per società sportive e scuole di pallavolo del territorio. Formazione tecnica, condivisione di metodologie e partecipazione a eventi esclusivi.</p>',
            ],

            // === SOCIALE ===
            [
                'title' => 'Volley 4 All',
                'slug' => 'volley-4-all',
                'template' => 'Public/Sociale',
                'meta_description' => 'Volley 4 All: il progetto di inclusione sociale della Savino Del Bene Volley. Sport per tutti, senza barriere.',
                'content' => '<h2>Volley 4 All</h2><p>Un progetto che abbatte le barriere e porta la pallavolo a tutti: persone con disabilità, ragazzi in situazioni di disagio sociale e comunità svantaggiate. Perché lo sport è un diritto, non un privilegio.</p>',
            ],
            [
                'title' => 'Progetti Sociali',
                'slug' => 'progetti-sociali',
                'template' => 'Public/Sociale',
                'meta_description' => 'I progetti sociali della Savino Del Bene Volley. Inclusione, territorio e responsabilità sociale.',
                'content' => '<p>La Savino Del Bene Volley è impegnata attivamente nel tessuto sociale del territorio con iniziative di inclusione, formazione e sostegno alle comunità locali.</p>',
            ],
            [
                'title' => 'Bilancio di Sostenibilità',
                'slug' => 'sostenibilita',
                'template' => 'Public/ContentPage',
                'meta_description' => 'Il bilancio di sostenibilità della Savino Del Bene Volley. Impegno ambientale, sociale e di governance.',
                'content' => '<h2>Sostenibilità</h2><p>La Savino Del Bene Volley pubblica annualmente il proprio bilancio di sostenibilità, documentando l\'impegno del club in ambito ambientale (riduzione impatto eventi), sociale (progetti inclusivi) e di governance (trasparenza gestionale).</p>',
            ],

            // === COMUNICAZIONE / MEDIA ===
            [
                'title' => 'Accrediti Stampa',
                'slug' => 'accrediti-stampa',
                'template' => 'Public/Comunicazione',
                'meta_description' => 'Richiedi l\'accredito stampa per le partite della Savino Del Bene Volley. Informazioni per giornalisti e fotografi.',
                'content' => '<p>Per richiedere l\'accredito stampa, compila il modulo dedicato almeno 48 ore prima dell\'evento. L\'ufficio stampa valuterà la richiesta e invierà conferma via email.</p>',
            ],
            [
                'title' => 'Cartelle Stampa',
                'slug' => 'cartelle-stampa',
                'template' => 'Public/Comunicazione',
                'meta_description' => 'Scarica le cartelle stampa ufficiali della Savino Del Bene Volley. Logo, foto ufficiali e materiali per la stampa.',
                'content' => '<h2>Materiale Stampa</h2><p>In questa sezione sono disponibili i materiali ufficiali per la stampa: logo del club in vari formati, foto ufficiali delle atlete, cartelle stampa pre-partita e comunicati ufficiali.</p>',
            ],
            [
                'title' => 'Double Face — Il Magazine',
                'slug' => 'double-face',
                'template' => 'Public/ContentPage',
                'meta_description' => 'Double Face, il magazine ufficiale della Savino Del Bene Volley. Interviste, approfondimenti e dietro le quinte.',
                'content' => '<h2>Double Face</h2><p>Il magazine ufficiale della Savino Del Bene Volley. Interviste esclusive con le atlete, approfondimenti tattici, dietro le quinte e storie dal settore giovanile. Disponibile in formato digitale.</p>',
            ],
        ];

        foreach ($pages as $pageData) {
            Page::updateOrCreate(
                ['slug' => $pageData['slug']],
                array_merge($pageData, [
                    'status' => PostStatus::Published->value,
                    'excerpt' => $pageData['meta_description'],
                ])
            );
        }

        $this->command->info('✅ 22 pagine CMS create/aggiornate con successo.');
    }
}
