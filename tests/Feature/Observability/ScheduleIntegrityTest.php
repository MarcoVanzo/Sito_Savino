<?php

namespace Tests\Feature\Observability;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il pianificatore gira in un componente suo (vedi .do/app.yaml). Se un giorno
 * `instance_count` venisse alzato, ogni comando senza `withoutOverlapping()`
 * verrebbe eseguito in parallelo su tutte le istanze — e nessuno se ne
 * accorgerebbe, perché il risultato è silenzioso: una sitemap rigenerata due
 * volte, log potati due volte, carrelli scaduti cancellati due volte.
 */
class ScheduleIntegrityTest extends TestCase
{
    /**
     * @return list<Event>
     */
    private function events(): array
    {
        return app(Schedule::class)->events();
    }

    #[Test]
    public function ogni_comando_schedulato_e_protetto_dalla_sovrapposizione(): void
    {
        $unprotected = [];

        foreach ($this->events() as $event) {
            // Il battito è l'unica eccezione legittima: è una singola scrittura
            // in cache, non ha stato da corrompere, e un lock lo renderebbe
            // dipendente dalla cache proprio mentre serve a diagnosticarla.
            if (str_contains((string) $event->command, 'scheduler:beat')) {
                continue;
            }

            if (! $event->withoutOverlapping) {
                $unprotected[] = $event->command;
            }
        }

        $this->assertSame([], $unprotected, 'Comandi schedulati senza withoutOverlapping(): '.implode(', ', $unprotected));
    }

    #[Test]
    public function il_battito_e_schedulato_ogni_minuto(): void
    {
        // È ciò che regge l'health check: se sparisse dal ciclo o rallentasse,
        // `/up` inizierebbe a dichiarare morto uno scheduler vivo.
        $beat = collect($this->events())
            ->first(fn (Event $e) => str_contains((string) $e->command, 'scheduler:beat'));

        $this->assertNotNull($beat, 'Il comando scheduler:beat non è più schedulato.');
        $this->assertSame('* * * * *', $beat->expression);
    }
}
