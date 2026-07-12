<?php

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $keys = [
            'email', 'phone', 'pec', 'address', 'city', 'office_hours',
            'legal_piva', 'legal_cf', 'legal_fipav', 'legal_sdi',
            'press_email', 'social_email', 'media_email', 'youth_email'
        ];

        // Recupere le impostazioni esistenti dal database
        $settings = DB::table('site_settings')->whereIn('key', $keys)->pluck('value', 'key')->toArray();

        if (!empty($settings)) {
            $page = Page::where('slug', 'contatti')->first();
            if ($page) {
                $locales = ['it', 'en'];
                
                // Leggiamo l'attributo content_data grezzo (che in Spatie è salvato come JSON delle traduzioni)
                $rawContentData = $page->getAttributes()['content_data'] ?? null;
                $contentDataDecoded = [];
                if ($rawContentData) {
                    $contentDataDecoded = json_decode($rawContentData, true);
                    if (!is_array($contentDataDecoded)) {
                        $contentDataDecoded = [];
                    }
                }

                foreach ($locales as $locale) {
                    if (!isset($contentDataDecoded[$locale])) {
                        $contentDataDecoded[$locale] = [];
                    }
                    if (!is_array($contentDataDecoded[$locale])) {
                        $contentDataDecoded[$locale] = [];
                    }

                    foreach ($settings as $key => $val) {
                        // Verifica se il valore è già un JSON con traduzioni o un valore piatto
                        $decodedVal = json_decode($val, true);
                        if (is_array($decodedVal) && (isset($decodedVal['it']) || isset($decodedVal['en']))) {
                            $resolvedVal = $decodedVal[$locale] ?? $decodedVal['it'] ?? '';
                        } else {
                            $resolvedVal = $val;
                        }

                        // Copia il valore nel content_data per questa locale se non già presente
                        if (!isset($contentDataDecoded[$locale][$key])) {
                            $contentDataDecoded[$locale][$key] = $resolvedVal;
                        }
                    }
                }

                // Aggiorna il content_data della pagina direttamente sul DB
                DB::table('pages')->where('id', $page->id)->update([
                    'content_data' => json_encode($contentDataDecoded)
                ]);
            }

            // Elimina le chiavi obsolete dalla tabella site_settings
            DB::table('site_settings')->whereIn('key', $keys)->delete();
            SiteSetting::clearCache();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non invertibile per evitare perdita di dati migrati
    }
};
