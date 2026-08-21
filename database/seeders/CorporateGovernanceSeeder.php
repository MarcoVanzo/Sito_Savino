<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * La colonna Corporate Governance del footer.
 *
 * Gira a ogni avvio del container (`start.sh`), quindi non si limita a creare:
 * riporta la colonna nella forma giusta. La versione precedente si fermava se
 * trovava il titolo con indirizzo `#`, e appena quell'indirizzo è stato
 * corretto ha ricreato l'intera colonna una seconda volta, con dentro i link a
 * pagine che non esistono. Un seeder che gira sempre deve convergere, non
 * decidere in base a un dettaglio che qualcun altro può cambiare.
 *
 * I quattro documenti non sono pagine: sono i PDF caricati in Impostazioni →
 * Documenti Legali, e `documento:<chiave>` viene risolto da MenuItem::href()
 * sul file del momento.
 */
class CorporateGovernanceSeeder extends Seeder
{
    private const TITOLO = 'Corporate Governance';

    /** L'ordine è quello in cui compaiono nel footer. */
    private const VOCI = [
        ['it' => 'Safeguarding', 'en' => 'Safeguarding', 'url' => '/societa/safeguarding'],
        ['it' => 'Protocollo Razzismo', 'en' => 'Anti-Racism Protocol', 'url' => 'documento:protocollo_razzismo'],
        ['it' => 'Protocollo Bullismo', 'en' => 'Anti-Bullying Protocol', 'url' => 'documento:protocollo_bullismo'],
        ['it' => 'Codice Tutela Minori', 'en' => 'Child Protection Code', 'url' => 'documento:codice_tutela_minori'],
        ['it' => 'Modello Organizzativo', 'en' => 'Organisational Model', 'url' => 'documento:modello_organizzativo'],
    ];

    public function run(): void
    {
        $colonna = $this->colonnaUnica();

        $colonna->update(['url' => '/societa/safeguarding', 'is_active' => true]);

        foreach (self::VOCI as $posizione => $voce) {
            $esistente = $colonna->children()->get()
                ->first(fn (MenuItem $figlia) => $this->etichettaItaliana($figlia) === $voce['it']);

            if ($esistente) {
                $esistente->update(['url' => $voce['url'], 'sort_order' => $posizione]);

                continue;
            }

            MenuItem::create([
                'label' => ['it' => $voce['it'], 'en' => $voce['en']],
                'url' => $voce['url'],
                'location' => 'footer',
                'parent_id' => $colonna->id,
                'sort_order' => $posizione,
                'is_active' => true,
            ]);
        }
    }

    /**
     * La colonna, creandola se manca e togliendo i doppioni.
     *
     * Si tiene la più vecchia: è quella a cui la redazione ha eventualmente
     * messo mano.
     */
    private function colonnaUnica(): MenuItem
    {
        $colonne = MenuItem::query()
            ->where('location', 'footer')
            ->whereNull('parent_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (MenuItem $voce) => $this->etichettaItaliana($voce) === self::TITOLO)
            ->values();

        if ($colonne->isEmpty()) {
            return MenuItem::create([
                'label' => ['it' => self::TITOLO, 'en' => self::TITOLO],
                'url' => '/societa/safeguarding',
                'location' => 'footer',
                'sort_order' => 3,
                'is_active' => true,
            ]);
        }

        $doppioni = $colonne->skip(1)->pluck('id');

        if ($doppioni->isNotEmpty()) {
            DB::transaction(function () use ($doppioni) {
                MenuItem::whereIn('parent_id', $doppioni)->delete();
                MenuItem::whereIn('id', $doppioni)->delete();
            });
        }

        return $colonne->first();
    }

    private function etichettaItaliana(MenuItem $voce): string
    {
        return trim((string) ($voce->getTranslation('label', 'it', false) ?: $voce->label));
    }
}
