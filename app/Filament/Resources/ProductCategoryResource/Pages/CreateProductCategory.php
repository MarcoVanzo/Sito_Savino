<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateProductCategory extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;

    // L'invalidazione di public:shop:<locale> è a carico di
    // CacheInvalidationObserver, registrato su ProductCategory in
    // AppServiceProvider: unico punto di verità per le chiavi di cache.
}
