<?php

namespace App\Http\Controllers;

use App\Support\Diretta\ImpostazioniDellaDiretta;
use Illuminate\Http\JsonResponse;

/**
 * Le due chiamate del sito verso la diretta con sponsor. Spento
 * l'interruttore generale non rispondono: la funzione non esiste.
 */
class DirettaController extends Controller
{
    /**
     * L'ora del server in millisecondi: il browser la confronta con la sua
     * per far comparire la cornice negli stessi istanti per tutti.
     */
    public function ora(): JsonResponse
    {
        abort_unless(ImpostazioniDellaDiretta::abilitata(), 404);

        return response()
            ->json(['ora' => now()->getTimestampMs()])
            ->header('Cache-Control', 'no-store');
    }
}
