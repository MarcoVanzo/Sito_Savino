<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Services\Affiliazioni\ParserDelleAffiliate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Riempie l'elenco del "Progetto Affiliazioni" leggendo la pagina pubblica del
 * sito precedente.
 *
 * Le societa' del progetto sono una cinquantina: caricarle a mano dal pannello
 * significherebbe altrettanti upload. L'import scrive dentro
 * `content_data.affiliates` della pagina `affiliazioni` — gli stessi campi del
 * form — e scarica i loghi sul disco configurato, che in produzione e' Spaces.
 *
 * E' idempotente: la chiave e' il nome della societa'. Rilanciarlo aggiorna
 * livello e sito delle societa' gia' presenti senza ricaricarne il logo, e non
 * tocca quelle aggiunte a mano che sulla pagina d'origine non ci sono.
 */
class ImportaLeSocietaAffiliate extends Command
{
    protected $signature = 'affiliazioni:importa-dal-vecchio-sito
        {--url=https://savinodelbenevolley.it/affiliazioni/ : Pagina da leggere}
        {--slug=affiliazioni : Pagina CMS da riempire}
        {--dry-run : Mostra cosa verrebbe importato senza scrivere nulla}
        {--skip-logos : Non scarica i loghi}';

    protected $description = 'Importa societa\', livelli e loghi dalla pagina affiliazioni del sito precedente';

    private const CARTELLA = 'affiliazioni';

    /**
     * I loghi gia' scaricati in questa esecuzione: l'elenco si scrive una volta
     * per lingua, e senza memoria ogni logo verrebbe scaricato due volte.
     *
     * @var array<string, string|null>
     */
    private array $loghiScaricati = [];

    public function handle(ParserDelleAffiliate $parser): int
    {
        $url = (string) $this->option('url');
        $risposta = Http::timeout(30)->withHeaders(['User-Agent' => 'SavinoDelBeneVolley/1.0'])->get($url);

        if (! $risposta->successful()) {
            $this->error("Pagina non raggiungibile ({$risposta->status()}): {$url}");

            return self::FAILURE;
        }

        $societa = $parser->analizza($risposta->body(), $url);

        if ($societa === []) {
            $this->error('Nessuna societa\' riconosciuta: il markup della pagina di origine è cambiato.');

            return self::FAILURE;
        }

        $this->line(count($societa).' societa\' riconosciute.');

        if ($this->option('dry-run')) {
            foreach ($societa as $voce) {
                $this->line(sprintf('  %-42s %-12s %s', $voce['name'], $voce['tier'], $voce['url'] ?? '—'));
            }

            return self::SUCCESS;
        }

        $pagina = Page::where('slug', (string) $this->option('slug'))->first();

        if (! $pagina) {
            $this->error('Pagina CMS non trovata: '.$this->option('slug'));

            return self::FAILURE;
        }

        $aggiunte = 0;
        $aggiornate = 0;

        foreach (config('app.supported_locales', ['it']) as $lingua) {
            $contenuto = $pagina->getTranslation('content_data', $lingua, false);
            $contenuto = is_array($contenuto) ? $contenuto : [];
            $elenco = is_array($contenuto['affiliates'] ?? null) ? array_values($contenuto['affiliates']) : [];

            foreach ($societa as $voce) {
                $posizione = $this->posizioneNellElenco($elenco, $voce['name']);

                if ($posizione === null) {
                    $elenco[] = [
                        'name' => $voce['name'],
                        'tier' => $voce['tier'],
                        'url' => $voce['url'],
                        'logo' => $this->logoSalvato($voce),
                    ];
                    $aggiunte++;

                    continue;
                }

                $esistente = $elenco[$posizione];
                $elenco[$posizione] = [
                    'name' => $esistente['name'],
                    'tier' => $voce['tier'],
                    'url' => $voce['url'] ?: ($esistente['url'] ?? null),
                    // Un logo gia' caricato dal pannello vince su quello del
                    // sito vecchio: e' stato scelto dopo.
                    'logo' => $this->percorso($esistente['logo'] ?? null) ?? $this->logoSalvato($voce),
                ];
                $aggiornate++;
            }

            $contenuto['affiliates'] = $elenco;
            $pagina->setTranslation('content_data', $lingua, $contenuto);
        }

        $pagina->save();

        $lingue = max(1, count(config('app.supported_locales', ['it'])));
        $this->info('Societa\' aggiunte: '.intdiv($aggiunte, $lingue).' — aggiornate: '.intdiv($aggiornate, $lingue).'.');

        return self::SUCCESS;
    }

    private function percorso(mixed $valore): ?string
    {
        return is_string($valore) && trim($valore) !== '' ? $valore : null;
    }

    /**
     * L'elenco arriva da `content_data`, cioè da JSON: le voci sono quello che
     * ci trova, non per forza array.
     *
     * @param  list<mixed>  $elenco
     */
    private function posizioneNellElenco(array $elenco, string $nome): ?int
    {
        foreach ($elenco as $posizione => $voce) {
            if (is_array($voce) && Str::lower(trim((string) ($voce['name'] ?? ''))) === Str::lower($nome)) {
                return $posizione;
            }
        }

        return null;
    }

    /**
     * Scarica il logo e lo salva dove li mette il pannello: il percorso
     * relativo al disco, che `CmsFile` trasforma poi in indirizzo pubblico.
     *
     * @param  array{name: string, tier: string, url: ?string, logo: string}  $voce
     */
    private function logoSalvato(array $voce): ?string
    {
        if ($this->option('skip-logos')) {
            return null;
        }

        if (array_key_exists($voce['logo'], $this->loghiScaricati)) {
            return $this->loghiScaricati[$voce['logo']];
        }

        return $this->loghiScaricati[$voce['logo']] = $this->scarica($voce);
    }

    /**
     * @param  array{name: string, tier: string, url: ?string, logo: string}  $voce
     */
    private function scarica(array $voce): ?string
    {
        try {
            $risposta = Http::timeout(30)->retry(2, 500, throw: false)->get($voce['logo']);

            if (! $risposta->successful()) {
                $this->warn("Logo non scaricato per {$voce['name']}: HTTP {$risposta->status()}");

                return null;
            }

            // Il pezzetto di hash distingue due loghi con lo stesso nome; non
            // e' un contesto crittografico, ma md5 fa scattare l'analisi
            // statica e sha256 troncato costa uguale.
            $estensione = pathinfo(parse_url($voce['logo'], PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'png';
            $percorso = self::CARTELLA.'/'.Str::slug($voce['name']).'-'.Str::substr(hash('sha256', $voce['logo']), 0, 8).'.'.$estensione;

            // Lo stesso disco dei campi di upload del pannello: in produzione
            // e' Spaces, in locale quello pubblico. Scrivendo sul disco
            // predefinito i loghi finivano dove il sito non li serve.
            Storage::disk(config('filament.default_filesystem_disk'))->put($percorso, $risposta->body(), 'public');

            return $percorso;
        } catch (\Throwable $e) {
            $this->warn("Logo non scaricato per {$voce['name']}: {$e->getMessage()}");

            return null;
        }
    }
}
