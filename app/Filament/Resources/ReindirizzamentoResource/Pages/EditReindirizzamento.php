<?php

namespace App\Filament\Resources\ReindirizzamentoResource\Pages;

use App\Filament\Resources\ReindirizzamentoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReindirizzamento extends EditRecord
{
    protected static string $resource = ReindirizzamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ReindirizzamentoResource::getUrl('index');
    }
}
