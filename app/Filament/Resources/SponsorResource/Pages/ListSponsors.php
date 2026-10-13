<?php

namespace App\Filament\Resources\SponsorResource\Pages;

use App\Filament\Actions\NomiDelleSezioniSponsorAction;
use App\Filament\Resources\SponsorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSponsors extends ListRecords
{
    protected static string $resource = SponsorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            NomiDelleSezioniSponsorAction::make(),
            Actions\CreateAction::make(),
        ];
    }
}
