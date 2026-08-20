<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La pagina Storia mostrava le tappe da un elenco cablato nel componente Vue:
 * online si vedevano, nel pannello non esistevano. Il fallback è stato tolto,
 * quindi le tappe devono stare in tabella — dove la redazione può modificarle.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('pages')->select('id', 'content_data')->where('slug', 'storia')->first();

        if (! $row) {
            return;
        }

        $timeline = require database_path('data/storia_timeline.php');
        $data = json_decode((string) $row->content_data, true);
        $data = is_array($data) ? $data : [];

        foreach (config('app.supported_locales', ['it', 'en']) as $locale) {
            if (! empty($data[$locale]['timeline'])) {
                continue;
            }

            $data[$locale]['timeline'] = $timeline[$locale] ?? $timeline['it'];
        }

        DB::table('pages')->where('id', $row->id)->update([
            'content_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function down(): void
    {
        // no-op: cancellare le tappe lascerebbe la pagina senza contenuto.
    }
};
