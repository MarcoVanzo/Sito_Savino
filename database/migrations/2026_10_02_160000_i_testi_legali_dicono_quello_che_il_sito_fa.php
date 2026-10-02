<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revisione del 2 ottobre 2026: frasi delle pagine legali dello shop che non
 * dicevano quello che il sito fa (confronto testo per testo con il codice).
 *
 * - Condizioni di vendita: Stripe offre anche Apple Pay, Google Pay e simili;
 *   gli ordini PayPal/Stripe non pagati si annullano dopo un'ora
 *   (`order:check-unpaid`); la spedizione e' gratuita sopra la soglia; i tempi
 *   sono preparazione (dal pagamento) piu' spedizione, come dice la pagina
 *   Spedizioni; la ricevuta del recesso e' a schermo e per email.
 * - Regolamento aste: il termine del vincitore sta nell'email e nella pagina
 *   di pagamento, e col bonifico diventa quello del bonifico
 *   (`AuctionCheckoutController::terminePerIlBonifico`); se non paga si scorre
 *   la classifica, non solo il secondo (`AuctionService`); l'asta benefica ha
 *   un testo libero, non i campi ente e quota.
 * - Informativa promozionale: la newsletter si conserva fino alla revoca, poi
 *   24 mesi di sola prova (non 24 mesi dal consenso, che nessuno applicava).
 * - Informativa fornitori: e' per fornitori e controparti, non per i clienti
 *   dello shop, che hanno la Privacy Policy.
 * - Dichiarazione di accessibilita': anche la ricevuta PDF non e' strutturata;
 *   le aste non sono tutte di beneficenza.
 *
 * A guardia (§14): si sostituisce solo la frase ancora uguale a quella
 * pubblicata; una pagina gia' riscritta dalla redazione non si tocca.
 * `down()` non fa niente: rimettere frasi false non ha senso.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: string, 2: string}> slug, frase pubblicata, frase nuova */
    private const CORREZIONI = [
        ['condizioni-di-vendita', 'Le spese di spedizione dipendono dal paese di destinazione e dal peso dell\'ordine, e sono mostrate prima della conferma.', 'Le spese di spedizione dipendono dal paese di destinazione e dal peso dell\'ordine, sono gratuite sopra la soglia di spesa indicata nella pagina <a href="/spedizioni">Spedizioni</a> e sono mostrate prima della conferma.'],
        ['condizioni-di-vendita', 'Shipping costs depend on the destination country and the weight of the order, and are shown before confirmation.', 'Shipping costs depend on the destination country and the weight of the order, are free above the spending threshold shown on the <a href="/en/spedizioni">Shipping</a> page, and are shown before confirmation.'],
        ['condizioni-di-vendita', 'Puoi pagare con i metodi mostrati al checkout: PayPal, carta di pagamento (quando disponibile) e bonifico bancario. Con il bonifico', 'Puoi pagare con i metodi mostrati al checkout: PayPal, carta di pagamento e gli altri metodi che Stripe propone nella sua pagina di pagamento (per esempio Apple Pay o Google Pay), e bonifico bancario. Se scegli PayPal o Stripe e non completi il pagamento, l\'ordine viene annullato automaticamente dopo un\'ora e i prodotti tornano disponibili; se il gestore segnala un pagamento ancora in verifica, l\'ordine attende il suo esito. Con il bonifico'],
        ['condizioni-di-vendita', 'You can pay with the methods shown at checkout: PayPal, card (when available) and bank transfer. With a bank transfer', 'You can pay with the methods shown at checkout: PayPal, card and the other methods Stripe offers on its payment page (for example Apple Pay or Google Pay), and bank transfer. If you choose PayPal or Stripe and do not complete the payment, the order is cancelled automatically after one hour and the products become available again; if the provider reports a payment still under review, the order waits for its outcome. With a bank transfer'],
        ['condizioni-di-vendita', 'I tempi stimati sono indicati prima della conferma e decorrono dalla ricezione del pagamento;', 'Prima della conferma sono indicati i tempi stimati di spedizione, a cui si aggiungono 1-2 giorni lavorativi di preparazione che decorrono dalla ricezione del pagamento;'],
        ['condizioni-di-vendita', 'Estimated times are shown before confirmation and run from receipt of payment;', 'Estimated shipping times are shown before confirmation, plus 1-2 working days of preparation that run from receipt of payment;'],
        ['condizioni-di-vendita', 'e ricevi subito via email una ricevuta con il contenuto della dichiarazione', 'e ricevi subito, a schermo e via email, una ricevuta con il contenuto della dichiarazione'],
        ['condizioni-di-vendita', 'and you immediately receive by email a receipt with the content of your statement', 'and you immediately receive, on screen and by email, a receipt with the content of your statement'],
        ['resi-e-rimborsi', 'Ricevi subito via email una ricevuta con data e ora.', 'Ricevi subito la ricevuta con data e ora, a schermo e via email.'],
        ['resi-e-rimborsi', 'You immediately receive a receipt by email with the date and time.', 'You immediately receive a receipt with the date and time, on screen and by email.'],
        ['regolamento-aste', 'Il vincitore ha il tempo indicato nella pagina dell\'asta per pagare; se non paga entro il termine, l\'oggetto viene proposto al secondo miglior offerente alle condizioni della sua offerta.', 'Il vincitore deve pagare entro il termine indicato nell\'email di aggiudicazione e nella pagina di pagamento; se sceglie il bonifico, il termine diventa quello del bonifico indicato nell\'email di conferma dell\'ordine. Se non paga entro il termine, l\'oggetto viene proposto all\'offerente successivo in classifica, alle condizioni della sua offerta, purché raggiunga l\'eventuale prezzo di riserva.'],
        ['regolamento-aste', 'The winner has the time shown on the auction page to pay; if payment is not made in time, the item is offered to the second-highest bidder on the terms of their bid.', 'The winner must pay within the deadline stated in the award email and on the payment page; if they choose bank transfer, the deadline becomes the bank transfer deadline stated in the order confirmation email. If payment is not made in time, the item is offered to the next bidder in the ranking, on the terms of their bid, provided it reaches any reserve price.'],
        ['regolamento-aste', 'Quando un\'asta è a scopo benefico, la sua pagina indica l\'ente destinatario e la quota del ricavato che gli viene devoluta.', 'Quando un\'asta è a scopo benefico, la sua pagina descrive la finalità benefica e l\'ente destinatario.'],
        ['regolamento-aste', 'When an auction is for charity, its page states the beneficiary and the share of the proceeds donated.', 'When an auction is for charity, its page describes the charitable purpose and the beneficiary.'],
        ['informativa-comunicazioni-promozionali', 'I dati personali trattati per le finalità indicate sono conservati per 24 mesi dalla data in cui hai prestato il consenso.', 'I dati personali trattati per le finalità indicate sono conservati finché non revochi il consenso. Per la newsletter del sito, dopo la disiscrizione restano per 24 mesi solo l\'email e le date di iscrizione, conferma e revoca, come prova del consenso; poi si cancellano.'],
        ['informativa-comunicazioni-promozionali', 'Personal data processed for the purposes above are kept for 24 months from the date you gave your consent.', 'Personal data processed for the purposes above are kept until you withdraw your consent. For the site newsletter, after you unsubscribe only your email and the dates of subscription, confirmation and withdrawal are kept for 24 months as proof of consent; then they are deleted.'],
        ['dichiarazione-di-accessibilita', 'si partecipa alle aste di beneficenza:', 'si partecipa alle aste online:'],
        ['dichiarazione-di-accessibilita', 'and charity auctions are held:', 'and online auctions are held:'],
        ['dichiarazione-di-accessibilita', '<li><strong>PDF delle condizioni allegato alla conferma d\'ordine</strong>: dichiara titolo e lingua ma non è strutturato; lo stesso testo', '<li><strong>PDF delle condizioni allegato alla conferma d\'ordine e ricevuta PDF dell\'ordine</strong>: dichiarano titolo e lingua ma non sono strutturati; i dati dell\'ordine sono anche nella pagina di conferma e nell\'area personale, e il testo delle condizioni'],
        ['dichiarazione-di-accessibilita', '<li><strong>Terms PDF attached to the order confirmation</strong>: it declares title and language but is not structured; the same text', '<li><strong>Terms PDF attached to the order confirmation and order receipt PDF</strong>: they declare title and language but are not structured; the order details are also on the confirmation page and in the personal area, and the terms'],
        ['condizioni-di-vendita', 'compresi i beni aggiudicati nelle aste online. Sono aggiornate al 25 settembre 2026.', 'compresi i beni aggiudicati nelle aste online. Sono aggiornate al 2 ottobre 2026.'],
        ['condizioni-di-vendita', 'including items won in online auctions. Last updated 25 September 2026.', 'including items won in online auctions. Last updated 2 October 2026.'],
        ['diritto-di-recesso', '<p>Aggiornata al 25 settembre 2026. Vale per gli acquisti', '<p>Aggiornata al 2 ottobre 2026. Vale per gli acquisti'],
        ['diritto-di-recesso', '<p>Last updated 25 September 2026. It applies to purchases', '<p>Last updated 2 October 2026. It applies to purchases'],
    ];

    /** @var array<string, array<string, array{0: string, 1: string}>> */
    private const DESCRIZIONI = [
        'informativa-fornitori' => [
            'it' => ['Informativa privacy (art. 13 GDPR) per clienti e fornitori della Savino Del Bene Volley: finalità, destinatari, conservazione e diritti.', 'Informativa privacy (art. 13 GDPR) per fornitori e controparti contrattuali della Savino Del Bene Volley; gli acquisti sullo shop sono descritti nella Privacy Policy.'],
            'en' => ['Privacy notice (art. 13 GDPR) for Savino Del Bene Volley customers and suppliers: purposes, recipients, retention and rights.', 'Privacy notice (art. 13 GDPR) for Savino Del Bene Volley suppliers and contractual counterparties; shop purchases are described in the Privacy Policy.'],
        ],
    ];

    public function up(): void
    {
        $cambiato = false;

        foreach (collect(self::CORREZIONI)->groupBy(0) as $slug => $correzioni) {
            $pagina = DB::table('pages')->where('slug', $slug)->first(['id', 'content']);
            $contenuto = $pagina ? json_decode((string) $pagina->content, true) : null;

            if (! is_array($contenuto)) {
                continue;
            }

            $prima = $contenuto;

            foreach ($contenuto as $lingua => $testo) {
                if (! is_string($testo)) {
                    continue;
                }

                foreach ($correzioni as [, $vecchio, $nuovo]) {
                    $testo = str_replace($vecchio, $nuovo, $testo);
                }

                $contenuto[$lingua] = $testo;
            }

            if ($contenuto !== $prima) {
                DB::table('pages')->where('id', $pagina->id)->update([
                    'content' => json_encode($contenuto, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
                $cambiato = true;
            }
        }

        foreach (self::DESCRIZIONI as $slug => $lingue) {
            $pagina = DB::table('pages')->where('slug', $slug)->first(['id', 'meta_description']);
            $descrizione = $pagina ? json_decode((string) $pagina->meta_description, true) : null;

            if (! is_array($descrizione)) {
                continue;
            }

            $prima = $descrizione;

            foreach ($lingue as $lingua => [$vecchia, $nuova]) {
                if (($descrizione[$lingua] ?? null) === $vecchia) {
                    $descrizione[$lingua] = $nuova;
                }
            }

            if ($descrizione !== $prima) {
                DB::table('pages')->where('id', $pagina->id)->update([
                    'meta_description' => json_encode($descrizione, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
                $cambiato = true;
            }
        }

        if ($cambiato) {
            CachePublicResponse::flush();
        }
    }

    public function down(): void
    {
        // Vedi sopra.
    }
};
