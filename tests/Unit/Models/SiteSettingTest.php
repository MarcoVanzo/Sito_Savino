<?php

namespace Tests\Unit\Models;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::clearCache();
    }

    public function test_get_returns_value_by_key(): void
    {
        SiteSetting::factory()->create([
            'key' => 'site.name',
            'value' => 'Savino Del Bene',
        ]);
        SiteSetting::clearCache();

        $this->assertEquals('Savino Del Bene', SiteSetting::get('site.name'));
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        $this->assertEquals('fallback', SiteSetting::get('nonexistent.key', 'fallback'));
    }

    public function test_set_creates_new_setting(): void
    {
        SiteSetting::set('test.key', 'test_value');

        $this->assertDatabaseHas('site_settings', [
            'key' => 'test.key',
            'value' => 'test_value',
        ]);
    }

    public function test_set_updates_existing_setting(): void
    {
        SiteSetting::set('test.key', 'original');
        SiteSetting::set('test.key', 'updated');

        $this->assertDatabaseHas('site_settings', [
            'key' => 'test.key',
            'value' => 'updated',
        ]);
        $this->assertDatabaseMissing('site_settings', [
            'key' => 'test.key',
            'value' => 'original',
        ]);
    }

    public function test_get_public_grouped_filters_groups(): void
    {
        SiteSetting::factory()->create([
            'key' => 'general.name',
            'value' => 'Test',
            'group' => 'general',
        ]);
        SiteSetting::factory()->create([
            'key' => 'system.secret',
            'value' => 'hidden',
            'group' => 'system',
        ]);
        SiteSetting::clearCache();

        $publicGrouped = SiteSetting::getPublicGrouped();

        $this->assertArrayHasKey('general', $publicGrouped);
        $this->assertArrayNotHasKey('system', $publicGrouped);
    }

    public function test_le_impostazioni_json_tradotte_arrivano_nella_lingua_corrente(): void
    {
        // I numeri della homepage sono di tipo `json` e tradotti: prima di
        // risolvere anche gli array già decodificati, al frontend arrivava
        // l'oggetto per lingua e la sezione restava vuota.
        SiteSetting::updateOrCreate(['key' => 'stats'], [
            'group' => 'home',
            'type' => 'json',
            'value' => json_encode([
                'it' => [['value' => '40+', 'label' => 'Anni di Storia']],
                'en' => [['value' => '40+', 'label' => 'Years of History']],
            ]),
        ]);
        SiteSetting::clearCache();

        app()->setLocale('it');
        $it = SiteSetting::getGroup('home')['stats'];

        $this->assertIsList($it);
        $this->assertSame('Anni di Storia', $it[0]['label']);

        SiteSetting::clearCache();
        app()->setLocale('en');

        $this->assertSame('Years of History', SiteSetting::getGroup('home')['stats'][0]['label']);
    }

    public function test_un_valore_json_senza_lingue_resta_intatto(): void
    {
        SiteSetting::updateOrCreate(['key' => 'active_payment_gateways'], [
            'group' => 'shop',
            'type' => 'json',
            'value' => json_encode(['stripe', 'paypal']),
        ]);
        SiteSetting::clearCache();

        $this->assertSame(['stripe', 'paypal'], SiteSetting::getGroup('shop')['active_payment_gateways']);
    }
}
