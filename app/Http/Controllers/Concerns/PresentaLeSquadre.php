<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Team;

/**
 * Dati di una squadra da mandare al frontend, condivisi fra la pagina della
 * stagione e quelle dei risultati.
 */
trait PresentaLeSquadre
{
    /**
     * Logo della squadra, o null se non ne ha uno.
     *
     * `logoUrl()` sceglie fra quello caricato in redazione e quello importato
     * dalla Lega, e per una squadra senza logo restituisce stringa vuota: nel
     * frontend una stringa vuota diventerebbe un `<img src="">`, cioe' una
     * richiesta alla pagina stessa.
     */
    private function teamLogo(?Team $team): ?string
    {
        $logo = $team?->logoUrl();

        return ($logo === null || $logo === '') ? null : $logo;
    }
}
