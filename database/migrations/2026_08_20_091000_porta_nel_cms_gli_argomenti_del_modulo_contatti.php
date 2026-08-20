<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gli argomenti della tendina "Oggetto" del modulo contatti, e i suggerimenti
 * che compaiono scegliendoli, erano scritti dentro il componente Vue: per
 * aggiungerne uno serviva un intervento sul codice.
 *
 * Qui diventano contenuto della pagina (Pagine → Contatti → Argomenti del
 * Modulo), con gli stessi valori che il sito sta già usando. Vengono aggiunte
 * anche le etichette del modulo, che erano nella stessa condizione.
 */
return new class extends Migration
{
    private function argomenti(string $locale): array
    {
        $it = [
            ['value' => 'Informazioni Generali', 'label' => 'Informazioni Generali'],
            [
                'value' => 'Ticketing / Biglietti',
                'label' => 'Ticketing / Biglietti',
                'tip_title' => 'Info Ticketing',
                'tip_text' => 'Sapevi che puoi acquistare i biglietti direttamente dalla nostra biglietteria online?',
                'tip_link_text' => 'Vai al Ticketing',
                'tip_link_url' => '/ticketing',
            ],
            [
                'value' => 'Sponsor / Commerciale',
                'label' => 'Sponsor / Commerciale',
                'tip_title' => 'Diventa Partner',
                'tip_text' => 'Vuoi diventare nostro partner? Visita la sezione dedicata agli sponsor.',
                'tip_link_text' => 'I nostri Sponsor',
                'tip_link_url' => '/sponsor',
            ],
            [
                'value' => 'Settore Giovanile',
                'label' => 'Settore Giovanile',
                'tip_title' => 'SDB Youth',
                'tip_text' => 'Scopri tutto sul nostro settore giovanile e le accademie.',
                'tip_link_text' => 'Settore Giovanile',
                'tip_link_url' => '/youth',
            ],
            ['value' => 'Stampa / Media', 'label' => 'Stampa / Media'],
        ];

        if ($locale === 'it') {
            return $it;
        }

        $en = [
            ['label' => 'General information'],
            [
                'label' => 'Tickets',
                'tip_title' => 'Ticket info',
                'tip_text' => 'You can buy your tickets directly from our online box office.',
                'tip_link_text' => 'Go to tickets',
            ],
            [
                'label' => 'Sponsorship',
                'tip_title' => 'Become a partner',
                'tip_text' => 'Would you like to become a partner? Have a look at the sponsors section.',
                'tip_link_text' => 'Our sponsors',
            ],
            [
                'label' => 'Youth sector',
                'tip_title' => 'SDB Youth',
                'tip_text' => 'Find out about our youth sector and academies.',
                'tip_link_text' => 'Youth sector',
            ],
            ['label' => 'Press / Media'],
        ];

        // Il valore inviato per mail resta quello italiano: è la chiave con cui
        // la redazione riconosce la richiesta, non un testo da tradurre.
        return collect($it)
            ->map(fn (array $topic, int $i) => array_merge($topic, $en[$i]))
            ->all();
    }

    private function etichette(string $locale): array
    {
        return $locale === 'it'
            ? [
                'form_subtitle' => 'Scrivici direttamente',
                'form_label_name' => 'Nome e Cognome',
                'form_placeholder_name' => 'Mario Rossi',
                'form_label_email' => 'Email',
                'form_placeholder_email' => 'mario.rossi@email.it',
                'form_label_subject' => 'Oggetto',
                'form_label_message' => 'Messaggio',
                'form_placeholder_message' => 'Scrivi qui il tuo messaggio...',
                'form_submit_label' => 'Invia Messaggio',
                'form_sending_label' => 'Invio in corso...',
                'form_reset_label' => 'Invia un altro messaggio',
            ]
            : [
                'form_subtitle' => 'Write to us',
                'form_label_name' => 'Full name',
                'form_placeholder_name' => 'John Smith',
                'form_label_email' => 'Email',
                'form_placeholder_email' => 'john.smith@email.com',
                'form_label_subject' => 'Subject',
                'form_label_message' => 'Message',
                'form_placeholder_message' => 'Write your message here...',
                'form_submit_label' => 'Send message',
                'form_sending_label' => 'Sending...',
                'form_reset_label' => 'Send another message',
            ];
    }

    public function up(): void
    {
        $pagina = DB::table('pages')->select('id', 'content_data')->where('slug', 'contatti')->first();

        if (! $pagina) {
            return;
        }

        $data = json_decode((string) $pagina->content_data, true);
        $data = is_array($data) ? $data : [];

        foreach (config('app.supported_locales', ['it', 'en']) as $locale) {
            if (empty($data[$locale]['form_topics'])) {
                $data[$locale]['form_topics'] = $this->argomenti($locale);
            }

            foreach ($this->etichette($locale) as $chiave => $valore) {
                $data[$locale][$chiave] = $data[$locale][$chiave] ?? $valore;
            }
        }

        DB::table('pages')->where('id', $pagina->id)->update([
            'content_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function down(): void
    {
        // no-op: senza questi valori il modulo tornerebbe a dipendere da un
        // elenco scritto nel componente.
    }
};
