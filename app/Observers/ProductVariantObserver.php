<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Le taglie create, modificate o cancellate dal pannello non passano dai
 * movimenti di magazzino: il riepilogo del prodotto si riallinea qui.
 */
class ProductVariantObserver
{
    public function saved(ProductVariant $variant): void
    {
        Product::riallineaLaGiacenzaDelleTaglie($variant->product_id);

        // Una taglia spostata su un altro prodotto lascia da riallineare anche quello.
        $prima = $variant->getOriginal('product_id');
        if ($variant->wasChanged('product_id') && $prima !== null) {
            Product::riallineaLaGiacenzaDelleTaglie((int) $prima);
        }
    }

    public function deleted(ProductVariant $variant): void
    {
        Product::riallineaLaGiacenzaDelleTaglie($variant->product_id);
    }
}
