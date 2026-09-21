<?php

namespace App\Observers;

use App\Enums\ProductType;
use App\Models\Auction;

/**
 * Il prodotto di un'asta esce dallo shop, e ci rientra quando l'asta non
 * c'e' piu'.
 *
 * Il tipo `auction` tiene il pezzo fuori dalla griglia e dal carrello
 * (`Product::scopeShoppable`). Finche' il cambio si faceva solo alla creazione
 * dell'asta, cancellarla lasciava il prodotto invisibile per sempre: la sua
 * pagina rispondeva 404 e in redazione non c'era modo di capire perche'.
 *
 * Sta qui e non nel model perche' e' il ciclo di vita del prodotto, non lo
 * stato dell'asta: sul model restano le sole transizioni di stato.
 */
class AuctionObserver
{
    public function created(Auction $asta): void
    {
        $this->togliIlProdottoDalloShop($asta);
    }

    public function deleted(Auction $asta): void
    {
        $this->riportaIlProdottoNelloShop($asta);
    }

    public function restored(Auction $asta): void
    {
        $this->togliIlProdottoDalloShop($asta);
    }

    private function togliIlProdottoDalloShop(Auction $asta): void
    {
        $asta->product?->update(['type' => ProductType::Auction]);
    }

    /**
     * Al ritorno il tipo si deduce dalle varianti, perche' quello di partenza
     * non e' conservato da nessuna parte.
     */
    private function riportaIlProdottoNelloShop(Auction $asta): void
    {
        $prodotto = $asta->product;

        if (! $prodotto || $prodotto->type !== ProductType::Auction) {
            return;
        }

        $prodotto->update([
            'type' => $prodotto->variants()->exists() ? ProductType::Variable : ProductType::Simple,
        ]);
    }
}
