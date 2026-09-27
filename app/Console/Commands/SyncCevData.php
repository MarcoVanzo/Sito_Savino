<?php

namespace App\Console\Commands;

use App\Services\Cev\CevSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincronizza calendario, risultati e classifica del girone della CEV
 * Champions League dal portale della CEV.
 */
class SyncCevData extends Command
{
    protected $signature = 'cev:sync
                            {--competizione= : Identificativo della competizione sul portale CEV (predefinito: services.cev.competition_id).}
                            {--season= : Anno di apertura della stagione (2026 = stagione 2026/2027).}';

    protected $description = 'Importa calendario, risultati e classifica della CEV Champions League';

    public function handle(): int
    {
        $competizione = (int) ($this->option('competizione') ?: config('services.cev.competition_id'));
        $stagione = (int) ($this->option('season') ?: config('services.cev.season_year'));

        if ($competizione <= 0 || $stagione <= 0) {
            $this->error('Competizione o stagione non configurate (services.cev).');

            return self::FAILURE;
        }

        $this->info("CEV: competizione {$competizione}, stagione {$stagione}/".($stagione + 1).'…');

        try {
            $stats = CevSyncService::make()->sync($competizione, $stagione);
        } catch (Throwable $e) {
            // Gira schedulato: l'errore va nei log, non solo a video.
            Log::error('Sync CEV fallita: '.$e->getMessage(), ['exception' => $e]);
            $this->error('Sincronizzazione fallita: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Gare create', 'Gare aggiornate', 'Gare rimosse', 'In attesa (squadre o data)', 'Squadre create', 'Classifica', 'Girone'],
            [[
                $stats['matches_created'],
                $stats['matches_updated'],
                $stats['matches_removed'],
                $stats['matches_skipped'],
                $stats['teams_created'],
                $stats['standings'],
                $stats['girone'] ?? '—',
            ]],
        );

        if (! $stats['societa_trovata']) {
            $this->warn('Nessuna gara della società: il nome in services.cev.nomi_della_societa corrisponde ancora a quello della CEV?');
        }

        return self::SUCCESS;
    }
}
