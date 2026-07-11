<?php

use App\Models\Player;
use App\Services\FacialRecognitionService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$service = app(FacialRecognitionService::class);

// Troviamo il primo player che ha un media associato
$player = Player::has('media')->first();

if (! $player) {
    echo "Nessun player con foto trovato nel database locale.\n";
    exit;
}

echo 'Test su Player: '.$player->full_name.' (ID: '.$player->id.")\n";

$media = $player->getFirstMedia('players');
if (! $media) {
    echo "Media non trovato per il player.\n";
    exit;
}

echo 'Download media da S3 ('.$media->disk.'): '.$media->file_name."...\n";
$path = $service->downloadMediaToTemp($media);

if ($path) {
    echo "Immagine scaricata temporaneamente in: $path\n";
    echo "Esecuzione riconoscimento facciale su CompreFace...\n";
    try {
        $result = $service->recognizeFaces($path, 0.80);
        echo "Risultato da CompreFace:\n";
        print_r($result);
    } catch (Throwable $e) {
        echo 'Errore durante il riconoscimento: '.$e->getMessage()."\n";
    }
    @unlink($path);
    echo "File temporaneo rimosso.\n";
} else {
    echo 'Errore nel download del media dal disk '.$media->disk."\n";
}
