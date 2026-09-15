<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * La pagina Convenzioni ha un template suo.
 *
 * Era una pagina di contenuto generica: la redazione vuole inserire dal
 * pannello i partner che offrono agevolazioni agli abbonati, con il link
 * al loro sito e il riassunto dello sconto. I valori iniziali stanno in
 * database/data/page_template_defaults.php; l'elenco dei partner parte
 * vuoto. Il testo scritto nell'editor resta dov'e'.
 */
return new class extends Migration
{
    private const TEMPLATE = 'Public/Convenzioni';

    public function up(): void
    {
        $defaults = require database_path('data/page_template_defaults.php');
        $pagina = DB::table('pages')->where('slug', 'convenzioni')->first(['id', 'template', 'content_data']);

        if (! $pagina || $pagina->template === self::TEMPLATE) {
            return;
        }

        $contenuti = json_decode((string) $pagina->content_data, true);
        $contenuti = is_array($contenuti) ? $contenuti : [];

        foreach ($defaults[self::TEMPLATE] as $lingua => $valori) {
            $esistenti = is_array($contenuti[$lingua] ?? null) ? $contenuti[$lingua] : [];
            $contenuti[$lingua] = array_merge($valori, $esistenti);
        }

        DB::table('pages')->where('id', $pagina->id)->update([
            'template' => self::TEMPLATE,
            'content_data' => json_encode($contenuti, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        foreach (array_keys($contenuti) as $lingua) {
            Cache::forget('public:page:convenzioni:'.$lingua);
        }

        CachePublicResponse::flush();
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'convenzioni')->where('template', self::TEMPLATE)->update([
            'template' => 'Public/ContentPage',
            'updated_at' => now(),
        ]);
    }
};
