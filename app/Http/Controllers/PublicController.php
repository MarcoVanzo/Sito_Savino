<?php

namespace App\Http\Controllers;


use Inertia\Inertia;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;

class PublicController extends Controller
{
    public function home()
    {
        return Inertia::render('Public/Home');
    }

    public function stagione()
    {
        // Cache la query roster per 10 minuti (i dati cambiano raramente)
        $data = Cache::remember('public:stagione', now()->addMinutes(10), function () {
            $teamA1 = Team::where('slug', 'serie-a1')->first();
            $currentSeason = Season::current()->first();

            $roster = [];
            $seasonName = null;

            if ($teamA1 && $currentSeason) {
                $roster = Roster::with([
                        'player',
                        'player.stats' => fn ($query) => $query->where('season_id', $currentSeason->id),
                    ])
                    ->where('team_id', $teamA1->id)
                    ->where('season_id', $currentSeason->id)
                    ->orderBy('jersey_number')
                    ->get();

                $seasonName = $currentSeason->name;
            }

            return compact('roster', 'seasonName');
        });

        return Inertia::render('Public/Stagione', $data);
    }
}
