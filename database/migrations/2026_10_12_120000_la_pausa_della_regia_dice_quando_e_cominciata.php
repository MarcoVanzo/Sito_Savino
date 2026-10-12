<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo spot «tra i set» della regia fa ruotare le cornici una dopo l'altra:
 * perché tutti gli spettatori vedano la stessa nello stesso momento serve
 * l'istante da cui si conta la rotazione, non solo quello in cui finisce.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('live_sessions', 'pausa_dalle')) {
            Schema::table('live_sessions', function (Blueprint $table) {
                $table->dateTime('pausa_dalle')->nullable()->after('creativita_in_onda_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('live_sessions', 'pausa_dalle')) {
            Schema::table('live_sessions', function (Blueprint $table) {
                $table->dropColumn('pausa_dalle');
            });
        }
    }
};
