<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Vivi l'Emozione" era il claim del ticketing in due posti diversi: nella
 * tendina del menu principale e nell'intestazione delle pagine Biglietteria,
 * Campagna Abbonamenti e Ticketing. Il claim della stagione è "Believe", e va
 * usato in entrambi.
 */
return new class extends Migration
{
    private const DA = [
        "Vivi l'Emozione",
        "Vivi l'emozione",
        'Vivi l’Emozione',
        'Feel the Thrill',
        'Feel the Emotion',
    ];

    private const A = 'Believe';

    public function up(): void
    {
        $this->sostituisciNeiMenu();
        $this->sostituisciNelleIntestazioni();
    }

    public function down(): void
    {
        // Non reversibile: le varianti di partenza erano cinque e non si sa
        // quale stesse dove. Il claim corretto resta comunque "Believe".
    }

    private function sostituisciNeiMenu(): void
    {
        DB::table('menu_items')
            ->orderBy('id')
            ->each(function (object $voce) {
                $nuovo = $this->sostituito((string) ($voce->motto_title ?? ''));

                if ($nuovo !== (string) ($voce->motto_title ?? '')) {
                    DB::table('menu_items')->where('id', $voce->id)->update(['motto_title' => $nuovo]);
                }
            });
    }

    private function sostituisciNelleIntestazioni(): void
    {
        DB::table('pages')
            ->where('template', 'Public/Ticketing')
            ->orderBy('id')
            ->each(function (object $pagina) {
                $contenuti = json_decode((string) $pagina->content_data, true);

                if (! is_array($contenuti)) {
                    return;
                }

                foreach ($contenuti as $lingua => $campi) {
                    if (is_array($campi) && isset($campi['hero_label'])) {
                        $contenuti[$lingua]['hero_label'] = $this->sostituito((string) $campi['hero_label']);
                    }
                }

                DB::table('pages')
                    ->where('id', $pagina->id)
                    ->update(['content_data' => json_encode($contenuti, JSON_UNESCAPED_UNICODE)]);
            });
    }

    private function sostituito(string $valore): string
    {
        return str_replace(self::DA, self::A, $valore);
    }
};
