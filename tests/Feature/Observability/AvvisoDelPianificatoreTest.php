<?php

namespace Tests\Feature\Observability;

use App\Services\AvvisoDelPianificatore;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `schedule:work` manda l'output dei comandi in /dev/null: un comando che
 * usciva con errore non lasciava traccia. Ora l'output si cattura e il
 * fallimento arriva per email.
 */
class AvvisoDelPianificatoreTest extends TestCase
{
    private function evento(string $comando): Event
    {
        $evento = app(Schedule::class)->exec($comando);
        AvvisoDelPianificatore::aggancia($evento);

        return $evento;
    }

    #[Test]
    public function un_comando_che_fallisce_manda_l_avviso_con_l_output(): void
    {
        config(['services.avvisi.email' => 'allarmi@example.com']);

        $this->evento('echo "Sincronizzazione fallita: timeout"; exit 3')->run(app());

        $messaggio = AvvisoTecnicoTest::inviate()->sole()->getOriginalMessage();
        $this->assertStringContainsString('Comando pianificato fallito', $messaggio->getSubject());
        $this->assertStringContainsString('codice 3', $messaggio->getTextBody());
        $this->assertStringContainsString('Sincronizzazione fallita: timeout', $messaggio->getTextBody());
    }

    #[Test]
    public function un_comando_riuscito_non_avvisa(): void
    {
        config(['services.avvisi.email' => 'allarmi@example.com']);

        $this->evento('echo tutto bene')->run(app());

        $this->assertCount(0, AvvisoTecnicoTest::inviate());
    }

    #[Test]
    public function lo_stesso_comando_non_inonda_la_casella(): void
    {
        config(['services.avvisi.email' => 'allarmi@example.com']);

        $evento = $this->evento('exit 1');
        $evento->run(app());
        $evento->run(app());

        $this->assertCount(1, AvvisoTecnicoTest::inviate());
    }

    #[Test]
    public function il_nome_e_quello_del_comando_artisan(): void
    {
        $evento = app(Schedule::class)->command('lvf:sync --season=2026');

        $this->assertSame('lvf:sync --season=2026', AvvisoDelPianificatore::nome($evento));
    }

    #[Test]
    public function ogni_evento_pianificato_cattura_l_output(): void
    {
        // Proxy dell'aggancio: onFailureWithOutput sposta l'output da
        // /dev/null a un file. Un evento registrato dopo il ciclo in fondo a
        // routes/console.php resterebbe muto.
        $muti = collect(app(Schedule::class)->events())
            ->filter(fn (Event $e) => $e->output === $e->getDefaultOutput())
            ->map(fn (Event $e) => AvvisoDelPianificatore::nome($e))
            ->values()
            ->all();

        $this->assertSame([], $muti, 'Eventi pianificati senza avviso sul fallimento: '.implode(', ', $muti));
    }
}
