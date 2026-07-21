<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at')->nullable();

            // Le query leggono sempre "ultime N password di un utente".
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->timestamp('password_expiry_notified_at')->nullable()->after('password_changed_at');
        });

        // Gli utenti esistenti partono da ADESSO, non dalla data di creazione:
        // datarli all'indietro li farebbe scadere tutti all'istante del deploy,
        // bloccando fuori anche chi non ha modo di essere avvisato (le mail non
        // sono ancora attive). Da qui in poi il conteggio è reale.
        DB::table('users')->update(['password_changed_at' => now()]);

        // Seed dello storico con la password attualmente in uso, così la prima
        // rotazione non può riproporre la stessa password.
        $now = now();
        DB::table('users')->orderBy('id')->select('id', 'password')->chunk(500, function ($users) use ($now) {
            $rows = [];
            foreach ($users as $user) {
                $rows[] = [
                    'user_id' => $user->id,
                    'password' => $user->password,
                    'created_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('password_histories')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_changed_at', 'password_expiry_notified_at']);
        });
    }
};
