<?php

namespace App\Console\Commands;

use App\Support\SchedulerHeartbeat;
use Illuminate\Console\Command;

class SchedulerBeat extends Command
{
    protected $signature = 'scheduler:beat';

    protected $description = 'Registra il battito del pianificatore, letto da /up per accorgersi se il processo è morto';

    public function handle(): int
    {
        SchedulerHeartbeat::beat();

        $this->info('Battito registrato.');

        return self::SUCCESS;
    }
}
