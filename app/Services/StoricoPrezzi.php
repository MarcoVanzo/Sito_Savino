<?php

namespace App\Services;

use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lo storico dei prezzi praticati, e il prezzo da barrare quando c'è uno
 * sconto (art. 17-bis del Codice del consumo, direttiva Omnibus).
 *
 * La regola: annunciando una riduzione di prezzo, il prezzo precedente che si
 * mostra è il più basso applicato nei 30 giorni prima della riduzione. Fino al
 * 25 settembre 2026 il sito barrava `products.price`, il listino, e non teneva
 * traccia di niente: non c'era modo di sapere quale fosse quel prezzo.
 *
 * Tre scelte:
 *
 * - si registra il **prezzo effettivo** (quello che il carrello fa pagare,
 *   `Product::effectivePrice()`), non le colonne: uno sconto programmato cambia
 *   il prezzo senza che nessuno salvi il prodotto, ed è per questo che oltre
 *   all'observer c'è `prezzi:registra`, ogni ora;
 * - ogni riga dice se quel prezzo era uno sconto (`in_sconto`), e la riduzione
 *   comincia dove comincia la serie **ininterrotta** di righe in sconto: una
 *   riduzione progressiva (20 → 15 → 10) ha come riferimento il prezzo prima
 *   della prima riduzione (art. 17-bis c. 4), non quello prima dell'ultima.
 *   Confrontare con il listino di oggi non basterebbe: un listino alzato dopo
 *   farebbe sembrare uno sconto il vecchio prezzo pieno;
 * - senza storico prima della riduzione **non si barra niente**: un prezzo
 *   mai praticato non è un prezzo precedente. Il prodotto si vende al prezzo
 *   scontato, senza l'annuncio.
 */
class StoricoPrezzi
{
    public const GIORNI = 30;

    /**
     * Apre una riga nuova se il prezzo effettivo è cambiato rispetto a quella
     * aperta, e chiude la precedente. Chiamarla due volte di seguito non
     * produce niente.
     */
    public function registra(Product $prodotto, ?CarbonInterface $quando = null): void
    {
        $quando ??= now();
        $prezzo = round($prodotto->effectivePrice(), 2);
        $inSconto = $prezzo < round((float) $prodotto->price, 2);

        $aperta = DB::table('storico_prezzi')
            ->where('product_id', $prodotto->id)
            ->whereNull('al')
            ->orderByDesc('dal')
            ->first();

        if ($aperta && round((float) $aperta->prezzo, 2) === $prezzo && (bool) $aperta->in_sconto === $inSconto) {
            return;
        }

        DB::transaction(function () use ($aperta, $prodotto, $prezzo, $inSconto, $quando) {
            if ($aperta) {
                DB::table('storico_prezzi')->where('id', $aperta->id)->update(['al' => $quando]);
            }

            DB::table('storico_prezzi')->insert([
                'product_id' => $prodotto->id,
                'prezzo' => $prezzo,
                'in_sconto' => $inSconto,
                'dal' => $quando,
                'al' => null,
            ]);
        });
    }

    /**
     * Il prezzo da barrare accanto allo sconto, o null quando non si può
     * annunciare una riduzione: prodotto non in sconto, sconto che non scende
     * sotto il riferimento, oppure nessun prezzo praticato prima.
     */
    public function prezzoDiRiferimento(Product $prodotto): ?float
    {
        if (! $prodotto->isOnSale()) {
            return null;
        }

        $righe = DB::table('storico_prezzi')
            ->where('product_id', $prodotto->id)
            ->orderByDesc('dal')
            ->get();

        $inizio = null;

        // Indietro nel tempo finché le righe sono sconti: dove la serie si
        // interrompe comincia la riduzione.
        foreach ($righe as $riga) {
            if (! $riga->in_sconto) {
                break;
            }

            $inizio = $riga->dal;
        }

        if ($inizio === null) {
            return null;
        }

        $inizio = Carbon::parse($inizio);
        $finestra = $inizio->copy()->subDays(self::GIORNI);

        $minimo = $righe
            ->filter(fn ($riga) => Carbon::parse($riga->dal)->lt($inizio)
                && ($riga->al === null || Carbon::parse($riga->al)->gt($finestra)))
            ->min(fn ($riga) => (float) $riga->prezzo);

        if ($minimo === null || round((float) $minimo, 2) <= round($prodotto->effectivePrice(), 2)) {
            return null;
        }

        return round((float) $minimo, 2);
    }
}
