<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traduce i titoli degli album di gallery generati automaticamente.
 *
 * `gallery:create-from-posts` crea un album al mese chiamandolo
 * "Giugno 2026 — News". Sono una sessantina e non ha senso elencarli a mano
 * nella mappa delle traduzioni: il titolo è composto da un nome di mese e da
 * un anno, quindi la traduzione si ricava dal pattern.
 *
 * Gli album creati o rinominati in redazione non seguono questo schema e
 * restano intatti: la sostituzione si applica solo dove il titolo italiano
 * corrisponde esattamente a "<Mese> <anno> — News".
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const MONTHS = [
        'Gennaio' => 'January',
        'Febbraio' => 'February',
        'Marzo' => 'March',
        'Aprile' => 'April',
        'Maggio' => 'May',
        'Giugno' => 'June',
        'Luglio' => 'July',
        'Agosto' => 'August',
        'Settembre' => 'September',
        'Ottobre' => 'October',
        'Novembre' => 'November',
        'Dicembre' => 'December',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('gallery_events')) {
            return;
        }

        $pattern = '/^('.implode('|', array_keys(self::MONTHS)).') (\d{4}) — News$/u';

        DB::table('gallery_events')->orderBy('id')->chunkById(200, function ($rows) use ($pattern) {
            foreach ($rows as $row) {
                $decoded = json_decode((string) ($row->title ?? ''), true);

                if (! is_array($decoded) || ! isset($decoded['it'])) {
                    continue;
                }

                // Una traduzione inglese già scritta non si tocca mai.
                if (($decoded['en'] ?? '') !== '' && $decoded['en'] !== $decoded['it']) {
                    continue;
                }

                if (! preg_match($pattern, $decoded['it'], $matches)) {
                    continue;
                }

                $decoded['en'] = self::MONTHS[$matches[1]].' '.$matches[2].' — News';

                DB::table('gallery_events')
                    ->where('id', $row->id)
                    ->update(['title' => json_encode($decoded, JSON_UNESCAPED_UNICODE)]);
            }
        });
    }

    /**
     * Non reversibile: rimuovere la traduzione inglese cancellerebbe anche
     * quelle eventualmente corrette a mano dopo questa migrazione.
     */
    public function down(): void {}
};
