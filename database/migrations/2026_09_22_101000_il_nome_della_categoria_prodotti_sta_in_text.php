<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `product_categories.name` è translatable: sta in `text`, non in `varchar`.
 *
 * È lo stesso difetto già chiuso su `products.name`: la colonna arriva
 * dall'importazione da WooCommerce come `varchar(255)`, ma il model la dichiara
 * translatable e ci tiene un JSON `{"it":…,"en":…}`. Con due lingue e un nome
 * lungo — le categorie del vivaio, i kit gara per stagione — il JSON sfonda i
 * 255 caratteri e MySQL rifiuta il salvataggio con l'errore 1406 "Data too
 * long", che in redazione si vede come un salvataggio che non riesce senza
 * spiegazione.
 *
 * La colonna non è indicizzata (nessun indice la nomina in
 * `add_missing_shop_indexes`), quindi allargarla non costa niente. `description`
 * era già `text`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->text('name')->change();
        });
    }

    /**
     * Non si annulla: restringere la colonna troncherebbe i nomi già tradotti.
     * Come per `products.name`, il `down()` è un no-op documentato.
     */
    public function down(): void
    {
        // no-op documentato
    }
};
