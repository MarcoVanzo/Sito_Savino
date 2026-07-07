<?php

namespace App\Console\Commands;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MigrateWooCommerceProducts extends Command
{
    protected $signature = 'migrate:woocommerce-products';

    protected $description = 'Importa categorie, prodotti e varianti da WooCommerce (via REST API)';

    private string $baseUrl;

    private string $consumerKey;

    private string $consumerSecret;

    public function handle(): int
    {
        $this->baseUrl = config('services.woocommerce.url') ?? env('WOOCOMMERCE_URL', '');
        $this->consumerKey = config('services.woocommerce.consumer_key') ?? env('WOOCOMMERCE_CONSUMER_KEY', '');
        $this->consumerSecret = config('services.woocommerce.consumer_secret') ?? env('WOOCOMMERCE_CONSUMER_SECRET', '');

        if (! $this->baseUrl || ! $this->consumerKey || ! $this->consumerSecret) {
            $this->error('Credenziali WooCommerce mancanti. Controlla il file .env.');

            return self::FAILURE;
        }

        $this->info('Inizio migrazione da WooCommerce...');

        try {
            $this->migrateCategories();
            $this->migrateProducts();

            $this->info('Migrazione WooCommerce completata con successo!');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Errore durante la migrazione: '.$e->getMessage());
            Log::error('Errore migrazione WooCommerce', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return self::FAILURE;
        }
    }

    private function getWooCommerceClient()
    {
        return Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->timeout(60);
    }

    private function migrateCategories(): void
    {
        $this->info('Importazione Categorie...');

        $page = 1;
        $categoryMap = [];

        while (true) {
            $response = $this->getWooCommerceClient()->get("{$this->baseUrl}/wp-json/wc/v3/products/categories", [
                'per_page' => 100,
                'page' => $page,
            ]);

            if ($response->failed()) {
                throw new \Exception('Impossibile scaricare le categorie da WooCommerce. HTTP '.$response->status());
            }

            $categories = $response->json();

            if (empty($categories)) {
                break;
            }

            foreach ($categories as $wcCategory) {
                $category = ProductCategory::firstOrCreate(
                    ['slug' => $wcCategory['slug']],
                    [
                        'name' => ['it' => $wcCategory['name']],
                        'description' => ['it' => strip_tags($wcCategory['description'] ?? '')],
                        'sort_order' => $wcCategory['menu_order'] ?? 0,
                    ]
                );

                $categoryMap[$wcCategory['id']] = $category->id;
            }

            $page++;
        }

        $this->info('Importate/Aggiornate '.count($categoryMap).' categorie.');
    }

    private function migrateProducts(): void
    {
        $this->info('Importazione Prodotti...');

        $page = 1;
        $count = 0;

        while (true) {
            $response = $this->getWooCommerceClient()->get("{$this->baseUrl}/wp-json/wc/v3/products", [
                'per_page' => 50,
                'page' => $page,
                'status' => 'publish',
            ]);

            if ($response->failed()) {
                throw new \Exception('Impossibile scaricare i prodotti. HTTP '.$response->status());
            }

            $products = $response->json();

            if (empty($products)) {
                break;
            }

            foreach ($products as $wcProduct) {
                $this->importProduct($wcProduct);
                $count++;
            }

            $page++;
        }

        $this->info("Importati {$count} prodotti base.");
    }

    private function importProduct(array $wcProduct): void
    {
        $categoryId = null;
        if (! empty($wcProduct['categories'])) {
            $catSlug = $wcProduct['categories'][0]['slug'] ?? null;
            if ($catSlug) {
                $categoryId = ProductCategory::where('slug', $catSlug)->value('id');
            }
        }

        $type = match ($wcProduct['type']) {
            'variable' => ProductType::Configurable,
            'simple' => ProductType::Simple,
            default => ProductType::Simple,
        };

        $product = Product::updateOrCreate(
            ['slug' => $wcProduct['slug']],
            [
                'product_category_id' => $categoryId,
                'name' => ['it' => $wcProduct['name']],
                'description' => ['it' => $wcProduct['description'] ?? ''],
                'short_description' => ['it' => $wcProduct['short_description'] ?? ''],
                'price' => floatval($wcProduct['regular_price'] ?: $wcProduct['price'] ?: 0),
                'sale_price' => $wcProduct['sale_price'] ? floatval($wcProduct['sale_price']) : null,
                'stock' => intval($wcProduct['stock_quantity'] ?? 0),
                'sku' => $wcProduct['sku'] ?: null,
                'is_active' => $wcProduct['status'] === 'publish',
                'type' => $type,
                'weight' => floatval($wcProduct['weight'] ?? 0),
            ]
        );

        $this->importImages($product, $wcProduct['images'] ?? []);

        if ($wcProduct['type'] === 'variable') {
            $this->importVariations($product, $wcProduct['id']);
        }
    }

    private function importVariations(Product $product, int $wcProductId): void
    {
        $page = 1;
        while (true) {
            $response = $this->getWooCommerceClient()->get("{$this->baseUrl}/wp-json/wc/v3/products/{$wcProductId}/variations", [
                'per_page' => 100,
                'page' => $page,
            ]);

            if ($response->failed()) {
                $this->warn("Impossibile scaricare varianti per il prodotto {$wcProductId}");
                break;
            }

            $variations = $response->json();

            if (empty($variations)) {
                break;
            }

            foreach ($variations as $wcVar) {
                $size = null;
                $color = null;

                foreach ($wcVar['attributes'] as $attr) {
                    $attrName = strtolower($attr['name']);
                    if (str_contains($attrName, 'taglia') || str_contains($attrName, 'size')) {
                        $size = $attr['option'];
                    }
                    if (str_contains($attrName, 'colore') || str_contains($attrName, 'color')) {
                        $color = $attr['option'];
                    }
                }

                if (! $size && ! $color && ! empty($wcVar['attributes'])) {
                    $size = $wcVar['attributes'][0]['option'] ?? null;
                }

                $variantPrice = floatval($wcVar['price'] ?: 0);
                $basePrice = $product->price;
                $priceModifier = $variantPrice - $basePrice;

                $sku = $wcVar['sku'] ?: substr($product->slug.'-'.Str::slug($size.'-'.$color), 0, 255);

                ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'sku' => $sku,
                    ],
                    [
                        'size' => $size,
                        'color' => $color,
                        'price_modifier' => $priceModifier,
                        'stock' => intval($wcVar['stock_quantity'] ?? 0),
                    ]
                );
            }

            $page++;
        }
    }

    private function importImages(Product $product, array $images): void
    {
        if (empty($images)) {
            return;
        }

        if ($product->getMedia('images')->count() > 0) {
            return;
        }

        foreach ($images as $img) {
            try {
                $product->addMediaFromUrl($img['src'])
                    ->usingName($img['name'] ?: $product->name)
                    ->toMediaCollection('images');
            } catch (Throwable $e) {
                $this->warn("Errore download immagine per prodotto {$product->id}: ".$e->getMessage());
            }
        }
    }
}
