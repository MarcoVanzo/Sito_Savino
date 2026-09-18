<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * La pagina Talent Day non si apriva piu' in redazione: 500.
 *
 * Il campo "Societa' partner" del Talent Day e' un testo e si chiamava
 * `content_data.partners`; nelle Convenzioni lo stesso nome e' l'elenco dei
 * partner, cioe' un Repeater. Filament idrata anche i campi delle sezioni
 * nascoste, quindi aprendo il Talent Day il Repeater delle Convenzioni si
 * trovava in mano una stringa e falliva ("foreach() argument must be of type
 * array|object, string given", Repeater.php:806).
 *
 * Qui il testo passa sotto `partners_note`, che non collide con niente. Il
 * valore non si perde: si sposta.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rinomina('partners', 'partners_note');
    }

    public function down(): void
    {
        $this->rinomina('partners_note', 'partners');
    }

    private function rinomina(string $da, string $a): void
    {
        $pagine = DB::table('pages')
            ->where('template', 'Public/TalentDay')
            ->get(['id', 'slug', 'content_data']);

        foreach ($pagine as $pagina) {
            $contenuti = json_decode((string) $pagina->content_data, true);

            if (! is_array($contenuti)) {
                continue;
            }

            $nuovi = $contenuti;

            foreach ($contenuti as $lingua => $valori) {
                if (! is_array($valori) || ! array_key_exists($da, $valori)) {
                    continue;
                }

                $valore = $valori[$da];
                unset($valori[$da]);

                // Solo il testo: se qualcuno ci avesse gia' salvato un elenco,
                // spostarlo sotto un campo di testo non avrebbe senso.
                if (is_string($valore)) {
                    $valori[$a] = $valore;
                }

                $nuovi[$lingua] = $valori;
            }

            if ($nuovi === $contenuti) {
                continue;
            }

            DB::table('pages')->where('id', $pagina->id)->update([
                'content_data' => json_encode($nuovi, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            foreach (array_keys($nuovi) as $lingua) {
                Cache::forget('public:page:'.$pagina->slug.':'.$lingua);
            }
        }

        CachePublicResponse::flush();
    }
};
