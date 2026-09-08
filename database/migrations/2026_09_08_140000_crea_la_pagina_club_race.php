<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SDB Volley Club Race: la nuova sottosezione del Ticketing chiesta dalla
 * redazione, con il regolamento (assegnazione punti e premi) nell'editor
 * della pagina e una classifica delle societa', con nome e punteggio, che si
 * compila a mano dal pannello.
 *
 * La pagina nasce in bozza: il regolamento lo scrive la redazione, e finche'
 * non e' pubblicata la voce di menu — creata qui sotto Ticketing — resta
 * nascosta da sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('pages')->where('slug', 'club-race')->exists()) {
            DB::table('pages')->insert([
                'title' => json_encode(['it' => 'SDB Volley Club Race', 'en' => 'SDB Volley Club Race'], JSON_UNESCAPED_UNICODE),
                'slug' => 'club-race',
                'template' => 'Public/ClubRace',
                'status' => 'draft',
                'content' => json_encode([
                    'it' => '<h2>Regolamento</h2><p>Come si assegnano i punti e quali sono i premi: il testo lo scrive la redazione.</p>',
                    'en' => '<h2>Rules</h2><p>How points are awarded and what the prizes are.</p>',
                ], JSON_UNESCAPED_UNICODE),
                'content_data' => json_encode([
                    'it' => [
                        'hero_label' => 'Ticketing',
                        'hero_subtitle' => 'La sfida fra le società che vengono a tifare al Pala BigMat.',
                        'standings_title' => 'Classifica',
                        'standings' => [],
                    ],
                    'en' => [
                        'hero_label' => 'Ticketing',
                        'hero_subtitle' => 'The race between the clubs that come to cheer at Pala BigMat.',
                        'standings_title' => 'Standings',
                        'standings' => [],
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'meta_description' => json_encode([
                    'it' => 'SDB Volley Club Race: regolamento, punti e classifica delle società che seguono la Savino Del Bene Volley al Pala BigMat.',
                    'en' => 'SDB Volley Club Race: rules, points and standings of the clubs following Savino Del Bene Volley at Pala BigMat.',
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $ticketing = DB::table('menu_items')
            ->where('location', 'main')
            ->whereNull('parent_id')
            ->where('url', 'like', '/ticketing%')
            ->first();

        if (! $ticketing || DB::table('menu_items')->where('url', 'like', '%/club-race%')->exists()) {
            return;
        }

        $ultimaPosizione = (int) DB::table('menu_items')->where('parent_id', $ticketing->id)->max('sort_order');

        DB::table('menu_items')->insert([
            'label' => json_encode(['it' => 'SDB Volley Club Race', 'en' => 'SDB Volley Club Race'], JSON_UNESCAPED_UNICODE),
            'url' => '/ticketing/club-race/',
            'description' => json_encode(['it' => 'Regolamento e classifica', 'en' => 'Rules and standings'], JSON_UNESCAPED_UNICODE),
            'parent_id' => $ticketing->id,
            'location' => 'main',
            'sort_order' => $ultimaPosizione + 1,
            'is_active' => true,
            'is_highlight' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', '/ticketing/club-race/')->delete();
        DB::table('pages')->where('slug', 'club-race')->where('template', 'Public/ClubRace')->delete();
    }
};
