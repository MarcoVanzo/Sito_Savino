<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migra i ruoli utente dal vecchio sistema a 3 ruoli al nuovo sistema a 5 ruoli.
     *
     * Mapping:
     *   admin  → super_admin
     *   editor → communication_manager (default; da riassegnare manualmente se necessario)
     *   user   → user (invariato)
     */
    public function up(): void
    {
        // MySQL 8.4: modifica l'enum per accettare i nuovi valori (prima di rinominare)
        // Fase 1: aggiungi i nuovi valori possibili
        DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(30) NOT NULL DEFAULT 'user'");

        // Fase 2: rinomina i vecchi valori
        DB::table('users')->where('role', 'admin')->update(['role' => 'super_admin']);
        DB::table('users')->where('role', 'editor')->update(['role' => 'communication_manager']);
        // 'user' resta 'user'
    }

    /**
     * Rollback: ripristina il vecchio sistema a 3 ruoli.
     */
    public function down(): void
    {
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'communication_manager')->update(['role' => 'editor']);
        DB::table('users')->where('role', 'shop_manager')->update(['role' => 'editor']);
        DB::table('users')->where('role', 'sport_coordinator')->update(['role' => 'editor']);
    }
};
