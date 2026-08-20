<?php

namespace Tests\Feature\Filament;

use App\Enums\PlayerHonourCategory;
use App\Enums\UserRole;
use App\Filament\Resources\PlayerResource\Pages\EditPlayer;
use App\Filament\Resources\PlayerResource\RelationManagers\PlayerHonoursRelationManager;
use App\Models\Player;
use App\Models\PlayerHonour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerHonoursRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();
        $this->actingAs($user);

        $this->player = Player::factory()->create(['first_name' => 'Caterina', 'last_name' => 'Bosetti']);
    }

    private function manager(): Testable
    {
        return Livewire::test(PlayerHonoursRelationManager::class, [
            'ownerRecord' => $this->player,
            'pageClass' => EditPlayer::class,
        ]);
    }

    #[Test]
    public function la_tabella_del_palmares_si_apre(): void
    {
        $honour = $this->player->honours()->create([
            'category' => PlayerHonourCategory::Club,
            'competition' => ['it' => 'Coppa Italia', 'en' => 'Italian Cup'],
            'edition' => '2009-10',
            'year' => 2009,
            'source' => PlayerHonour::SOURCE_WIKIPEDIA,
        ]);

        $this->manager()
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$honour]);
    }

    #[Test]
    public function una_riga_modificata_in_redazione_diventa_manuale(): void
    {
        $honour = $this->player->honours()->create([
            'category' => PlayerHonourCategory::Club,
            'competition' => ['it' => 'Coppa Italia', 'en' => 'Italian Cup'],
            'edition' => '2009-10',
            'year' => 2009,
            'source' => PlayerHonour::SOURCE_WIKIPEDIA,
        ]);

        $this->manager()
            ->callTableAction('edit', $honour, [
                'category' => PlayerHonourCategory::Club->value,
                'competition' => ['it' => 'Coppa Italia 2009/10', 'en' => 'Italian Cup 2009/10'],
                'edition' => '2009-10',
                'year' => 2009,
                'is_visible' => true,
            ])
            ->assertHasNoTableActionErrors();

        $honour->refresh();

        $this->assertSame(PlayerHonour::SOURCE_MANUAL, $honour->source);
        $this->assertSame('Coppa Italia 2009/10', $honour->getTranslation('competition', 'it'));
    }

    #[Test]
    public function rimuovere_una_riga_di_wikipedia_la_nasconde_invece_di_cancellarla(): void
    {
        // Cancellarla sarebbe inutile: la prima reimportazione la rimetterebbe
        // online. Nascosta e marcata "manual", il sync la lascia stare.
        $honour = $this->player->honours()->create([
            'category' => PlayerHonourCategory::Club,
            'competition' => ['it' => 'Coppa Italia'],
            'edition' => '2009-10',
            'source' => PlayerHonour::SOURCE_WIKIPEDIA,
        ]);

        $this->manager()->callTableAction('hide', $honour);

        $honour->refresh();

        $this->assertFalse($honour->is_visible);
        $this->assertSame(PlayerHonour::SOURCE_MANUAL, $honour->source);
    }

    #[Test]
    public function rimuovere_una_riga_della_redazione_la_cancella(): void
    {
        $honour = $this->player->honours()->create([
            'category' => PlayerHonourCategory::Club,
            'competition' => ['it' => 'Trofeo interno'],
            'source' => PlayerHonour::SOURCE_MANUAL,
        ]);

        $this->manager()->callTableAction('hide', $honour);

        $this->assertDatabaseMissing('player_honours', ['id' => $honour->id]);
    }

    #[Test]
    public function il_pulsante_crea_palmares_importa_dalla_voce_indicata(): void
    {
        $wikitext = file_get_contents(base_path('tests/Fixtures/Wikipedia/bosetti.wikitext'));

        Http::fake(['*' => Http::response([
            'query' => ['pages' => [[
                'pageid' => 1,
                'title' => 'Caterina Bosetti',
                'revisions' => [['revid' => 999, 'slots' => ['main' => ['content' => $wikitext]]]],
            ]]],
        ])]);

        $this->manager()
            ->callTableAction('importPalmares', data: ['wikipedia_title' => 'Caterina Bosetti'])
            ->assertHasNoTableActionErrors();

        $this->player->refresh();

        $this->assertGreaterThan(10, $this->player->honours()->count());
        $this->assertSame('Caterina Bosetti', $this->player->wikipedia_title);
        $this->assertSame(999, $this->player->wikipedia_revid);
    }
}
