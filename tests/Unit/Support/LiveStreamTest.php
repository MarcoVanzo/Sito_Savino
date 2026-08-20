<?php

namespace Tests\Unit\Support;

use App\Support\LiveStream;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LiveStreamTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function piattaformeIncorporabili(): array
    {
        return [
            'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1'],
            'youtube corto' => ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1'],
            'youtube live' => ['https://www.youtube.com/live/abc123XYZ', 'https://www.youtube.com/embed/abc123XYZ?autoplay=1'],
            'vimeo' => ['https://vimeo.com/123456789', 'https://player.vimeo.com/video/123456789'],
            'dailymotion' => ['https://www.dailymotion.com/video/x8abcd', 'https://www.dailymotion.com/embed/video/x8abcd'],
        ];
    }

    #[DataProvider('piattaformeIncorporabili')]
    public function test_le_piattaforme_conosciute_diventano_url_di_embed(string $url, string $atteso): void
    {
        $this->assertSame($atteso, LiveStream::embedUrl($url));
    }

    public function test_twitch_riceve_il_dominio_che_ospita_iframe(): void
    {
        config(['app.url' => 'https://savinodelbenevolley.it']);

        $this->assertSame(
            'https://player.twitch.tv/?channel=legavolley&parent=savinodelbenevolley.it',
            LiveStream::embedUrl('https://www.twitch.tv/legavolley')
        );
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function indirizziNonIncorporabili(): array
    {
        return [
            'vuoto' => [''],
            'null' => [null],
            'dominio sconosciuto' => ['https://streaming-qualsiasi.example/diretta'],
            'schema pericoloso' => ['javascript:alert(1)'],
            // `parse_url` legge qui l'host di una piattaforma ammessa: senza il
            // controllo sul protocollo l'indirizzo finiva dritto nell'iframe.
            'schema pericoloso con host ammesso' => ['javascript://player.vimeo.com/%0aalert(1)'],
            'dati inline' => ['data:text/html,<script>alert(1)</script>'],
            'youtube senza id' => ['https://www.youtube.com/watch?list=PL123'],
        ];
    }

    /**
     * L'indirizzo del player passa intero: la chiave `h=` dei video non elencati
     * fa parte del link e toglierla lascerebbe un iframe che non parte.
     */
    public function test_il_player_di_vimeo_passa_con_la_sua_chiave(): void
    {
        $this->assertSame(
            'https://player.vimeo.com/video/123456789?h=abc',
            LiveStream::embedUrl('https://player.vimeo.com/video/123456789?h=abc')
        );
    }

    /**
     * Il link non incorporabile viene aperto in una scheda nuova, cioè finisce
     * in un `href`: deve restare un indirizzo web.
     */
    public function test_solo_i_link_web_vengono_riproposti_al_frontend(): void
    {
        $this->assertSame(
            'https://streaming-qualsiasi.example/diretta',
            LiveStream::externalUrl('https://streaming-qualsiasi.example/diretta')
        );

        $this->assertNull(LiveStream::externalUrl('javascript:alert(1)'));
        $this->assertNull(LiveStream::externalUrl('javascript://player.vimeo.com/%0aalert(1)'));
        $this->assertNull(LiveStream::externalUrl(null));
    }

    /**
     * Un dominio fuori elenco non finisce in un iframe: il frontend lo apre in
     * una scheda nuova, così la pagina non carica codice di terzi sconosciuti.
     */
    #[DataProvider('indirizziNonIncorporabili')]
    public function test_gli_altri_indirizzi_non_vengono_incorporati(?string $url): void
    {
        $this->assertNull(LiveStream::embedUrl($url));
    }
}
