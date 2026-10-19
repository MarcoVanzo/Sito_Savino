<?php

namespace Tests\Feature\Diretta;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `/diretta`, l'indirizzo da promuovere: porta alla gara della società con il
 * link della diretta, in corso o la prossima, e resta valido fra una gara e
 * l'altra.
 */
class IndirizzoFissoDellaDirettaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @param  array<string, mixed>  $attributi
     */
    private function gara(array $attributi = [], bool $dellaSocieta = true): Game
    {
        return Game::factory()->create([
            'home_team_id' => Team::factory()->create(['is_internal' => $dellaSocieta])->id,
            'away_team_id' => Team::factory()->create(['is_internal' => false])->id,
            'match_date' => now()->addDays(3),
            'status' => GameStatus::Scheduled,
            'stream_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ...$attributi,
        ]);
    }

    #[Test]
    public function mostra_la_gara_in_corso_prima_della_prossima(): void
    {
        $inCorso = $this->gara(['match_date' => now()->subHour(), 'status' => GameStatus::InProgress]);
        $this->gara(['match_date' => now()->addDays(2)]);

        $this->get(route('diretta'))
            ->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('Public/Diretta')
                ->where('gara.id', $inCorso->id)
                ->where('gara.embedUrl', fn (?string $url) => str_contains((string) $url, 'youtube-nocookie.com/embed/dQw4w9WgXcQ')));
    }

    #[Test]
    public function salta_le_gare_senza_link_rinviate_finite_o_di_altre_squadre(): void
    {
        $this->gara(['stream_url' => null, 'match_date' => now()->addHour()]);
        $this->gara(['status' => GameStatus::Postponed, 'match_date' => now()->addHours(2)]);
        $this->gara(['match_date' => now()->subHours(4)]);
        $this->gara(['match_date' => now()->addHours(3)], dellaSocieta: false);
        $giusta = $this->gara(['match_date' => now()->addDays(5)]);

        $this->get(route('diretta'))
            ->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina->where('gara.id', $giusta->id));
    }

    #[Test]
    public function senza_gare_in_diretta_la_pagina_esiste_lo_stesso(): void
    {
        $this->get(route('diretta'))
            ->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('Public/Diretta')
                ->where('gara', null));

        $this->get('/en/diretta')->assertOk();
        // Sul server vero una cartella omonima in public/ vincerebbe sulla rotta.
        $this->assertDirectoryDoesNotExist(public_path('diretta'));
    }
}
