<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le estensioni PHP che servono in produzione devono stare in `composer.json`.
 *
 * Il buildpack di App Platform abilita solo quelle dichiarate lì. `ext-gd` non
 * c'era, e ogni ridimensionamento di immagine moriva con "Call to undefined
 * function imagecreatefromstring": nessuna anteprima veniva generata — non per
 * le foto della gallery, non per i loghi degli sponsor — e i job di conversione
 * riempivano la coda fallendo e riprovando. Il sito mostrava le immagini a
 * piena risoluzione, quindi il difetto si vedeva solo nella lentezza e nella
 * coda ferma.
 */
class EstensioniPhpTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function requisiti(): array
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        return $composer['require'] ?? [];
    }

    #[Test]
    public function gd_e_dichiarata_fra_i_requisiti(): void
    {
        $this->assertArrayHasKey(
            'ext-gd',
            $this->requisiti(),
            'Senza ext-gd in composer.json il buildpack non abilita GD e le anteprime non si generano.'
        );
    }

    /** E deve esserci davvero dove i test girano. */
    #[Test]
    public function gd_e_disponibile(): void
    {
        $this->assertTrue(extension_loaded('gd'));
        $this->assertTrue(function_exists('imagecreatefromstring'));
    }

    /**
     * Il driver configurato per le immagini deve corrispondere a un'estensione
     * dichiarata: con `imagick` senza `ext-imagick` si tornerebbe al punto di
     * partenza.
     */
    #[Test]
    public function il_driver_immagini_ha_la_sua_estensione(): void
    {
        $driver = config('media-library.image_driver');

        $this->assertArrayHasKey('ext-'.$driver, $this->requisiti());
    }
}
