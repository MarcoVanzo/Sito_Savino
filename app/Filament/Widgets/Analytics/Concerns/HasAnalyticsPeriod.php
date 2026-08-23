<?php

namespace App\Filament\Widgets\Analytics\Concerns;

use Livewire\Attributes\Reactive;

/**
 * Il periodo su cui il widget legge i dati, in giorni.
 *
 * Arriva da getWidgetData() della pagina. #[Reactive] non è decorativo: senza,
 * Livewire applica i mount param una sola volta e il widget resta fermo al
 * periodo con cui è stato montato — la pagina cambia, i numeri no. È il
 * meccanismo che Filament usa nel suo InteractsWithPageFilters.
 *
 * Sta in un trait e non copiato in ogni widget perché la spiegazione va letta
 * una volta sola, e perché otto preamboli identici facevano scattare il
 * rilevatore di duplicazione su file che per il resto non si somigliano.
 */
trait HasAnalyticsPeriod
{
    #[Reactive]
    public int $days = 28;
}
