<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Team;
use App\Observers\CacheInvalidationObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Le pagine delle rose del vivaio e la loro cache.
 *
 * Aprendo /stagione/u17 e /stagione/u15 l'elenco delle chiavi da dimenticare
 * è rimasto fermo alla B1: la redazione modificava una rosa e online restava
 * quella di prima finché la cache non scadeva da sé.
 */
class CacheStagioneVivaioTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ogni_categoria_del_vivaio_ha_la_sua_chiave_di_cache(): void
    {
        $mappa = (new ReflectionClass(CacheInvalidationObserver::class))->getConstant('MODEL_CACHE_MAP');

        foreach ([Player::class, Team::class] as $modello) {
            foreach (Team::CATEGORIE_VIVAIO as $categoria) {
                $this->assertContains(
                    'public:stagione:'.strtolower($categoria),
                    $mappa[$modello],
                    "Manca la chiave di cache della categoria {$categoria} per {$modello}",
                );
            }
        }
    }

    #[Test]
    public function salvare_unatleta_butta_la_cache_delle_pagine_del_vivaio(): void
    {
        foreach (config('app.supported_locales') as $locale) {
            Cache::put("public:stagione:u17:{$locale}", ['vecchio'], 600);
            Cache::put("public:stagione:u15:{$locale}", ['vecchio'], 600);
        }

        Player::factory()->create();

        foreach (config('app.supported_locales') as $locale) {
            $this->assertNull(Cache::get("public:stagione:u17:{$locale}"));
            $this->assertNull(Cache::get("public:stagione:u15:{$locale}"));
        }
    }
}
