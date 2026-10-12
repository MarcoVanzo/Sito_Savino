<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diretta con sponsor, fase 1: le creatività e l'interruttore generale.
 *
 * Le impostazioni nascono qui nel gruppo `diretta`: `SiteSetting::set()`
 * metterebbe una chiave nuova nel gruppo predefinito (CLAUDE.md §14).
 * L'interruttore parte spento: finché la redazione non lo accende il sito
 * mostra la diretta come prima.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('live_creatives')) {
            Schema::create('live_creatives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sponsor_id')->constrained('sponsors')->cascadeOnDelete();
                $table->string('nome');
                $table->string('uso');
                $table->string('tipo');
                $table->unsignedSmallInteger('durata_secondi')->default(10);
                $table->string('link_url', 2048)->nullable();
                // Tradotto: `text`, mai `varchar` né `json` (CLAUDE.md §9).
                $table->text('testo_alternativo')->nullable();
                $table->boolean('attiva')->default(true);
                $table->date('valida_dal')->nullable();
                $table->date('valida_al')->nullable();
                $table->timestamps();

                $table->index(['uso', 'attiva']);
            });
        }

        $impostazioni = [
            'diretta_sponsor_abilitata' => ['0', 'boolean', 'Sponsor nella diretta'],
            'diretta_preroll_salta_dopo_secondi' => ['5', 'number', 'Secondi prima di «Salta»'],
            'diretta_ciclo_attivo' => ['1', 'boolean', 'Ciclo automatico durante la gara'],
            'diretta_ciclo_ogni_minuti' => ['10', 'number', 'Ogni quanti minuti'],
            'diretta_ciclo_durata_secondi' => ['15', 'number', 'Per quanti secondi'],
            'diretta_ciclo_ritardo_minuti' => ['5', 'number', 'Ritardo dall\'inizio della gara'],
            'diretta_pausa_durata_secondi' => ['180', 'number', 'Durata degli spot mandati dalla regia'],
        ];

        $ordine = 0;

        foreach ($impostazioni as $chiave => [$valore, $tipo, $etichetta]) {
            $ordine++;

            if (DB::table('site_settings')->where('key', $chiave)->exists()) {
                continue;
            }

            DB::table('site_settings')->insert([
                'key' => $chiave,
                'value' => $valore,
                'type' => $tipo,
                'group' => 'diretta',
                'label' => $etichetta,
                'sort_order' => $ordine,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        SiteSetting::clearCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('live_creatives');

        DB::table('site_settings')->where('group', 'diretta')->delete();
        SiteSetting::clearCache();
    }
};
