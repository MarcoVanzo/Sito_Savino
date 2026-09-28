<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Due funzioni della homepage chieste dalla redazione per il go-live.
 *
 * 1. Lo spazio «Eventi»: appuntamenti fuori dal calendario delle gare
 *    (presentazioni, feste, iniziative). Tabella propria e non un elenco
 *    nelle impostazioni, perché ogni evento ha una copertina e le
 *    impostazioni non sanno tenere file.
 *
 * 2. La modalità Match Day e il suo pop-up verso la biglietteria. Le chiavi
 *    nascono qui nel gruppo `home`: `SiteSetting::set()` metterebbe una chiave
 *    nuova nel gruppo predefinito, che non arriva al frontend.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('eventi')) {
            Schema::create('eventi', function (Blueprint $table) {
                $table->id();
                // Tradotti: `text`, mai `varchar` né `json` (CLAUDE.md §9).
                $table->text('titolo');
                $table->text('descrizione')->nullable();
                $table->string('luogo')->nullable();
                $table->dateTime('inizia_il');
                $table->dateTime('finisce_il')->nullable();
                $table->string('link')->nullable();
                $table->boolean('pubblicato')->default(true);
                $table->timestamps();

                $table->index(['pubblicato', 'inizia_il']);
            });
        }

        $impostazioni = [
            'match_day_modalita' => ['auto', 'select', 'Modalità Match Day'],
            'match_day_popup_attivo' => ['1', 'boolean', 'Pop-up biglietti nel Match Day'],
            'match_day_popup_titolo' => [json_encode(['it' => 'Oggi si gioca!', 'en' => 'Match day!']), 'text', 'Titolo del pop-up'],
            'match_day_popup_testo' => [json_encode(['it' => 'Vieni a tifare con noi al palazzetto: i biglietti sono disponibili online.', 'en' => 'Come and cheer with us: tickets are available online.']), 'text', 'Testo del pop-up'],
            'match_day_popup_pulsante' => [json_encode(['it' => 'Acquista i biglietti', 'en' => 'Buy tickets']), 'text', 'Pulsante del pop-up'],
            'match_day_popup_url' => ['', 'text', 'Indirizzo del pop-up'],
        ];

        foreach ($impostazioni as $chiave => [$valore, $tipo, $etichetta]) {
            if (DB::table('site_settings')->where('key', $chiave)->exists()) {
                continue;
            }

            DB::table('site_settings')->insert([
                'key' => $chiave,
                'value' => $valore,
                'type' => $tipo,
                'group' => 'home',
                'label' => $etichetta,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        SiteSetting::clearCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('eventi');

        DB::table('site_settings')->where('key', 'like', 'match_day_%')->delete();
        SiteSetting::clearCache();
    }
};
