<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ogni schermata del pannello si apre.
 *
 * Le risorse di Filament si scoprono da sole leggendo la cartella: una che
 * smette di funzionare — una relazione rinominata, una colonna tolta, un campo
 * che non esiste più — non rompe nessun test finché qualcuno non ci entra. In
 * redazione lo si scopre aprendo la pagina e trovando un errore.
 *
 * Qui si visitano tutte le schermate senza parametri con il ruolo che può
 * vederle. Le rotte si leggono a test avviato: un data provider girerebbe
 * prima che l'applicazione le abbia registrate.
 */
class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function schermate(): array
    {
        $indirizzi = [];

        foreach (Route::getRoutes() as $rotta) {
            if (! in_array('GET', $rotta->methods(), true)) {
                continue;
            }

            $uri = $rotta->uri();

            // Solo il pannello, e solo gli indirizzi senza parametri: una
            // pagina di modifica ha bisogno di un record che qui non esiste.
            if (! str_starts_with($uri, 'admin') || str_contains($uri, '{')) {
                continue;
            }

            if (str_contains($uri, 'login') || str_contains($uri, 'logout')) {
                continue;
            }

            $indirizzi['/'.$uri] = true;
        }

        ksort($indirizzi);

        return array_keys($indirizzi);
    }

    #[Test]
    public function tutte_le_schermate_del_pannello_si_aprono(): void
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        $schermate = $this->schermate();

        $this->assertNotEmpty($schermate, 'Nessuna schermata del pannello trovata: il test non sta controllando niente.');

        $rotte = [];

        foreach ($schermate as $indirizzo) {
            try {
                $stato = $this->get($indirizzo)->status();
            } catch (\Throwable $e) {
                $rotte[] = $indirizzo.' → '.$e::class.': '.$e->getMessage();

                continue;
            }

            // 403 è una risposta legittima (la schermata esiste e nega
            // l'accesso), 500 no: quello è il pannello che si rompe in faccia
            // alla redazione.
            if (! in_array($stato, [200, 302, 403], true)) {
                $rotte[] = $indirizzo.' → HTTP '.$stato;
            }
        }

        $this->assertSame([], $rotte, "Schermate del pannello in errore:\n".implode("\n", $rotte));
    }
}
