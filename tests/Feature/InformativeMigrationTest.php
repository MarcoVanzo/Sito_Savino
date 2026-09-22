<?php

namespace Tests\Feature;

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
}
