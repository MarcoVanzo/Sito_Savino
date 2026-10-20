<?php

namespace App\Support\Accessibilita;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * I PDF taggati, stampati da Chrome senza interfaccia.
 *
 * dompdf non sa scrivere la struttura (StructTreeRoot): per uno screen reader
 * il PDF è un muro di testo, senza titoli né tabelle (WCAG 1.3.1). Chrome,
 * stampando una pagina in PDF, la tagga da sé partendo dall'HTML — `h1`-`h3`,
 * `p`, `ul`, `table` con `th` — e scrive titolo (`DisplayDocTitle`) e lingua
 * (`/Lang` da `<html lang>`). Gli stessi modelli Blade servono a tutti e due.
 *
 * Nessuna dipendenza nuova: si chiama il binario con `--print-to-pdf`. Dove
 * Chrome non c'è, o fallisce, si torna `null` e `PdfAccessibile` ripiega su
 * dompdf: un PDF senza tag è meglio di una conferma d'ordine senza allegato.
 *
 * Il binario: `CHROME_PATH` (`services.chrome.path`), altrimenti i percorsi
 * soliti di macOS e Linux. Spento con `PDF_CON_CHROME=false`.
 */
class PdfConChrome
{
    /** @var list<string> */
    private const PERCORSI_SOLITI = [
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
    ];

    /**
     * Niente script (i modelli non ne hanno: se un dato dell'ordine ne
     * contenesse uno, non deve girare; `--blink-settings=scriptEnabled=false`
     * invece blocca la stampa stessa), stampa fedele dei colori (la testata
     * blu della ricevuta) e Montserrat, che a dompdf arriva dalla sua cartella
     * dei caratteri e a Chrome da qui.
     */
    private const STILE = <<<'CSS'
    <meta http-equiv="Content-Security-Policy" content="script-src 'none'">
    <style>
        @font-face { font-family: 'Montserrat'; font-weight: 400; src: url('file://%s/montserrat-normal.ttf'); }
        @font-face { font-family: 'Montserrat'; font-weight: 700; src: url('file://%s/montserrat-bold.ttf'); }
        @page { size: A4; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    </style>
    CSS;

    public static function binario(): ?string
    {
        if (! config('services.chrome.abilitato', true)) {
            return null;
        }

        $configurato = config('services.chrome.path');

        if (is_string($configurato) && $configurato !== '') {
            return is_executable($configurato) ? $configurato : null;
        }

        foreach (self::PERCORSI_SOLITI as $percorso) {
            if (is_executable($percorso)) {
                return $percorso;
            }
        }

        return null;
    }

    /**
     * Il PDF dell'HTML dato, o `null` se Chrome non c'è o non ce la fa.
     */
    public static function daHtml(string $html): ?string
    {
        $binario = self::binario();

        if ($binario === null) {
            return null;
        }

        $cartella = storage_path('framework/cache/pdf-'.Str::random(16));
        File::ensureDirectoryExists($cartella);

        try {
            $sorgente = $cartella.'/documento.html';
            $destinazione = $cartella.'/documento.pdf';
            $stile = sprintf(self::STILE, resource_path('fonts/pdf'), resource_path('fonts/pdf'));

            File::put($sorgente, preg_match('/<head[^>]*>/i', $html) ? (string) preg_replace('/<head[^>]*>/i', '$0'.$stile, $html, 1) : $stile.$html);

            $comando = [
                $binario,
                '--headless',
                '--disable-gpu',
                '--no-first-run',
                '--disable-extensions',
                '--no-pdf-header-footer',
                '--user-data-dir='.$cartella.'/profilo',
                '--print-to-pdf='.$destinazione,
                'file://'.$sorgente,
            ];

            // Da root (i container) Chrome non parte senza, e nemmeno dove il
            // sistema non gli concede la sandbox (`CHROME_SENZA_SANDBOX`).
            if (config('services.chrome.senza_sandbox') || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
                array_splice($comando, 1, 0, ['--no-sandbox']);
            }

            $processo = new Process($comando);
            $processo->setTimeout(60);
            $processo->run();

            $pdf = is_file($destinazione) ? (string) file_get_contents($destinazione) : '';

            if (! $processo->isSuccessful() || ! str_starts_with($pdf, '%PDF')) {
                Log::warning('PDF con Chrome non riuscito, ripiego su dompdf', [
                    'uscita' => $processo->getExitCode(),
                    'errore' => Str::limit($processo->getErrorOutput(), 500),
                ]);

                return null;
            }

            return $pdf;
        } catch (Throwable $errore) {
            Log::warning('PDF con Chrome non riuscito, ripiego su dompdf', ['errore' => $errore->getMessage()]);

            return null;
        } finally {
            File::deleteDirectory($cartella);
        }
    }
}
