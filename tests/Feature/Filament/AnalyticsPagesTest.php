<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\NewsletterAnalyticsPage;
use App\Filament\Pages\Settings\AnalyticsSettingsPage;
use App\Filament\Pages\SocialAnalyticsPage;
use App\Filament\Pages\WebAnalyticsPage;
use App\Models\AnalyticsSite;
use App\Models\NewsletterSubscriber;
use App\Models\SocialAccount;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le tre pagine di analytics leggono servizi esterni che in produzione possono
 * non essere configurati o non rispondere. Il requisito è che si aprano
 * comunque: una pagina del pannello che va in errore 500 perché Google è lento
 * è peggio di una che dice "dati non disponibili".
 *
 * Qui si verifica anche chi può vederle: sono dati di comunicazione, non di
 * shop, e i ruoli non vanno confusi.
 */
class AnalyticsPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_gestione_comunicazione_vede_le_tre_pagine(): void
    {
        $this->actingAs($this->utenteConRuolo(UserRole::CommunicationManager));

        $this->assertTrue(WebAnalyticsPage::canAccess());
        $this->assertTrue(SocialAnalyticsPage::canAccess());
        $this->assertTrue(NewsletterAnalyticsPage::canAccess());
    }

    #[Test]
    public function le_tre_pagine_sono_registrate_nel_pannello_sotto_marketing(): void
    {
        // Una pagina può esistere, avere i permessi giusti e non comparire lo
        // stesso: basta che Filament non la scopra o che il gruppo di
        // navigazione non corrisponda a nessuno di quelli dichiarati nel
        // pannello. Da fuori sembra che il lavoro non sia stato fatto.
        $this->actingAs($this->utenteConRuolo(UserRole::SuperAdmin));

        $registrate = array_map(
            fn (string $page): string => $page,
            Filament::getPanel('admin')->getPages(),
        );

        foreach ([WebAnalyticsPage::class, SocialAnalyticsPage::class, NewsletterAnalyticsPage::class] as $page) {
            $this->assertContains($page, $registrate, $page.' non è registrata nel pannello');
            $this->assertSame('Marketing', $page::getNavigationGroup(), $page.' non è nel gruppo Marketing');
        }

        $gruppi = array_map(
            fn ($group): ?string => $group->getLabel(),
            Filament::getPanel('admin')->getNavigationGroups(),
        );

        $this->assertContains('Marketing', $gruppi, 'Il gruppo Marketing non è dichiarato nel pannello');
    }

    #[Test]
    public function chi_gestisce_solo_lo_shop_non_le_vede(): void
    {
        $this->actingAs($this->utenteConRuolo(UserRole::ShopManager));

        $this->assertFalse(WebAnalyticsPage::canAccess());
        $this->assertFalse(SocialAnalyticsPage::canAccess());
        $this->assertFalse(NewsletterAnalyticsPage::canAccess());
    }

    #[Test]
    public function analytics_sito_si_apre_senza_nessun_sito_configurato(): void
    {
        // La configurazione di partenza arriva da una migrazione: qui si prova
        // il caso opposto, cioè un ambiente in cui i siti non ci sono ancora.
        AnalyticsSite::query()->delete();

        Http::fake();

        Livewire::actingAs($this->utenteConRuolo(UserRole::CommunicationManager))
            ->test(WebAnalyticsPage::class)
            ->assertSuccessful()
            ->assertSee('Nessun sito configurato');

        // Senza siti non c'è niente da chiedere a Google.
        Http::assertNothingSent();
    }

    #[Test]
    public function analytics_sito_si_apre_anche_se_google_risponde_male(): void
    {
        AnalyticsSite::query()->delete();
        AnalyticsSite::factory()->create(['name' => 'Sito ufficiale', 'property_id' => '123456789']);

        config()->set('services.ga4.service_account_json', json_encode([
            'client_email' => 'analytics@savino.iam.gserviceaccount.com',
            'private_key' => 'chiave-non-valida',
        ]));

        Http::fake(['*' => Http::response('', 500)]);

        Livewire::actingAs($this->utenteConRuolo(UserRole::CommunicationManager))
            ->test(WebAnalyticsPage::class)
            ->assertSuccessful()
            ->assertSee('Dati non disponibili');
    }

    #[Test]
    public function social_analytics_si_apre_senza_account_collegati(): void
    {
        config()->set('services.meta.app_id', '123');
        config()->set('services.meta.app_secret', 'segreto');

        Http::fake();

        Livewire::actingAs($this->utenteConRuolo(UserRole::CommunicationManager))
            ->test(SocialAnalyticsPage::class)
            ->assertSuccessful()
            ->assertSee('Nessun account collegato');

        Http::assertNothingSent();
    }

    #[Test]
    public function social_analytics_dice_cosa_manca_se_l_app_meta_non_e_configurata(): void
    {
        config()->set('services.meta.app_id', null);
        config()->set('services.meta.app_secret', null);

        SocialAccount::factory()->create();

        Livewire::actingAs($this->utenteConRuolo(UserRole::CommunicationManager))
            ->test(SocialAnalyticsPage::class)
            ->assertSuccessful()
            ->assertSee('App Meta non configurata');
    }

    #[Test]
    public function newsletter_si_apre_anche_senza_activecampaign(): void
    {
        config()->set('services.activecampaign.url', null);
        config()->set('services.activecampaign.key', null);

        Http::fake();

        Livewire::actingAs($this->utenteConRuolo(UserRole::CommunicationManager))
            ->test(NewsletterAnalyticsPage::class)
            ->assertSuccessful()
            ->assertSee('Campagne non disponibili');

        Http::assertNothingSent();
    }

    #[Test]
    public function newsletter_mostra_gli_iscritti_nella_stessa_pagina(): void
    {
        config()->set('services.activecampaign.url', null);
        config()->set('services.activecampaign.key', null);

        Http::fake();

        // L'elenco era una voce di menu separata: se sparisce dalla pagina,
        // dal pannello non si raggiunge più.
        $iscritta = NewsletterSubscriber::factory()->create(['email' => 'tifosa@example.com']);

        Livewire::actingAs($this->utenteConRuolo(UserRole::CommunicationManager))
            ->test(NewsletterAnalyticsPage::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$iscritta]);
    }

    #[Test]
    public function le_impostazioni_mostrano_cosa_manca_e_cosa_autorizzare(): void
    {
        config()->set('services.ga4.service_account_json', json_encode([
            'client_email' => 'analytics@savino.iam.gserviceaccount.com',
            'private_key' => 'chiave',
        ]));
        config()->set('services.meta.app_id', null);
        config()->set('services.meta.app_secret', null);

        Livewire::actingAs($this->utenteConRuolo(UserRole::SuperAdmin))
            ->test(AnalyticsSettingsPage::class)
            ->assertSuccessful()
            // Le due domande che ci si fa ogni volta: chi autorizzare su Google
            // e quale URI dichiarare su Meta.
            ->assertSee('analytics@savino.iam.gserviceaccount.com')
            ->assertSee('admin/social/meta/callback')
            ->assertSee('Non configurata');
    }

    #[Test]
    public function il_bottone_collega_meta_manda_davvero_su_facebook(): void
    {
        config()->set('services.meta.app_id', '1098244582719500');
        config()->set('services.meta.app_secret', 'segreto-di-prova');
        config()->set('services.meta.config_id', '1530681078385238');

        // Era un link, e in modalità SPA non funzionava: Livewire intercettava
        // il click e caricava la rotta via fetch, che rispondeva con un redirect
        // cross-origin verso facebook.com. Il fetch falliva e non succedeva
        // niente — nessun errore a schermo, nessun giro OAuth. Da azione il
        // redirect lo esegue Livewire, e questo test lo blocca lì.
        Livewire::actingAs($this->utenteConRuolo(UserRole::CommunicationManager))
            ->test(SocialAnalyticsPage::class)
            ->callAction('connect')
            ->assertRedirectContains('facebook.com');

        $this->assertDatabaseCount('social_oauth_states', 1);
    }

    /**
     * `role` e `must_change_password` non sono assegnabili in massa (lo dice il
     * model): passarli alla factory non ha alcun effetto e il test finirebbe per
     * verificare i permessi di un utente senza ruolo, cioè niente.
     */
    private function utenteConRuolo(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'must_change_password' => false])->save();

        return $user->refresh();
    }
}
