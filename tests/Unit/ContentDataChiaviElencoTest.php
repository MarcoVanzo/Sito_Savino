<?php

namespace Tests\Unit;

use App\Support\ContentData;
use PHPUnit\Framework\TestCase;

/**
 * `ContentData::CHIAVI_ELENCO` deve dire la verita' su quali chiavi di
 * `content_data` i form trattano come elenchi: e' quello che tiene fuori dal
 * modulo un valore di tipo sbagliato prima che un Repeater ci finisca sopra.
 *
 * Il confronto e' sui sorgenti dei form, non su un elenco scritto a mano una
 * seconda volta: aggiungendo un Repeater e dimenticando la costante, il test
 * dice quale chiave manca.
 */
class ContentDataChiaviElencoTest extends TestCase
{
    public function test_elenca_tutte_le_chiavi_dichiarate_come_ripetibili_nei_form(): void
    {
        $this->assertSame($this->chiaviDichiarateNeiForm(), ContentData::CHIAVI_ELENCO);
    }

    public function test_toglie_solo_le_chiavi_di_elenco_con_un_valore_di_altro_tipo(): void
    {
        $contenuti = [
            'partners' => 'In collaborazione con Civitavecchia Volley',
            'plans' => [['name' => 'Tribuna']],
            'hero_label' => 'Talent Scouting',
            'stages' => ['abc-1' => ['date' => '19 maggio']],
        ];

        $this->assertSame([
            'plans' => [['name' => 'Tribuna']],
            'hero_label' => 'Talent Scouting',
            'stages' => ['abc-1' => ['date' => '19 maggio']],
        ], ContentData::soloElenchiValidi($contenuti));
    }

    /**
     * @return list<string>
     */
    private function chiaviDichiarateNeiForm(): array
    {
        $chiavi = [];

        foreach ($this->fileDeiForm() as $file) {
            preg_match_all(
                "/Repeater::make\('content_data\.([a-z0-9_]+)'\)/",
                (string) file_get_contents($file),
                $trovate
            );

            $chiavi = [...$chiavi, ...$trovate[1]];
        }

        $chiavi = array_values(array_unique($chiavi));
        sort($chiavi);

        return $chiavi;
    }

    /**
     * @return list<string>
     */
    private function fileDeiForm(): array
    {
        $cartella = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/Filament')
        );

        $file = [];

        foreach ($cartella as $elemento) {
            if ($elemento->isFile() && $elemento->getExtension() === 'php') {
                $file[] = $elemento->getPathname();
            }
        }

        return $file;
    }
}
