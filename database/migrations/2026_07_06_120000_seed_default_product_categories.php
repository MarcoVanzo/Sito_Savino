<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Default product categories to seed.
     * name is stored as JSON (Spatie Translatable).
     */
    private array $categories = [
        ['slug' => 'kit-gara-25-26',     'sort_order' => 1, 'name' => '{"it":"Kit Gara 25-26","en":"Match Kit 25-26"}'],
        ['slug' => 'abbigliamento',      'sort_order' => 2, 'name' => '{"it":"Abbigliamento","en":"Clothing"}'],
        ['slug' => 'accessori',          'sort_order' => 3, 'name' => '{"it":"Accessori","en":"Accessories"}'],
        ['slug' => 'kit-champion-25-26', 'sort_order' => 4, 'name' => '{"it":"Kit Champion 25-26","en":"Champion Kit 25-26"}'],
        ['slug' => 'gift-card',          'sort_order' => 5, 'name' => '{"it":"Gift Card","en":"Gift Card"}'],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->categories as $cat) {
            $exists = DB::table('product_categories')->where('slug', $cat['slug'])->exists();

            if (! $exists) {
                DB::table('product_categories')->insert([
                    'slug' => $cat['slug'],
                    'sort_order' => $cat['sort_order'],
                    'name' => $cat['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Assign orphaned products to 'Accessori' as catch-all
        $accessoriId = DB::table('product_categories')->where('slug', 'accessori')->value('id');

        if ($accessoriId) {
            DB::table('products')
                ->whereNull('product_category_id')
                ->update(['product_category_id' => $accessoriId]);
        }
    }

    public function down(): void
    {
        $slugs = array_column($this->categories, 'slug');

        // Reset products assigned to these categories back to NULL
        $categoryIds = DB::table('product_categories')
            ->whereIn('slug', $slugs)
            ->pluck('id');

        if ($categoryIds->isNotEmpty()) {
            DB::table('products')
                ->whereIn('product_category_id', $categoryIds)
                ->update(['product_category_id' => null]);
        }

        // Remove seeded categories
        DB::table('product_categories')->whereIn('slug', $slugs)->delete();
    }
};
