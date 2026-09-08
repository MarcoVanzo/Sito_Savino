<?php

use App\Support\ContentData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le pagine salvate dal pannello avevano gli elenchi come mappe `{uuid: voce}`
 * e i file come mappe `{uuid: percorso}`: lo stato grezzo di Livewire finiva in
 * archivio al posto del modulo deidratato (vedi `PreservaContentData`). I
 * template chiedono un elenco e nascondevano la sezione: i piani abbonamento
 * (campagna "non ancora aperta"), i progetti sociali, i valori del vivaio, le
 * cartelle stampa e il bilancio di sostenibilita' erano in tabella ma non
 * online.
 *
 * Qui si riportano alla forma giusta, lingua per lingua. Idempotente: su una
 * pagina gia' corretta non cambia nulla.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('pages')->select('id', 'content_data')->get() as $pagina) {
            $contenuti = is_string($pagina->content_data) ? json_decode($pagina->content_data, true) : $pagina->content_data;

            if (! is_array($contenuti)) {
                continue;
            }

            $corretti = ContentData::normalizza($contenuti);

            if ($corretti === $contenuti) {
                continue;
            }

            DB::table('pages')
                ->where('id', $pagina->id)
                ->update(['content_data' => json_encode($corretti, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        // La forma con le chiavi UUID era un difetto, non uno stato da ripristinare.
    }
};
