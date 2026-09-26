<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le informative riscritte su quello che il sito fa davvero.
 *
 * Il testo precedente diceva che il sito raccoglie "esclusivamente dati
 * tecnici" e che non usa cookie di terze parti, mentre caricava Google
 * Analytics e il pixel di Meta. Qui si verifica che la correzione arrivi dove
 * serve e che non passi sopra al lavoro della redazione.
 */
class InformativeMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function eseguiLaMigrazione(): void
    {
        (require database_path('migrations/2026_09_22_120000_le_informative_dicono_quello_che_il_sito_fa.php'))->up();
    }

    private function scriviIlTesto(string $slug, array $contenuti): void
    {
        DB::table('pages')->where('slug', $slug)->update([
            'content' => json_encode($contenuti, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function testo(string $slug, string $lingua = 'it'): string
    {
        $contenuti = json_decode((string) DB::table('pages')->where('slug', $slug)->value('content'), true);

        return (string) ($contenuti[$lingua] ?? '');
    }

    public function test_il_testo_che_diceva_il_falso_viene_riscritto(): void
    {
        $this->scriviIlTesto('cookie-policy', [
            'it' => '<p>Il sito non utilizza cookie di profilazione o di tracciamento di terze parti.</p>',
            'en' => '<p>This site only uses technical cookies.</p>',
        ]);

        $this->eseguiLaMigrazione();

        $italiano = $this->testo('cookie-policy');

        $this->assertStringNotContainsString('non utilizza cookie di profilazione', $italiano);
        $this->assertStringContainsString('Google Analytics 4', $italiano);
        $this->assertStringContainsString('Meta Platforms Ireland', $italiano);

        // Anche l'inglese, che condivide la stessa colonna tradotta.
        $this->assertStringContainsString('Google Analytics 4', $this->testo('cookie-policy', 'en'));
    }

    public function test_la_privacy_policy_riporta_la_sede_delle_impostazioni(): void
    {
        $this->scriviIlTesto('privacy-policy', [
            'it' => '<p>Il sito raccoglie esclusivamente dati tecnici necessari alla navigazione.</p>',
            'en' => '<p>Only technical data.</p>',
        ]);

        $this->eseguiLaMigrazione();

        $italiano = $this->testo('privacy-policy');

        // L'indirizzo vecchio era di Firenze, la sede sta a Scandicci.
        $this->assertStringNotContainsString('Via di Scandicci', $italiano);
        $this->assertStringContainsString('Via Benozzo Gozzoli', $italiano);
        $this->assertStringContainsString('garanteprivacy.it', $italiano);
    }

    public function test_non_passa_sopra_a_un_testo_gia_riscritto_dalla_redazione(): void
    {
        $suo = '<p>Informativa curata dallo studio legale, da non toccare.</p>';

        $this->scriviIlTesto('cookie-policy', ['it' => $suo, 'en' => $suo]);

        $this->eseguiLaMigrazione();

        $this->assertSame($suo, $this->testo('cookie-policy'));
    }

    public function test_si_puo_rieseguire_senza_cambiare_niente(): void
    {
        $this->scriviIlTesto('cookie-policy', [
            'it' => '<p>Il sito non utilizza cookie di profilazione.</p>',
            'en' => '<p>No profiling cookies.</p>',
        ]);

        $this->eseguiLaMigrazione();
        $dopoLaPrima = $this->testo('cookie-policy');

        $this->eseguiLaMigrazione();

        $this->assertSame($dopoLaPrima, $this->testo('cookie-policy'));
    }

    public function test_il_testo_nuovo_non_indenta_l_html(): void
    {
        $this->scriviIlTesto('privacy-policy', [
            'it' => '<p>Il sito raccoglie esclusivamente dati tecnici necessari alla navigazione.</p>',
            'en' => '<p>x</p>',
        ]);

        $this->eseguiLaMigrazione();

        // L'heredoc del file dati è indentato per leggibilità: quegli spazi non
        // devono finire nell'editor del pannello.
        $this->assertDoesNotMatchRegularExpression('/^[ \t]+</m', $this->testo('privacy-policy'));
    }

    // --- Revisione del 23 settembre 2026 ---

    private function eseguiLaRevisione(): void
    {
        (require database_path('migrations/2026_09_23_110000_le_informative_dicono_anche_dei_volti.php'))->up();
    }

    public function test_la_revisione_riscrive_il_testo_della_versione_precedente(): void
    {
        // Il testo del 22 settembre: vero ma incompleto. La firma che lo
        // riconosce è la data di aggiornamento che portava scritta.
        $this->scriviIlTesto('privacy-policy', [
            'it' => '<p>È aggiornata al 22 settembre 2026.</p>',
            'en' => '<p>Last updated 22 September 2026.</p>',
        ]);

        $this->eseguiLaRevisione();

        $italiano = $this->testo('privacy-policy');

        $this->assertStringContainsString('riconoscimento dei volti', $italiano);
        $this->assertStringContainsString('Resend', $italiano);
        $this->assertStringContainsString('codice fiscale', $italiano);
        $this->assertStringContainsString('face recognition', $this->testo('privacy-policy', 'en'));
    }

    public function test_la_cookie_policy_dice_dei_contenuti_incorporati(): void
    {
        // La frase della versione del 22 settembre, per intero: il solo
        // "quelli di marketing di Meta Platforms Ireland Ltd." c'e' anche nel
        // testo di oggi, e non e' una firma (FirmeDeiTestiLegaliTest).
        $this->scriviIlTesto('cookie-policy', [
            'it' => '<p>quelli di marketing di Meta Platforms Ireland Ltd. I loro trattamenti, e i trasferimenti fuori dall\'Unione Europea</p>',
            'en' => '<p>marketing cookies to Meta Platforms Ireland Ltd. Their processing, and transfers outside the European Union</p>',
        ]);

        $this->eseguiLaRevisione();

        // Mappa e video partono con la pagina: finché è così, va scritto.
        $this->assertStringContainsString('mappa del palazzetto', $this->testo('cookie-policy'));
        $this->assertStringContainsString('YouTube', $this->testo('cookie-policy'));
        $this->assertStringContainsString('arena map', $this->testo('cookie-policy', 'en'));
    }

    public function test_la_revisione_non_passa_sopra_alla_redazione(): void
    {
        $suo = '<p>Informativa curata dallo studio legale, da non toccare.</p>';

        $this->scriviIlTesto('privacy-policy', ['it' => $suo, 'en' => $suo]);

        $this->eseguiLaRevisione();

        $this->assertSame($suo, $this->testo('privacy-policy'));
    }

    public function test_le_firme_riconoscono_anche_il_testo_originale(): void
    {
        // Le firme sono cumulative: un database che si ferma al testo del 2025
        // — un ambiente nuovo, o uno rimasto indietro — deve essere riscritto
        // dalla revisione anche senza passare dalla migrazione di mezzo.
        $this->scriviIlTesto('privacy-policy', [
            'it' => '<p>Il sito raccoglie esclusivamente dati tecnici necessari alla navigazione.</p>',
            'en' => '<p>Only technical data.</p>',
        ]);

        $this->eseguiLaRevisione();

        $this->assertStringContainsString('riconoscimento dei volti', $this->testo('privacy-policy'));
    }

    public function test_il_seeder_non_fa_nascere_una_pagina_col_testo_vecchio(): void
    {
        // La copia nel seeder diceva "esclusivamente dati tecnici" e riportava
        // un indirizzo che non è la sede: ogni ambiente nuovo, e il database
        // dei test, nascevano con quella.
        DB::table('pages')->whereIn('slug', ['privacy-policy', 'cookie-policy'])->delete();

        $this->seed(PageSeeder::class);

        $italiano = $this->testo('privacy-policy');

        $this->assertStringNotContainsString('esclusivamente dati tecnici', $italiano);
        $this->assertStringNotContainsString('Via di Scandicci', $italiano);
        $this->assertStringContainsString('riconoscimento dei volti', $italiano);
        $this->assertStringContainsString('face recognition', $this->testo('privacy-policy', 'en'));
        $this->assertStringContainsString('mappa del palazzetto', $this->testo('cookie-policy'));
    }

    // --- Il titolare e' la ragione sociale ---

    public function test_il_titolare_e_la_ragione_sociale_per_esteso(): void
    {
        // Il nome con cui la squadra gioca non e' la denominazione di nessuno:
        // i diritti si esercitano verso la persona giuridica.
        $this->scriviIlTesto('privacy-policy', [
            'it' => '<p>Savino Del Bene Volley S.S.D. a r.l. — Via Benozzo Gozzoli 5/6</p>',
            'en' => '<p>Savino Del Bene Volley S.S.D. a r.l.</p>',
        ]);

        (require database_path('migrations/2026_09_23_150000_il_titolare_e_la_ragione_sociale.php'))->up();

        foreach (['it', 'en'] as $lingua) {
            $testo = $this->testo('privacy-policy', $lingua);

            $this->assertStringNotContainsString('S.S.D. a r.l.', $testo);
            $this->assertStringContainsString('Pallavolo Scandicci Savino Del Bene Società Sportiva Dilettantistica a Responsabilità Limitata', $testo);
            $this->assertStringContainsString('Via Benozzo Gozzoli, 5/6', $testo);
            $this->assertStringContainsString('94217750481', $testo);
            $this->assertStringContainsString('pallavoloscandicci@legalmail.it', $testo);

            // I diritti si esercitano alla casella della privacy, non al
            // recapito generale del sito.
            $this->assertStringContainsString('privacy@savinodelbenevolley.it', $testo);
            $this->assertStringNotContainsString('mailto:info@savinodelbenevolley.it', $testo);
        }
    }
}
