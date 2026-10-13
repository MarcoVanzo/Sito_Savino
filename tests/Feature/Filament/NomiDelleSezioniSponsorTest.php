<?php

namespace Tests\Feature\Filament;

use App\Enums\SponsorTier;
use App\Enums\UserRole;
use App\Filament\Resources\SponsorResource\Pages\ListSponsors;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\SponsorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La redazione rinomina le sezioni della pagina Sponsor dal pannello, in
 * italiano e in inglese; un campo lasciato vuoto torna al nome predefinito.
 */
class NomiDelleSezioniSponsorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());
    }

    #[Test]
    public function il_nome_scritto_nel_pannello_arriva_sulla_pagina_nella_sua_lingua(): void
    {
        Sponsor::factory()->create(['tier' => SponsorTier::Health]);
        Sponsor::factory()->create(['tier' => SponsorTier::Media]);

        // La pagina pubblica è già in cache prima del cambio di nome.
        $this->assertSame('Health & Performance Partner', app(SponsorDirectory::class)->tiers()[0]['label']);

        Livewire::test(ListSponsors::class)
            ->callAction('nomiDelleSezioni', [
                'nomi' => [
                    'it' => ['health' => '  Partner Salute  ', 'media' => ''],
                    'en' => ['health' => 'Health Partner', 'media' => ''],
                ],
            ])
            ->assertHasNoActionErrors();

        app()->setLocale('it');
        $this->assertSame(['Partner Salute', 'Media Partner'], array_column(app(SponsorDirectory::class)->tiers(), 'label'));

        app()->setLocale('en');
        $this->assertSame(['Health Partner', 'Media Partner'], array_column(app(SponsorDirectory::class)->tiers(), 'label'));
    }

    #[Test]
    public function svuotando_il_campo_torna_il_nome_predefinito(): void
    {
        Livewire::test(ListSponsors::class)
            ->callAction('nomiDelleSezioni', ['nomi' => ['it' => ['health' => 'Partner Salute']]]);

        Livewire::test(ListSponsors::class)
            ->mountAction('nomiDelleSezioni')
            ->assertActionDataSet(['nomi.it.health' => 'Partner Salute'])
            ->setActionData(['nomi.it.health' => ''])
            ->callMountedAction();

        $this->assertSame('Health & Performance Partner', SponsorTier::Health->getLabel());
    }
}
