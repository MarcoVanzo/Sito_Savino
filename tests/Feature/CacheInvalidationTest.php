<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_cache_is_cleared_when_roster_is_created(): void
    {
        // La create stessa triggerà saved() — verifichiamo che la cache si svuoti
        Cache::put('public:stagione', 'cached_data', now()->addMinutes(30));

        Roster::factory()->create();

        $this->assertNull(Cache::get('public:stagione'));
    }

    public function test_cache_is_cleared_when_player_is_updated(): void
    {
        // Creare il player PRIMA di popolare la cache,
        // altrimenti la create() svuota la cache e il test è un falso positivo
        $player = Player::factory()->create();

        Cache::put('public:staff', 'cached_data', now()->addMinutes(30));
        Cache::put('public:stagione', 'cached_data', now()->addMinutes(30));

        $player->update(['first_name' => 'Nuova']);

        $this->assertNull(Cache::get('public:staff'));
        $this->assertNull(Cache::get('public:stagione'));
    }

    public function test_cache_is_cleared_when_season_is_deleted(): void
    {
        // Creare la season PRIMA di popolare la cache
        $season = Season::factory()->create();

        Cache::put('public:risultati', 'cached_data', now()->addMinutes(30));

        $season->delete();

        $this->assertNull(Cache::get('public:risultati'));
    }

    public function test_cache_is_cleared_when_team_is_updated(): void
    {
        // Creare il team PRIMA di popolare la cache
        $team = Team::factory()->create();

        Cache::put('public:stagione:b1', 'cached_data', now()->addMinutes(30));
        Cache::put('public:risultati', 'cached_data', now()->addMinutes(30));

        $team->update(['name' => 'Nuovo Nome']);

        $this->assertNull(Cache::get('public:stagione:b1'));
        $this->assertNull(Cache::get('public:risultati'));
    }
}
