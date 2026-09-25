<?php

namespace App\Support;

use App\Enums\EtichettaProdotto;
use App\Enums\ProductType;
use App\Models\Product;
use App\Services\StoricoPrezzi;

/**
 * Quali etichette mostrare sulla foto di un prodotto.
 *
 * `products.etichette` nullo: automatiche, come prima che la redazione potesse
 * sceglierle — NUOVO nei primi 30 giorni, IN OFFERTA durante lo sconto.
 * Un elenco, anche vuoto, e' la scelta della redazione e vince.
 *
 * In entrambi i casi IN OFFERTA e ULTIMO RIMASTO passano da un controllo sui
 * dati (EtichettaProdotto::descrizioneNelPannello): la redazione le accende,
 * ma a schermo arrivano solo quando sono vere. Un prodotto esaurito tiene solo
 * NUOVO, perche' sopra c'e' gia' la scritta "Esaurito".
 */
final class EtichetteDelProdotto
{
    /** La card ha due angoli: oltre, le etichette si coprirebbero. */
    public const MASSIMO = 2;

    private const GIORNI_DA_NUOVO = 30;

    /**
     * @param  bool|null  $scontoAnnunciabile  se il chiamante ha gia' chiesto a
     *                                         StoricoPrezzi il prezzo di riferimento, per non
     *                                         rifare la query; null: lo chiede qui
     * @return list<string> i valori di EtichettaProdotto, nell'ordine scelto
     */
    public static function per(Product $prodotto, ?bool $scontoAnnunciabile = null): array
    {
        $esaurito = $prodotto->availableStock() <= 0;
        $scontoAnnunciabile ??= fn (): bool => app(StoricoPrezzi::class)->prezzoDiRiferimento($prodotto) !== null;

        $visibili = array_filter(
            self::scelte($prodotto),
            fn (EtichettaProdotto $e) => self::eVera($e, $prodotto, $esaurito, $scontoAnnunciabile),
        );

        return array_slice(
            array_values(array_map(fn (EtichettaProdotto $e) => $e->value, $visibili)),
            0,
            self::MASSIMO,
        );
    }

    /**
     * @return list<EtichettaProdotto>
     */
    private static function scelte(Product $prodotto): array
    {
        $salvate = $prodotto->etichette;

        if (! is_array($salvate)) {
            return [EtichettaProdotto::Nuovo, EtichettaProdotto::InOfferta];
        }

        return array_values(array_filter(array_map(
            fn ($valore) => is_string($valore) ? EtichettaProdotto::tryFrom($valore) : null,
            $salvate,
        )));
    }

    /**
     * @param  bool|\Closure(): bool  $scontoAnnunciabile
     */
    private static function eVera(EtichettaProdotto $etichetta, Product $prodotto, bool $esaurito, bool|\Closure $scontoAnnunciabile): bool
    {
        if ($esaurito && $etichetta !== EtichettaProdotto::Nuovo) {
            return false;
        }

        return match ($etichetta) {
            // Automatica: solo nei primi giorni. Scelta dalla redazione: finche'
            // non la toglie, come per HOT SALES.
            EtichettaProdotto::Nuovo => is_array($prodotto->etichette)
                || ($prodotto->created_at?->greaterThan(now()->subDays(self::GIORNI_DA_NUOVO)) ?? false),
            EtichettaProdotto::HotSales => true,
            // Stessa regola del prezzo barrato: uno sconto si annuncia solo
            // con un prezzo precedente praticato (art. 17-bis, StoricoPrezzi).
            EtichettaProdotto::InOfferta => is_bool($scontoAnnunciabile) ? $scontoAnnunciabile : $scontoAnnunciabile(),
            EtichettaProdotto::UltimoRimasto => self::unPezzoPerTaglia($prodotto),
        };
    }

    /**
     * "Ultimo rimasto" e' vero se ogni taglia ancora in vendita ha un pezzo
     * solo: e' il caso delle maglie gara, una per atleta e taglia. Il chiamante
     * che vuole evitare una query per card carica `withMax('variants', 'stock')`.
     */
    private static function unPezzoPerTaglia(Product $prodotto): bool
    {
        if ($prodotto->type !== ProductType::Variable) {
            return (int) $prodotto->stock === 1;
        }

        $massimo = match (true) {
            array_key_exists('variants_max_stock', $prodotto->getAttributes()) => $prodotto->getAttribute('variants_max_stock'),
            $prodotto->relationLoaded('variants') => $prodotto->variants->max('stock'),
            default => $prodotto->variants()->max('stock'),
        };

        return (int) $massimo === 1;
    }
}
