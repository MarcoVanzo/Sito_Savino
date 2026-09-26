<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le pagine del recesso nominano la personalizzazione che il negozio vende
 * davvero: la firma della giocatrice, aggiunta su richiesta dalla scheda
 * prodotto (`products.personalizzazione_nome`, riga d'ordine
 * `order_items.personalizzazione`). Prima l'esempio era solo "una maglia con
 * nome e numero", che il negozio non offre: chi aggiungeva la firma non
 * leggeva da nessuna parte che quell'articolo esce dal recesso (art. 59 c. 1
 * lett. c del Codice del consumo).
 *
 * Nella stessa passata i link delle pagine inglesi alla funzione di recesso
 * passano a `/en/withdrawal`.
 *
 * Non alza `CondizioniDiVendita::VERSIONE`: le condizioni escludevano già i
 * beni personalizzati su richiesta, la frase nomina il caso concreto.
 *
 * A guardia (§14): si riscrive solo la frase ancora uguale a quella
 * pubblicata; una pagina già corretta dalla redazione non si tocca. I testi
 * nuovi sono gli stessi di `database/data/condizioni_di_vendita.php` e
 * `condizioni_shop.php`. `down()` non fa niente: togliere l'esempio non
 * renderebbe restituibile un articolo firmato.
 */
return new class extends Migration
{
    private const CORREZIONI = [
        [
            'slug' => 'condizioni-di-vendita',
            'vecchio' => 'per esempio una maglia con nome e numero scelti da te (art. 59 c. 1 lett. c). Resta invece valido per i beni aggiudicati nelle aste online.',
            'nuovo' => 'per esempio una maglia con nome e numero scelti da te o un articolo a cui hai aggiunto la firma di una giocatrice scegliendo la personalizzazione nella scheda del prodotto (art. 59 c. 1 lett. c). Sono esclusi solo gli articoli personalizzati: il resto dello stesso ordine si restituisce normalmente. Il recesso resta invece valido per i beni aggiudicati nelle aste online.',
        ],
        [
            'slug' => 'condizioni-di-vendita',
            'vecchio' => 'for example a shirt with a name and number chosen by you. It does apply to items won in online auctions.',
            'nuovo' => 'for example a shirt with a name and number chosen by you, or an item to which you added a player\'s signature by choosing the personalisation on the product page. Only the personalised items are excluded: the rest of the same order can be returned as usual. The right of withdrawal does apply to items won in online auctions.',
        ],
        [
            'slug' => 'diritto-di-recesso',
            'vecchio' => 'per esempio una maglia con nome e numero scelti da te (art. 59 c. 1 lett. c del Codice del consumo).',
            'nuovo' => 'per esempio una maglia con nome e numero scelti da te o un articolo a cui hai aggiunto la firma di una giocatrice scegliendo la personalizzazione nella scheda del prodotto (art. 59 c. 1 lett. c del Codice del consumo). Sono esclusi solo gli articoli personalizzati: il resto dello stesso ordine si restituisce normalmente.',
        ],
        [
            'slug' => 'diritto-di-recesso',
            'vecchio' => 'clearly personalised at your request, for example a shirt with a name and number chosen by you.</p>',
            'nuovo' => 'clearly personalised at your request, for example a shirt with a name and number chosen by you, or an item to which you added a player\'s signature by choosing the personalisation on the product page. Only the personalised items are excluded: the rest of the same order can be returned as usual.</p>',
        ],
        [
            'slug' => 'resi-e-rimborsi',
            'vecchio' => 'Non si possono restituire per recesso i prodotti personalizzati su tua richiesta, per esempio una maglia con nome e numero a tua scelta: la personalizzazione la chiedi tu prima dell\'acquisto, e solo quegli articoli sono esclusi.',
            'nuovo' => 'Non si possono restituire per recesso i prodotti personalizzati su tua richiesta, per esempio una maglia con nome e numero a tua scelta o un articolo con la firma di una giocatrice aggiunta dalla scheda del prodotto: la personalizzazione la chiedi tu prima dell\'acquisto, e solo quegli articoli sono esclusi.',
        ],
        [
            'slug' => 'resi-e-rimborsi',
            'vecchio' => 'Products personalised at your request, for example a shirt with a name and number of your choice, cannot be returned on withdrawal:',
            'nuovo' => 'Products personalised at your request, for example a shirt with a name and number of your choice or an item with a player\'s signature added from the product page, cannot be returned on withdrawal:',
        ],
        // La funzione di recesso in inglese sta su `/en/withdrawal` (prima
        // `/en/recesso`, che ora risponde 301): i link delle pagine inglesi
        // vanno diritti, senza passare dal redirect.
        ['slug' => 'condizioni-di-vendita', 'vecchio' => 'href="/en/recesso"', 'nuovo' => 'href="/en/withdrawal"'],
        ['slug' => 'diritto-di-recesso', 'vecchio' => 'href="/en/recesso"', 'nuovo' => 'href="/en/withdrawal"'],
        ['slug' => 'resi-e-rimborsi', 'vecchio' => 'href="/en/recesso"', 'nuovo' => 'href="/en/withdrawal"'],
    ];

    public function up(): void
    {
        $toccata = false;

        foreach (self::CORREZIONI as $correzione) {
            $pagina = DB::table('pages')->where('slug', $correzione['slug'])->first(['id', 'content']);
            $contenuto = $pagina ? json_decode((string) $pagina->content, true) : null;

            if (! is_array($contenuto)) {
                continue;
            }

            $cambiato = false;

            foreach ($contenuto as $lingua => $testo) {
                if (is_string($testo) && str_contains($testo, $correzione['vecchio'])) {
                    $contenuto[$lingua] = str_replace($correzione['vecchio'], $correzione['nuovo'], $testo);
                    $cambiato = true;
                }
            }

            if ($cambiato) {
                DB::table('pages')->where('id', $pagina->id)->update([
                    'content' => json_encode($contenuto, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
                $toccata = true;
            }
        }

        if ($toccata) {
            CachePublicResponse::flush();
        }
    }

    public function down(): void
    {
        // Vedi sopra.
    }
};
