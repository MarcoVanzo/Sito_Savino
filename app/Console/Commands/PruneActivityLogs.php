<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class PruneActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'activity-log:prune
                            {--days=180 : Cancella i log più vecchi di N giorni}
                            {--dry-run : Mostra quanti record verrebbero cancellati senza cancellarli}';

    /**
     * The console command description.
     */
    protected $description = 'Elimina i record di activity log più vecchi di un numero specificato di giorni';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $query = ActivityLog::where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->info("Nessun log più vecchio di {$days} giorni.");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[Dry run] {$count} record verrebbero cancellati (più vecchi di {$cutoff->format('d/m/Y')}).");

            return self::SUCCESS;
        }

        if (! $this->confirm("Cancellare {$count} record di log più vecchi di {$days} giorni?")) {
            $this->info('Operazione annullata.');

            return self::SUCCESS;
        }

        // Cancellazione in chunk per evitare memory leak su grandi dataset
        $deleted = 0;
        ActivityLog::where('created_at', '<', $cutoff)
            ->chunkById(1000, function ($logs) use (&$deleted) {
                $ids = $logs->pluck('id');
                ActivityLog::whereIn('id', $ids)->delete();
                $deleted += $ids->count();
            });

        $this->info("✓ {$deleted} record eliminati.");

        return self::SUCCESS;
    }
}
