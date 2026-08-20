<?php

namespace Tests\Feature;

use App\Enums\SponsorTier;
use App\Models\Sponsor;
use App\Services\SponsorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SponsorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_gli_sponsor_sono_raggruppati_nellordine_dei_livelli(): void
    {
        Sponsor::factory()->create(['name' => 'Media', 'tier' => SponsorTier::Media]);
        Sponsor::factory()->create(['name' => 'Titolare', 'tier' => SponsorTier::Title]);
        Sponsor::factory()->create(['name' => 'Principale', 'tier' => SponsorTier::Main]);

        $tiers = app(SponsorDirectory::class)->tiers();

        $this->assertSame(['title', 'main', 'media'], array_column($tiers, 'key'));
        $this->assertSame('Title Sponsor', $tiers[0]['label']);
        $this->assertSame('Titolare', $tiers[0]['sponsors'][0]['name']);
    }

    public function test_i_livelli_senza_sponsor_non_vengono_pubblicati(): void
    {
        Sponsor::factory()->create(['tier' => SponsorTier::Supporter]);

        $chiavi = array_column(app(SponsorDirectory::class)->tiers(), 'key');

        $this->assertSame(['supporter'], $chiavi);
    }

    public function test_dentro_al_livello_conta_lordine_impostato_nel_pannello(): void
    {
        Sponsor::factory()->create(['name' => 'Secondo', 'tier' => SponsorTier::Official, 'sort_order' => 2]);
        Sponsor::factory()->create(['name' => 'Primo', 'tier' => SponsorTier::Official, 'sort_order' => 1]);

        $sponsors = app(SponsorDirectory::class)->tiers()[0]['sponsors'];

        $this->assertSame(['Primo', 'Secondo'], array_column($sponsors, 'name'));
    }

    public function test_la_pagina_pubblica_riceve_i_livelli(): void
    {
        Sponsor::factory()->create(['tier' => SponsorTier::Title]);

        $this->get(route('sponsor'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Sponsor')
                ->has('tiers', 1)
                ->where('tiers.0.key', 'title')
            );
    }
}
