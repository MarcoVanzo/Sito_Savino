<?php

namespace App\Filament\Resources\LiveCreativeResource\Pages;

use App\Filament\Resources\LiveCreativeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListLiveCreatives extends ListRecords
{
    use Translatable;

    protected static string $resource = LiveCreativeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('modelloCornice')
                ->label('Modello della cornice')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(asset(LiveCreativeResource::MODELLO_CORNICE))
                ->extraAttributes(['download' => 'modello-cornice-1920x1080.png']),
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
