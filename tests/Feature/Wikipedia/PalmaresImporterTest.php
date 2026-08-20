<?php

namespace Tests\Feature\Wikipedia;

use App\Enums\PlayerHonourCategory;
use App\Models\Player;
use App\Models\PlayerHonour;
use App\Services\Wikipedia\PalmaresImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PalmaresImporterTest extends TestCase
{
    use RefreshDatabase;

    private function wikitext(string $name = 'bosetti'): string
    {
        return file_get_contents(base_path("tests/Fixtures/Wikipedia/{$name}.wikitext"));
    }

    private function import(Player $player, string $fixture = 'bosetti'): array
    {
        return app(PalmaresImporter::class)
            ->import($player, $this->wikitext($fixture), 'Caterina Bosetti', 150053182);
    }

    #[Test]
    public function scrive_il_palmares_e_laggancio_alla_voce(): void
    {
        $player = Player::factory()->create();

        $stats = $this->import($player);

        $this->assertGreaterThan(0, $stats['imported']);
        $this->assertSame($stats['imported'], $player->honours()->count());

        $player->refresh();
        $this->assertSame('Caterina Bosetti', $player->wikipedia_title);
        $this->assertSame('it', $player->wikipedia_lang);
        $this->assertSame(150053182, $player->wikipedia_revid);
        $this->assertNotNull($player->palmares_synced_at);
    }

    #[Test]
    public function rilanciarlo_non_duplica_niente(): void
    {
        $player = Player::factory()->create();

        $primo = $this->import($player);
        $secondo = $this->import($player);

        $this->assertSame($primo['imported'], $secondo['imported']);
        $this->assertSame($primo['imported'], $player->honours()->count());
    }

    #[Test]
    public function le_righe_della_redazione_sopravvivono_allimportazione(): void
    {
        $player = Player::factory()->create();

        $manuale = $player->honours()->create([
            'category' => PlayerHonourCategory::Club,
            'competition' => ['it' => 'Trofeo di famiglia'],
            'edition' => '2020',
            'year' => 2020,
            'source' => PlayerHonour::SOURCE_MANUAL,
        ]);

        $this->import($player);

        $this->assertDatabaseHas('player_honours', ['id' => $manuale->id]);
        $this->assertSame(1, $player->honours()->where('source', PlayerHonour::SOURCE_MANUAL)->count());
    }

    #[Test]
    public function un_trofeo_gia_corretto_a_mano_non_rientra_in_doppio(): void
    {
        $player = Player::factory()->create();

        // Stessa chiave naturale di una riga presente nella voce: la versione
        // della redazione vince e quella importata viene scartata.
        $player->honours()->create([
            'category' => PlayerHonourCategory::Club,
            'competition' => ['it' => 'Coppa Italia'],
            'edition' => '2009-10',
            'year' => 2009,
            'source' => PlayerHonour::SOURCE_MANUAL,
        ]);

        $stats = $this->import($player);

        $this->assertSame(1, $stats['skipped']);
        $this->assertSame(
            1,
            $player->honours()
                ->where('category', PlayerHonourCategory::Club->value)
                ->where('edition', '2009-10')
                ->count(),
        );
    }

    #[Test]
    public function una_riga_nascosta_dalla_redazione_non_torna_online(): void
    {
        $player = Player::factory()->create();
        $this->import($player);

        $riga = $player->honours()->where('edition', '2009-10')->firstOrFail();
        $riga->update(['is_visible' => false, 'source' => PlayerHonour::SOURCE_MANUAL]);

        $this->import($player);

        $this->assertSame(
            1,
            $player->honours()->where('edition', '2009-10')->count(),
        );
        $this->assertFalse((bool) $player->honours()->where('edition', '2009-10')->value('is_visible'));
    }

    #[Test]
    public function traduce_in_inglese_i_nomi_in_dizionario_e_lascia_gli_altri(): void
    {
        $player = Player::factory()->create();
        $this->import($player);

        $coppaItalia = $player->honours()->where('edition', '2009-10')->firstOrFail();

        $this->assertSame('Coppa Italia', $coppaItalia->getTranslation('competition', 'it'));
        $this->assertSame('Italian Cup', $coppaItalia->getTranslation('competition', 'en'));

        // Le categorie giovanili non stanno in dizionario una per una: si
        // traduce la competizione e si riattacca il suffisso.
        $under = $player->honours()
            ->where('category', PlayerHonourCategory::National->value)
            ->get()
            ->first(fn ($h) => str_contains($h->getTranslation('competition', 'it'), 'under 19'));

        $this->assertNotNull($under);
        $this->assertSame('European Championship U19', $under->getTranslation('competition', 'en'));
    }

    #[Test]
    public function ordina_prima_i_club_poi_la_nazionale_poi_i_premi(): void
    {
        $player = Player::factory()->create();
        $this->import($player);

        $categorie = $player->honours()->orderBy('sort_order')->pluck('category')->unique()->values()->all();

        $this->assertSame(
            [PlayerHonourCategory::Club, PlayerHonourCategory::National, PlayerHonourCategory::Individual],
            $categorie,
        );
    }
}
