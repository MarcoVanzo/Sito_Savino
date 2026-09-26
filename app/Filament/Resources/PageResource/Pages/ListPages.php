<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Support\CondizioniDiVendita;
use App\Support\InformativeDaDocumento;
use App\Support\PagineLegaliDelloShop;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Illuminate\Database\Eloquent\Builder;

class ListPages extends ListRecords
{
    use Translatable;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Le voci legali del footer sono pagine, ma in un elenco di quasi quaranta
     * righe ordinate per creazione finivano in quarta pagina: la redazione
     * non le trovava e le credeva gestite altrove.
     */
    public function getTabs(): array
    {
        return [
            'tutte' => Tab::make('Tutte'),
            'legali' => Tab::make('Legali (footer)')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('slug', self::slugLegali())),
        ];
    }

    /**
     * Le pagine che il footer linka in fondo a ogni pagina del sito, piu' il
     * regolamento delle aste, che la pagina delle aste linka da se'.
     *
     * Gli slug sono scritti a mano anche in `SiteFooter.vue`: le due liste le
     * confronta `tests/Unit/SlugLegaliDelFooterTest.php`.
     *
     * @return list<string>
     */
    public static function slugLegali(): array
    {
        return [
            'privacy-policy',
            'cookie-policy',
            CondizioniDiVendita::SLUG_CONDIZIONI,
            CondizioniDiVendita::SLUG_RECESSO,
            ...array_keys(PagineLegaliDelloShop::tutte()),
            'dichiarazione-di-accessibilita',
            ...array_keys(InformativeDaDocumento::tutte()),
        ];
    }
}
