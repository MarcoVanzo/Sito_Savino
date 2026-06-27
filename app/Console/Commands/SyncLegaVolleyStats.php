<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Player;
use App\Models\PlayerStat;
use Illuminate\Support\Facades\Log;

class SyncLegaVolleyStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:legavolley';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizza le statistiche delle giocatrici (Punti, Muri, Ace) dalla Lega Volley';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // GUARD: impedisci l'esecuzione in produzione con dati simulati
        if (app()->isProduction()) {
            $this->error('Questo comando usa dati simulati e NON deve essere eseguito in produzione.');
            $this->error('Implementare prima il vero web scraping dalla Lega Volley.');
            return self::FAILURE;
        }

        $this->info('Inizio sincronizzazione statistiche Lega Volley (SIMULAZIONE)...');
        
        $players = Player::whereNotNull('lega_volley_id')->get();
        
        if ($players->isEmpty()) {
            $this->warn('Nessuna giocatrice con lega_volley_id trovata nel database.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($players));
        $bar->start();

        $currentSeason = \App\Models\Season::where('is_current', true)->first();
        if (!$currentSeason) {
            $this->error('Nessuna stagione corrente (is_current = true) trovata nel database.');
            return self::FAILURE;
        }

        foreach ($players as $player) {
            // Qui andrebbe la logica REALE di web scraping (es. Goutte/DOM Crawler)
            // Http::get("https://www.legavolleyfemminile.it/player/.../{$player->lega_volley_id}");
            // Per questa architettura, simuliamo l'estrazione dati usando il lega_volley_id come seme.
            
            // SIMULAZIONE DI SCRAPING
            // Genera dati verosimili e stabili per la giornata (deterministico senza rand)
            $seed = crc32($player->lega_volley_id . date('Ymd'));
            $scrapedData = [
                'points' => 50 + abs($seed) % 401,           // 50-450
                'blocks' => 5 + abs($seed >> 8) % 76,         // 5-80
                'aces'   => 2 + abs($seed >> 16) % 39,        // 2-40
            ];
            
            // Salvataggio nel Data Warehouse interno (Upsert o updateOrCreate)
            PlayerStat::updateOrCreate(
                [
                    'player_id' => $player->id,
                    'season_id' => $currentSeason->id,
                ],
                [
                    'points' => $scrapedData['points'],
                    'blocks' => $scrapedData['blocks'],
                    'aces' => $scrapedData['aces'],
                    'last_synced_at' => now(),
                ]
            );

            // Logghiamo l'operazione per controllo
            Log::info("Sync Statistiche per {$player->last_name} ({$player->lega_volley_id}) completato.");
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sincronizzazione completata con successo!');

        return self::SUCCESS;
    }
}
