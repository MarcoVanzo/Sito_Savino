<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Progetto Scuola era un testo unico, mentre nella pagina generale Progetti
 * Sociali i percorsi comparivano già divisi. Qui prende lo stesso modello
 * (`Public/Sociale`) e le quattro voci — Elementare, Media, Superiore, School
 * Cup — diventano quattro schede.
 *
 * Le schede nascono con la sola intestazione: i testi li scrive la redazione,
 * che è l'unica a sapere cosa si fa in ciascun grado di scuola. Una scheda
 * senza descrizione mostra il titolo e basta, non un testo inventato.
 */
return new class extends Migration
{
    private const PERCORSI = [
        'it' => [
            ['title' => 'Scuola Elementare', 'tag' => 'Primaria', 'icon' => '🎈', 'color' => 'savino-blue', 'description' => ''],
            ['title' => 'Scuola Media', 'tag' => 'Secondaria di primo grado', 'icon' => '🏐', 'color' => 'savino-fucsia', 'description' => ''],
            ['title' => 'Scuola Superiore', 'tag' => 'Secondaria di secondo grado', 'icon' => '🎓', 'color' => 'savino-blue', 'description' => ''],
            ['title' => 'School Cup', 'tag' => 'Torneo', 'icon' => '🏆', 'color' => 'savino-fucsia', 'description' => ''],
        ],
        'en' => [
            ['title' => 'Primary School', 'tag' => 'Primary', 'icon' => '🎈', 'color' => 'savino-blue', 'description' => ''],
            ['title' => 'Middle School', 'tag' => 'Lower secondary', 'icon' => '🏐', 'color' => 'savino-fucsia', 'description' => ''],
            ['title' => 'High School', 'tag' => 'Upper secondary', 'icon' => '🎓', 'color' => 'savino-blue', 'description' => ''],
            ['title' => 'School Cup', 'tag' => 'Tournament', 'icon' => '🏆', 'color' => 'savino-fucsia', 'description' => ''],
        ],
    ];

    public function up(): void
    {
        $pagina = DB::table('pages')->where('slug', 'progetto-scuola')->first();

        if (! $pagina) {
            return;
        }

        $contenuti = json_decode((string) $pagina->content_data, true);
        $contenuti = is_array($contenuti) ? $contenuti : [];

        foreach (self::PERCORSI as $lingua => $percorsi) {
            $campi = is_array($contenuti[$lingua] ?? null) ? $contenuti[$lingua] : [];

            // Un elenco già scritto in redazione non si tocca.
            if (! empty($campi['projects'])) {
                continue;
            }

            $campi['projects'] = $percorsi;
            $contenuti[$lingua] = $campi;
        }

        DB::table('pages')->where('id', $pagina->id)->update([
            'template' => 'Public/Sociale',
            'content_data' => json_encode($contenuti, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function down(): void
    {
        DB::table('pages')
            ->where('slug', 'progetto-scuola')
            ->where('template', 'Public/Sociale')
            ->update(['template' => 'Public/ContentPage']);
    }
};
