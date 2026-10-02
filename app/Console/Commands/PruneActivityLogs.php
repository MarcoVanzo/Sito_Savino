<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\RevocaDelRiconoscimentoDeiVolti;
use Illuminate\Console\Command;

class PruneActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'activity-log:prune
                            {--days=180 : Cancella i log più vecchi di N giorni}
                            {--dry-run : Mostra quanti record verrebbero cancellati senza cancellarli}
                            {--force : Cancella senza chiedere conferma (pianificatore)}';

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

        // La revoca del riconoscimento dei volti e' la prova che un consenso
        // biometrico e' stato ritirato e il dato cancellato: non scade con il
        // resto del registro.
        $query = ActivityLog::where('created_at', '<', $cutoff)->where('action', '!=', RevocaDelRiconoscimentoDeiVolti::AZIONE);
        $count = $query->count();

        if ($count === 0) {
            $this->info("Nessun log più vecchio di {$days} giorni.");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[Dry run] {$count} record verrebbero cancellati (più vecchi di {$cutoff->format('d/m/Y')}).");

            return self::SUCCESS;
        }

        // Dal pianificatore non c'è nessuno a rispondere: `confirm()` in modalità
        // non interattiva risponde no, e la pulizia settimanale (lanciata con
        // --force da routes/console.php) annullava sempre senza cancellare nulla.
        if (! $this->option('force') && ! $this->confirm("Cancellare {$count} record di log più vecchi di {$days} giorni?")) {
            $this->info('Operazione annullata.');

            return self::SUCCESS;
        }

        // Cancellazione in chunk per evitare memory leak su grandi dataset
        $deleted = 0;
        ActivityLog::where('created_at', '<', $cutoff)->where('action', '!=', RevocaDelRiconoscimentoDeiVolti::AZIONE)
            ->chunkById(1000, function ($logs) use (&$deleted) {
                $ids = $logs->pluck('id');
                ActivityLog::whereIn('id', $ids)->delete();
                $deleted += $ids->count();
            });

        $this->info("✓ {$deleted} record eliminati.");

        return self::SUCCESS;
    }
}
