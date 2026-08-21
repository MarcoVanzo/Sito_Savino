<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rimette i testi italiani che il pannello aveva cancellato.
 *
 * Fino alla correzione di `PreservaContentData`, salvare una pagina riscriveva
 * `content_data` con i soli campi visibili in quel momento: tutto il resto
 * spariva. La redazione se n'era accorta ("ho modificato Impatto e numeri ma
 * non appaiono più", "ho caricato il logo e poi non c'è più nulla") ma il danno
 * era già fatto, e riguarda solo la lingua in cui stavano lavorando: online
 * l'italiano era vuoto mentre l'inglese era intatto.
 *
 * Qui si riempiono le chiavi rimaste vuote con i valori iniziali del template.
 * Non si sovrascrive niente di ciò che c'è: quello che la redazione ha scritto
 * dopo resta.
 *
 * Restano da riscrivere a mano le cose che nessuno può indovinare: il
 * collegamento a Vivaticket e i file caricati.
 */
return new class extends Migration
{
    /**
     * I settori dell'impianto, per rimettere in italiano le tariffe degli
     * abbonamenti: i prezzi erano sopravvissuti solo nella copia inglese.
     */
    private const SETTORI = [
        'West Stand' => 'Tribuna Ovest',
        'East and South Stand' => 'Tribuna Est e Sud',
        'North Stand' => 'Tribuna Nord',
        'Raised East' => 'Est Rialzata',
    ];

    public function up(): void
    {
        $iniziali = require database_path('data/page_template_defaults.php');
        $lingue = config('app.supported_locales', ['it', 'en']);

        $pagine = DB::table('pages')->select('id', 'slug', 'template', 'content_data')->get();

        foreach ($pagine as $pagina) {
            $dati = json_decode((string) $pagina->content_data, true);
            $dati = is_array($dati) ? $dati : [];

            $prima = $dati;

            foreach ($lingue as $lingua) {
                $valori = $iniziali[$pagina->template][$lingua]
                    ?? $iniziali[$pagina->template]['it']
                    ?? [];

                foreach ($valori as $chiave => $valore) {
                    if (empty($dati[$lingua][$chiave]) && ! empty($valore)) {
                        $dati[$lingua][$chiave] = $valore;
                    }
                }
            }

            if ($pagina->slug === 'abbonamenti') {
                $dati = $this->rimettiLeTariffe($dati);
            }

            if ($dati !== $prima) {
                DB::table('pages')->where('id', $pagina->id)->update([
                    'content_data' => json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }
    }

    /**
     * Le quattro tariffe della campagna abbonamenti, con i prezzi decisi dalla
     * redazione: erano rimaste solo nella copia inglese, tradotte nei nomi ma
     * identiche nelle cifre.
     *
     * @param  array<string, mixed>  $dati
     * @return array<string, mixed>
     */
    private function rimettiLeTariffe(array $dati): array
    {
        $inglesi = $dati['en']['plans'] ?? [];

        if (! is_array($inglesi) || $inglesi === [] || ! empty($dati['it']['plans'])) {
            return $dati;
        }

        $dati['it']['plans'] = array_map(function (array $tariffa) {
            $nome = (string) ($tariffa['name'] ?? '');
            $tariffa['name'] = self::SETTORI[$nome] ?? $nome;

            return $tariffa;
        }, $inglesi);

        // "Believe" è il nome della campagna: uguale nelle due lingue. La
        // redazione l'aveva scritto e online compariva ancora il testo di
        // ripiego "Vivi l'emozione".
        if (($dati['en']['hero_label'] ?? null) === 'Believe') {
            $dati['it']['hero_label'] = 'Believe';
        }

        return $dati;
    }

    /**
     * Non reversibile: toglierli riporterebbe le pagine a com'erano quando la
     * redazione ha segnalato che erano vuote.
     */
    public function down(): void {}
};
