<?php

namespace App\Filament\Support;

use Filament\SpatieLaravelTranslatableContentDriver;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;

use function Filament\Support\generate_search_column_expression;
use function Filament\Support\generate_search_term_expression;

/**
 * Sostituisce il content driver del plugin filament/spatie-laravel-translatable
 * per la sola ricerca in tabella, che nella versione originale non funziona:
 *
 * 1. il driver del plugin passa il termine cercato così com'è, mentre la colonna
 *    viene avvolta in `lower()` (Filament forza la case-insensitivity quando
 *    l'espressione contiene `json_extract(`). Il confronto
 *    `lower(json_extract(title, "$.it")) like '%Savino%'` non può quindi mai
 *    corrispondere: qualunque ricerca con una maiuscola restituisce zero righe.
 *    Il percorso non tradotto usa `generate_search_term_expression()` per
 *    normalizzare anche il termine — qui facciamo lo stesso;
 * 2. `json_extract()` va in errore (MySQL 3141 "Invalid JSON text") appena una
 *    riga della colonna contiene testo semplice invece del JSON per lingua.
 *    Righe simili esistono ancora in archivio — le colonne translatable sono
 *    `text`, non `json`, proprio per non vincolarne il contenuto — e una sola
 *    basta a far fallire l'intera ricerca con un 500. La guardia `json_valid()`
 *    isola quelle righe e le cerca sul valore grezzo, che è la traduzione
 *    italiana implicita.
 *
 * `json_unquote()` toglie gli apici del valore JSON e scioglie le sequenze di
 * escape (`\/`, `\uXXXX`): senza, un titolo come "Serie A1 2026/2027" non è
 * cercabile per come spatie serializza le traduzioni.
 *
 * La ricerca resta limitata alla lingua attiva nello switcher, come nel driver
 * originale.
 */
class TranslatableContentDriver extends SpatieLaravelTranslatableContentDriver
{
    public function applySearchConstraintToQuery(Builder $query, string $column, string $search, string $whereClause, ?bool $isCaseInsensitivityForced = null): Builder
    {
        /** @var Connection $databaseConnection */
        $databaseConnection = $query->getConnection();

        // L'espressione contiene sempre una funzione JSON, per cui Filament
        // forzerebbe comunque `lower()` sulla colonna: fissiamo il default qui
        // così che colonna e termine restino coerenti.
        $isCaseInsensitivityForced ??= true;

        $expression = match ($databaseConnection->getDriverName()) {
            'pgsql' => "{$column}->>'{$this->activeLocale}'",
            default => "case when json_valid({$column}) then json_unquote(json_extract({$column}, \"$.{$this->activeLocale}\")) else {$column} end",
        };

        return $query->{$whereClause}(
            generate_search_column_expression($expression, $isCaseInsensitivityForced, $databaseConnection),
            'like',
            '%'.generate_search_term_expression($search, $isCaseInsensitivityForced, $databaseConnection).'%',
        );
    }
}
