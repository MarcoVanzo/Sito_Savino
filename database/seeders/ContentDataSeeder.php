<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class ContentDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── SOCIETÀ ──────────────────────────────────────────────────
        Page::where('slug', 'societa')->update(['content_data' => [
            'hero' => [
                'badge' => 'Dal 1982',
                'subtitle' => 'Oltre quarant\'anni di passione, tradizione e successi nel panorama della pallavolo femminile italiana. Una storia costruita con determinazione e visione.',
            ],
            'storia' => [
                'badge' => 'La Nostra Storia',
                'title' => 'Una Tradizione di Eccellenza',
                'paragraphs' => [
                    'Fondata nel 1982 a Scandicci, la Savino Del Bene Volley è diventata una delle realtà più importanti della pallavolo femminile italiana. Dalle origini nel campionato regionale alla Serie A1, il percorso del club è stato segnato da una crescita costante.',
                    'Con la partnership strategica del Gruppo Savino Del Bene, il club ha raggiunto traguardi storici: la Finale Scudetto, la partecipazione alla CEV Champions League e la conquista di un posto stabile tra le migliori squadre d\'Europa.',
                    'Oggi la Savino Del Bene Volley rappresenta un modello di gestione sportiva, con un settore giovanile d\'eccellenza, un impegno sociale concreto e una visione proiettata verso il futuro.',
                ],
                'highlight_value' => '40+',
                'highlight_label' => 'Anni di Storia',
            ],
            'organigramma' => [
                'badge' => 'Organigramma',
                'title' => 'Il Nostro Team Dirigenziale',
                'roles' => [
                    ['title' => 'Presidente', 'name' => 'Presidenza', 'desc' => 'Guida strategica e visione del club'],
                    ['title' => 'Direttore Generale', 'name' => 'Direzione', 'desc' => 'Gestione operativa e coordinamento'],
                    ['title' => 'Direttore Sportivo', 'name' => 'Area Tecnica', 'desc' => 'Pianificazione sportiva e roster'],
                    ['title' => 'Head Coach', 'name' => 'Staff Tecnico', 'desc' => 'Guida tecnica della prima squadra'],
                ],
            ],
            'palazzetto' => [
                'badge' => 'La Nostra Casa',
                'title' => 'Palazzo Wanny',
                'description' => 'Il Palazzo Wanny di Firenze è la casa della Savino Del Bene Volley. Con una capienza di oltre 4.000 posti, l\'impianto offre un\'esperienza unica per tifosi e appassionati di pallavolo.',
                'stats' => [
                    ['value' => '4.000+', 'label' => 'Posti a Sedere'],
                    ['value' => 'Serie A1', 'label' => 'Omologazione'],
                ],
                'address' => 'Via del Tridente, 5 — 50127 Firenze (FI)',
            ],
            'contatti' => [
                'badge' => 'Resta in Contatto',
                'title' => 'Contattaci',
                'items' => [
                    ['title' => 'Email', 'value' => 'info@savinodelbenevolley.com'],
                    ['title' => 'Telefono', 'value' => '+39 055 XXX XXXX'],
                    ['title' => 'Sede', 'value' => 'Scandicci (FI), Toscana'],
                ],
            ],
        ]]);

        // ── TICKETING ────────────────────────────────────────────────
        Page::where('slug', 'ticketing')->update(['content_data' => [
            'hero' => [
                'badge' => 'Vivi l\'Emozione',
                'subtitle' => 'Scegli il tuo posto e vivi l\'emozione della pallavolo dal vivo al Palazzo Wanny.',
            ],
            'plans' => [
                [
                    'name' => 'Singola Partita',
                    'price' => '15',
                    'period' => 'a partita',
                    'features' => [
                        'Accesso al Palazzo Wanny',
                        'Posto in tribuna laterale',
                        'Acquisto online o in cassa',
                    ],
                    'highlight' => false,
                    'cta' => 'Acquista Biglietto',
                ],
                [
                    'name' => 'Abbonamento Gold',
                    'price' => '199',
                    'period' => 'stagione',
                    'features' => [
                        'Tutte le partite casalinghe',
                        'Posto numerato in tribuna centrale',
                        'Accesso prioritario al palazzetto',
                        'Sconto 10% su merchandising',
                        'Meet & Greet con le atlete',
                    ],
                    'highlight' => true,
                    'cta' => 'Sottoscrivi Ora',
                ],
                [
                    'name' => 'Abbonamento Base',
                    'price' => '99',
                    'period' => 'stagione',
                    'features' => [
                        'Tutte le partite casalinghe',
                        'Posto in tribuna laterale',
                        'Ingresso dedicato',
                    ],
                    'highlight' => false,
                    'cta' => 'Sottoscrivi Ora',
                ],
            ],
            'purchase_info' => [
                'title' => 'Informazioni sull\'Acquisto',
                'channels' => [
                    [
                        'title' => 'Online',
                        'description' => 'Acquista i biglietti comodamente online tramite il nostro sistema di ticketing. Pagamento sicuro con carta di credito o PayPal.',
                    ],
                    [
                        'title' => 'Al Botteghino',
                        'description' => 'La biglietteria del Palazzo Wanny apre 2 ore prima dell\'inizio di ogni partita. Accettiamo contanti e pagamenti elettronici.',
                    ],
                ],
            ],
        ]]);

        // ── SUMMER CAMP ──────────────────────────────────────────────
        Page::where('slug', 'summer-camp')->update(['content_data' => [
            'hero' => [
                'badge' => 'Estate di Sport',
                'subtitle' => 'Un\'estate indimenticabile tra sport, divertimento e crescita personale.',
            ],
            'intro' => [
                'badge' => 'Il Camp',
                'title' => 'Vivi la Pallavolo',
                'paragraphs' => [
                    'Il Summer Camp della Savino Del Bene è un\'esperienza unica pensata per ragazze e ragazzi dai 6 ai 16 anni. Un programma completo che unisce la passione per la pallavolo ad attività ricreative e formative.',
                    'I nostri istruttori qualificati seguiranno ogni partecipante in un percorso di crescita sportiva e personale, in un ambiente sicuro e stimolante.',
                ],
                'features' => [
                    'Staff tecnico qualificato',
                    'Gruppi per fasce d\'età',
                    'Assicurazione inclusa',
                    'Pranzo e merenda inclusi',
                ],
                'edition_label' => 'Summer Camp 2026',
                'edition_note' => 'Edizione speciale',
            ],
            'activities' => [
                ['icon' => '🏐', 'title' => 'Pallavolo', 'description' => 'Allenamenti tecnici e tattici con i coach della prima squadra.'],
                ['icon' => '🏋️', 'title' => 'Preparazione Atletica', 'description' => 'Sessioni di forza, agilità e coordinazione per ogni età.'],
                ['icon' => '🎯', 'title' => 'Talent Day', 'description' => 'Giornate di selezione aperte per scoprire nuovi talenti.'],
                ['icon' => '🏊', 'title' => 'Attività Acquatiche', 'description' => 'Divertimento in piscina e giochi d\'acqua per rinfrescarsi.'],
                ['icon' => '🎨', 'title' => 'Laboratori Creativi', 'description' => 'Arte, musica e attività espressive per stimolare la creatività.'],
                ['icon' => '🤝', 'title' => 'Team Building', 'description' => 'Giochi di squadra e attività per rafforzare lo spirito di gruppo.'],
            ],
            'dates' => [
                ['period' => '1ª Settimana', 'dates' => '16 - 20 Giugno', 'status' => 'Iscrizioni Aperte'],
                ['period' => '2ª Settimana', 'dates' => '23 - 27 Giugno', 'status' => 'Iscrizioni Aperte'],
                ['period' => '3ª Settimana', 'dates' => '30 Giugno - 4 Luglio', 'status' => 'Posti Limitati'],
                ['period' => '4ª Settimana', 'dates' => '7 - 11 Luglio', 'status' => 'Prossimamente'],
            ],
        ]]);

        // ── YOUTH / SETTORE GIOVANILE ────────────────────────────────
        Page::where('slug', 'youth')->update(['content_data' => [
            'hero' => [
                'badge' => 'Il Futuro in Campo',
                'subtitle' => 'Costruiamo il futuro della pallavolo con passione, talento e dedizione.',
            ],
            'intro' => [
                'badge' => 'La Nostra Filosofia',
                'title' => 'Formare Campioni Dentro e Fuori dal Campo',
                'paragraphs' => [
                    'Il settore giovanile della Savino Del Bene rappresenta il cuore pulsante della nostra società. Crediamo che la formazione sportiva debba andare di pari passo con la crescita personale, accompagnando ogni giovane atleta in un percorso di eccellenza.',
                    'Con oltre 70 atlete distribuite nelle diverse categorie, il nostro vivaio è una fucina di talenti che si allenano quotidianamente con l\'obiettivo di raggiungere i massimi livelli.',
                ],
                'stats' => [
                    ['value' => '70+', 'label' => 'Giovani Atlete'],
                    ['value' => '4', 'label' => 'Categorie'],
                    ['value' => '12', 'label' => 'Allenatori'],
                    ['value' => '15+', 'label' => 'Anni di Attività'],
                ],
            ],
            'values' => [
                ['icon' => 'star', 'title' => 'Eccellenza Tecnica', 'description' => 'Formazione completa dei fondamentali con metodologie all\'avanguardia e allenatori qualificati.'],
                ['icon' => 'heart', 'title' => 'Crescita Personale', 'description' => 'Non solo sport: i nostri ragazzi imparano valori come rispetto, disciplina e lavoro di squadra.'],
                ['icon' => 'trophy', 'title' => 'Percorso Verso la Prima Squadra', 'description' => 'I migliori talenti del settore giovanile vengono inseriti nel programma di sviluppo verso la Serie A1.'],
                ['icon' => 'users', 'title' => 'Staff Dedicato', 'description' => 'Allenatori certificati, preparatori atletici e supporto psicologico per ogni categoria.'],
            ],
            'teams' => [
                ['name' => 'Under 18', 'category' => 'Serie C', 'coach' => 'Alessandro Tozzi', 'training' => 'Lun-Mer-Ven 16:00-18:00', 'players' => 14, 'color' => 'savino-blue'],
                ['name' => 'Under 16', 'category' => 'Campionato Regionale', 'coach' => 'Francesca Galli', 'training' => 'Mar-Gio-Sab 15:00-17:00', 'players' => 16, 'color' => 'savino-gold'],
                ['name' => 'Under 14', 'category' => 'Campionato Provinciale', 'coach' => 'Simone Marchetti', 'training' => 'Lun-Mer-Ven 14:30-16:30', 'players' => 18, 'color' => 'savino-red'],
                ['name' => 'Under 12', 'category' => 'Minivolley', 'coach' => 'Laura Rinaldi', 'training' => 'Mar-Gio 14:00-15:30', 'players' => 20, 'color' => 'savino-blue'],
            ],
            'talent_scouting' => [
                'badge' => 'Talent Scouting',
                'title' => 'Cerchiamo Nuovi Talenti',
                'description' => 'Sei una giovane atleta con la passione per la pallavolo? Il nostro programma di scouting è sempre alla ricerca di nuovi talenti. Partecipa alle giornate di prova e inizia il tuo percorso verso l\'eccellenza sportiva.',
                'note' => 'Per informazioni sulle prove e le iscrizioni, contattaci via email o telefono.',
                'cta_primary_text' => 'Contattaci',
                'cta_primary_url' => '/contatti',
                'cta_secondary_text' => 'Scrivi una Email',
                'cta_secondary_url' => 'mailto:giovanili@savinodelbenescandicci.it',
            ],
        ]]);

        // ── SPONSOR ──────────────────────────────────────────────────
        Page::where('slug', 'sponsor')->update(['content_data' => [
            'hero' => [
                'badge' => 'I Nostri Partner',
                'subtitle' => 'Un network di eccellenza che condivide la passione per lo sport e i valori della Savino Del Bene Volley. Insieme, costruiamo il futuro della pallavolo.',
            ],
            'tiers' => [
                ['key' => 'main', 'label' => 'Title Sponsor'],
                ['key' => 'gold', 'label' => 'Gold Partner'],
                ['key' => 'silver', 'label' => 'Silver Partner'],
                ['key' => 'technical', 'label' => 'Partner Tecnici'],
                ['key' => 'standard', 'label' => 'Supporter'],
            ],
            'become_sponsor' => [
                'badge' => 'Unisciti a Noi',
                'title' => 'Diventa Partner',
                'description' => 'Associa il tuo brand a una realtà sportiva di primo livello. Offriamo pacchetti di visibilità personalizzati con presenza su maglia, LED bordocampo, digital e hospitality al Palazzo Wanny.',
                'stats' => [
                    ['value' => '2M+', 'label' => 'Impressioni Social'],
                    ['value' => '50K+', 'label' => 'Spettatori Stagione'],
                    ['value' => '100+', 'label' => 'Eventi Annuali'],
                ],
                'cta_text' => 'Contattaci per una Proposta',
            ],
        ]]);

        // ── COMUNICAZIONE ────────────────────────────────────────────
        Page::where('slug', 'comunicazione')->update(['content_data' => [
            'hero' => [
                'badge' => 'Area Stampa',
                'subtitle' => 'Risorse, contatti e materiali per giornalisti e operatori media.',
            ],
            'accreditation' => [
                'badge' => 'Accrediti',
                'title' => 'Accreditamento Stampa',
                'paragraphs' => [
                    'Giornalisti, fotografi e operatori video possono richiedere l\'accreditamento per le partite casalinghe e gli eventi organizzati dalla Savino Del Bene Volley.',
                    'L\'accreditamento consente l\'accesso alla tribuna stampa, alla zona mista post-partita e alle conferenze stampa pre e post gara.',
                ],
                'steps' => [
                    'Inviare una mail a media@savinodelbenevolley.it',
                    'Indicare testata, nome del giornalista e tipo di accredito richiesto',
                    'Inviare la richiesta almeno 48 ore prima dell\'evento',
                ],
            ],
            'press_kit' => [
                'badge' => 'Download',
                'title' => 'Press Kit',
                'items' => [
                    ['icon' => '📸', 'title' => 'Foto Ufficiali', 'description' => 'Immagini ad alta risoluzione della squadra, dello staff e del palazzetto.', 'format' => 'ZIP — 45 MB'],
                    ['icon' => '🎨', 'title' => 'Logo e Brand Kit', 'description' => 'Loghi in tutti i formati, palette colori, font e linee guida del brand.', 'format' => 'ZIP — 12 MB'],
                    ['icon' => '📄', 'title' => 'Cartella Stampa', 'description' => 'Comunicati stampa, schede tecniche e profili delle atlete.', 'format' => 'PDF — 8 MB'],
                    ['icon' => '📊', 'title' => 'Statistiche Stagionali', 'description' => 'Dati e statistiche aggiornate della stagione in corso.', 'format' => 'PDF — 3 MB'],
                ],
            ],
            'contacts' => [
                'badge' => 'Contatti',
                'title' => 'Ufficio Comunicazione',
                'items' => [
                    ['role' => 'Ufficio Stampa', 'name' => 'Responsabile Comunicazione', 'email' => 'stampa@savinodelbenevolley.it', 'phone' => '+39 055 000 0000'],
                    ['role' => 'Social Media', 'name' => 'Social Media Manager', 'email' => 'social@savinodelbenevolley.it', 'phone' => '+39 055 000 0001'],
                    ['role' => 'Accrediti & Media', 'name' => 'Coordinatore Media', 'email' => 'media@savinodelbenevolley.it', 'phone' => '+39 055 000 0002'],
                ],
            ],
        ]]);

        // ── SOCIALE ──────────────────────────────────────────────────
        Page::where('slug', 'sociale')->update(['content_data' => [
            'hero' => [
                'badge' => 'Sport e Società',
                'subtitle' => 'Il nostro impegno per la comunità va oltre il campo da gioco.',
            ],
            'mission' => [
                'badge' => 'La Nostra Missione',
                'title' => 'Sport Come Strumento Sociale',
                'paragraphs' => [
                    'La Savino Del Bene crede fermamente nel potere trasformativo dello sport. Attraverso i nostri progetti sociali, lavoriamo ogni giorno per costruire una comunità più inclusiva, sostenibile e attenta ai bisogni di tutti.',
                    'Dalla pallavolo per tutti ai programmi educativi, dal sitting volley alle iniziative ambientali: ogni progetto è un passo verso un futuro migliore.',
                ],
            ],
            'projects' => [
                ['icon' => '🏐', 'title' => 'Volley4All', 'color' => 'savino-blue', 'description' => 'Pallavolo gratuita per ragazzi provenienti da famiglie in difficoltà economica. Lo sport come diritto, non come privilegio.', 'tag' => 'Inclusione Sportiva'],
                ['icon' => '📚', 'title' => 'Scuola & Sport', 'color' => 'savino-gold', 'description' => 'Programma di doposcuola che unisce supporto scolastico e attività sportiva, promuovendo la crescita a 360°.', 'tag' => 'Educazione'],
                ['icon' => '♿', 'title' => 'Inclusione', 'color' => 'savino-red', 'description' => 'Attività di sitting volley e progetti dedicati ad atleti con disabilità. Abbattere le barriere attraverso lo sport.', 'tag' => 'Accessibilità'],
                ['icon' => '🌱', 'title' => 'Sostenibilità', 'color' => 'savino-blue', 'description' => 'Iniziative green per ridurre l\'impatto ambientale degli eventi sportivi e sensibilizzare la community.', 'tag' => 'Ambiente'],
            ],
            'impact' => [
                'badge' => 'Risultati',
                'title' => 'Il Nostro Impatto',
                'stats' => [
                    ['value' => '500+', 'label' => 'Ragazzi Coinvolti'],
                    ['value' => '12', 'label' => 'Scuole Partner'],
                    ['value' => '30+', 'label' => 'Eventi Sociali'],
                    ['value' => '€50K', 'label' => 'Fondi Raccolti'],
                ],
            ],
        ]]);
    }
}
