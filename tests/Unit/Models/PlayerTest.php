<?php

namespace Tests\Unit\Models;

use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Roster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_name_accessor(): void
    {
        $player = Player::factory()->create([
            'first_name' => 'Paola',
            'last_name' => 'Egonu',
        ]);

        $this->assertEquals('Paola Egonu', $player->full_name);
    }

    public function test_full_name_is_appended_in_json(): void
    {
        $player = Player::factory()->create([
            'first_name' => 'Paola',
            'last_name' => 'Egonu',
        ]);

        $json = $player->toArray();
        $this->assertArrayHasKey('full_name', $json);
        $this->assertEquals('Paola Egonu', $json['full_name']);
    }

    public function test_player_has_many_rosters(): void
    {
        $player = Player::factory()->create();
        Roster::factory()->create(['player_id' => $player->id]);

        $this->assertCount(1, $player->rosters);
        $this->assertInstanceOf(Roster::class, $player->rosters->first());
    }

    public function test_player_has_many_stats(): void
    {
        $player = Player::factory()->create();
        PlayerStat::factory()->create(['player_id' => $player->id]);

        $this->assertCount(1, $player->stats);
        $this->assertInstanceOf(PlayerStat::class, $player->stats->first());
    }

    public function test_player_uses_soft_deletes(): void
    {
        $player = Player::factory()->create();
        $player->delete();

        $this->assertSoftDeleted($player);
        $this->assertCount(0, Player::all());
        $this->assertCount(1, Player::withTrashed()->get());
    }

    public function test_date_of_birth_is_cast_to_date(): void
    {
        $player = Player::factory()->create(['date_of_birth' => '1998-12-18']);

        $this->assertInstanceOf(Carbon::class, $player->date_of_birth);
    }

}
