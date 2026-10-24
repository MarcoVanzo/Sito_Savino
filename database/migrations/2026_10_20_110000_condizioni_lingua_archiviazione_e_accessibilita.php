<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revisione del 20 ottobre 2026 delle Condizioni di vendita
 * (CondizioniDiVendita::VERSIONE = 2026-10-20), dall'analisi di conformità:
 *
 * - in che lingue si conclude il contratto (art. 12 d.lgs. 70/2003): italiano
 *   e inglese, quella del sito in cui si ordina;
 * - che il contratto viene archiviato e come il cliente lo ritrova (PDF
 *   allegato alla conferma, pagina dell'ordine, «I miei ordini»);
 * - un richiamo alla Dichiarazione di accessibilità.
 *
 * Gli ordini già fatti non cambiano: la loro prova è l'istantanea in
 * `versioni_condizioni`, e il PDF si rigenera da quella.
 *
 * A guardia (§14): si sostituisce solo la frase ancora uguale a quella
 * pubblicata; una pagina riscritta dalla redazione non si tocca. Una frase
 * già sostituita non si ripete. `down()` non fa niente: è una revisione del
 * testo, e gli ordini accettati hanno la loro istantanea.
 */
return new class extends Migration
{
    private const SLUG = 'condizioni-di-vendita';

    /** @var list<array{0: string, 1: string}> frase pubblicata, frase nuova */
    private const CORREZIONI = [
        ['Sono aggiornate al 2 ottobre 2026.', 'Sono aggiornate al 20 ottobre 2026.'],
        ['Last updated 2 October 2026. Please read', 'Last updated 20 October 2026. Please read'],
        ['può essere assegnato al miglior offerente successivo.</p>', 'può essere assegnato al miglior offerente successivo.</p>
<p><strong>Lingua.</strong> Puoi concludere il contratto in italiano o in inglese: vale la lingua del sito in cui fai l\'ordine, e nella stessa lingua ricevi l\'email di conferma e queste condizioni. Se la versione italiana e quella inglese non coincidono, vale quella italiana.</p>
<p><strong>Dove ritrovi il contratto.</strong> Il contratto viene archiviato: insieme all\'ordine conserviamo il testo esatto delle condizioni e dell\'informativa sul recesso che hai accettato. Lo ricevi in PDF allegato all\'email di conferma. Il riepilogo dell\'ordine e la ricevuta restano nella pagina dell\'ordine, che apri dal link nell\'email di conferma e, se hai un account, da «I miei ordini» nell\'area personale.</p>'],
        ['the item may be offered to the next highest bidder.</p>', 'the item may be offered to the next highest bidder.</p>
<p><strong>Language.</strong> You can conclude the contract in Italian or in English: the language is the one of the site where you place the order, and you receive the confirmation email and these terms in that same language. If the Italian and English versions differ, the Italian version prevails.</p>
<p><strong>Where to find your contract.</strong> The contract is archived: together with the order we keep the exact text of the terms and of the withdrawal information you accepted. You receive it as a PDF attached to the confirmation email. The order summary and the receipt remain on the order page, which you open from the link in the confirmation email and, if you have an account, from “My Orders” in your personal area.</p>'],
        ['hai il domicilio, se sei un consumatore in Italia.</p>', 'hai il domicilio, se sei un consumatore in Italia.</p>

<h3>11. Accessibilità</h3>
<p>Lo shop è pensato per essere usato da tutti, anche con le tecnologie assistive. A che punto siamo, i limiti che conosciamo e come segnalarci un problema sono nella <a href="/dichiarazione-di-accessibilita">Dichiarazione di accessibilità</a>.</p>'],
        ['the courts of the place where you reside or are domiciled have jurisdiction.</p>', 'the courts of the place where you reside or are domiciled have jurisdiction.</p>

<h3>11. Accessibility</h3>
<p>The shop is designed to be usable by everyone, including with assistive technologies. Where we stand, the known limitations and how to report a problem are in the <a href="/en/dichiarazione-di-accessibilita">Accessibility statement</a>.</p>'],
    ];

    public function up(): void
    {
        $pagina = DB::table('pages')->where('slug', self::SLUG)->first(['id', 'content']);
        $contenuto = $pagina ? json_decode((string) $pagina->content, true) : null;

        if (! is_array($contenuto)) {
            return;
        }

        $prima = $contenuto;

        foreach ($contenuto as $lingua => $testo) {
            if (! is_string($testo)) {
                continue;
            }

            foreach (self::CORREZIONI as [$vecchio, $nuovo]) {
                // Le aggiunte contengono la frase a cui si agganciano: già
                // applicate, non si ripetono.
                if (str_contains($testo, $nuovo)) {
                    continue;
                }

                $testo = str_replace($vecchio, $nuovo, $testo);
            }

            $contenuto[$lingua] = $testo;
        }

        if ($contenuto === $prima) {
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
