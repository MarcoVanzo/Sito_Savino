<?php
$service = new \App\Services\FacialRecognitionService();
// Create a fake player for testing
$player = new \App\Models\Player();
$player->id = 99999;
$player->first_name = 'Bill';
$player->last_name = 'Gates';

$imagePath = storage_path('app/public/test_face.jpg');

echo "1. Creating subject...\n";
$created = $service->createSubject($player);
echo "Created: " . ($created ? "YES" : "NO") . "\n";

echo "2. Adding face example...\n";
$added = $service->addFaceExample($player, $imagePath);
echo "Added: " . ($added ? "YES" : "NO") . "\n";

echo "3. Recognizing face...\n";
$results = $service->recognizeFaces($imagePath);
dump($results);

echo "4. Cleaning up...\n";
$service->deleteAllSubjectExamples($player);
echo "Done.\n";
