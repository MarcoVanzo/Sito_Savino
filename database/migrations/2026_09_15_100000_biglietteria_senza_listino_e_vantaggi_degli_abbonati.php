<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Biglietteria e Campagna Abbonamenti, come chiesto dalla redazione.
 *
 * 1. La biglietteria perde il listino: i prezzi dei biglietti cambiano di
 *    partita in partita e stanno su Vivaticket, mentre in pagina c'era una
 *    copia del listino abbonamenti con una "Gift Card" infilata fra i piani.
 *    Al suo posto uno spazio in evidenza (testo, grafica, pulsante) che eredita
 *    il link della biglietteria online dall'hero, e un blocco dedicato alla
 *    Gift Card che eredita il link dal vecchio piano.
 *
 * 2. La campagna abbonamenti elenca i vantaggi riservati agli abbonati, come
 *    sul sito precedente. Le fasi di conferma e prelazione hanno il loro
 *    spazio ma restano vuote: serviranno alla campagna di luglio 2027.
 *
 * Ogni lingua si tocca una volta sola: se il blocco esiste già, la redazione
 * ci ha messo mano e non si sovrascrive.
 */
return new class extends Migration
{
    private const TESTI = [
        'it' => [
            'feature_title' => 'Biglietti gara per gara',
            'feature_text' => "I biglietti delle gare casalinghe al Pala BigMat si acquistano online su Vivaticket, partita per partita.\n\nI prezzi variano in base alla gara e al settore: li trovi sempre aggiornati nella pagina di vendita.",
            'feature_button_text' => 'Acquista i biglietti',
            'gift_card_title' => 'Gift Card',
            'gift_card_text' => "Regala una serata al Pala BigMat: scegli l'importo, ricevi il codice via email e usalo su Vivaticket per biglietti e abbonamenti della Savino Del Bene Volley.",
            'gift_card_button_text' => 'Acquista la Gift Card',
            'benefits_heading' => 'Vantaggi',
            'phases_heading' => 'Le fasi della campagna',
            'benefits' => [
                'Ogni abbonato avrà accesso a sconti sul merchandising, ticketing, gadget, inviti ed eventi esclusivi, sconti presso i nostri partner e sulle trasferte',
                'Possibilità di dilazionare il pagamento online grazie a Klarna e offline grazie a Splittypay! Scopri di più scrivendo a ticketing@savinodelbenevolley.it',
                "Sconti presso i nostri partner presentando la tesserina dell'abbonamento e documento d'identità",
                "Sconto del 10% presso il merchandising ufficiale della Savino Del Bene Volley presso il corner dedicato a Pala BigMat presentando la tesserina dell'abbonamento e documento d'identità",
                "Possibilità di acquisto di 1 biglietto a tariffa ridotta per ogni partita, fatta eccezione di quelle di cartello contro Conegliano e Milano (non garantiamo la prossimità del posto a quello dell'abbonamento)",
                'Scontistiche e prelazione in occasione delle trasferte organizzate dalla società',
                "Quest'anno uno speciale kit del tifoso pensato esclusivamente per voi!",
            ],
        ],
        'en' => [
            'feature_title' => 'Match-by-match tickets',
            'feature_text' => "Tickets for home matches at Pala BigMat are sold online on Vivaticket, one match at a time.\n\nPrices depend on the match and the sector: you will always find them up to date on the sales page.",
            'feature_button_text' => 'Buy tickets',
            'gift_card_title' => 'Gift Card',
            'gift_card_text' => 'Give a night at Pala BigMat: choose the amount, receive the code by email and use it on Vivaticket for Savino Del Bene Volley tickets and season passes.',
            'gift_card_button_text' => 'Buy a Gift Card',
            'benefits_heading' => 'Benefits',
            'phases_heading' => 'Campaign phases',
            'benefits' => [
                'Every season ticket holder gets discounts on merchandising, ticketing, gadgets, invitations and exclusive events, plus discounts at our partners and on away trips',
                'Pay in instalments online with Klarna and offline with Splittypay! Find out more by writing to ticketing@savinodelbenevolley.it',
                'Discounts at our partners on showing your season ticket card and an ID document',
                '10% discount at the official Savino Del Bene Volley merchandising corner at Pala BigMat on showing your season ticket card and an ID document',
                'One reduced-price ticket per match, except for the top matches against Conegliano and Milano (we cannot guarantee a seat close to your season ticket seat)',
                'Discounts and priority access for away trips organised by the club',
                'This year a special fan kit designed exclusively for you!',
            ],
        ],
    ];

    public function up(): void
    {
        $this->aggiorna('biglietteria', fn (array $valori, string $lingua): array => $this->biglietteria($valori, $lingua));
        $this->aggiorna('abbonamenti', fn (array $valori, string $lingua): array => $this->abbonamenti($valori, $lingua));
    }

    /**
     * I contenuti precedenti erano un listino copiato: non si ripristinano.
     */
    public function down(): void {}

    /**
     * @param  array<string, mixed>  $valori
     * @return array<string, mixed>
     */
    private function biglietteria(array $valori, string $lingua): array
    {
        if (array_key_exists('feature_title', $valori)) {
            return $valori;
        }

        $testi = self::TESTI[$lingua] ?? self::TESTI['it'];
        $piani = is_array($valori['plans'] ?? null) ? array_values($valori['plans']) : [];
        $giftCard = null;

        foreach ($piani as $piano) {
            if (is_array($piano) && str_contains(mb_strtolower((string) ($piano['name'] ?? '')), 'gift')) {
                $giftCard = $piano;
                break;
            }
        }

        $valori['plans'] = [];
        $valori['plans_heading'] = '';
        $valori['plans_empty'] = '';

        $valori['feature_title'] = $testi['feature_title'];
        $valori['feature_text'] = $testi['feature_text'];
        $valori['feature_image'] = null;
        $valori['feature_button_text'] = $this->testo($valori['tickets_button_text'] ?? null) ?? $testi['feature_button_text'];
        $valori['feature_button_url'] = $this->testo($valori['tickets_url'] ?? null);

        // Il pulsante passa allo spazio in evidenza: lasciarlo anche nell'hero
        // farebbe due pulsanti identici uno sopra l'altro.
        $valori['tickets_url'] = null;
        $valori['tickets_button_text'] = null;
        $valori['tickets_note'] = null;

        $valori['gift_card_title'] = $this->testo($giftCard['name'] ?? null) ?? $testi['gift_card_title'];
        $valori['gift_card_text'] = $testi['gift_card_text'];
        $valori['gift_card_image'] = null;
        $valori['gift_card_button_text'] = $this->testo($giftCard['cta'] ?? null) ?? $testi['gift_card_button_text'];
        $valori['gift_card_url'] = $this->testo($giftCard['cta_url'] ?? null);

        return $valori;
    }

    /**
     * @param  array<string, mixed>  $valori
     * @return array<string, mixed>
     */
    private function abbonamenti(array $valori, string $lingua): array
    {
        if (array_key_exists('benefits', $valori)) {
            return $valori;
        }

        $testi = self::TESTI[$lingua] ?? self::TESTI['it'];

        $valori['benefits_heading'] = $testi['benefits_heading'];
        $valori['benefits'] = array_map(fn (string $testo): array => ['text' => $testo], $testi['benefits']);
        $valori['phases_heading'] = $testi['phases_heading'];
        $valori['phases'] = [];

        return $valori;
    }

    /**
     * @param  callable(array<string, mixed>, string): array<string, mixed>  $trasforma
     */
    private function aggiorna(string $slug, callable $trasforma): void
    {
        foreach (DB::table('pages')->where('slug', $slug)->where('template', 'Public/Ticketing')->get(['id', 'content_data']) as $pagina) {
            $contenuti = json_decode((string) $pagina->content_data, true);

            if (! is_array($contenuti)) {
                continue;
            }

            $nuovi = $contenuti;

            foreach ($contenuti as $lingua => $valori) {
                if (is_array($valori) && ! array_is_list($valori)) {
                    $nuovi[$lingua] = $trasforma($valori, (string) $lingua);
                }
            }

            if ($nuovi === $contenuti) {
                continue;
            }

            DB::table('pages')->where('id', $pagina->id)->update([
                'content_data' => json_encode($nuovi, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            foreach (array_keys($nuovi) as $lingua) {
                Cache::forget('public:page:'.$slug.':'.$lingua);
            }
        }

        CachePublicResponse::flush();
    }

    private function testo(mixed $valore): ?string
    {
        $valore = is_string($valore) ? trim($valore) : '';

        return $valore === '' ? null : $valore;
    }
};
