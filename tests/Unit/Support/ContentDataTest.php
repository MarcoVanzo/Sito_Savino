<?php

namespace Tests\Unit\Support;

use App\Support\ContentData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContentDataTest extends TestCase
{
    private const UUID_A = '3678fee3-1850-4b49-b2d2-2d1743568de8';

    private const UUID_B = '90665992-591d-4c90-a6fc-07461799f35d';

    #[Test]
    public function un_elenco_salvato_come_mappa_uuid_torna_elenco(): void
    {
        $piani = [
            self::UUID_A => ['name' => 'Tribuna Ovest', 'price' => '460'],
            self::UUID_B => ['name' => 'Tribuna Nord', 'price' => '310'],
        ];

        $this->assertSame(
            [['name' => 'Tribuna Ovest', 'price' => '460'], ['name' => 'Tribuna Nord', 'price' => '310']],
            ContentData::normalizza($piani),
        );
    }

    #[Test]
    public function il_file_di_un_upload_singolo_torna_percorso(): void
    {
        $documenti = [
            self::UUID_A => [
                'title' => 'Bilancio',
                'file' => [self::UUID_B => 'documenti/bilancio.pdf'],
            ],
        ];

        $this->assertSame(
            [['title' => 'Bilancio', 'file' => 'documenti/bilancio.pdf']],
            ContentData::normalizza($documenti),
        );
    }

    #[Test]
    public function i_dati_gia_corretti_non_cambiano(): void
    {
        $contenuti = [
            'hero_badge' => 'Believe',
            'plans' => [['name' => 'Ovest', 'features' => ['Posto numerato']]],
            'documents' => [],
            'button_image' => 'img/pulsante.jpg',
            'stat1_value' => null,
        ];

        $this->assertSame($contenuti, ContentData::normalizza($contenuti));
    }

    #[Test]
    public function le_chiavi_che_non_sono_uuid_restano_mappa(): void
    {
        $this->assertSame(['it' => ['a'], 'en' => []], ContentData::normalizza(['it' => ['a'], 'en' => []]));
    }
}
