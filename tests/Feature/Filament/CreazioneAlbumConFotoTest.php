<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\GalleryImageResource\Pages\ListGalleryImages;
use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'azione con cui la redazione crea un album e ci carica dentro le foto in
 * un colpo solo, dalla pagina delle immagini.
 *
 * È la via che si usa dopo ogni partita, con decine di file per volta: le due
 * cose che devono reggere sono che l'album nasca con i suoi dati e che la
 * stessa foto caricata due volte non diventi due righe.
 */
class CreazioneAlbumConFotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
    }

    private function utenteConRuolo(UserRole $ruolo): User
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => $ruolo, 'must_change_password' => false])->save();

        return $utente->refresh();
    }

    private function fotoCaricata(string $nome, string $contenuto): string
    {
        Storage::disk('local')->put('temp_gallery_uploads/'.$nome, $contenuto);

        return 'temp_gallery_uploads/'.$nome;
    }

    private function paginaDelleFoto(): Testable
    {
        return Livewire::actingAs($this->utenteConRuolo(UserRole::SuperAdmin))
            ->test(ListGalleryImages::class);
    }

    #[Test]
    public function la_pagina_delle_foto_si_apre(): void
    {
        $this->paginaDelleFoto()->assertSuccessful();
    }

    #[Test]
    public function l_album_nasce_con_i_dati_del_modulo_e_le_sue_foto(): void
    {
        $this->paginaDelleFoto()
            ->callAction('create_event', [
                'title' => 'Savino - Novara',
                'event_date' => '2026-03-15',
                'category' => 'Partite',
                'description' => 'Gara di ritorno',
                'uploaded_photos' => [
                    $this->fotoCaricata('a.jpg', 'prima foto'),
                    $this->fotoCaricata('b.jpg', 'seconda foto'),
                ],
            ])
            ->assertHasNoActionErrors();

        $album = GalleryEvent::first();
        $this->assertNotNull($album);
        $this->assertSame('Savino - Novara', $album->title);
        $this->assertSame('Partite', $album->category);
        $this->assertTrue((bool) $album->is_active);

        $this->assertSame(2, GalleryImage::where('gallery_event_id', $album->id)->count());
        Queue::assertPushed(AnalyzeGalleryImageJob::class, 2);
    }

    #[Test]
    public function la_stessa_foto_caricata_due_volte_non_si_duplica(): void
    {
        $this->paginaDelleFoto()->callAction('create_event', [
            'title' => 'Primo album',
            'category' => 'Partite',
            'uploaded_photos' => [$this->fotoCaricata('prima.jpg', 'contenuto identico')],
        ]);

        $this->paginaDelleFoto()->callAction('create_event', [
            'title' => 'Secondo album',
            'category' => 'Eventi',
            'uploaded_photos' => [$this->fotoCaricata('seconda.jpg', 'contenuto identico')],
        ]);

        $this->assertSame(2, GalleryEvent::count(), 'I due album esistono comunque.');
        $this->assertSame(1, GalleryImage::count(), 'La foto no: è la stessa.');
    }

    #[Test]
    public function il_titolo_e_la_categoria_sono_obbligatori(): void
    {
        $this->paginaDelleFoto()
            ->callAction('create_event', [
                'title' => '',
                'category' => '',
                'uploaded_photos' => [$this->fotoCaricata('a.jpg', 'contenuto')],
            ])
            ->assertHasActionErrors(['title', 'category']);

        $this->assertSame(0, GalleryEvent::count());
    }

    #[Test]
    public function senza_foto_l_album_non_si_crea(): void
    {
        $this->paginaDelleFoto()
            ->callAction('create_event', [
                'title' => 'Album vuoto',
                'category' => 'Partite',
                'uploaded_photos' => [],
            ])
            ->assertHasActionErrors(['uploaded_photos']);

        $this->assertSame(0, GalleryEvent::count());
    }
}
