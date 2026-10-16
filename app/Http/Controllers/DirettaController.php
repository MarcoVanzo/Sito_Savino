<?php

namespace App\Http\Controllers;

use App\Enums\EventoDellaDiretta;
use App\Filament\Pages\Settings\DirettaSettingsPage;
use App\Models\Game;
use App\Models\LiveAdStat;
use App\Models\LiveSession;
use App\Support\Diretta\DirettaPerIlSito;
use App\Support\Diretta\ImpostazioniDellaDiretta;
use App\Support\LiveStream;
use App\Support\MatchDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Le chiamate del sito verso la diretta con sponsor. Spento l'interruttore
 * generale `ora` ed `eventi` non rispondono: la funzione non esiste. La
 * pagina di prova invece funziona sempre, ma solo per lo staff.
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
        $evento = EventoDellaDiretta::from($dati['evento']);

        // Fuori dal tempo della gara non si conta, ma non è un errore: chi apre
        // la diretta in anticipo manda i suoi eventi, e i 422 riempivano la
        // console. Un evento incoerente con la grafica resta un 422.
        if (! $this->nelTempoDellaGara($game, $sessione)) {
            return response()->noContent();
        }

        abort_unless($this->possibile($sessione, $evento, $creativita), 422);

        LiveAdStat::conta($game->id, $creativita, $evento);

        return response()->noContent();
    }

    /**
     * Gli eventi contano solo attorno alla gara: un'ora prima del fischio
     * d'inizio (il pre-roll si vede aprendo la diretta in anticipo) e fino a
     * un'ora dopo la fine del ciclo. Fuori, nessuno sta guardando la diretta.
     */
    private function nelTempoDellaGara(Game $game, LiveSession $sessione): bool
    {
        $fine = $sessione->ciclo_fine ?? $game->match_date->copy()->addHours(MatchDay::ORE_DOPO_L_INIZIO);

        return now()->between($game->match_date->copy()->subHour(), $fine->copy()->addHour());
    }

    /**
     * L'evento è coerente con dove la creatività va in onda: un pre-roll
     * solo per gli spot del pre-roll acceso, una cornice solo per quelle
     * della gara. Senza, uno script poteva riempire il report di
     * «completati» su una cornice.
     */
    private function possibile(LiveSession $sessione, EventoDellaDiretta $evento, int $creativita): bool
    {
        $nelPreroll = fn (): bool => $sessione->preroll_attivo && $sessione->creativitaDelPreroll()->whereKey($creativita)->exists();
        $nellaGara = fn (): bool => $sessione->creativitaDellaGara()->whereKey($creativita)->exists();

        return match ($evento) {
            EventoDellaDiretta::PrerollAvviato, EventoDellaDiretta::PrerollCompletato, EventoDellaDiretta::PrerollSaltato => $nelPreroll(),
            EventoDellaDiretta::CicloMostrato => $nellaGara(),
            EventoDellaDiretta::PausaMostrata => $creativita === $sessione->creativita_in_onda_id || $nellaGara(),
            EventoDellaDiretta::Clic => $creativita === $sessione->creativita_in_onda_id || $nelPreroll() || $nellaGara(),
        };
    }

    /**
     * La prova della diretta per lo staff: la stessa finestra che vede il
     * tifoso, con spot e cornici della gara, anche a interruttore spento.
     * Riservata a chi cura gli sponsor; mai in cache, mai indicizzata.
     */
    public function prova(Request $request, Game $game): \Inertia\Response|\Symfony\Component\HttpFoundation\Response
    {
        abort_unless((bool) $request->user()?->can('update', LiveSession::class), 403);

        $game->load(['homeTeam', 'awayTeam']);

        $risposta = Inertia::render('Public/ProvaDiretta', [
            'gara' => [
                'titolo' => $game->homeTeam?->nomePubblico().' — '.$game->awayTeam?->nomePubblico(),
                'data' => $game->match_date->toIso8601String(),
                'embedUrl' => LiveStream::embedUrl($game->stream_url),
            ],
            'sponsor' => DirettaPerIlSito::perLaProva($game),
            'interruttoreAcceso' => ImpostazioniDellaDiretta::abilitata(),
            'urlImposta' => DirettaSettingsPage::getUrl(),
        ])->toResponse($request);

        $risposta->headers->set('Cache-Control', 'no-store, private');
        $risposta->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $risposta;
    }
}
