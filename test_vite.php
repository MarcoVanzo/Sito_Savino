<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);
try {
    echo app(Illuminate\Foundation\Vite::class)->__invoke(['resources/js/app.js', 'resources/js/Pages/Public/Missing.vue'])->toHtml();
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage();
}
echo "\nDONE\n";
