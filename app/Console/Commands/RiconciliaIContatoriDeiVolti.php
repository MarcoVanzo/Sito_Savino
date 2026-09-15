<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Services\FacialRecognitionException;
use App\Services\FacialRecognitionService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

/**
 * Riallinea `players.ai_face_examples` a quanto CompreFace conserva davvero.
 *
 * Il contatore cresce a ogni foto appresa dal pannello, ma il servizio di
 * riconoscimento vive su un altro server e può essere azzerato o ricreato
 * senza che l'archivio lo sappia: la colonna "Esempi AI" del pannello
 * mostrava 3-7 esempi per atleta quando CompreFace ne aveva uno solo, e la
 * redazione non aveva modo di capire perché le foto non venissero taggate.
 * Gira ogni giorno dallo scheduler e si può lanciare a mano.
 */
class RiconciliaIContatoriDeiVolti extends Command
{
    protected $signature = 'volti:riconcilia-contatori';

    protected $description = 'Riallinea il numero di esempi di volto delle atlete a quello conservato da CompreFace';

    public function handle(FacialRecognitionService $servizio): int
    {
        try {
            $conteggi = $servizio->esempiPerSoggetto();
        } catch (ConnectionException|FacialRecognitionException $e) {
            // Senza risposta i contatori restano com'erano: un valore vecchio è
            // meglio di uno azzerato per un timeout.
            $this->error('CompreFace non raggiungibile, contatori lasciati invariati: '.$e->getMessage());

            return self::FAILURE;
        }

        $aggiornate = 0;

        foreach (Player::query()->get(['id', 'ai_face_examples']) as $atleta) {
            $esempi = $conteggi[$servizio->getSubjectName($atleta)] ?? 0;

            if ($atleta->ai_face_examples !== $esempi) {
                $atleta->update(['ai_face_examples' => $esempi]);
                $aggiornate++;
            }
        }

        $senzaEsempi = Player::query()->where('ai_face_examples', 0)->count();
        $staff = collect($conteggi)->filter(fn (int $n, string $soggetto) => str_starts_with($soggetto, 'staff_'));

        $this->info("Contatori aggiornati: {$aggiornate}. Atlete senza esempi: {$senzaEsempi}. Membri dello staff con esempi: {$staff->count()}.");

        return self::SUCCESS;
    }
}
