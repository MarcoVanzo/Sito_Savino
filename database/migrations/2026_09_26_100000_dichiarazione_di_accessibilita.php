<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La dichiarazione di accessibilita' del sito e dello shop (European
 * Accessibility Act, D.Lgs. 82/2022, Allegato V): testo in
 * `database/data/dichiarazione_accessibilita.php`. Lo slug non e'
 * `accessibilita`, che e' la pagina sull'accessibilita' del palazzetto.
 * Una pagina che esiste gia' non si tocca.
 */
return new class extends Migration
{
    public const SLUG = 'dichiarazione-di-accessibilita';

    public function up(): void
    {
        if (DB::table('pages')->where('slug', self::SLUG)->exists()) {
            return;
        }

        $pagina = require database_path('data/dichiarazione_accessibilita.php');

        DB::table('pages')->insert([
            'title' => json_encode($pagina['titolo'], JSON_UNESCAPED_UNICODE),
            'slug' => self::SLUG,
            'template' => 'Public/ContentPage',
            'content' => json_encode($pagina['contenuto'], JSON_UNESCAPED_UNICODE),
            'meta_description' => json_encode($pagina['descrizione'], JSON_UNESCAPED_UNICODE),
            'status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CachePublicResponse::flush();
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', self::SLUG)->whereColumn('updated_at', 'created_at')->delete();
    }
};
