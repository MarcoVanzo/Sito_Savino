<?php

namespace Tests\Feature\Filament;

use Filament\Forms\Components\FileUpload;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il pannello deve salvare i file dove li salva il resto dell'applicazione.
 *
 * Filament non segue `FILESYSTEM_DISK`: il suo valore di serie è `public`,
 * cioè il disco locale. In produzione il container viene ricreato a ogni
 * rilascio e quella cartella se ne va, mentre in archivio resta il percorso.
 * Sono spariti così tutti e sette i documenti legali, il logo delle Cartelle
 * Stampa e il bilancio di sostenibilità: il pannello diceva "salvato", il sito
 * mostrava un link, e dietro non c'era nessun file.
 *
 * Le foto passano invece dalla media library, che ha una sua impostazione già
 * puntata su Spaces: funzionavano solo quelle, ed è il motivo per cui il
 * difetto è rimasto nascosto a lungo.
 */
class DiscoDeiFileCaricatiTest extends TestCase
{
    #[Test]
    public function il_pannello_scrive_sul_disco_dell_applicazione(): void
    {
        $this->assertSame(
            config('filesystems.default'),
            config('filament.default_filesystem_disk'),
            'Il pannello salva i file su un disco diverso da quello del sito.'
        );
    }

    /**
     * Nessun campo di caricamento deve appoggiarsi al valore di serie del
     * pacchetto: se `config/filament.php` sparisse, tornerebbe `public`.
     */
    #[Test]
    public function un_campo_senza_disco_esplicito_usa_quello_giusto(): void
    {
        $campo = FileUpload::make('documento');

        $this->assertSame(config('filesystems.default'), $campo->getDiskName());
    }

    /** La configurazione deve stare nel repository, non solo nell'ambiente. */
    #[Test]
    public function la_configurazione_e_nel_repository(): void
    {
        $this->assertFileExists(config_path('filament.php'));
    }
}
