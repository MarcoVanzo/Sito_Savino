<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Inertia\Inertia;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;

class PublicController extends Controller
{
    public function home()
    {
        return Inertia::render('Public/Home');
    }

    public function stagione()
    {
        $teamA1 = Team::where('slug', 'serie-a1')->first();

        // Usa la stagione corrente (is_current=true) anziché ID hardcoded
        $currentSeason = Season::current()->first();

        $roster = [];
        if ($teamA1 && $currentSeason) {
            $roster = Roster::with([
                    'player',
                    'player.stats' => fn ($query) => $query->where('season_id', $currentSeason->id),
                ])
                ->where('team_id', $teamA1->id)
                ->where('season_id', $currentSeason->id)
                ->orderBy('jersey_number')
                ->get();
        }

        return Inertia::render('Public/Stagione', [
            'roster' => $roster,
            'seasonName' => $currentSeason?->name,
        ]);
    }
}
