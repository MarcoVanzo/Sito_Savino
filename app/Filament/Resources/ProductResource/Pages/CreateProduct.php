<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateProduct extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        \Illuminate\Support\Facades\Cache::forget('public:shop:it');
        \Illuminate\Support\Facades\Cache::forget('public:shop:en');
    }
}
