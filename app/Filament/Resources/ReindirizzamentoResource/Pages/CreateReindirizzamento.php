<?php

namespace App\Filament\Resources\ReindirizzamentoResource\Pages;

use App\Filament\Resources\ReindirizzamentoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReindirizzamento extends CreateRecord
{
    protected static string $resource = ReindirizzamentoResource::class;

    protected function getRedirectUrl(): string
    {
        return ReindirizzamentoResource::getUrl('index');
    }
}
