<?php

namespace Tests\Unit\Support;

use App\Support\CmsFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsFileTest extends TestCase
{
    public function test_un_percorso_relativo_diventa_indirizzo_del_disco(): void
    {
        Storage::fake('local');

        $this->assertSame(Storage::url('press-kit/foto.zip'), CmsFile::url('press-kit/foto.zip'));
    }

    public function test_un_indirizzo_gia_completo_resta_intatto(): void
    {
        $this->assertSame('https://cdn.example/file.pdf', CmsFile::url('https://cdn.example/file.pdf'));
        $this->assertSame('/images/logo.png', CmsFile::url('/images/logo.png'));
    }

    public function test_i_segnaposto_non_diventano_link(): void
    {
        // '#' arrivava dai dati iniziali dei documenti di safeguarding: senza
        // questo controllo il sito mostrava un pulsante "Scarica" che non
        // scaricava niente.
        $this->assertNull(CmsFile::url('#'));
        $this->assertNull(CmsFile::url(''));
        $this->assertNull(CmsFile::url(null));
    }

    public function test_risolve_i_file_dentro_content_data(): void
    {
        Storage::fake('local');

        $risolto = CmsFile::resolveInContentData([
            // Il materiale stampa e' un elenco libero: le quattro caselle fisse
            // press_kit_1..4 non bastavano per una cartella a gara.
            'press_kits' => [
                ['title' => 'Foto', 'file' => 'press-kit/foto.zip'],
                ['title' => 'Brand book', 'file' => ''],
            ],
            'button_image' => 'pulsanti/sfondo.jpg',
            'magazines' => [
                ['title' => 'Numero 1', 'file_url' => 'magazine/n1.pdf', 'cover_image_url' => ''],
            ],
            'documents' => [
                ['title' => 'Modello', 'file' => '#'],
            ],
            'hero_label' => 'Area Stampa',
        ]);

        $this->assertSame(Storage::url('press-kit/foto.zip'), $risolto['press_kits'][0]['file']);
        $this->assertNull($risolto['press_kits'][1]['file']);
        $this->assertSame(Storage::url('pulsanti/sfondo.jpg'), $risolto['button_image']);
        $this->assertSame(Storage::url('magazine/n1.pdf'), $risolto['magazines'][0]['file_url']);
        $this->assertNull($risolto['magazines'][0]['cover_image_url']);
        $this->assertNull($risolto['documents'][0]['file']);
        $this->assertSame('Area Stampa', $risolto['hero_label']);
    }
}
