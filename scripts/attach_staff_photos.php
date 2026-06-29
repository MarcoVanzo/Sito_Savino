<?php

/**
 * Script to download staff photos from the old WP site and attach them
 * to staff members in the local database using Spatie MediaLibrary.
 *
 * Usage: php artisan tinker scripts/attach_staff_photos.php
 */

use App\Enums\StaffType;
use App\Models\StaffMember;

// Map of last_name => photo URL from the old WP media library
$photoMap = [
    // Staff Tecnico
    'Cozzi' => 'https://savinodelbenevolley.it/wp-content/uploads/2025/05/Mattia_Cozzi-2.jpg',
    'Sesia' => 'https://savinodelbenevolley.it/wp-content/uploads/2026/01/MARCO-SESIA-MRZ_5773.png',

    // Staff Medico
    'Corti' => 'https://savinodelbenevolley.it/wp-content/uploads/2025/05/Gioele-Corti.jpg',
];

// Staff members without individual photos on the WP site:
$notFound = [
    'Gaspari' => 'Marco Gaspari - Primo Allenatore',
    'Kántor' => 'Sándor Kántor - Vice Allenatore',
    'Maurilli' => 'Simone Maurilli - Scoutman',
    'Panzeri' => 'Andrea Panzeri - Sparring Partner',
    'Cavalli' => 'Eligio Cavalli - Medico',
    'Fabbri' => 'Monica Fabbri - Medico',
    'Cencini' => 'Sebastiano Cencini - Responsabile Fisioterapia',
    'Gori' => 'Matteo Gori - Osteopata',
    'Petri' => 'Christian Petri - Nutrizionista',
    'Simone' => 'Alessandra Simone - Assistente Nutrizionista',
];

$tempDir = storage_path('app/temp_staff_photos');
if (! is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$attached = 0;
$skipped = 0;
$errors = 0;

foreach ($photoMap as $lastName => $url) {
    $staff = StaffMember::where('last_name', $lastName)
        ->whereIn('type', [StaffType::Tecnico, StaffType::Medico])
        ->first();

    if (! $staff) {
        echo "⚠️  Staff member '{$lastName}' not found in database\n";
        $errors++;

        continue;
    }

    // Check if already has media
    if ($staff->getFirstMedia('staff')) {
        echo "⏭️  {$staff->first_name} {$staff->last_name} already has a photo, skipping\n";
        $skipped++;

        continue;
    }

    // Download the image
    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    $filename = str_replace(' ', '-', $staff->first_name).'-'.str_replace(' ', '-', $staff->last_name).'.'.$ext;
    $tempPath = $tempDir.'/'.$filename;

    echo "📥 Downloading photo for {$staff->first_name} {$staff->last_name}...\n";
    $imageData = @file_get_contents($url);

    if ($imageData === false) {
        echo "❌ Failed to download: {$url}\n";
        $errors++;

        continue;
    }

    file_put_contents($tempPath, $imageData);

    // Attach using Spatie MediaLibrary
    try {
        $staff->addMedia($tempPath)
            ->usingFileName($filename)
            ->toMediaCollection('staff');

        echo "✅ Attached photo for {$staff->first_name} {$staff->last_name}\n";
        $attached++;
    } catch (Exception $e) {
        echo "❌ Error attaching photo for {$staff->first_name} {$staff->last_name}: ".$e->getMessage()."\n";
        $errors++;
    }
}

echo "\n".str_repeat('=', 60)."\n";
echo "📊 Results:\n";
echo "  ✅ Attached: {$attached}\n";
echo "  ⏭️  Skipped (already had photo): {$skipped}\n";
echo "  ❌ Errors: {$errors}\n";
echo "\n";
echo "⚠️  Staff members WITHOUT photos available on the WP site:\n";
foreach ($notFound as $lastName => $description) {
    echo "  • {$description}\n";
}
echo "\nThese photos will need to be uploaded manually via the CMS.\n";

// Cleanup temp directory
@rmdir($tempDir);
