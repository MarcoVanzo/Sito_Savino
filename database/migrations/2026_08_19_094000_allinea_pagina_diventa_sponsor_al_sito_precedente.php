<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La pagina "Diventa Sponsor" del nuovo sito conteneva testi di riempimento
 * scritti in fase di sviluppo. Qui vengono sostituiti con quelli realmente
 * pubblicati sul sito precedente, più il pulsante di contatto verso l'indirizzo
 * marketing, con l'oggetto della mail già compilato.
 *
 * Da qui in avanti la pagina si modifica dal pannello: questa migrazione serve
 * solo a portare online il testo giusto una volta.
 */
return new class extends Migration
{
    public function up(): void
    {
        $it = <<<'HTML'
<h2>Diventa Sponsor di una squadra unica</h2>
<h3>Perché investire sulla Savino Del Bene Volley?</h3>
<p>La società sportiva rappresenta l'eccellenza del volley femminile a livello nazionale e internazionale e da anni pone le sue basi sopra un capillare tessuto giovanile, sociale e imprenditoriale.</p>
<p>Sponsorizzare la Savino Del Bene Volley significa:</p>
<ul>
<li>implementare la brand awareness;</li>
<li>condividere valori comuni e passioni;</li>
<li>facilitare e rafforzare le interazioni fra le aziende del territorio, nazionali e internazionali;</li>
<li>creare e rafforzare le relazioni grazie alla Savino Del Bene Volley Hospitality interna al palazzetto.</li>
</ul>
<h3>Quali asset strategici mette in campo la Savino Del Bene Volley per raggiungere gli obiettivi dei partner?</h3>
<ol>
<li><strong>Visibilità e posizionamento</strong>: maglia ufficiale nelle competizioni nazionali e internazionali, LED bordocampo, adesivi fissi sul taraflex, backdrop interviste, maxischermo, Match Day Program e logo sul sito ufficiale nella sezione riservata.</li>
<li><strong>Contatto con la fan base</strong> attraverso attività online e offline.</li>
<li><strong>Savino Del Bene Volley Hospitality</strong>, riservata ai partner per le gare casalinghe, per favorire e consolidare le relazioni.</li>
<li><strong>Eventi speciali</strong> dedicati ai partner della Savino Del Bene Volley.</li>
</ol>
<h3>Sei interessato?</h3>
<p>Scrivici per conoscere tutti i dettagli e le opportunità di partnership.</p>
HTML;

        $en = <<<'HTML'
<h2>Become a sponsor of a one-of-a-kind team</h2>
<h3>Why invest in Savino Del Bene Volley?</h3>
<p>The club stands for excellence in women's volleyball at national and international level, and has been built for years on a widespread network of youth, social and business relationships.</p>
<p>Sponsoring Savino Del Bene Volley means:</p>
<ul>
<li>growing your brand awareness;</li>
<li>sharing common values and passions;</li>
<li>easing and strengthening the connections between local, national and international companies;</li>
<li>building and consolidating relationships through the Savino Del Bene Volley Hospitality inside the arena.</li>
</ul>
<h3>Which strategic assets does Savino Del Bene Volley put on the court for its partners?</h3>
<ol>
<li><strong>Visibility and positioning</strong>: official jersey in national and international competitions, courtside LED, stickers on the taraflex, interview backdrop, big screen, Match Day Program and logo on the official website.</li>
<li><strong>Contact with the fan base</strong> through online and offline activities.</li>
<li><strong>Savino Del Bene Volley Hospitality</strong>, reserved to partners on home match days, to build and consolidate relationships.</li>
<li><strong>Special events</strong> dedicated to Savino Del Bene Volley partners.</li>
</ol>
<h3>Interested?</h3>
<p>Write to us to learn all the details and partnership opportunities.</p>
HTML;

        $mailto = 'mailto:marketing@savinodelbenevolley.it?subject='
            .rawurlencode('Savino Del Bene Volley — Richiesta di sponsorizzazione');

        $row = DB::table('pages')->select('id', 'content_data')->where('slug', 'diventa-sponsor')->first();

        if (! $row) {
            return;
        }

        $contentData = json_decode((string) $row->content_data, true);
        $contentData = is_array($contentData) ? $contentData : [];

        foreach (['it' => 'Scrivici', 'en' => 'Write to us'] as $locale => $label) {
            $contentData[$locale]['button_text'] = $contentData[$locale]['button_text'] ?? $label;
            $contentData[$locale]['button_url'] = $contentData[$locale]['button_url'] ?? $mailto;
        }

        DB::table('pages')->where('id', $row->id)->update([
            'content' => json_encode(['it' => $it, 'en' => $en], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_data' => json_encode($contentData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta_description' => json_encode([
                'it' => 'Diventa sponsor della Savino Del Bene Volley: visibilità, relazioni e hospitality al fianco di una squadra di vertice del volley femminile.',
                'en' => 'Become a Savino Del Bene Volley sponsor: visibility, business relationships and hospitality alongside a top women\'s volleyball team.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'template' => 'Public/ContentPage',
        ]);
    }

    public function down(): void
    {
        // no-op: i testi precedenti erano contenuto di riempimento.
    }
};
