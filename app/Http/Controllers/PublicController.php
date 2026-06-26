<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Inertia\Inertia;
use App\Models\Roster;
use App\Models\Team;

class PublicController extends Controller
{
    public function stagione()
    {
        $teamA1 = Team::where('slug', 'serie-a1')->first();
        
        $roster = [];
        if ($teamA1) {
            $roster = Roster::with('player', 'player.stats')
                ->where('team_id', $teamA1->id)
                ->where('season_id', 1) // Attualmente hardcoded per la 26/27 (id 1)
                ->orderBy('jersey_number')
                ->get();
        }

        return Inertia::render('Public/Stagione', [
            'roster' => $roster
        ]);
    }
}
