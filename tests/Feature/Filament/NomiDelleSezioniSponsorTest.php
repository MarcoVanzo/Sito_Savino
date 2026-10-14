<?php

namespace Tests\Feature\Filament;

use App\Enums\SponsorTier;
use App\Enums\UserRole;
use App\Filament\Actions\NomiDelleSezioniSponsorAction;
use App\Filament\Resources\SponsorResource\Pages\ListSponsors;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\SponsorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La redazione rinomina e riordina le sezioni della pagina Sponsor dal
 * pannello, in italiano e in inglese; un nome lasciato vuoto torna al
 * predefinito.
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
    public function senza_un_ordine_salvato_le_sezioni_seguono_quello_chiesto_dalla_societa(): void
    {
        $this->assertSame([
            'Title Sponsor', 'Main Sponsor', 'Premium Partner', 'Mobility Partner', 'Sponsor Tecnico',
            'Health & Performance Partner', 'Sister Companies', 'Official Partner', 'Sustainability Partner',
            'Official Water', 'Official Coffee', 'Official Supporter', 'Official Supplier',
            'Institutional Education Partner', 'Official Radio', 'Media Partner',
        ], array_map(fn (SponsorTier $tier): string => $tier->getLabel(), SponsorTier::rinominabili()));
    }

    #[Test]
    public function il_nome_scritto_nel_pannello_arriva_sulla_pagina_nella_sua_lingua(): void
    {
        Sponsor::factory()->create(['tier' => SponsorTier::Health]);
        Sponsor::factory()->create(['tier' => SponsorTier::Media]);

        // La pagina pubblica è già in cache prima del cambio di nome.
        $this->assertSame('Health & Performance Partner', app(SponsorDirectory::class)->tiers()[0]['label']);

        $this->compila(Livewire::test(ListSponsors::class)->mountAction('nomiDelleSezioni'), [
            'health' => ['it' => '  Partner Salute  ', 'en' => 'Health Partner'],
        ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        app()->setLocale('it');
        $this->assertSame(['Partner Salute', 'Media Partner'], array_column(app(SponsorDirectory::class)->tiers(), 'label'));

        app()->setLocale('en');
        $this->assertSame(['Health Partner', 'Media Partner'], array_column(app(SponsorDirectory::class)->tiers(), 'label'));
    }

    #[Test]
    public function l_ordine_salvato_nel_pannello_e_quello_della_pagina(): void
    {
        Sponsor::factory()->create(['tier' => SponsorTier::Title]);
        Sponsor::factory()->create(['tier' => SponsorTier::Media]);
        Sponsor::factory()->create(['tier' => SponsorTier::Official]);

        $this->assertSame(['title', 'official', 'media'], array_column(app(SponsorDirectory::class)->tiers(), 'key'));

        // «Media Partner» trascinata in cima.
        $this->compila(Livewire::test(ListSponsors::class)->mountAction('nomiDelleSezioni'), primo: 'media')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(['media', 'title', 'official'], array_column(app(SponsorDirectory::class)->tiers(), 'key'));

        // Riaprendo il pannello le sezioni sono nell'ordine salvato.
        $voci = Livewire::test(ListSponsors::class)
            ->mountAction('nomiDelleSezioni')
            ->get('mountedActionsData.0.sezioni');

        $this->assertSame('media', array_values($voci)[0]['tier']);
    }

    #[Test]
    public function un_livello_assente_dall_ordine_salvato_va_in_fondo(): void
    {
        NomiDelleSezioniSponsorAction::salva([['tier' => 'media'], ['tier' => 'nonesiste'], ['tier' => 'media'], ['tier' => 'title']]);

        $ordine = SponsorTier::ordered();

        $this->assertSame([SponsorTier::Media, SponsorTier::Title, SponsorTier::Main], array_slice($ordine, 0, 3));
        $this->assertCount(count(SponsorTier::cases()), $ordine);
    }

    #[Test]
    public function svuotando_il_campo_torna_il_nome_predefinito(): void
    {
        NomiDelleSezioniSponsorAction::salva($this->sezioni(['health' => ['it' => 'Partner Salute']]));

        $this->assertSame('Partner Salute', SponsorTier::Health->getLabel());

        $this->compila(Livewire::test(ListSponsors::class)->mountAction('nomiDelleSezioni'), ['health' => ['it' => '']])
            ->callMountedAction();

        $this->assertSame('Health & Performance Partner', SponsorTier::Health->getLabel());
    }

    /**
     * Scrive i nomi nelle voci del Repeater (chiavi uuid) ed eventualmente
     * porta una sezione in cima, come trascinandola.
     *
     * @param  array<string, array<string, string>>  $nomi
     */
    private function compila(Testable $pannello, array $nomi = [], ?string $primo = null): Testable
    {
        $voci = $pannello->get('mountedActionsData.0.sezioni');

        foreach ($voci as $uuid => $voce) {
            $voci[$uuid] = [...$voce, ...($nomi[$voce['tier']] ?? [])];
        }

        if ($primo !== null) {
            uasort($voci, fn (array $a, array $b): int => ($b['tier'] === $primo) <=> ($a['tier'] === $primo));
        }

        return $pannello->set('mountedActionsData.0.sezioni', $voci);
    }

    /**
     * Le voci del pannello nell'ordine attuale, coi nomi dati.
     *
     * @param  array<string, array<string, string>>  $nomi
     * @return list<array<string, string>>
     */
    private function sezioni(array $nomi = []): array
    {
        return array_map(
            fn (SponsorTier $tier): array => ['tier' => $tier->value, 'it' => '', 'en' => '', ...($nomi[$tier->value] ?? [])],
            SponsorTier::rinominabili(),
        );
    }
}
