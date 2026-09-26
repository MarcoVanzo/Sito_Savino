<?php

namespace Tests\Unit;

use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Support\PagineLegaliDelloShop;
use Tests\TestCase;

/**
 * La scheda "Legali (footer)" dell'elenco Pagine e il footer del sito
 * elencano gli stessi slug, scritti due volte: in PHP
 * (`ListPages::slugLegali()`) e nel componente Vue. Una pagina legale nuova
 * aggiunta al footer e non alla scheda sparirebbe dalla vista della redazione
 * senza che niente lo dica; il contrario lascerebbe nella scheda una pagina
 * che online nessuno raggiunge.
 */
class SlugLegaliDelFooterTest extends TestCase
{
    /**
     * Nella scheda ma non nel footer, ciascuna col suo motivo.
     */
    private const SOLO_NELLA_SCHEDA = [
        // La linka la pagina delle aste (Shop/Auctions/Index.vue), non il footer.
        PagineLegaliDelloShop::REGOLAMENTO_ASTE,
    ];

    public function test_la_scheda_legali_e_il_footer_elencano_le_stesse_pagine(): void
    {
        $vue = (string) file_get_contents(resource_path('js/Components/SiteFooter.vue'));
        preg_match_all("/route\\('pages\\.show',\\s*'([^']+)'\\)/", $vue, $trovati);
        $nelFooter = array_values(array_unique($trovati[1]));

        $this->assertNotEmpty($nelFooter, 'Nessun link a pages.show trovato in SiteFooter.vue: e\' cambiata la forma dei link?');

        $nellaScheda = array_values(array_diff(ListPages::slugLegali(), self::SOLO_NELLA_SCHEDA));

        sort($nelFooter);
        sort($nellaScheda);

        $this->assertSame($nellaScheda, $nelFooter);
    }
}
