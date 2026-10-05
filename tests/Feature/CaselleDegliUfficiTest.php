<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le caselle dei singoli uffici (una è nominativa) viaggiavano in ogni pagina
 * del sito fra le impostazioni condivise (parere del 5/10/2026). Ora le riceve
 * solo la pagina che le mostra.
 */
class CaselleDegliUfficiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['press_email' => 'stampa@example.test', 'social_email' => 'nome.cognome@example.test', 'contact_email' => 'info@example.test'] as $chiave => $valore) {
            SiteSetting::updateOrCreate(
                ['group' => 'contact', 'key' => $chiave === 'contact_email' ? 'email' : $chiave],
                ['value' => $valore, 'type' => 'text'],
            );
        }

        Cache::flush();
    }

    #[Test]
    public function le_caselle_degli_uffici_non_viaggiano_nelle_altre_pagine(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('nome.cognome@example.test', $html);
        $this->assertStringNotContainsString('stampa@example.test', $html);
        $this->assertStringContainsString('info@example.test', $html, 'La casella generale resta: la usano footer e Contatti.');
    }

    #[Test]
    public function la_pagina_comunicazione_riceve_le_sue_caselle(): void
    {
        Page::factory()->create([
            'slug' => 'accrediti-stampa',
            'template' => 'Public/Comunicazione',
            'status' => PostStatus::Published,
        ]);

        $this->get(route('comunicazione.page', ['slug' => 'accrediti-stampa']))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('page.caselle.social_email', 'nome.cognome@example.test')
                ->where('page.caselle.press_email', 'stampa@example.test')
                ->missing('siteSettings.contact.social_email'));
    }
}
