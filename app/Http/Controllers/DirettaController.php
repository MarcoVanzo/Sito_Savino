<?php

namespace App\Http\Controllers;

use App\Enums\EventoDellaDiretta;
use App\Models\Game;
use App\Models\LiveAdStat;
use App\Models\LiveSession;
use App\Support\Diretta\ImpostazioniDellaDiretta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

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

    /**
     * Un evento per il report dello sponsor (`navigator.sendBeacon`).
     *
     * Conta e basta: nessun dato del visitatore, nemmeno l'IP (il limite delle
     * richieste lo usa solo per il proprio contatore in cache). Si contano
     * solo le creatività che la diretta di quella gara manda davvero.
     */
    public function eventi(Request $request, Game $game): Response
    {
        abort_unless(ImpostazioniDellaDiretta::abilitata(), 404);

        $sessione = LiveSession::query()->where('game_id', $game->id)->first();
        abort_if($sessione === null, 404);

        $dati = $request->validate([
            'evento' => ['required', Rule::enum(EventoDellaDiretta::class)],
            'creativita' => ['required', 'integer'],
        ]);

        $creativita = (int) $dati['creativita'];
        $inOnda = $creativita === $sessione->creativita_in_onda_id
            || $sessione->creativitaDelPreroll()->whereKey($creativita)->exists()
            || $sessione->creativitaDellaGara()->whereKey($creativita)->exists();

        abort_unless($inOnda, 422);

        LiveAdStat::conta($game->id, $creativita, EventoDellaDiretta::from($dati['evento']));

        return response()->noContent();
    }
}
