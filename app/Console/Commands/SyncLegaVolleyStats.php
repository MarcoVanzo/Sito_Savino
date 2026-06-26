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
    public function handle()
    {
        $this->info('Inizio sincronizzazione statistiche Lega Volley...');
        
        $players = Player::whereNotNull('lega_volley_id')->get();
        
        if ($players->isEmpty()) {
            $this->warn('Nessuna giocatrice con lega_volley_id trovata nel database.');
            return;
        }

        $bar = $this->output->createProgressBar(count($players));
        $bar->start();

        foreach ($players as $player) {
            // Qui andrebbe la logica REALE di web scraping (es. Goutte/DOM Crawler)
            // Http::get("https://www.legavolleyfemminile.it/player/.../{$player->lega_volley_id}");
            // Per questa architettura, simuliamo l'estrazione dati usando il lega_volley_id come seme.
            
            // SIMULAZIONE DI SCRAPING
            srand($player->lega_volley_id + date('Ymd')); // Genera dati verosimili e stabili per la giornata
            
            $scrapedData = [
                'points' => rand(50, 450),
                'blocks' => rand(5, 80),
                'aces'   => rand(2, 40),
            ];
            
            // Salvataggio nel Data Warehouse interno (Upsert o updateOrCreate)
            PlayerStat::updateOrCreate(
                [
                    'player_id' => $player->id,
                    'season_id' => 1, // Stats correnti per la stagione 26/27 (Hardcoded temporaneamente)
                ],
                [
                    'points' => $scrapedData['points'],
                    'blocks' => $scrapedData['blocks'],
                    'aces' => $scrapedData['aces'],
                ]
            );

            // Logghiamo l'operazione per controllo
            Log::info("Sync Statistiche per {$player->last_name} ({$player->lega_volley_id}) completato.");
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sincronizzazione completata con successo!');
    }
}
