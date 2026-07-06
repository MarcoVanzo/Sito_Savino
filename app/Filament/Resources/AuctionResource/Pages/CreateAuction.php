<?php

namespace App\Filament\Resources\AuctionResource\Pages;

use App\Filament\Resources\AuctionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateAuction extends CreateRecord
{
    use Translatable;

    protected static string $resource = AuctionResource::class;

    protected function afterCreate(): void
    {
        $this->record->product->update(['type' => \App\Enums\ProductType::Auction]);
    }
}
