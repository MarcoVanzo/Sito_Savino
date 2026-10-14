<?php

use App\Enums\CodiceDelRedirect;
use App\Models\Reindirizzamento;
use App\Support\RedirectGestiti;
use Illuminate\Database\Migrations\Migration;

/**
 * I 404 che Search Console segnalava il 14/10/2026 (212), tolti gli articoli
 * del vecchio sito non migrati, che restano 404 per scelta di Marco: per gli
 * altri la destinazione è in `database/data/redirect_search_console_2026_10_14.csv`.
 *
 * A guardie: un vecchio indirizzo che ha già un redirect (deciso dal
 * pannello) resta com'è. Stessa validazione del pannello e di
 * `redirect:importa`. `down()` non fa niente: i redirect li gestisce il
 * pannello da qui in poi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $file = database_path('data/redirect_search_console_2026_10_14.csv');
        $righe = array_map('str_getcsv', array_slice(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], 1));

        foreach ($righe as [$da, $verso, $stato]) {
            $codice = CodiceDelRedirect::from($stato);
            $percorso = RedirectGestiti::normalizza($da);

            if ($percorso === null
                || RedirectGestiti::errore($da, $verso, $codice) !== null
                || Reindirizzamento::query()->where('from_path', $percorso)->exists()) {
                continue;
            }

            Reindirizzamento::query()->create([
                'from_path' => $percorso,
                'to_path' => $codice === CodiceDelRedirect::Rimosso ? null : $verso,
                'status_code' => $codice,
            ]);
        }
    }

    public function down(): void {}
};
