<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->index(['synced_to_ac', 'unsubscribed_at', 'created_at'], 'idx_newsletter_retry');
            $table->index('unsubscribed_at', 'idx_newsletter_active');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropIndex('idx_newsletter_retry');
            $table->dropIndex('idx_newsletter_active');
        });
    }
};
