<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private $tables = [
        'pages' => ['title', 'content', 'excerpt', 'content_data', 'meta_description'],
        'posts' => ['title', 'content', 'excerpt', 'meta_description'],
        'hero_slides' => ['title', 'subtitle', 'cta_text'],
        'staff_members' => ['role', 'biography'],
        'players' => ['role', 'biography'],
        'sponsors' => ['description'],
        'products' => ['name', 'description', 'short_description'],
        'product_categories' => ['name', 'description'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName => $columns) {
            if (!Schema::hasTable($tableName)) continue;

            $records = DB::table($tableName)->get();
            foreach ($records as $record) {
                $updateData = [];
                foreach ($columns as $column) {
                    if (!property_exists($record, $column)) continue;
                    $value = $record->$column;
                    if ($value !== null && $value !== '') {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded) && (isset($decoded['it']) || isset($decoded['en']))) {
                            continue;
                        }
                        
                        if (is_array($decoded)) {
                            $updateData[$column] = json_encode(['it' => $decoded], JSON_UNESCAPED_UNICODE);
                        } else {
                            $updateData[$column] = json_encode(['it' => $value], JSON_UNESCAPED_UNICODE);
                        }
                    } else {
                        $updateData[$column] = null;
                    }
                }
                if (!empty($updateData)) {
                    DB::table($tableName)->where('id', $record->id)->update($updateData);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName => $columns) {
            if (!Schema::hasTable($tableName)) continue;

            $records = DB::table($tableName)->get();
            foreach ($records as $record) {
                $updateData = [];
                foreach ($columns as $column) {
                    if (!property_exists($record, $column)) continue;
                    $value = $record->$column;
                    if ($value !== null && $value !== '') {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded) && isset($decoded['it'])) {
                            $itValue = $decoded['it'];
                            if (is_array($itValue)) {
                                $updateData[$column] = json_encode($itValue, JSON_UNESCAPED_UNICODE);
                            } else {
                                $updateData[$column] = $itValue;
                            }
                        }
                    }
                }
                if (!empty($updateData)) {
                    DB::table($tableName)->where('id', $record->id)->update($updateData);
                }
            }
        }
    }
};
