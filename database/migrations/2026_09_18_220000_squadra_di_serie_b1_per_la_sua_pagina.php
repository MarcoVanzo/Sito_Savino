<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La squadra della Serie B1 / U19.
 *
 * La voce di menu e la pagina esistono da sempre, la squadra no: in archivio
 * c'erano solo la prima squadra e — da stamattina — Under 17 e Under 15, quindi
 * /stagione/b1 mostrava una rosa vuota e in redazione non c'era niente a cui
 * legare le atlete. Stesso criterio della migrazione delle Under: la chiave e'
 * `teams.category`, `is_internal` acceso alla nascita perche' la
 * sincronizzazione con la Lega non la duplichi (CLAUDE.md §12).
 *
 * Il nome e' quello della voce di menu e lo cambia la redazione quando cambia
 * il campionato.
 */
return new class extends Migration
{
    public function up(): void
    {
        $esiste = DB::table('teams')
            ->where('is_internal', true)
            ->where(fn ($query) => $query->where('category', 'B1')->orWhere('slug', 'serie-b1'))
            ->exists();

        if ($esiste) {
            return;
        }

        DB::table('teams')->insert([
            'name' => 'Serie B1 / U19',
            'slug' => 'serie-b1',
            'category' => 'B1',
            'is_internal' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * La squadra resta: puo' gia' avere atlete legate, e cancellarla le
     * lascerebbe orfane.
     */
    public function down(): void {}
};
