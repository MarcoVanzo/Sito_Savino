<?php

namespace App\Console\Commands;

use App\Enums\CodiceDelRedirect;
use App\Models\Reindirizzamento;
use App\Support\RedirectGestiti;
use Illuminate\Console\Command;

/**
 * Importa i redirect del vecchio sito da un CSV `from,to,status`.
 *
 * Pensato per l'elenco delle pagine 404 di Search Console, completato a mano
 * con la destinazione: intestazione facoltativa, separatore `,` o `;` (Excel
 * in italiano), stato vuoto = 301 se c'è una destinazione, 410 se non c'è.
 * Le righe si confrontano su `from_path` normalizzato: un indirizzo già
 * presente si aggiorna, non si duplica. Le stesse regole del pannello
 * (`RedirectGestiti::errore`) decidono cosa si scarta.
 *
 *   php artisan redirect:importa storage/app/redirect.csv --dry-run
 */
class ImportaIRedirect extends Command
{
    protected $signature = 'redirect:importa
        {file : Il CSV con le colonne from,to,status}
        {--dry-run : Dice cosa farebbe senza scrivere niente}';

    protected $description = 'Importa da CSV i redirect del vecchio sito (upsert sul vecchio indirizzo)';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file) || ! is_readable($file)) {
            $this->error('File non trovato o non leggibile: '.$file);

            return self::FAILURE;
        }

        $righe = $this->leggi($file);
        $prova = (bool) $this->option('dry-run');
        $create = 0;
        $aggiornate = 0;
        $invariate = 0;
        /** @var list<array{int, string, string}> $scartate */
        $scartate = [];
        /** @var array<string, string> $accettate */
        $accettate = [];

        foreach ($righe as $numero => $campi) {
            $da = trim($campi[0] ?? '');
            $verso = trim($campi[1] ?? '');
            $stato = trim($campi[2] ?? '');

            $codice = $stato === ''
                ? ($verso === '' ? CodiceDelRedirect::Rimosso : CodiceDelRedirect::Permanente)
                : CodiceDelRedirect::tryFrom($stato);

            if ($codice === null) {
                $scartate[] = [$numero, $da, 'stato «'.$stato.'» non ammesso (301 o 410)'];

                continue;
            }

            // In prova nulla si salva: le righe già accettate si seguono da qui,
            // o A→B e B→A nello stesso file risultavano «Create: 2».
            $errore = RedirectGestiti::errore($da, $verso, $codice, $accettate);

            if ($errore !== null) {
                $scartate[] = [$numero, $da, $errore];

                continue;
            }

            $percorso = (string) RedirectGestiti::normalizza($da);
            $redirect = Reindirizzamento::query()->firstOrNew(['from_path' => $percorso]);
            $redirect->fill([
                'to_path' => $codice === CodiceDelRedirect::Rimosso ? null : $verso,
                'status_code' => $codice,
            ]);

            if ($redirect->exists && ! $redirect->isDirty()) {
                $invariate++;

                continue;
            }

            $redirect->exists ? $aggiornate++ : $create++;

            if ($codice === CodiceDelRedirect::Rimosso) {
                unset($accettate[$percorso]);
            } else {
                $accettate[$percorso] = $verso;
            }

            if (! $prova) {
                $redirect->save();
            }
        }

        if ($prova) {
            $this->warn('Prova: nessuna modifica salvata.');
        }

        $this->info(sprintf('Create: %d · aggiornate: %d · invariate: %d · scartate: %d', $create, $aggiornate, $invariate, count($scartate)));

        if ($scartate !== []) {
            $this->table(['Riga', 'Vecchio indirizzo', 'Motivo'], $scartate);
        }

        return self::SUCCESS;
    }

    /**
     * Le righe del CSV per numero di riga, senza intestazione né righe vuote.
     *
     * @return array<int, list<string>>
     */
    private function leggi(string $file): array
    {
        $contenuto = (string) file_get_contents($file);
        $contenuto = preg_replace('/^\xEF\xBB\xBF/', '', $contenuto) ?? $contenuto;
        $linee = preg_split('/\r\n|\n|\r/', $contenuto) ?: [];
        $prima = $linee[0] ?? '';
        $separatore = substr_count($prima, ';') > substr_count($prima, ',') ? ';' : ',';

        $righe = [];

        foreach ($linee as $indice => $linea) {
            if (trim($linea) === '') {
                continue;
            }

            $campi = array_map(fn (?string $campo): string => (string) $campo, str_getcsv($linea, $separatore, '"', ''));

            if ($indice === 0 && in_array(strtolower(trim($campi[0])), ['from', 'from_path', 'da'], true)) {
                continue;
            }

            $righe[$indice + 1] = $campi;
        }

        return $righe;
    }
}
