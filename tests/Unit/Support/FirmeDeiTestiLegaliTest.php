<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Le `firme` di un file dati riconoscono le versioni PRECEDENTI di un testo:
 * una migrazione di revisione riscrive la pagina solo se ne trova una, perché
 * vuol dire che la redazione non l'ha ancora toccata (§21 del CLAUDE.md).
 *
 * Una firma che compare anche nel testo di oggi rompe la guardia: la pagina
 * riscritta dalla redazione tenendo quella frase sembra "non toccata", e la
 * revisione successiva cancella il suo lavoro. È successo con la Cookie
 * Policy: 'quelli di marketing di Meta Platforms Ireland Ltd.' stava sia
 * nella versione del 22/09 sia in quella del 23/09.
 */
class FirmeDeiTestiLegaliTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function fileConFirme(): array
    {
        return [
            'informative_privacy' => ['informative_privacy.php'],
            'condizioni_di_vendita' => ['condizioni_di_vendita.php'],
            'condizioni_shop' => ['condizioni_shop.php'],
            'informative_da_documento' => ['informative_da_documento.php'],
        ];
    }

    #[DataProvider('fileConFirme')]
    public function test_nessuna_firma_compare_nel_testo_attuale(string $file): void
    {
        $testi = require database_path('data/'.$file);

        $this->assertNotEmpty($testi);

        foreach ($testi as $slug => $testo) {
            $this->assertArrayHasKey('firme', $testo, "{$file}: '{$slug}' senza firme");
            $attuale = implode(' ', array_map('strval', $testo['contenuto']));

            foreach ($testo['firme'] as $firma) {
                $this->assertStringNotContainsString(
                    $firma,
                    $attuale,
                    "{$file}: la firma di '{$slug}' «{$firma}» compare nel testo attuale, quindi riconosce anche la pagina già riscritta dalla redazione."
                );
            }
        }
    }

    /**
     * Tutti i file dati con le firme sono nell'elenco qui sopra: uno nuovo
     * dimenticato sfuggirebbe al controllo.
     */
    public function test_l_elenco_dei_file_con_firme_e_completo(): void
    {
        $conFirme = [];

        foreach (glob(database_path('data/*.php')) ?: [] as $percorso) {
            if (str_contains((string) file_get_contents($percorso), "'firme'")) {
                $conFirme[] = basename($percorso);
            }
        }

        $attesi = array_map(fn (array $riga): string => $riga[0], self::fileConFirme());
        sort($conFirme);
        sort($attesi);

        $this->assertSame($attesi, $conFirme);
    }
}
