<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le Condizioni di vendita vendono maglie da gara e autografati «nello stato
 * descritto nella scheda»: dal 26/09/2026 la scheda ha la voce «Stato
 * dell'articolo», che l'ordine fotografa e la conferma riporta. La frase ora
 * rimanda a quella voce.
 *
 * A guardia (§14): si riscrive solo la frase ancora uguale a quella
 * pubblicata; un testo gia' riscritto dalla redazione non si tocca.
 * Non alza CondizioniDiVendita::VERSIONE: la sostanza non cambia, la frase
 * dice solo dove si legge lo stato.
 * `down()` non fa niente: togliere il rimando non ha senso.
 */
return new class extends Migration
{
    private const SLUG = 'condizioni-di-vendita';

    private const CORREZIONI = [
        'I prodotti autografati e le maglie da gara sono venduti nello stato descritto nella scheda.' => 'I prodotti autografati e le maglie da gara sono venduti nello stato descritto nella scheda alla voce «Stato dell\'articolo», riportato anche nella conferma d\'ordine.',
        'Signed products and match shirts are sold in the condition described on the product page.' => 'Signed products and match shirts are sold in the condition described under “Item condition” on the product page, which is also shown in the order confirmation.',
    ];

    public function up(): void
    {
        $pagina = DB::table('pages')->where('slug', self::SLUG)->first(['id', 'content']);
        $contenuto = $pagina ? json_decode((string) $pagina->content, true) : null;

        if (! is_array($contenuto)) {
            return;
        }

        $cambiato = false;

        foreach ($contenuto as $lingua => $testo) {
            if (! is_string($testo)) {
                continue;
            }

            foreach (self::CORREZIONI as $vecchio => $nuovo) {
                if (str_contains($testo, $vecchio)) {
                    $testo = str_replace($vecchio, $nuovo, $testo);
                    $contenuto[$lingua] = $testo;
                    $cambiato = true;
                }
            }
        }

        if (! $cambiato) {
            return;
        }

        DB::table('pages')->where('id', $pagina->id)->update([
            'content' => json_encode($contenuto, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        CachePublicResponse::flush();
    }

    public function down(): void
    {
        // Vedi sopra.
    }
};
