<?php

namespace Tests\Unit;

use App\Filament\Forms\PageTemplateForms;
use App\Models\Player;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La redazione incolla quello che le app le danno: il link del profilo
 * Instagram con i parametri di tracciamento, il codice `<iframe>` di Google
 * Maps. Il pannello tiene solo la parte che serve.
 */
class CampiIncollatiDallaRedazioneTest extends TestCase
{
    #[Test]
    public function del_profilo_instagram_resta_il_nome_utente(): void
    {
        $this->assertSame('majaognjenovic10', Player::instagramHandleDa('https://www.instagram.com/majaognjenovic10?utm_source=ig_web_button_share_sheet&igsh=abc'));
        $this->assertSame('maja.o_10', Player::instagramHandleDa('@maja.o_10'));
        $this->assertSame('maja', Player::instagramHandleDa(' maja '));
        $this->assertNull(Player::instagramHandleDa(''));
        $this->assertNull(Player::instagramHandleDa('non è un profilo'));
    }

    #[Test]
    public function della_mappa_resta_l_indirizzo_da_incorporare(): void
    {
        $src = 'https://www.google.com/maps/embed?pb=!1m18!2sPala%20BigMat!5e0';

        $this->assertSame($src, PageTemplateForms::srcDellaMappa('<iframe src="'.$src.'" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>'));
        $this->assertSame($src, PageTemplateForms::srcDellaMappa($src));
        $this->assertSame('https://maps.google.com/maps?q=a&output=embed', PageTemplateForms::srcDellaMappa("<iframe src='https://maps.google.com/maps?q=a&amp;output=embed'></iframe>"));
        $this->assertNull(PageTemplateForms::srcDellaMappa('  '));
    }
}
