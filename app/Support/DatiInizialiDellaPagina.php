<?php

namespace App\Support;

/**
 * La direttiva `@inertia`: la pagina iniziale in un `<script type="application/json">`
 * invece che nell'attributo `data-page` del contenitore.
 *
 * Nell'attributo ogni virgoletta del JSON diventava `&quot;`: sulla pagina
 * Risultati 148 KB di dati pesavano 230 KB di HTML (audit SEO del
 * 14/10/2026). Nello script il JSON viaggia così com'è, e il client di
 * Inertia lo legge da lì (`future.useScriptElementForInitialPage` in app.js).
 *
 * La direttiva del pacchetto stampa il JSON con i flag predefiniti, che non
 * toccano `<`: un testo della redazione con `<!--` e `<script` (un incorporamento
 * copiato da Instagram) metterebbe il parser HTML in uno stato da cui il
 * `</script>` di chiusura non lo fa più uscire, e la pagina finirebbe dentro lo
 * script. Qui `<` e `>` diventano `<` e `>`, che `JSON.parse` riporta
 * identici. Le barre e le lettere accentate restano in chiaro: con `<`
 * neutralizzato non servono escape.
 */
class DatiInizialiDellaPagina
{
    /**
     * Compila `@inertia` (o `@inertia('id')`). Con l'SSR acceso stampa il
     * corpo reso dal server, come la direttiva del pacchetto.
     */
    public static function compila(string $expression = ''): string
    {
        $id = trim(trim($expression), "\'\"") ?: 'app';

        return '<?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); } '
            .'if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } else { ?>'
            .'<script data-page="'.$id.'" type="application/json"><?php echo \\'.self::class.'::json($page); ?></script><div id="'.$id.'"></div>'
            .'<?php } ?>';
    }

    /**
     * @param  array<string, mixed>  $page
     */
    public static function json(array $page): string
    {
        return (string) json_encode($page, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
