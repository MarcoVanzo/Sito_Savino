<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le pulizie chieste dal parere del 5 ottobre 2026 (punto 4): security.txt,
 * soglie dei limiti fuori dagli header, cookie del vecchio sito scaduti.
 */
class RispostaSenzaResiduiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function security_txt_risponde_con_contatto_e_scadenza_futura(): void
    {
        $risposta = $this->get('/.well-known/security.txt')->assertOk();

        $this->assertStringStartsWith('text/plain', (string) $risposta->headers->get('Content-Type'));
        $testo = $risposta->getContent();
        $this->assertStringContainsString('Contact: mailto:allarmi@mv-consulting.it', $testo);
        $this->assertMatchesRegularExpression('/^Expires: (\S+)$/m', $testo);

        preg_match('/^Expires: (\S+)$/m', $testo, $scadenza);
        $this->assertTrue(Carbon::parse($scadenza[1])->isAfter(now()->addMonths(10)), 'RFC 9116: al massimo un anno, mai già scaduto.');
    }

    /** Senza la cartella vera, Apache del buildpack rispondeva 403. */
    #[Test]
    public function la_cartella_well_known_esiste_davvero(): void
    {
        $this->assertDirectoryExists(public_path('.well-known'));
    }

    #[Test]
    public function le_soglie_dei_limiti_non_escono_negli_header(): void
    {
        $risposta = $this->get('/');

        $this->assertFalse($risposta->headers->has('X-RateLimit-Limit'));
        $this->assertFalse($risposta->headers->has('X-RateLimit-Remaining'));
    }

    #[Test]
    public function i_cookie_del_vecchio_sito_tornano_scaduti(): void
    {
        $risposta = $this->withUnencryptedCookies([
            'wp-settings-1' => 'x',
            'pys_first_visit' => 'x',
            'cookieyes-consent' => 'x',
        ])->get('/');

        $scaduti = collect($risposta->headers->getCookies())
            ->filter(fn ($cookie) => $cookie->isCleared())
            ->map(fn ($cookie) => $cookie->getName())
            ->unique()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(['wp-settings-1', 'pys_first_visit', 'cookieyes-consent'], $scaduti);
    }

    #[Test]
    public function chi_non_ha_cookie_vecchi_non_riceve_niente_in_piu(): void
    {
        $risposta = $this->get('/');

        $nomi = collect($risposta->headers->getCookies())->map(fn ($cookie) => $cookie->getName())->all();

        $this->assertEmpty(array_filter($nomi, fn (string $nome): bool => str_starts_with($nome, 'wp-') || str_starts_with($nome, 'pys_')));
    }
}
