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
    /**
     * Il disco del pannello non deve mai essere quello privato di Laravel
     * (`storage/app/private`): i suoi indirizzi rispondono 403 e il file
     * caricato non si vedrebbe.
     */
    #[Test]
    public function il_pannello_non_scrive_sul_disco_privato(): void
    {
        $this->assertNotSame('local', config('filament.default_filesystem_disk'));
    }

    /**
     * Quando l'applicazione usa un disco remoto — in produzione Spaces — il
     * pannello deve usare quello stesso. Il valore di serie del pacchetto è il
     * disco locale del container, che App Platform ricrea a ogni rilascio.
     */
    #[Test]
    public function con_un_disco_remoto_il_pannello_lo_segue(): void
    {
        $disco = config('filesystems.default');

        if ($disco === 'local' || $disco === 'public') {
            $this->markTestSkipped('Serve un disco remoto per avere qualcosa da verificare.');
        }

        $this->assertSame($disco, config('filament.default_filesystem_disk'));
    }

    /**
     * Nessun campo di caricamento deve appoggiarsi al valore di serie del
     * pacchetto: se `config/filament.php` sparisse, tornerebbe `public` anche
     * in produzione.
     */
    #[Test]
    public function un_campo_senza_disco_esplicito_usa_quello_configurato(): void
    {
        $campo = FileUpload::make('documento');

        $this->assertSame(config('filament.default_filesystem_disk'), $campo->getDiskName());
    }

    /** La configurazione deve stare nel repository, non solo nell'ambiente. */
    #[Test]
    public function la_configurazione_e_nel_repository(): void
    {
        $this->assertFileExists(config_path('filament.php'));
    }
}
