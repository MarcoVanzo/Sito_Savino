<?php

namespace Tests;

use Filament\Notifications\NotificationMailbox;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var list<array<string, mixed>> */
    private array $notificheDiFilament = [];

    /**
     * Le notifiche di Filament mandate finora, in ordine.
     *
     * Da Filament 4 non stanno più in sessione (`filament.notifications`) ma
     * in una casella in cache, che si svuota leggendola: qui si tengono da
     * parte, così il test può rileggerle.
     *
     * @return list<array<string, mixed>>
     */
    protected function notificheDiFilament(): array
    {
        $casella = app(NotificationMailbox::class);
        $casella->flush();

        return $this->notificheDiFilament = [...$this->notificheDiFilament, ...array_values($casella->pull())];
    }
}
