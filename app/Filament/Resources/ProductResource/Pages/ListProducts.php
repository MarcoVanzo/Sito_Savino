<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ListProducts extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Il riordino rimescola le posizioni che quei prodotti occupano gia'.
     *
     * Filament le rinumererebbe da 1: riordinando la sola categoria Home
     * (filtro attivo) le sue maglie finirebbero in cima anche all'elenco
     * "Tutti" della vetrina, davanti a ogni altro prodotto. Cosi' invece si
     * scambiano fra loro e il resto del negozio non si muove. Per questo la
     * migrazione ha dato a ogni prodotto una posizione distinta.
     *
     * Filament scrive con il query builder, senza eventi del modello: la
     * cache della vetrina si butta qui, o l'ordine nuovo arriverebbe dopo
     * dieci minuti.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order): void
    {
        if (! $this->getTable()->isReorderable() || $order === []) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $posizioni = Product::withTrashed()
                ->whereKey($order)
                ->lockForUpdate()
                ->pluck('sort_order')
                ->sort()
                ->values();

            // Due prodotti alla stessa posizione (nati dopo la migrazione,
            // entrambi a zero) non si potrebbero scambiare: si allargano.
            if ($posizioni->unique()->count() < $posizioni->count()) {
                $partenza = (int) $posizioni->first();
                $posizioni = $posizioni->keys()->map(fn (int $i) => $partenza + $i);
            }

            foreach (array_values($order) as $i => $id) {
                Product::withTrashed()->whereKey($id)->toBase()->update(['sort_order' => $posizioni[$i]]);
            }
        });

        foreach (config('app.supported_locales', ['it']) as $locale) {
            Cache::forget("public:shop:{$locale}");
        }
    }
}
