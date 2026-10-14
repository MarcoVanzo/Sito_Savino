<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le props condivise viaggiano nel `data-page` di ogni pagina (audit SEO del
 * 14/10/2026): portano solo ciò che layout e pagine leggono davvero.
 */
class PropsCondiviseSoloQuelleCheIlSitoLeggeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['contact', 'email', 'info@example.test'],
            ['shop', 'order_notification_emails', 'ordini.interni@example.test'],
            ['shop', 'receipt_footer_text', 'Testo della ricevuta'],
            ['auctions', 'min_bid_increment', '5'],
            ['home', 'hero_title', 'Titolo della home'],
        ] as [$gruppo, $chiave, $valore]) {
            SiteSetting::updateOrCreate(['group' => $gruppo, 'key' => $chiave], ['value' => $valore, 'type' => 'text']);
        }

        Cache::flush();
    }

    #[Test]
    public function le_impostazioni_dello_shop_e_della_home_non_viaggiano_sulle_altre_pagine(): void
    {
        $risposta = $this->get(route('news.index'))->assertOk();

        $risposta->assertInertia(fn ($pagina) => $pagina
            ->where('siteSettings.contact.email', 'info@example.test')
            ->has('siteSettings.analytics')
            ->missing('siteSettings.shop')
            ->missing('siteSettings.auctions')
            ->missing('siteSettings.legal')
            ->missing('siteSettings.home'));
        $this->assertStringNotContainsString('ordini.interni@example.test', $risposta->getContent());
    }

    #[Test]
    public function la_homepage_riceve_le_sue_impostazioni(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('siteSettings.home.hero_title', 'Titolo della home'));
    }

    #[Test]
    public function i_testi_della_pagina_contatti_restano_alla_pagina_contatti(): void
    {
        // Le migrazioni creano già la pagina: si riscrive il suo content_data.
        Page::where('slug', 'contatti')->delete();
        Page::factory()->create([
            'slug' => 'contatti',
            'status' => PostStatus::Published,
            'content_data' => ['it' => [
                'office_hours' => 'Lun-Ven 9-13',
                'form_title' => 'Scrivici',
                'hero_subtitle' => 'Contatti',
                'contacts_list' => [['name' => 'Ufficio', 'email' => 'ufficio@example.test']],
            ]],
        ]);
        Cache::flush();

        $this->get(route('news.index'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('siteSettings.contact.office_hours', 'Lun-Ven 9-13')
                ->missing('siteSettings.contact.form_title')
                ->missing('siteSettings.contact.hero_subtitle')
                ->missing('siteSettings.contact.contacts_list'));
    }

    #[Test]
    public function il_menu_del_footer_porta_solo_etichette_e_indirizzi(): void
    {
        $colonna = MenuItem::create(['location' => 'footer', 'label' => ['it' => 'Colonna di prova'], 'url' => '/societa', 'sort_order' => 99, 'is_active' => true]);
        MenuItem::create(['location' => 'footer', 'parent_id' => $colonna->id, 'label' => ['it' => 'Sponsor'], 'url' => '/sponsor', 'sort_order' => 0, 'is_active' => true]);
        Cache::flush();

        $this->get(route('news.index'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('footerMenu', fn ($colonne) => collect($colonne)->contains([
                    'label' => 'Colonna di prova',
                    'children' => [['label' => 'Sponsor', 'href' => '/sponsor']],
                ]) && collect($colonne)->every(fn ($c) => array_keys($c) === ['label', 'children']
                    && collect($c['children'])->every(fn ($v) => array_keys($v) === ['label', 'href']))));
    }
}
