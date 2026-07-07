<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Illuminate\Support\Facades\Cache;

class CreateProductCategory extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;

    protected function afterCreate(): void
    {
        Cache::forget('public:shop:it');
        Cache::forget('public:shop:en');
    }
}
