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
}
