<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'import dal vecchio sito e' schedulato solo fino al passaggio del dominio.
 *
 * Il 1 ottobre 2026 `savinodelbenevolley.it` diventa questo sito: `wp-json` non
 * risponde piu' e il comando fallirebbe a ogni giro, tutti i giorni, finche'
 * qualcuno non se ne accorge. La scadenza e' in `config`, e questo test e' cio'
 * che impedisce che resti una buona intenzione scritta in un commento.
 */
class ImportNotizieSchedulatoTest extends TestCase
{
    private function voceDelloScheduler(): Event
    {
        $eventi = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (Event $evento): bool => str_contains($evento->command ?? '', 'news:importa-dal-vecchio-sito'),
        ));

        $this->assertCount(1, $eventi, "l'import dal vecchio sito deve essere schedulato una volta sola");

        return $eventi[0];
    }

    #[Test]
    public function gira_ogni_ora_finche_il_vecchio_sito_e_leggibile(): void
    {
        Carbon::setTestNow('2026-09-25 11:00:00');

        $evento = $this->voceDelloScheduler();

        $this->assertSame('0 * * * *', $evento->expression);
        $this->assertTrue($evento->filtersPass($this->app), 'prima del passaggio deve girare');
    }

    /**
     * Il giorno dello switch gira ancora: un comunicato uscito in mattinata,
     * con il dominio che passa nel pomeriggio, e' l'ultimo recuperabile.
     */
    #[Test]
    public function gira_ancora_il_giorno_del_passaggio(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00');

        $this->assertTrue($this->voceDelloScheduler()->filtersPass($this->app));
    }

    #[Test]
    public function si_spegne_da_solo_dopo_il_passaggio(): void
    {
        Carbon::setTestNow('2026-10-02 00:00:01');

        $this->assertFalse(
            $this->voceDelloScheduler()->filtersPass($this->app),
            'dopo il passaggio il vecchio sito non risponde: continuare a chiamarlo e\' solo rumore'
        );
    }

    #[Test]
    public function la_scadenza_si_sposta_dalla_configurazione(): void
    {
        config(['services.vecchio_sito.leggibile_fino_a' => '2026-11-01']);
        Carbon::setTestNow('2026-10-15 09:00:00');

        $this->assertTrue(
            $this->voceDelloScheduler()->filtersPass($this->app),
            'se il passaggio slitta basta spostare la data, senza toccare il codice'
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
