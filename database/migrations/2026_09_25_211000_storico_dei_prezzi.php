<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo storico dei prezzi praticati, per barrare accanto a uno sconto il prezzo
 * più basso dei 30 giorni precedenti (art. 17-bis del Codice del consumo). La
 * logica sta in `App\Services\StoricoPrezzi`.
 *
 * Il punto di partenza è l'unica cosa che si sa: il prezzo di oggi. Per i
 * prodotti in sconto il sito barrava il listino senza sapere se fosse stato
 * davvero praticato, e l'archivio non lo dice. Quello che dice è poco ma
 * verificabile: l'export di WooCommerce del 4 luglio 2026 non aveva sconti su
 * nessuno dei prodotti oggi scontati, quindi al listino sono stati venduti
 * almeno dall'import (`created_at`) fino all'ultima modifica (`updated_at`),
 * che è quando lo sconto è arrivato. Per questi si apre una riga al listino
 * fra quelle due date e una allo sconto da lì.
 *
 * Un prodotto nato in sconto — creato e scontato lo stesso giorno, come le
 * maglie gara 2025/2026 del 25 settembre — non ha mai avuto un prezzo pieno
 * praticato: riceve la sola riga dello sconto, e il prezzo barrato non
 * compare. È quello che la norma chiede, non una svista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storico_prezzi')) {
            Schema::create('storico_prezzi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->decimal('prezzo', 10, 2);
                $table->boolean('in_sconto')->default(false);
                $table->timestamp('dal');
                $table->timestamp('al')->nullable();

                $table->index(['product_id', 'dal']);
            });
        }

        if (DB::table('storico_prezzi')->exists()) {
            return;
        }

        // Il popolamento sta in una transazione: la guardia qui sopra salta
        // tutto appena trova una riga, quindi un giro interrotto a meta'
        // lascerebbe per sempre lo storico dei soli primi prodotti.
        DB::transaction(fn () => $this->popola());
    }

    private function popola(): void
    {
        $adesso = now();

        DB::table('products')->whereNull('deleted_at')->orderBy('id')->each(function ($prodotto) use ($adesso) {
            $listino = round((float) $prodotto->price, 2);
            $scontato = $prodotto->sale_price !== null ? round((float) $prodotto->sale_price, 2) : null;
            $inCorso = $scontato !== null && $scontato > 0 && $scontato < $listino
                && ($prodotto->sale_start === null || $adesso->gte(Carbon::parse($prodotto->sale_start)))
                && ($prodotto->sale_end === null || $adesso->lte(Carbon::parse($prodotto->sale_end)));

            if (! $inCorso) {
                DB::table('storico_prezzi')->insert([
                    'product_id' => $prodotto->id, 'prezzo' => $listino, 'in_sconto' => false,
                    'dal' => $prodotto->created_at ?? $adesso, 'al' => null,
                ]);

                return;
            }

            $inizioSconto = $prodotto->sale_start ?? $prodotto->updated_at ?? $adesso;

            // Al listino solo se ci è rimasto almeno un giorno: un prodotto
            // creato e scontato nello stesso giorno è nato in sconto.
            if ($prodotto->created_at && Carbon::parse($prodotto->created_at)->addDay()->lte(Carbon::parse($inizioSconto))) {
                DB::table('storico_prezzi')->insert([
                    'product_id' => $prodotto->id, 'prezzo' => $listino, 'in_sconto' => false,
                    'dal' => $prodotto->created_at, 'al' => $inizioSconto,
                ]);
            }

            DB::table('storico_prezzi')->insert([
                'product_id' => $prodotto->id, 'prezzo' => $scontato, 'in_sconto' => true,
                'dal' => $inizioSconto, 'al' => null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storico_prezzi');
    }
};
