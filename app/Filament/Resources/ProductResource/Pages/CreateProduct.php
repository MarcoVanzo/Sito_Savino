<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateProduct extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    // L'invalidazione della cache shop è a carico di CacheInvalidationObserver,
    // che osserva Product: farla anche qui creava una seconda sorgente di verità
    // sulle chiavi, già divergente rispetto ai suffissi di lingua.
}
