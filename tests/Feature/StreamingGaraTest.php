<?php

namespace Tests\Feature;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StreamingGaraTest extends TestCase
{
    use RefreshDatabase;

    private function garaProgrammata(?string $streamUrl): Game
    {
        $season = Season::factory()->current()->create();
        $casa = Team::factory()->create(['is_internal' => true]);
        $ospite = Team::factory()->create(['is_internal' => false]);

        return Game::factory()->create([
            'season_id' => $season->id,
            'home_team_id' => $casa->id,
            'away_team_id' => $ospite->id,
            'match_date' => now()->addDays(3),
            'status' => GameStatus::Scheduled,
            'competition_type' => CompetitionType::Championship,
            'stream_url' => $streamUrl,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_una_piattaforma_conosciuta_arriva_pronta_per_il_riquadro(): void
    {
        $this->garaProgrammata('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->get(route('stagione.risultati'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('games.0.streamUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
                ->where('games.0.streamEmbedUrl', 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1')
            );
    }

    public function test_un_dominio_sconosciuto_non_viene_incorporato(): void
    {
        // Il link resta, ma senza indirizzo di embed: il frontend apre una
        // scheda nuova invece di caricare un sito qualsiasi dentro la pagina.
        $this->garaProgrammata('https://streaming-qualsiasi.example/diretta');

        $this->get(route('stagione.risultati'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('games.0.streamUrl', 'https://streaming-qualsiasi.example/diretta')
                ->where('games.0.streamEmbedUrl', null)
            );
    }

    public function test_una_gara_senza_diretta_non_espone_nulla(): void
    {
        $this->garaProgrammata(null);

        $this->get(route('stagione.risultati'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('games.0.streamUrl', null)
                ->where('games.0.streamEmbedUrl', null)
            );
    }
}
