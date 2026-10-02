<?php

namespace App\Models\Traits;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un prodotto in piu' categorie: la principale e' la colonna
 * `product_category_id`, le altre stanno in `product_category_product`.
 */
trait StaInPiuCategorie
{
    /**
     * Le categorie in cui il prodotto compare oltre alla principale: la
     * maglia del libero sia in Home sia in Away, un capo anche in Outlet.
     *
     * La principale resta `category`: e' quella scritta sulla card e quella
     * degli articoli correlati. Per chiedere "sta in questa categoria?" si
     * passa da scopeNelleCategorie o idCategorie, mai da una delle due sole.
     *
     * @return BelongsToMany<ProductCategory, $this>
     */
    public function altreCategorie(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_product');
    }

    /**
     * La categoria principale non si ripete fra le aggiuntive: nel modulo non
     * la si puo' scegliere, ma cambiando la principale con una che era gia'
     * fra le "Anche in" ci resterebbe.
     */
    public function togliLaPrincipaleDalleAltre(): void
    {
        if ($this->product_category_id !== null) {
            $this->altreCategorie()->detach($this->product_category_id);
        }
    }

    /**
     * I prodotti che stanno in almeno una delle categorie, come principale o
     * come aggiuntiva.
     *
     * @param  array<int, int>  $idCategorie
     */
    public function scopeNelleCategorie($query, array $idCategorie)
    {
        return $query->where(fn ($q) => $q
            ->whereIn('product_category_id', $idCategorie)
            ->orWhereHas('altreCategorie', fn ($c) => $c->whereIn('product_categories.id', $idCategorie)));
    }

    /**
     * Tutte le categorie del prodotto: la principale e le aggiuntive.
     *
     * @return array<int, int>
     */
    public function idCategorie(): array
    {
        $this->loadMissing('altreCategorie:id');

        return collect([$this->product_category_id])
            ->merge($this->altreCategorie->pluck('id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
