<?php

namespace App\Filament\Resources\GalleryEventResource\Pages;

use App\Filament\Resources\GalleryEventResource;
use App\Models\GalleryEvent;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditGalleryEvent extends EditRecord
{
    use Translatable;

    protected static string $resource = GalleryEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('Attenzione: verranno eliminate anche tutte le foto associate a questo evento. L\'operazione è irreversibile.')
                ->before(function (GalleryEvent $record) {
                    $record->loadMissing('galleryImages');
                    foreach ($record->galleryImages as $image) {
                        $image->clearMediaCollection('gallery');
                        $image->delete();
                    }
                }),
        ];
    }
}
