<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La biglietteria non ha un listino (i prezzi cambiano di partita in
 * partita): al suo posto uno spazio in evidenza e la Gift Card. La campagna
 * abbonamenti elenca i vantaggi degli abbonati.
 */
class BiglietteriaSenzaListinoTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRAZIONE = 'migrations/2026_09_15_100000_biglietteria_senza_listino_e_vantaggi_degli_abbonati.php';

    #[Test]
    public function la_biglietteria_perde_il_listino_e_la_gift_card_diventa_un_blocco_suo(): void
    {
        $pagina = Page::create([
            'title' => ['it' => 'Biglietteria', 'en' => 'Ticketing'],
            'slug' => 'biglietteria',
            'template' => 'Public/Ticketing',
            'status' => PostStatus::Published,
            'content_data' => [
                'it' => [
                    'tickets_url' => 'https://savinodelbenevolley.vivaticket.it/it/event/x',
                    'tickets_button_text' => 'Acquista su Vivaticket',
                    'plans_heading' => 'Il regalo perfetto',
                    'plans_empty' => 'Campagna non aperta',
                    'plans' => [
                        ['name' => 'Gift Card', 'price' => '20€ +', 'period' => 'stagione', 'cta' => null, 'cta_url' => 'https://savinodelbenevolley.vivaticket.it/it/vivacard/prices', 'highlight' => true],
                        ['name' => 'Tribuna Est e Sud', 'price' => '380', 'period' => 'stagione'],
                    ],
                ],
                'en' => [
                    'tickets_url' => null,
                    'plans' => [['name' => 'West Stand', 'price' => '460', 'period' => 'season']],
                ],
            ],
        ]);

        (require database_path(self::MIGRAZIONE))->up();

        $it = $pagina->refresh()->getTranslation('content_data', 'it');
        $en = $pagina->getTranslation('content_data', 'en');

        $this->assertSame([], $it['plans']);
        $this->assertSame('', $it['plans_heading']);
        $this->assertSame('', $it['plans_empty']);
        $this->assertSame('https://savinodelbenevolley.vivaticket.it/it/vivacard/prices', $it['gift_card_url']);
        $this->assertSame('Gift Card', $it['gift_card_title']);
        $this->assertSame('https://savinodelbenevolley.vivaticket.it/it/event/x', $it['feature_button_url']);
        $this->assertSame('Acquista su Vivaticket', $it['feature_button_text']);
        $this->assertNull($it['tickets_url'], 'il pulsante passa allo spazio in evidenza, non resta anche nell\'hero');
        $this->assertNotSame('', $it['feature_text']);

        $this->assertSame([], $en['plans']);
        $this->assertNull($en['gift_card_url']);
        $this->assertSame('Buy tickets', $en['feature_button_text']);
    }

    #[Test]
    public function la_migrazione_non_sovrascrive_il_lavoro_della_redazione(): void
    {
        $pagina = Page::create([
            'title' => ['it' => 'Biglietteria'],
            'slug' => 'biglietteria',
            'template' => 'Public/Ticketing',
            'status' => PostStatus::Published,
            'content_data' => ['it' => ['plans' => [['name' => 'Gift Card', 'price' => '20']]]],
        ]);

        $migrazione = require database_path(self::MIGRAZIONE);
        $migrazione->up();

        $pagina->refresh()->setTranslation('content_data', 'it', array_merge($pagina->getTranslation('content_data', 'it'), ['feature_title' => 'Scritto dalla redazione']));
        $pagina->save();

        $migrazione->up();

        $this->assertSame('Scritto dalla redazione', $pagina->refresh()->getTranslation('content_data', 'it')['feature_title']);
    }

    #[Test]
    public function la_campagna_abbonamenti_elenca_i_vantaggi_e_tiene_il_listino(): void
    {
        $pagina = Page::create([
            'title' => ['it' => 'Campagna Abbonamenti', 'en' => 'Season Tickets'],
            'slug' => 'abbonamenti',
            'template' => 'Public/Ticketing',
            'status' => PostStatus::Published,
            'content_data' => [
                'it' => ['plans' => [['name' => 'Tribuna Ovest', 'price' => '460', 'period' => 'stagione']]],
                'en' => ['plans' => [['name' => 'West Stand', 'price' => '460', 'period' => 'season']]],
            ],
        ]);

        $migrazione = require database_path(self::MIGRAZIONE);
        $migrazione->up();
        $migrazione->up();

        $it = $pagina->refresh()->getTranslation('content_data', 'it');
        $en = $pagina->getTranslation('content_data', 'en');

        $this->assertCount(1, $it['plans']);
        $this->assertCount(7, $it['benefits']);
        $this->assertTrue(array_is_list($it['benefits']));
        $this->assertSame('Vantaggi', $it['benefits_heading']);
        $this->assertSame([], $it['phases']);
        $this->assertCount(7, $en['benefits']);
        $this->assertSame('Benefits', $en['benefits_heading']);
    }

    #[Test]
    public function le_grafiche_dello_spazio_in_evidenza_e_della_gift_card_arrivano_come_indirizzi_pubblici(): void
    {
        Page::create([
            'title' => ['it' => 'Biglietteria'],
            'slug' => 'biglietteria',
            'template' => 'Public/Ticketing',
            'status' => PostStatus::Published,
            'content_data' => ['it' => [
                'feature_title' => 'Biglietti',
                'feature_image' => 'ticketing/believe.jpg',
                'gift_card_text' => 'Scegli l\'importo',
                'gift_card_image' => 'ticketing/gift.png',
                'benefits' => [['text' => 'Sconto 10%']],
            ]],
        ]);

        $this->get('/ticketing/biglietteria')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Public/Ticketing')
                ->where('page.content_data.feature_image', Storage::url('ticketing/believe.jpg'))
                ->where('page.content_data.gift_card_image', Storage::url('ticketing/gift.png'))
                ->where('page.content_data.benefits.0.text', 'Sconto 10%'));
    }

    #[Test]
    public function dal_pannello_i_vantaggi_e_le_fasi_si_salvano_come_elenchi(): void
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        $pagina = Page::create([
            'title' => ['it' => 'Campagna Abbonamenti'],
            'slug' => 'abbonamenti',
            'template' => 'Public/Ticketing',
            'status' => PostStatus::Published,
            'content_data' => ['it' => ['benefits' => [['text' => 'Sconto 10%']], 'phases' => []]],
        ]);

        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('data.content_data.gift_card_title', 'Gift Card')
            ->set('data.content_data.gift_card_url', 'https://savinodelbenevolley.vivaticket.it/it/vivacard/prices')
            ->set('data.content_data.phases', [['title' => 'Prelazione ex abbonati', 'period' => 'Dal 28 luglio', 'description' => 'Scrivi a ticketing@']])
            ->call('save')
            ->assertHasNoErrors();

        $contenuti = $pagina->refresh()->getTranslation('content_data', 'it');

        $this->assertTrue(array_is_list($contenuti['benefits']));
        $this->assertSame('Sconto 10%', $contenuti['benefits'][0]['text']);
        $this->assertTrue(array_is_list($contenuti['phases']));
        $this->assertSame('Prelazione ex abbonati', $contenuti['phases'][0]['title']);
        $this->assertSame('Gift Card', $contenuti['gift_card_title']);
    }
}
