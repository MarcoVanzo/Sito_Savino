<?php

namespace Tests\Feature\Filament;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TemplateNonSelezionabiliTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function pagine(): array
    {
        return [
            'contatti' => ['contatti', 'Public/Contatti'],
            'home' => ['home', 'Public/Home'],
        ];
    }

    #[Test]
    #[DataProvider('pagine')]
    public function la_pagina_si_salva_e_tiene_il_suo_template(string $slug, string $template): void
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        $pagina = Page::updateOrCreate(['slug' => $slug], [
            'title' => ['it' => ucfirst($slug)],
            'template' => $template,
            'status' => PostStatus::Published,
            'content_data' => ['it' => []],
        ]);

        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('data.title', 'Titolo nuovo')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($template, $pagina->refresh()->template);
    }
}
