<?php

namespace App\Console\Commands;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Exceptions\UnreachableUrl;

class MigrateWpProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wp:migrate-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate WooCommerce products from JSON export';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $exportFile = database_path('data/woocommerce_export.json');

        if (!file_exists($exportFile)) {
            $this->error("Export file not found at $exportFile");
            return Command::FAILURE;
        }

        $this->info("Loading export file...");
        $products = json_decode(file_get_contents($exportFile), true);

        if (!$products) {
            $this->error("Invalid JSON or empty array.");
            return Command::FAILURE;
        }

        $this->info("Found " . count($products) . " products. Starting migration...");

        DB::beginTransaction();
        try {
            foreach ($products as $wpProduct) {
                $this->migrateProduct($wpProduct);
            }
            DB::commit();
            $this->info("Migrazione completata. Prodotti importati/aggiornati: " . count($products));

            $this->info("Aggiornamento fallback lingua inglese in corso...");
            \Illuminate\Support\Facades\DB::update('UPDATE products SET name = JSON_SET(name, \'$.en\', JSON_UNQUOTE(JSON_EXTRACT(name, \'$.it\'))), description = JSON_SET(description, \'$.en\', JSON_UNQUOTE(JSON_EXTRACT(description, \'$.it\'))), short_description = JSON_SET(short_description, \'$.en\', JSON_UNQUOTE(JSON_EXTRACT(short_description, \'$.it\'))) WHERE JSON_EXTRACT(name, \'$.it\') IS NOT NULL');
            \Illuminate\Support\Facades\DB::update('UPDATE product_categories SET name = JSON_SET(name, \'$.en\', JSON_UNQUOTE(JSON_EXTRACT(name, \'$.it\'))), description = JSON_SET(description, \'$.en\', JSON_UNQUOTE(JSON_EXTRACT(description, \'$.it\'))) WHERE JSON_EXTRACT(name, \'$.it\') IS NOT NULL');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function migrateProduct(array $wpProduct)
    {
        $this->line("Migrating product: {$wpProduct['name']}...");

        // 1. Handle Categories
        $categoryId = null;
        if (!empty($wpProduct['categories'])) {
            $wpCategory = $wpProduct['categories'][0]; // Take first category
            $category = ProductCategory::firstOrCreate(
                ['slug' => $wpCategory['slug']],
                ['name' => ['it' => html_entity_decode($wpCategory['name'])]] // Assuming default language IT
            );
            $categoryId = $category->id;
        }

        // 2. Parse price (woo prices are in minor units as string "2000" -> 20.00)
        $price = isset($wpProduct['prices']['price']) ? (float) $wpProduct['prices']['price'] / 100 : 0;
        $salePrice = null;
        if ($wpProduct['on_sale'] && isset($wpProduct['prices']['sale_price'])) {
            $salePrice = (float) $wpProduct['prices']['sale_price'] / 100;
        }

        $type = $wpProduct['type'] === 'variable' ? ProductType::Variable : ProductType::Simple;

        // Extract description, removing HTML entities from name
        $name = html_entity_decode($wpProduct['name']);
        
        $stock = 0;
        if ($wpProduct['stock_availability']['class'] === 'in-stock') {
             // Try to parse '56 disponibili'
             preg_match('/(\d+)/', $wpProduct['stock_availability']['text'] ?? '', $matches);
             if (!empty($matches[1])) {
                 $stock = (int) $matches[1];
             } else {
                 $stock = 10; // Default if in-stock but quantity unknown
             }
        }

        $product = Product::updateOrCreate(
            ['slug' => $wpProduct['slug']],
            [
                'product_category_id' => $categoryId,
                'name' => ['it' => $name],
                'description' => ['it' => $wpProduct['description'] ?? ''],
                'short_description' => ['it' => $wpProduct['short_description'] ?? ''],
                'price' => $price,
                'sale_price' => $salePrice,
                'stock' => $stock,
                'sku' => !empty($wpProduct['sku']) ? $wpProduct['sku'] : null,
                'is_active' => $wpProduct['is_purchasable'] ?? true,
                'type' => $type,
                'weight' => !empty($wpProduct['weight']) ? (float)$wpProduct['weight'] : null,
            ]
        );

        // 3. Handle Images
        if (!empty($wpProduct['images']) && $product->getMedia('images')->count() === 0) {
            foreach ($wpProduct['images'] as $image) {
                try {
                    $product->addMediaFromUrl($image['src'])
                        ->toMediaCollection('images');
                } catch (UnreachableUrl $e) {
                    $this->warn("    Could not download image: {$image['src']}");
                }
            }
        }

        // 4. Handle Variations
        if ($type === ProductType::Variable && !empty($wpProduct['detailed_variations'])) {
            // Determine all unique sizes
            foreach ($wpProduct['detailed_variations'] as $wpVariant) {
                // Find attributes
                $size = 'TU';
                if (!empty($wpVariant['attributes'])) {
                    foreach ($wpVariant['attributes'] as $attr) {
                        if (strtolower($attr['name']) === 'taglia' || strtolower($attr['name']) === 'size') {
                            $size = strtoupper($attr['value']);
                            break;
                        }
                    }
                }

                $varPrice = isset($wpVariant['prices']['price']) ? (float) $wpVariant['prices']['price'] / 100 : $price;
                $priceModifier = $varPrice - $price;

                $varStock = 0;
                if (($wpVariant['stock_availability']['class'] ?? '') === 'in-stock') {
                    preg_match('/(\d+)/', $wpVariant['stock_availability']['text'] ?? '', $matches);
                    if (!empty($matches[1])) {
                        $varStock = (int) $matches[1];
                    } else {
                        $varStock = 10;
                    }
                }

                ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'size' => $size,
                        // Color is skipped for now unless specified
                    ],
                    [
                        'sku' => !empty($wpVariant['sku']) ? $wpVariant['sku'] : null,
                        'price_modifier' => $priceModifier,
                        'stock' => $varStock,
                    ]
                );
            }
        }
    }
}
