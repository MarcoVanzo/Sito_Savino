<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Filament\Support\TranslatableContentDriver;
use App\Models\Post;
use App\Models\User;
use Filament\SpatieLaravelTranslatableContentDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La ricerca nelle tabelle del CMS sulle colonne tradotte con spatie: il driver
 * originale del plugin non trova nulla appena il termine contiene una maiuscola
 * e va in errore sulle righe legacy in testo semplice.
 *
 * @see TranslatableContentDriver
 */
class TranslatableSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user;
    }

    public function test_il_content_driver_del_plugin_e_sostituito_dal_nostro(): void
    {
        $driver = app(SpatieLaravelTranslatableContentDriver::class, ['activeLocale' => 'it']);

        $this->assertInstanceOf(TranslatableContentDriver::class, $driver);
    }

    public function test_la_ricerca_trova_il_titolo_tradotto(): void
    {
        $match = Post::factory()->create(['title' => ['it' => 'Vittoria a scandicci', 'en' => 'Win in Scandicci']]);
        $other = Post::factory()->create(['title' => ['it' => 'Amichevole a firenze', 'en' => 'Friendly in Florence']]);

        Livewire::actingAs($this->admin())
            ->test(ListPosts::class)
            ->set('tableSearch', 'scandicci')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_la_ricerca_ignora_le_maiuscole(): void
    {
        $match = Post::factory()->create(['title' => ['it' => 'vittoria a scandicci']]);
        $other = Post::factory()->create(['title' => ['it' => 'Amichevole a Firenze']]);

        Livewire::actingAs($this->admin())
            ->test(ListPosts::class)
            ->set('tableSearch', 'SCANDICCI')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);

        Livewire::actingAs($this->admin())
            ->test(ListPosts::class)
            ->set('tableSearch', 'firenze')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$match]);
    }

    public function test_la_ricerca_gestisce_gli_escape_json(): void
    {
        // spatie serializza con json_encode: "/" diventa "\/" e le lettere
        // accentate diventano sequenze \uXXXX.
        $slash = Post::factory()->create(['title' => ['it' => 'Serie A1 2026/2027 al via']]);
        $accento = Post::factory()->create(['title' => ['it' => 'Perché la squadra è in ritiro']]);

        Livewire::actingAs($this->admin())
            ->test(ListPosts::class)
            ->set('tableSearch', '2026/2027')
            ->assertCanSeeTableRecords([$slash])
            ->assertCanNotSeeTableRecords([$accento]);

        Livewire::actingAs($this->admin())
            ->test(ListPosts::class)
            ->set('tableSearch', 'Perché')
            ->assertCanSeeTableRecords([$accento])
            ->assertCanNotSeeTableRecords([$slash]);
    }

    public function test_la_ricerca_sopravvive_alle_righe_legacy_in_testo_semplice(): void
    {
        $legacy = Post::factory()->create(['title' => ['it' => 'segnaposto']]);
        DB::table('posts')->where('id', $legacy->getKey())->update(['title' => 'Riga legacy in testo semplice']);

        $match = Post::factory()->create(['title' => ['it' => 'Vittoria a Scandicci']]);

        Livewire::actingAs($this->admin())
            ->test(ListPosts::class)
            ->set('tableSearch', 'scandicci')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$legacy]);

        // La riga legacy resta cercabile sul proprio valore grezzo.
        Livewire::actingAs($this->admin())
            ->test(ListPosts::class)
            ->set('tableSearch', 'legacy')
            ->assertCanSeeTableRecords([$legacy])
            ->assertCanNotSeeTableRecords([$match]);
    }
}
