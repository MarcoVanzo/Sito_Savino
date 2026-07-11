<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Services\FacialRecognitionService;
use Illuminate\Console\Command;
use Throwable;

class TestCompreFace extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compreface:test {--player= : ID of the player to test (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the Facial Recognition Service using a player image';

    /**
     * Execute the console command.
     */
    public function handle(FacialRecognitionService $service)
    {
        $playerId = $this->option('player');

        if ($playerId) {
            $player = Player::find($playerId);
        } else {
            // Troviamo il primo player che ha un media associato
            $player = Player::has('media')->first();
        }

        if (! $player) {
            $this->error('Nessun player con foto trovato nel database locale.');

            return 1;
        }

        $this->info('Test su Player: '.$player->full_name.' (ID: '.$player->id.')');

        $media = $player->getFirstMedia('players');
        if (! $media) {
            $this->error('Media non trovato per il player.');

            return 1;
        }

        $this->line('Download media da S3 ('.$media->disk.'): '.$media->file_name.'...');
        $path = $service->downloadMediaToTemp($media);

        if ($path) {
            $this->info("Immagine scaricata temporaneamente in: $path");
            $this->line('Esecuzione riconoscimento facciale su CompreFace...');
            try {
                $result = $service->recognizeFaces($path, 0.80);
                $this->info('Risultato da CompreFace:');
                $this->line(print_r($result, true));
            } catch (Throwable $e) {
                $this->error('Errore durante il riconoscimento: '.$e->getMessage());
            }
            @unlink($path);
            $this->info('File temporaneo rimosso.');
        } else {
            $this->error('Errore nel download del media dal disk '.$media->disk);

            return 1;
        }

        return 0;
    }
}
