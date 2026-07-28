<?php

namespace App\Console\Commands;

use App\Models\Season;
use App\Services\Lvf\LvfStatsSyncService;
use App\Services\Lvf\LvfSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincronizza calendario, risultati e classifica dal sito della Lega.
 */
class SyncLvfData extends Command
{
    protected $signature = 'lvf:sync
                            {--season= : Anno di apertura della stagione (2026 = stagione 2026/2027). Se omesso viene dedotto dalla data odierna.}
                            {--skip-stats : Non scarica i tabellini delle gare giocate.}
                            {--force-stats : Riscarica anche i tabellini già importati.}';

    protected $description = 'Importa calendario, risultati e classifica dal sito della Lega Volley Femminile';

    public function handle(): int
    {
        $seasonYear = $this->resolveSeasonYear();

        $this->info("Sincronizzazione stagione {$seasonYear}/".($seasonYear + 1).'…');

        try {
            $stats = LvfSyncService::make()->sync($seasonYear);
        } catch (Throwable $e) {
            // Il comando gira schedulato: l'errore va nei log, non solo a video.
            Log::error('Sync Lega fallita: '.$e->getMessage(), ['exception' => $e]);
            $this->error('Sincronizzazione fallita: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Gare create', 'Gare aggiornate', 'Squadre create', 'Righe di classifica'],
            [[$stats['matches_created'], $stats['matches_updated'], $stats['teams_created'], $stats['standings']]],
        );

        if ($this->option('skip-stats')) {
            return self::SUCCESS;
        }

        // I tabellini si scaricano solo per le gare del club già concluse.
        $this->info('Import dei tabellini…');

        try {
            $season = Season::where('lvf_season_year', $seasonYear)->first();
            $statsResult = LvfStatsSyncService::make()->sync($season?->id, (bool) $this->option('force-stats'));
        } catch (Throwable $e) {
            Log::error('Import tabellini fallito: '.$e->getMessage(), ['exception' => $e]);
            $this->error('Import dei tabellini fallito: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Gare con tabellino', 'Righe importate', 'Agganciate alle nostre atlete', 'Saltate'],
            [[$statsResult['games'], $statsResult['rows'], $statsResult['matched'], $statsResult['skipped']]],
        );

        return self::SUCCESS;
    }

    /**
     * La stagione sportiva si apre in autunno: fino a giugno si è ancora nella
     * stagione iniziata l'anno precedente.
     */
    private function resolveSeasonYear(): int
    {
        $option = $this->option('season');

        if ($option !== null && $option !== '') {
            return (int) $option;
        }

        $now = now();

        return $now->month >= 7 ? $now->year : $now->year - 1;
    }
}
