<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditProduct extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ProductResource::etichetteDalModulo($data);
    }

    protected function beforeSave(): void
    {
        ProductResource::verificaStatoDellArticolo($this);
    }

    protected function afterSave(): void
    {
        $prodotto = $this->getRecord();

        if ($prodotto instanceof Product) {
            $prodotto->togliLaPrincipaleDalleAltre();
        }
    }

    // L'invalidazione della cache shop è a carico di CacheInvalidationObserver,
    // che osserva Product: farla anche qui creava una seconda sorgente di verità
    // sulle chiavi, già divergente rispetto ai suffissi di lingua.
}
