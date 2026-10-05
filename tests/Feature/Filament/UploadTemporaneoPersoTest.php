<?php

namespace Tests\Feature\Filament;

use App\Filament\Support\UploadTemporaneoPerso;
use Illuminate\Support\Facades\Log;
use League\Flysystem\UnableToRetrieveMetadata;
use Livewire\Component;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Il file caricato vive in `livewire-tmp/` sul container: se un rilascio lo
 * sostituisce prima del Salva, la redazione deve sapere che va ricaricato,
 * non ricevere un 500 (Sentry, 05/10).
 */
class UploadTemporaneoPersoTest extends TestCase
{
    #[Test]
    public function un_caricamento_sparito_prima_del_salva_avvisa_invece_del_500(): void
    {
        // Nei test Livewire legge la dimensione dal nome del file finto, e il
        // Salva vero non arriva mai al disco: si lancia l'eccezione vista in
        // produzione da un componente qualunque.
        Log::spy();

        Livewire::test(SalvaConCaricamentoSparito::class)
            ->call('save')
            ->assertNotified('Il file caricato non è più disponibile');

        // Sentry non lo vede più: resta il log, per sapere quanto capita.
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function un_altro_errore_dentro_un_componente_resta_un_errore(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);

        Livewire::test(SalvaConCaricamentoSparito::class)->call('salvaAltrove');
    }

    #[Test]
    public function gli_altri_errori_dei_file_non_vengono_toccati(): void
    {
        $this->assertTrue(UploadTemporaneoPerso::eUnCaricamentoSparito(
            UnableToRetrieveMetadata::fileSize('livewire-tmp/abc.jpg'),
        ));
        $this->assertFalse(UploadTemporaneoPerso::eUnCaricamentoSparito(
            UnableToRetrieveMetadata::fileSize('pages/abc.jpg'),
        ));
        $this->assertFalse(UploadTemporaneoPerso::eUnCaricamentoSparito(
            new RuntimeException('livewire-tmp/abc.jpg'),
        ));
    }

    #[Test]
    public function riconosce_il_caricamento_sparito_anche_dentro_un_altra_eccezione(): void
    {
        $this->assertTrue(UploadTemporaneoPerso::eUnCaricamentoSparito(new RuntimeException(
            'Salvataggio fallito',
            previous: UnableToRetrieveMetadata::fileSize('livewire-tmp/abc.jpg'),
        )));
    }
}

class SalvaConCaricamentoSparito extends Component
{
    public function save(): void
    {
        throw UnableToRetrieveMetadata::fileSize('livewire-tmp/abc.jpg');
    }

    public function salvaAltrove(): void
    {
        throw UnableToRetrieveMetadata::fileSize('pages/abc.jpg');
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
