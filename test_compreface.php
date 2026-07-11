<?php

use App\Models\GalleryImage;
use App\Services\FacialRecognitionService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$service = app(FacialRecognitionService::class);
// Troviamo una gallery image
$image = GalleryImage::with('media')->has('media')->first();
if (! $image) {
    echo "Nessuna immagine\n";
    exit;
}
$media = $image->getFirstMedia('gallery');
$path = $service->downloadMediaToTemp($media);

if ($path) {
    echo "Immagine temporanea: $path\n";
    $result = $service->recognizeFaces($path, 0.85); // testiamo con 0.85
    print_r($result);
    @unlink($path);
} else {
    echo "Errore download media\n";
}
