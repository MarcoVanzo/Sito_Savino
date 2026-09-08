<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tre pagine di sezione esistevano in doppia copia, con lo stesso modello e
 * quasi lo stesso titolo:
 *
 *  - `youth` e `settore-giovanile` (entrambe "Settore Giovanile");
 *  - `ticketing` e `biglietteria`;
 *  - `sociale` e `progetti-sociali` (entrambe "Progetti Sociali").
 *
 * La prima di ogni coppia era la copia del seeder, raggiunta da `/youth`,
 * `/ticketing` e mai dal menu per `sociale`. La redazione modificava la
 * seconda e online, dalla voce di primo livello del menu, vedeva la prima:
 * "le modifiche in backend non corrispondono al frontend". Su `/ticketing`
 * c'era perfino il listino di esempio del seeder (15/99/199 EUR).
 *
 * Le rotte `/youth` e `/ticketing` ora portano alla pagina vera; qui si
 * tolgono le copie, ma solo se hanno ancora i testi del seeder: una copia
 * riscritta dalla redazione resta, e ci si pensa a mano. La voce di menu
 * "Biglietteria" punta alla pagina giusta invece che alla sezione.
 */
return new class extends Migration
{
    /**
     * Slug della copia e frase del seeder che la identifica.
     */
    private const COPIE = [
        'youth' => 'Formare Campioni Dentro e Fuori dal Campo',
        'ticketing' => 'Abbonamento Gold',
        'sociale' => 'Pallavolo gratuita per ragazzi provenienti da famiglie in difficoltà economica',
    ];

    public function up(): void
    {
        foreach (self::COPIE as $slug => $frase) {
            $pagina = Page::query()->where('slug', $slug)->first();

            if (! $pagina) {
                continue;
            }

            $contenuti = json_encode($pagina->getTranslations('content_data'), JSON_UNESCAPED_UNICODE);

            if (! is_string($contenuti) || ! str_contains($contenuti, $frase)) {
                continue;
            }

            $pagina->delete();
        }

        DB::table('menu_items')
            ->where('location', 'main')
            ->whereNotNull('parent_id')
            ->where('url', '/ticketing/')
            ->update(['url' => '/ticketing/biglietteria/']);
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->where('location', 'main')
            ->whereNotNull('parent_id')
            ->where('url', '/ticketing/biglietteria/')
            ->update(['url' => '/ticketing/']);

        // Le copie del seeder non si ricreano.
    }
};
