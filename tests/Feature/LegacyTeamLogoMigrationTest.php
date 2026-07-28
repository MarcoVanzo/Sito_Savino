<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Copre la migrazione che sposta i loghi squadra dalla vecchia collezione
 * `teams` (mai registrata sul modello) a `logo` (Team::LOGO_CUSTOM).
 */
class LegacyTeamLogoMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const DISK = 'public';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(self::DISK);
    }

    /**
     * La migrazione è una classe anonima: `up()` e `down()` esistono solo
     * sull'istanza restituita dal file, non su Migration.
     */
    private function migrazione(): object
    {
        return require database_path(
            'migrations/2026_07_28_160000_migrate_legacy_team_logos_to_logo_collection.php'
        );
    }

    /**
     * Inserisce una riga `media` come la scriveva il vecchio TeamResource,
     * creando anche il file sul disco finto (salvo richiesta contraria).
     */
    private function media(Team $team, string $collection, bool $conFile = true): int
    {
        $id = DB::table('media')->insertGetId([
            'model_type' => Team::class,
            'model_id' => $team->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => $collection,
            'name' => 'logo',
            'file_name' => 'logo.png',
            'mime_type' => 'image/png',
            'disk' => self::DISK,
            'conversions_disk' => self::DISK,
            'size' => 1024,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
            'order_column' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($conFile) {
            Storage::disk(self::DISK)->put($id.'/logo.png', 'fake');
        }

        return $id;
    }

    private function collezione(int $mediaId): string
    {
        return DB::table('media')->where('id', $mediaId)->value('collection_name');
    }

    #[Test]
    public function promuove_il_logo_legacy_a_collezione_logo(): void
    {
        $team = Team::factory()->create();
        $legacy = $this->media($team, 'teams');

        $this->migrazione()->up();

        $this->assertSame(Team::LOGO_CUSTOM, $this->collezione($legacy));
        $this->assertNotNull($team->fresh()->logoUrl());
        $this->assertTrue($team->fresh()->hasCustomLogo());
    }

    #[Test]
    public function lascia_stare_il_legacy_se_la_squadra_ha_gia_un_logo_dal_cms(): void
    {
        $team = Team::factory()->create();
        $custom = $this->media($team, Team::LOGO_CUSTOM);
        $legacy = $this->media($team, 'teams');

        $this->migrazione()->up();

        $this->assertSame('teams', $this->collezione($legacy));
        $this->assertSame(Team::LOGO_CUSTOM, $this->collezione($custom));
        // `logo` è singleFile: deve restarci un solo media.
        $this->assertSame(1, DB::table('media')
            ->where('model_id', $team->id)
            ->where('collection_name', Team::LOGO_CUSTOM)
            ->count());
    }

    #[Test]
    public function con_piu_legacy_promuove_solo_il_piu_recente(): void
    {
        $team = Team::factory()->create();
        $vecchio = $this->media($team, 'teams');
        $recente = $this->media($team, 'teams');

        $this->migrazione()->up();

        $this->assertSame(Team::LOGO_CUSTOM, $this->collezione($recente));
        $this->assertSame('teams', $this->collezione($vecchio));
    }

    #[Test]
    public function non_promuove_un_media_senza_file_su_disco(): void
    {
        $team = Team::factory()->create();
        $orfano = $this->media($team, 'teams', conFile: false);

        $this->migrazione()->up();

        $this->assertSame('teams', $this->collezione($orfano));
    }

    #[Test]
    public function non_tocca_il_logo_importato_dalla_lega(): void
    {
        $team = Team::factory()->create();
        $importato = $this->media($team, Team::LOGO_IMPORTED);

        $this->migrazione()->up();

        $this->assertSame(Team::LOGO_IMPORTED, $this->collezione($importato));
    }

    #[Test]
    public function down_rimette_indietro_solo_le_righe_promosse(): void
    {
        $team = Team::factory()->create();
        $legacy = $this->media($team, 'teams');

        $altro = Team::factory()->create();
        $customNativo = $this->media($altro, Team::LOGO_CUSTOM);

        $migrazione = $this->migrazione();
        $migrazione->up();
        $migrazione->down();

        $this->assertSame('teams', $this->collezione($legacy));
        $this->assertSame(Team::LOGO_CUSTOM, $this->collezione($customNativo));

        $proprieta = json_decode(
            DB::table('media')->where('id', $legacy)->value('custom_properties'),
            true
        );
        $this->assertArrayNotHasKey('legacy_collection', $proprieta ?: []);
    }
}
