<?php

namespace App\Filament\Resources\AuctionResource\Pages;

use App\Filament\Resources\AuctionResource;
use App\Models\Auction;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateAuction extends CreateRecord
{
    use Translatable;

    protected static string $resource = AuctionResource::class;

    /**
     * Lo stato scelto nel modulo, che `fill()` non puo' scrivere.
     */
    private ?string $statoRichiesto = null;

    /**
     * Vedi EditAuction: `status` non e' mass-assignable e va applicato a
     * parte. In creazione l'unica scelta e' la bozza, ma il giro e' lo stesso
     * perche' non torni a perdersi se un giorno si aprono altri stati.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('status', $data)) {
            $this->statoRichiesto = $data['status'];

            unset($data['status']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $asta = $this->getRecord();

        // Il prodotto esce dallo shop da se': lo fa AuctionObserver.
        if ($asta instanceof Auction) {
            $asta->cambiaStato($this->statoRichiesto);
        }
    }
}
