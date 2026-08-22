<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controlla che i file caricati dal pannello esistano davvero.
 *
 * In archivio resta solo il percorso. Se il file non arriva sul disco giusto —
 * ed è successo: il pannello scriveva sul disco locale del container, che in
 * produzione viene ricreato a ogni rilascio — la pagina continua a mostrare un
 * link, che però non apre niente. Sono spariti così tutti e sette i documenti
 * di Corporate Governance, il logo delle Cartelle Stampa e il bilancio di
 * sostenibilità.
 *
 * Si esegue anche contro i dati veri, come `menu:verifica`.
 */
class VerificaIFileCaricati extends Command
{
    protected $signature = 'file:verifica';

    protected $description = 'Elenca i file caricati dal pannello che non esistono sul disco';

    /** I campi di `content_data` che contengono un file, dentro un elenco. */
    private const CAMPI_NEGLI_ELENCHI = [
        'magazines' => ['file_url', 'cover_image_url'],
        'documents' => ['file'],
        'press_kits' => ['file'],
    ];

    private const CAMPI_SEMPLICI = ['button_image'];

    public function handle(): int
    {
        $mancanti = array_merge($this->nelleImpostazioni(), $this->nellePagine());

        if ($mancanti === []) {
            $this->info('Tutti i file caricati dal pannello esistono sul disco "'.config('filesystems.default').'".');

            return self::SUCCESS;
        }

        $this->table(['Dove', 'Campo', 'Percorso'], $mancanti);
        $this->error(count($mancanti).' file risultano caricati ma non esistono.');

        return self::FAILURE;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function nelleImpostazioni(): array
    {
        $mancanti = [];

        foreach (SiteSetting::all() as $impostazione) {
            if (! $this->sembraUnPercorso($impostazione->value)) {
                continue;
            }

            if (! $this->esiste($impostazione->value)) {
                $mancanti[] = ['Impostazioni', $impostazione->key, $impostazione->value];
            }
        }

        return $mancanti;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function nellePagine(): array
    {
        $mancanti = [];

        foreach (Page::all() as $pagina) {
            foreach (config('app.supported_locales', ['it', 'en']) as $lingua) {
                $contenuti = $pagina->getTranslation('content_data', $lingua, false);

                if (! is_array($contenuti)) {
                    continue;
                }

                foreach ($this->percorsiNeiContenuti($contenuti) as $campo => $percorso) {
                    if (! $this->esiste($percorso)) {
                        $mancanti[] = ["Pagina {$pagina->slug} ({$lingua})", $campo, $percorso];
                    }
                }
            }
        }

        return $mancanti;
    }

    /**
     * @param  array<string, mixed>  $contenuti
     * @return array<string, string>
     */
    private function percorsiNeiContenuti(array $contenuti): array
    {
        $percorsi = [];

        foreach (self::CAMPI_SEMPLICI as $campo) {
            if ($this->sembraUnPercorso($contenuti[$campo] ?? null)) {
                $percorsi[$campo] = $contenuti[$campo];
            }
        }

        foreach (self::CAMPI_NEGLI_ELENCHI as $elenco => $campi) {
            if (! is_array($contenuti[$elenco] ?? null)) {
                continue;
            }

            $percorsi += $this->percorsiNellElenco($elenco, $contenuti[$elenco], $campi);
        }

        return $percorsi;
    }

    /**
     * Percorsi trovati dentro un elenco ripetibile del pannello (i documenti
     * di una cartella stampa, le foto di una sezione).
     *
     * @param  array<int|string, mixed>  $voci
     * @param  array<int, string>  $campi
     * @return array<string, string>
     */
    private function percorsiNellElenco(string $elenco, array $voci, array $campi): array
    {
        $percorsi = [];

        foreach ($voci as $posizione => $voce) {
            if (! is_array($voce)) {
                continue;
            }

            foreach ($campi as $campo) {
                if ($this->sembraUnPercorso($voce[$campo] ?? null)) {
                    $percorsi["{$elenco}.{$posizione}.{$campo}"] = $voce[$campo];
                }
            }
        }

        return $percorsi;
    }

    /**
     * Un valore è un file caricato dal pannello, non un indirizzo scritto a
     * mano né un testo qualunque.
     */
    private function sembraUnPercorso(mixed $valore): bool
    {
        if (! is_string($valore) || trim($valore) === '') {
            return false;
        }

        $valore = trim($valore);

        if (Str::startsWith($valore, ['http://', 'https://', '/', '#'])) {
            return false;
        }

        return (bool) preg_match('#^[\w\-/. ]+\.(pdf|jpe?g|png|webp|gif|svg|zip|docx?|xlsx?)$#i', $valore);
    }

    private function esiste(string $percorso): bool
    {
        try {
            return Storage::disk(config('filesystems.default'))->exists($percorso);
        } catch (\Throwable $errore) {
            $this->warn("Disco non raggiungibile per {$percorso}: ".$errore->getMessage());

            // Un disco che non risponde non è un file mancante: meglio non
            // segnalare nulla che segnalare tutto.
            return true;
        }
    }
}
