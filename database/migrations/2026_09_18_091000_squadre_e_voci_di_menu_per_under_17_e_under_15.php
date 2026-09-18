<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Under 17 e Under 15 hanno una voce di menu e una pagina ciascuna, come la
 * B1 / U19: prima erano un'unica voce "Serie U17 & U15" che portava alla
 * pagina "in costruzione", e in redazione non c'era nessuna squadra a cui
 * legare le atlete.
 *
 * Le due squadre si creano solo se non esistono gia': la chiave e'
 * `teams.category` (U17, U15), la stessa con cui le riconoscono il pannello
 * delle Atlete Youth e le pagine pubbliche. `is_internal` va acceso alla
 * nascita, altrimenti la sincronizzazione con la Lega le duplicherebbe al
 * primo giro (vedi CLAUDE.md §12).
 */
return new class extends Migration
{
    private const SQUADRE = [
        ['categoria' => 'U17', 'nome' => 'Serie C / Under 17', 'slug' => 'serie-c-u17'],
        ['categoria' => 'U15', 'nome' => 'Seconda Divisione / Under 15', 'slug' => 'seconda-divisione-u15'],
    ];

    public function up(): void
    {
        foreach (self::SQUADRE as $squadra) {
            $esiste = DB::table('teams')
                ->where('is_internal', true)
                ->where(fn ($query) => $query->where('category', $squadra['categoria'])->orWhere('slug', $squadra['slug']))
                ->exists();

            if ($esiste) {
                continue;
            }

            DB::table('teams')->insert([
                'name' => $squadra['nome'],
                'slug' => $squadra['slug'],
                'category' => $squadra['categoria'],
                'is_internal' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Con e senza barra finale: il seeder la mette, ma una voce
        // ritoccata dal pannello puo' averla persa, e senza questa riga la
        // migrazione passerebbe senza fare niente lasciando la vecchia voce.
        $vecchia = DB::table('menu_items')
            ->whereIn('url', ['/youth/u17-u15/', '/youth/u17-u15'])
            ->first(['id', 'parent_id', 'location', 'sort_order']);

        if (! $vecchia) {
            return;
        }

        // Le voci che stavano sotto scalano di uno: la seconda squadra si
        // infila fra la U17 e il Settore Giovanile.
        DB::table('menu_items')
            ->where('parent_id', $vecchia->parent_id)
            ->where('sort_order', '>', $vecchia->sort_order)
            ->increment('sort_order');

        DB::table('menu_items')->where('id', $vecchia->id)->update([
            'label' => json_encode(['it' => 'Serie C / Under 17', 'en' => 'Serie C / Under 17'], JSON_UNESCAPED_UNICODE),
            'description' => json_encode(['it' => 'Roster e Staff', 'en' => 'Roster and staff'], JSON_UNESCAPED_UNICODE),
            'url' => '/youth/u17/',
            'updated_at' => now(),
        ]);

        if (! DB::table('menu_items')->whereIn('url', ['/youth/u15/', '/youth/u15'])->exists()) {
            DB::table('menu_items')->insert([
                'label' => json_encode(['it' => 'Seconda Divisione / Under 15', 'en' => 'Second Division / Under 15'], JSON_UNESCAPED_UNICODE),
                'description' => json_encode(['it' => 'Roster e Staff', 'en' => 'Roster and staff'], JSON_UNESCAPED_UNICODE),
                'url' => '/youth/u15/',
                'parent_id' => $vecchia->parent_id,
                'location' => $vecchia->location,
                'sort_order' => $vecchia->sort_order + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->svuotaLeCache();
    }

    /**
     * Le squadre restano: possono gia' avere atlete legate. Torna indietro solo
     * il menu, all'unica voce di prima.
     */
    public function down(): void
    {
        DB::table('menu_items')->whereIn('url', ['/youth/u15/', '/youth/u15'])->delete();

        DB::table('menu_items')->whereIn('url', ['/youth/u17/', '/youth/u17'])->update([
            'label' => json_encode(['it' => 'Serie U17 & U15', 'en' => 'U17 & U15'], JSON_UNESCAPED_UNICODE),
            'url' => '/youth/u17-u15/',
            'updated_at' => now(),
        ]);

        $this->svuotaLeCache();
    }

    private function svuotaLeCache(): void
    {
        foreach (config('app.supported_locales', ['it']) as $lingua) {
            Cache::forget('menu_items_main_'.$lingua);
            Cache::forget('menu_items_footer_'.$lingua);
            Cache::forget('public:stagione:u17:'.$lingua);
            Cache::forget('public:stagione:u15:'.$lingua);
        }

        CachePublicResponse::flush();
    }
};
