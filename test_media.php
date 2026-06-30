<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$slides = App\Models\HeroSlide::active()->ordered()->with('media')->get();
foreach ($slides as $slide) {
    echo "Slide {$slide->id}: " . $slide->getFirstMediaUrl('hero-slides') . "\n";
}
