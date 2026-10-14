<?php

namespace App\Support;

use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Support\Str;

/**
 * Le schede `/giocatrice/{slug}/` del vecchio WordPress, ricondotte alla
 * pagina dell'atleta (`stagione.atleta`) quando l'atleta è nella rosa della
 * prima squadra di oggi.
 *
 * Lo slug era quasi sempre il cognome, a volte nome e cognome, a volte due
 * persone insieme (`daniele-fallani-stefano-campani`): vale solo una
 * corrispondenza unica, altrimenti chi chiama porta alla rosa.
 */
class SchedaAtletaDelVecchioSito
{
    /**
     * Lo slug `{id}-{nome-cognome}` della pagina dell'atleta, o null.
     */
    public static function slugDellaPagina(string $vecchio): ?string
    {
        $vecchio = Str::slug(trim($vecchio, '/'));

        if ($vecchio === '') {
            return null;
        }

        $stagione = Season::current()->latest('id')->first() ?? Season::latest('id')->first();
        $squadra = Team::where('is_internal', true)
            ->where(fn ($query) => $query->where('slug', 'savino-del-bene-volley')->orWhere('category', 'A1'))
            ->first();

        if (! $stagione || ! $squadra) {
            return null;
        }

        $trovate = Roster::with('player')
            ->where('season_id', $stagione->id)
            ->where('team_id', $squadra->id)
            ->get()
            ->pluck('player')
            ->filter()
            ->filter(fn ($atleta) => in_array($vecchio, [
                Str::slug($atleta->last_name),
                Str::slug($atleta->full_name),
                Str::slug($atleta->last_name.' '.$atleta->first_name),
            ], true))
            ->unique('id');

        if ($trovate->count() !== 1) {
            return null;
        }

        $atleta = $trovate->first();

        return $atleta->id.'-'.Str::slug($atleta->full_name);
    }
}
