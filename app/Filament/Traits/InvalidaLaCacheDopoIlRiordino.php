<?php

namespace App\Filament\Traits;

/**
 * Il riordino di Filament scrive `sort_order` con un solo UPDATE sul query
 * builder: nessun evento del model parte, quindi né `CacheInvalidationObserver`
 * né i `static::saved()` dei model buttano la cache. La redazione trascinava le
 * righe e il sito restava com'era fino alla scadenza (mezz'ora per
 * l'organigramma). Qui si rilancia a mano `saved` su una delle righe spostate:
 * le chiavi da buttare dipendono dal model, non dalla riga.
 */
trait InvalidaLaCacheDopoIlRiordino
{
    public function reorderTable(array $order): void
    {
        parent::reorderTable($order);

        $model = $this->getTable()->getModel();
        $record = $model::query()->find(reset($order));

        if ($record !== null) {
            event('eloquent.saved: '.$model, $record);
        }
    }
}
