<?php

/**
 * Script to download products from WooCommerce Store API
 */
$baseUrl = 'https://shop.savinodelbenevolley.it/wp-json/wc/store/products';
$allProducts = [];
$page = 1;

function fetchUrlWithRetry($url, $retries = 3)
{
    for ($i = 0; $i < $retries; $i++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Laravel Migration Script');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 15 seconds timeout
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (function_exists('curl_close')) {
            @curl_close($ch);
        }

        if ($httpCode === 200 && $response) {
            return $response;
        }
        echo '    Attempt '.($i + 1)." failed (HTTP $httpCode). Retrying...\n";
        sleep(1);
    }

    return false;
}

echo "Downloading WooCommerce products...\n";

do {
    echo "Fetching page $page...\n";
    $url = $baseUrl.'?per_page=10&page='.$page;
    $response = fetchUrlWithRetry($url);

    if (! $response) {
        echo "Error fetching page $page after retries.\n";
        break;
    }

    $products = json_decode($response, true);
    if (empty($products)) {
        break;
    }

    foreach ($products as &$product) {
        if ($product['type'] === 'variable' && ! empty($product['variations'])) {
            $detailedVariations = [];
            foreach ($product['variations'] as $variationBasic) {
                $varId = $variationBasic['id'];
                echo "  Fetching variation $varId for product {$product['id']}...\n";
                $varUrl = $baseUrl.'/'.$varId;

                $vResponse = fetchUrlWithRetry($varUrl);
                if ($vResponse) {
                    $detailedVariations[] = json_decode($vResponse, true);
                } else {
                    echo "    Failed to fetch variation $varId\n";
                }
            }
            $product['detailed_variations'] = $detailedVariations;
        }
        $allProducts[] = $product;
    }

    $page++;
} while (! empty($products));

$exportDir = __DIR__.'/../database/data';
if (! is_dir($exportDir)) {
    mkdir($exportDir, 0755, true);
}

$outFile = $exportDir.'/woocommerce_export.json';
file_put_contents($outFile, json_encode($allProducts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'Downloaded '.count($allProducts)." products. Saved to $outFile.\n";
