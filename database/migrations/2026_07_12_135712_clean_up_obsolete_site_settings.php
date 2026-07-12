<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $obsoleteKeys = [
            'seo_default_title',
            'seo_default_description',
            'seo_og_image',
            'primary_color',
            'secondary_color',
            'site_name',
            'site_description',
            'site_logo',
            'shop.contact_email',
            'shop.stripe_enabled',
            'shop.paypal_enabled',
            'shop.bank_transfer_enabled',
            'shop.return_policy_text',
        ];

        DB::table('site_settings')->whereIn('key', $obsoleteKeys)->delete();
        SiteSetting::clearCache();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible
    }
};
