<?php

namespace Tests\Feature;

use App\Enums\CodiceDelRedirect;
use App\Models\Reindirizzamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportaIRedirectDaCsvTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $contenuto): string
    {
        $file = tempnam(sys_get_temp_dir(), 'redirect').'.csv';
        file_put_contents($file, $contenuto);

        return $file;
    }

    private function esempio(): string
    {
        return $this->csv(implode("\n", [
            'from,to,status',
            'https://savinodelbenevolley.it/tag/scudetto/,/stagione/playoff,301',
            '/concorso-2019/,,410',
            '/vecchia-pagina/,/societa/organigramma,',
            '/giro/,/giro,301',
            '/senza-destinazione/,,301',
            '/stato-strano/,/news,302',
            '/admin/users,/news,301',
        ]));
    }

    #[Test]
    public function la_prova_dice_cosa_farebbe_senza_scrivere(): void
    {
        $this->artisan('redirect:importa', ['file' => $this->esempio(), '--dry-run' => true])
            ->expectsOutputToContain('Create: 3 · aggiornate: 0 · invariate: 0 · scartate: 4')
            ->assertSuccessful();

        $this->assertDatabaseCount('redirects', 0);
    }

    #[Test]
    public function l_import_crea_aggiorna_e_scarta_col_motivo(): void
    {
        Reindirizzamento::create(['from_path' => '/vecchia-pagina', 'to_path' => '/news', 'status_code' => CodiceDelRedirect::Permanente]);

        $this->artisan('redirect:importa', ['file' => $this->esempio()])
            ->expectsOutputToContain('Create: 2 · aggiornate: 1 · invariate: 0 · scartate: 4')
            ->expectsOutputToContain('girerebbe su sé stesso')
            ->expectsOutputToContain('Un 301 vuole una destinazione.')
            ->expectsOutputToContain('non ammesso')
            ->assertSuccessful();

        $this->assertDatabaseHas('redirects', ['from_path' => '/tag/scudetto', 'to_path' => '/stagione/playoff', 'status_code' => '301']);
        $this->assertDatabaseHas('redirects', ['from_path' => '/concorso-2019', 'to_path' => null, 'status_code' => '410']);
        $this->assertDatabaseHas('redirects', ['from_path' => '/vecchia-pagina', 'to_path' => '/societa/organigramma']);
        $this->assertDatabaseCount('redirects', 3);

        // Rilanciato, non duplica niente.
        $this->artisan('redirect:importa', ['file' => $this->esempio()])
            ->expectsOutputToContain('Create: 0 · aggiornate: 0 · invariate: 3 · scartate: 4')
            ->assertSuccessful();
    }

    #[Test]
    public function il_separatore_di_excel_in_italiano_va_bene_anche_senza_intestazione(): void
    {
        $this->artisan('redirect:importa', ['file' => $this->csv("/tag/coppa-italia/;/stagione/coppa-italia;301\r\n")])
            ->assertSuccessful();

        $this->assertDatabaseHas('redirects', ['from_path' => '/tag/coppa-italia', 'to_path' => '/stagione/coppa-italia']);
    }
}
