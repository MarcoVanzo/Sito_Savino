<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditProductCategory extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // La cancellazione è definitiva e i prodotti resterebbero senza
            // categoria (la chiave esterna li mette a NULL) senza che nessuno
            // lo dica: con prodotti dentro non si cancella, e lo si spiega.
            Actions\DeleteAction::make()
                ->modalDescription('La categoria viene eliminata per sempre. Si può fare solo se è vuota.')
                ->before(function (Actions\DeleteAction $action): void {
                    $prodotti = $this->getRecord()->products()->count();

                    if ($prodotti === 0) {
                        return;
                    }

                    Notification::make()
                        ->title('Categoria non eliminata')
                        ->body("Contiene {$prodotti} prodotti: spostali in un'altra categoria e riprova.")
                        ->danger()
                        ->send();

                    $action->halt();
                }),
        ];
    }

    // L'invalidazione di public:shop:<locale> è a carico di
    // CacheInvalidationObserver, registrato su ProductCategory in
    // AppServiceProvider: unico punto di verità per le chiavi di cache.
}
