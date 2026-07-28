<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
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

    // L'invalidazione della cache shop è a carico di CacheInvalidationObserver,
    // che osserva Product: farla anche qui creava una seconda sorgente di verità
    // sulle chiavi, già divergente rispetto ai suffissi di lingua.
}
