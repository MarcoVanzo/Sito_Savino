<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le categorie delle news si ordinano dalla redazione.
 *
 * Comparivano in ordine alfabetico, che non dice niente a chi legge: la
 * redazione voleva la stagione in corso, poi le coppe, poi le rubriche fisse.
 * Cablare quell'elenco qui sarebbe scaduto a fine stagione, quindi la
 * posizione diventa un campo che si trascina nel pannello.
 *
 * L'ordine iniziale è quello chiesto dalla redazione; a parità di posizione
 * resta l'ordine alfabetico di prima.
 */
return new class extends Migration
{
    /**
     * Slug nell'ordine in cui la redazione li vuole vedere.
     *
     * La stagione in corso non e' in elenco perche' il suo slug cambia ogni
     * anno: la si trova sotto, prendendo la piu' recente fra quelle presenti.
     */
    private const ORDINE = [
        'cev-champions-league',
        'coppa-italia',
        'sponsor',
        'societa',
        'notizie',
        'sdb-youth',
        'mondiale-per-club',
    ];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('slug');
        });

        // Posto 1 alla stagione in corso, qualunque sia il suo slug.
        $stagioneInCorso = DB::table('categories')
            ->where('slug', 'like', 'serie-a1-%')
            ->orderByDesc('slug')
            ->value('id');

        if ($stagioneInCorso !== null) {
            DB::table('categories')->where('id', $stagioneInCorso)->update(['sort_order' => 1]);
        }

        foreach (self::ORDINE as $posizione => $slug) {
            DB::table('categories')
                ->where('slug', $slug)
                ->update(['sort_order' => $posizione + 2]);
        }

        // Le annate passate vanno dopo quelle elencate, dalla più recente:
        // sono la parte che si consulta di rado.
        $stagioni = DB::table('categories')
            ->where('slug', 'like', 'serie-a1-%')
            ->where('sort_order', 0)
            ->orderByDesc('slug')
            ->pluck('id');

        foreach ($stagioni as $posizione => $id) {
            DB::table('categories')
                ->where('id', $id)
                ->update(['sort_order' => 100 + $posizione]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
