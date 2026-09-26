<?php

namespace App\Console\Commands;

use App\Support\Accessibilita\TitoliDelleNotizie;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Corregge nell'archivio delle notizie i difetti di accessibilità che si
 * possono correggere senza scrivere testo: livelli dei titoli e `aria-level`
 * fuori posto (regole in TitoliDelleNotizie). Elenca le notizie che hanno
 * ancora immagini senza testo alternativo (vuoto o assente): quello non si
 * inventa, lo scrive una persona guardando l'immagine.
 *
 * Idempotente. `--prova` mostra cosa cambierebbe senza scrivere. Va lanciato
 * dalla console dell'app, dove vive il database, e prima con `--prova`.
 *
 * Scrive sulla colonna grezza: `content` è tradotto (JSON per lingua) ma le
 * righe importate da WordPress possono essere testo semplice, e passare dal
 * modello le trasformerebbe (§9 di CLAUDE.md). Il formato di ciascuna riga
 * resta quello che era.
 */
class CorreggiLAccessibilitaDelleNotizie extends Command
{
    protected $signature = 'news:correggi-accessibilita
        {--prova : Mostra cosa cambierebbe, senza scrivere}';

    protected $description = 'Riallinea i titoli e toglie gli aria-level fuori posto nel testo delle notizie';

    public function handle(): int
    {
        $prova = (bool) $this->option('prova');
        $corrette = 0;
        $immagini = [];

        DB::table('posts')->select(['id', 'slug', 'content'])->orderBy('id')
            ->chunkById(200, function ($righe) use ($prova, &$corrette, &$immagini): void {
                foreach ($righe as $riga) {
                    $grezzo = (string) $riga->content;
                    $lingue = json_decode($grezzo, true);
                    $testi = is_array($lingue) ? $lingue : ['*' => $grezzo];
                    $modifiche = [];

                    foreach ($testi as $lingua => $html) {
                        if (! is_string($html) || $html === '') {
                            continue;
                        }
                        $esito = TitoliDelleNotizie::correggi($html);
                        if ($esito['immagini_senza_testo'] > 0 && $lingua !== 'en') {
                            $immagini[] = [$riga->slug, $esito['immagini_senza_testo']];
                        }
                        if ($esito['html'] !== $html) {
                            $testi[$lingua] = $esito['html'];
                            $modifiche[] = sprintf('%s: titoli %+d, aria-level tolti %d', $lingua, $esito['scarto'], $esito['aria_level']);
                        }
                    }

                    if ($modifiche === []) {
                        continue;
                    }

                    $corrette++;
                    $this->line(($prova ? '[prova] ' : '').$riga->slug.' — '.implode('; ', $modifiche));

                    if (! $prova) {
                        $nuovo = is_array($lingue) ? json_encode($testi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $testi['*'];
                        DB::table('posts')->where('id', $riga->id)->update(['content' => $nuovo]);
                        // La scrittura salta il modello, quindi anche
                        // CacheInvalidationObserver: la scheda della notizia
                        // si butta qui, e solo lei (un flush svuoterebbe
                        // anche la cache della gallery, §12-bis).
                        foreach (config('app.supported_locales') as $locale) {
                            Cache::forget('public:news:'.$locale.':'.$riga->slug);
                        }
                    }
                }
            });

        $this->newLine();
        $this->info(($prova ? 'Da correggere: ' : 'Corrette: ').$corrette.' notizie.');

        if ($immagini !== []) {
            $this->warn(count($immagini).' notizie hanno ancora immagini senza testo alternativo: descrivile dal pannello, o lascia vuoto l\'alt se sono solo decorative.');
            $this->table(['Notizia', 'Immagini'], $immagini);
        }

        return self::SUCCESS;
    }
}
