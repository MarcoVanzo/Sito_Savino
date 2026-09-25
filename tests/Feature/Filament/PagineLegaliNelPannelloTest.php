<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le voci legali del footer sono pagine, e nel pannello si devono trovare:
 * con dieci righe per volta finivano in quarta pagina dell'elenco.
 */
class PagineLegaliNelPannelloTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_scheda_legali_mostra_tutte_le_pagine_del_footer_e_nient_altro(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::SuperAdmin])->save();

        foreach (ListPages::slugLegali() as $slug) {
            Page::firstOrCreate(['slug' => $slug], ['title' => ['it' => $slug], 'template' => 'Public/ContentPage', 'status' => 'publish']);
        }
        $altra = Page::factory()->create(['slug' => 'una-pagina-qualunque']);

        Livewire::actingAs($admin)
            ->test(ListPages::class)
            ->set('activeTab', 'legali')
            ->assertCanSeeTableRecords(Page::whereIn('slug', ListPages::slugLegali())->get())
            ->assertCanNotSeeTableRecords([$altra]);
    }
}
