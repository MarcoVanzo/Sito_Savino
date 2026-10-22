<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uno sponsor «solo diretta» porta grafiche in diretta (per esempio la cornice
 * dello shop ufficiale) ma non compare nella pagina Sponsor né nella striscia
 * della home.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->boolean('solo_diretta')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropColumn('solo_diretta');
        });
    }
};
