<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $page = Page::where('slug', 'contatti')->first();
        if ($page) {
            // Get raw content_data translations
            $raw = $page->getAttributes()['content_data'] ?? null;
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $locale => $data) {
                        if (is_array($data)) {
                            // Rename keys
                            $renameMap = [
                                'legal_email' => 'email',
                                'legal_phone' => 'phone',
                                'legal_pec' => 'pec',
                                'legal_address' => 'address',
                            ];
                            foreach ($renameMap as $old => $new) {
                                if (isset($data[$old]) && ! isset($data[$new])) {
                                    $data[$new] = $data[$old];
                                    unset($data[$old]);
                                }
                            }
                            $decoded[$locale] = $data;
                        }
                    }
                    $page->timestamps = false;
                    $page->update([
                        'content_data' => $decoded,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $page = Page::where('slug', 'contatti')->first();
        if ($page) {
            $raw = $page->getAttributes()['content_data'] ?? null;
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $locale => $data) {
                        if (is_array($data)) {
                            // Revert renaming
                            $renameMap = [
                                'email' => 'legal_email',
                                'phone' => 'legal_phone',
                                'pec' => 'legal_pec',
                                'address' => 'legal_address',
                            ];
                            foreach ($renameMap as $old => $new) {
                                if (isset($data[$old]) && ! isset($data[$new])) {
                                    $data[$new] = $data[$old];
                                    unset($data[$old]);
                                }
                            }
                            $decoded[$locale] = $data;
                        }
                    }
                    $page->timestamps = false;
                    $page->update([
                        'content_data' => $decoded,
                    ]);
                }
            }
        }
    }
};
