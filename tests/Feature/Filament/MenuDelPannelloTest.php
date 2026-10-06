<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le voci del menu che puntano a una pagina del CMS si risolvono con una
 * query sola: una per voce era l'N+1 segnalato da Sentry su ogni pagina del
 * pannello (SITO-SAVINO-6/7).
 */
class MenuDelPannelloTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_pagine_del_menu_si_cercano_con_una_query_sola(): void
    {
        $organigramma = Page::factory()->create(['slug' => 'organigramma']);

        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        DB::enableQueryLog();

        $risposta = $this->actingAs($utente->refresh())->get('/admin');

        $ricerche = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'from `pages`') && str_contains($query['query'], '`slug`'))
            ->count();

        $risposta->assertOk();
        $risposta->assertSee("/admin/pages/{$organigramma->id}/edit", false);
        $this->assertSame(1, $ricerche);
    }
}
