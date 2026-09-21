<?php

namespace App\Filament\Resources\AuctionResource\Pages;

use App\Filament\Resources\AuctionResource;
use App\Models\Auction;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditAuction extends EditRecord
{
    use Translatable;

    protected static string $resource = AuctionResource::class;

    /**
     * Lo stato scelto nel modulo, che `fill()` non puo' scrivere.
     */
    private ?string $statoRichiesto = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * `status` non e' mass-assignable (vedi Auction::TRANSIZIONI_AMMESSE):
     * va tolto dai dati del salvataggio e applicato a parte, altrimenti il
     * campo "Stato" del pannello non produce alcun effetto e l'asta resta
     * invisibile sul sito.
     *
     * Il controllo sulla chiave non e' pleonastico: con i campi tradotti
     * questo metodo viene richiamato una volta per ogni altra lingua, con i
     * soli attributi traducibili, e un `?? null` cancellerebbe lo stato
     * raccolto al primo giro.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('status', $data)) {
            $this->statoRichiesto = $data['status'];

            unset($data['status']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $asta = $this->getRecord();
        $richiesto = $this->statoRichiesto;

        if (! $asta instanceof Auction || $richiesto === null || $richiesto === $asta->status->value) {
            return;
        }

        if ($asta->cambiaStato($richiesto)) {
            return;
        }

        Notification::make()
            ->title('Stato non modificato')
            ->body('Da "'.$asta->status->getLabel().'" non si può passare a quello scelto: il resto delle modifiche è stato salvato.')
            ->warning()
            ->send();
    }
}
