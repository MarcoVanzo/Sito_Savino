<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * I template Vue e i form del pannello leggono `content_data` con chiavi piatte
 * (`hero_label`, `cta_title`, `stat1_value`…), mentre i dati storici in tabella
 * usano la struttura annidata del vecchio ContentDataSeeder (`hero.badge`,
 * `become_sponsor.stats.0.value`…). Il risultato è che il sito mostra i testi di
 * fallback e la redazione non trova nel pannello nulla di quello che vede online.
 *
 * Qui i dati esistenti vengono riscritti nella struttura piatta, lingua per
 * lingua, senza mai sovrascrivere un valore già presente in forma piatta.
 */
return new class extends Migration
{
    /**
     * Mappa "percorso annidato" => "chiave piatta", per slug di pagina.
     */
    private function mappings(): array
    {
        return [
            'ticketing' => [
                'hero.badge' => 'hero_label',
                'hero.subtitle' => 'hero_subtitle',
                'purchase_info.title' => 'info_heading',
                'purchase_info.channels.0.title' => 'online_title',
                'purchase_info.channels.0.description' => 'online_description',
                'purchase_info.channels.1.title' => 'boxoffice_title',
                'purchase_info.channels.1.description' => 'boxoffice_description',
            ],
            'sponsor' => [
                'hero.badge' => 'hero_subtitle',
                'hero.subtitle' => 'hero_description',
                'become_sponsor.badge' => 'cta_subtitle',
                'become_sponsor.title' => 'cta_title',
                'become_sponsor.description' => 'cta_description',
                'become_sponsor.cta_text' => 'cta_button_text',
                'become_sponsor.stats.0.value' => 'stat1_value',
                'become_sponsor.stats.0.label' => 'stat1_label',
                'become_sponsor.stats.1.value' => 'stat2_value',
                'become_sponsor.stats.1.label' => 'stat2_label',
                'become_sponsor.stats.2.value' => 'stat3_value',
                'become_sponsor.stats.2.label' => 'stat3_label',
            ],
            'youth' => [
                'hero.badge' => 'hero_subtitle',
                'hero.subtitle' => 'hero_description',
                'intro.badge' => 'intro_label',
                'intro.title' => 'intro_title',
                'intro.paragraphs.0' => 'intro_paragraph_1',
                'intro.paragraphs.1' => 'intro_paragraph_2',
                'intro.stats.0.value' => 'stat_athletes',
                'intro.stats.0.label' => 'stat_athletes_label',
                'intro.stats.1.value' => 'stat_categories',
                'intro.stats.1.label' => 'stat_categories_label',
                'intro.stats.2.value' => 'stat_coaches',
                'intro.stats.2.label' => 'stat_coaches_label',
                'intro.stats.3.value' => 'stat_years',
                'intro.stats.3.label' => 'stat_years_label',
                'teams' => 'youth_teams',
                'talent_scouting.badge' => 'scouting_label',
                'talent_scouting.title' => 'scouting_title',
                'talent_scouting.description' => 'scouting_description',
                'talent_scouting.note' => 'scouting_info',
                'talent_scouting.cta_primary_text' => 'scouting_cta_primary',
                'talent_scouting.cta_secondary_text' => 'scouting_cta_secondary',
            ],
            'summer-camp' => [
                'hero.badge' => 'hero_label',
                'hero.subtitle' => 'hero_subtitle',
                'intro.badge' => 'camp_section_label',
                'intro.title' => 'camp_title',
                'intro.paragraphs.0' => 'camp_description_1',
                'intro.paragraphs.1' => 'camp_description_2',
                'intro.features' => 'highlights',
                'intro.edition_label' => 'camp_badge_title',
                'intro.edition_note' => 'camp_badge_subtitle',
            ],
            'comunicazione' => [
                'hero.badge' => 'hero_badge',
                'hero.subtitle' => 'hero_subtitle',
                'accreditation.badge' => 'accreditation_badge',
                'accreditation.title' => 'accreditation_title',
                'accreditation.paragraphs.0' => 'accreditation_text_1',
                'accreditation.paragraphs.1' => 'accreditation_text_2',
                'accreditation.steps.0' => 'accreditation_step_1',
                'accreditation.steps.1' => 'accreditation_step_2',
                'accreditation.steps.2' => 'accreditation_step_3',
                'press_kit.badge' => 'press_kit_badge',
                'press_kit.title' => 'press_kit_section_title',
                'press_kit.items.0.icon' => 'press_kit_1_icon',
                'press_kit.items.0.title' => 'press_kit_1_title',
                'press_kit.items.0.description' => 'press_kit_1_description',
                'press_kit.items.0.format' => 'press_kit_1_format',
                'press_kit.items.1.icon' => 'press_kit_2_icon',
                'press_kit.items.1.title' => 'press_kit_2_title',
                'press_kit.items.1.description' => 'press_kit_2_description',
                'press_kit.items.1.format' => 'press_kit_2_format',
                'press_kit.items.2.icon' => 'press_kit_3_icon',
                'press_kit.items.2.title' => 'press_kit_3_title',
                'press_kit.items.2.description' => 'press_kit_3_description',
                'press_kit.items.2.format' => 'press_kit_3_format',
                'press_kit.items.3.icon' => 'press_kit_4_icon',
                'press_kit.items.3.title' => 'press_kit_4_title',
                'press_kit.items.3.description' => 'press_kit_4_description',
                'press_kit.items.3.format' => 'press_kit_4_format',
                'contacts.badge' => 'contacts_badge',
                'contacts.title' => 'contacts_section_title',
                'contacts.items.0.role' => 'contact_1_role',
                'contacts.items.0.name' => 'contact_1_name',
                'contacts.items.0.email' => 'contact_1_email',
                'contacts.items.0.phone' => 'contact_1_phone',
                'contacts.items.1.role' => 'contact_2_role',
                'contacts.items.1.name' => 'contact_2_name',
                'contacts.items.1.email' => 'contact_2_email',
                'contacts.items.1.phone' => 'contact_2_phone',
                'contacts.items.2.role' => 'contact_3_role',
                'contacts.items.2.name' => 'contact_3_name',
                'contacts.items.2.email' => 'contact_3_email',
                'contacts.items.2.phone' => 'contact_3_phone',
            ],
            'sociale' => [
                'hero.badge' => 'hero_badge',
                'hero.subtitle' => 'hero_description',
                'mission.badge' => 'mission_badge',
                'mission.title' => 'mission_title',
                'mission.paragraphs.0' => 'mission_text_1',
                'mission.paragraphs.1' => 'mission_text_2',
                'impact.badge' => 'results_badge',
                'impact.title' => 'impact_title',
                'impact.stats' => 'impact_stats',
            ],
        ];
    }

    /**
     * Chiavi annidate da eliminare dopo la conversione, per slug.
     * `tiers` sparisce e basta: i livelli sponsor arrivano dall'enum SponsorTier.
     */
    private function obsoleteKeys(): array
    {
        return [
            'ticketing' => ['hero', 'purchase_info'],
            'sponsor' => ['hero', 'become_sponsor', 'tiers'],
            'youth' => ['hero', 'intro', 'teams', 'talent_scouting'],
            'summer-camp' => ['hero', 'intro'],
            'comunicazione' => ['hero', 'accreditation', 'press_kit', 'contacts'],
            'sociale' => ['hero', 'mission', 'impact'],
        ];
    }

    public function up(): void
    {
        $mappings = $this->mappings();
        $obsolete = $this->obsoleteKeys();

        // Le pagine di sezione condividono il template (e quindi i campi) della pagina madre.
        $sharedTemplate = [
            'abbonamenti' => 'ticketing',
            'biglietteria' => 'ticketing',
        ];

        $rows = DB::table('pages')
            ->select('id', 'slug', 'content_data')
            ->whereIn('slug', array_merge(array_keys($mappings), array_keys($sharedTemplate)))
            ->get();

        foreach ($rows as $row) {
            $slug = $sharedTemplate[$row->slug] ?? $row->slug;
            $decoded = json_decode((string) $row->content_data, true);

            if (! is_array($decoded) || $decoded === []) {
                continue;
            }

            $isPerLocale = $this->isPerLocale($decoded);
            $locales = $isPerLocale ? array_keys($decoded) : ['__single'];
            $result = [];

            foreach ($locales as $locale) {
                $data = $isPerLocale ? $decoded[$locale] : $decoded;

                if (! is_array($data)) {
                    continue;
                }

                $data = $this->flatten($data, $mappings[$slug], $obsolete[$slug] ?? []);

                if ($isPerLocale) {
                    $result[$locale] = $data;
                } else {
                    $result = $data;
                }
            }

            DB::table('pages')->where('id', $row->id)->update([
                'content_data' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    /**
     * `content_data` è translatable: al primo livello ci sono i codici lingua.
     */
    private function isPerLocale(array $decoded): bool
    {
        $locales = config('app.supported_locales', ['it', 'en']);

        foreach (array_keys($decoded) as $key) {
            if (! in_array($key, $locales, true)) {
                return false;
            }
        }

        return $decoded !== [];
    }

    private function flatten(array $data, array $map, array $obsolete): array
    {
        foreach ($map as $from => $to) {
            $value = Arr::get($data, $from);

            // Non si sovrascrive quello che la redazione ha già salvato in forma piatta.
            if ($value === null || $value === '' || (isset($data[$to]) && $data[$to] !== '' && $data[$to] !== [])) {
                continue;
            }

            $data[$to] = $value;
        }

        foreach ($obsolete as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    /**
     * Irreversibile: la struttura annidata di partenza non è ricostruibile
     * senza reintrodurre lo stesso disallineamento che questa migrazione chiude.
     */
    public function down(): void
    {
        // no-op
    }
};
