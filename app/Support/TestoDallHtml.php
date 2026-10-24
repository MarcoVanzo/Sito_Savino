<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Il testo semplice di un frammento HTML della redazione, per description,
 * `og:description` e JSON-LD.
 *
 * `strip_tags()` da solo toglie i tag senza lasciare niente al loro posto:
 * `<p>…rosa.</p><p>Il design…` diventava «rosa.Il design». Qui ogni fine di
 * blocco e ogni `<br>` lascia uno spazio, le entità tornano caratteri (anche
 * `&nbsp;`) e gli spazi si comprimono in uno.
 */
class TestoDallHtml
{
    public static function testo(?string $html): string
    {
        $conGliSpazi = preg_replace('~<br\s*/?>|</(p|div|li|h[1-6]|blockquote|tr|td|th)>~i', '$0 ', (string) $html);

        return Str::squish(html_entity_decode(strip_tags((string) $conGliSpazi), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
