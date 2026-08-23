<?php

namespace App\Filament\Forms;

use Filament\Forms;

/**
 * La griglia dei contatori che diverse pagine mostrano in evidenza: un numero
 * grande e la sua didascalia, ripetuti tre o quattro volte.
 *
 * Lo stesso blocco stava scritto per esteso nel template Sponsor e in quello
 * del Settore Giovanile, con le sole chiavi diverse: due gruppi di campi
 * identici riga per riga, che andavano corretti in due posti ogni volta che
 * cambiava qualcosa nel modo di presentarli.
 *
 * Le chiavi si passano per intero perche' le due pagine non seguono la stessa
 * convenzione — `stat1_value` in una, `stat_athletes` nell'altra — e allinearle
 * significherebbe migrare i `content_data` gia' scritti dalla redazione.
 */
class CampiDelleStatistiche
{
    /**
     * @param  array<int, array{valore: string, etichetta: string, nome: string, esempioValore?: string, esempioEtichetta?: string}>  $statistiche
     */
    public static function griglia(array $statistiche, int $colonne): Forms\Components\Grid
    {
        return Forms\Components\Grid::make($colonne)
            ->schema(array_map(
                static fn (array $statistica) => self::coppia($statistica),
                $statistiche,
            ));
    }

    /**
     * Il numero e la didascalia di un singolo contatore.
     *
     * @param  array{valore: string, etichetta: string, nome: string, esempioValore?: string, esempioEtichetta?: string}  $statistica
     */
    private static function coppia(array $statistica): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\TextInput::make($statistica['valore'])
                ->label('Valore '.$statistica['nome'])
                ->placeholder($statistica['esempioValore'] ?? null),
            Forms\Components\TextInput::make($statistica['etichetta'])
                ->label('Etichetta '.$statistica['nome'])
                ->placeholder($statistica['esempioEtichetta'] ?? null),
        ]);
    }
}
