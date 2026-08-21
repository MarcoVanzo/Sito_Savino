<?php

namespace App\Console\Commands;

use App\Enums\SponsorTier;
use App\Models\Sponsor;
use App\Support\RitaglioDelMargine;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Importa gli sponsor dalla pagina pubblica del sito precedente.
 *
 * La pagina non espone API: i loghi stanno in sezioni introdotte da un titolo
 * ("Main Sponsor", "Official Partner"…) che è il livello di sponsorizzazione.
 * Si legge quindi il documento in ordine, tenendo memoria dell'ultimo titolo
 * incontrato, e ogni immagine viene attribuita a quel livello.
 *
 * L'import è idempotente: la chiave è il nome normalizzato dello sponsor, così
 * rilanciarlo aggiorna livello, sito e logo invece di creare doppioni. Gli
 * sponsor inseriti a mano dal pannello e assenti dalla pagina non vengono
 * toccati.
 */
class ImportLegacySponsors extends Command
{
    protected $signature = 'sponsors:import-legacy
        {--url=https://savinodelbenevolley.it/sponsor/ : Pagina da leggere}
        {--dry-run : Mostra cosa verrebbe importato senza scrivere nulla}
        {--skip-logos : Non scarica i loghi}';

    protected $description = 'Importa sponsor, livelli e loghi dalla pagina sponsor del sito precedente';

    /**
     * Titolo di sezione della pagina di origine => livello.
     */
    private const TIER_BY_HEADING = [
        'title sponsor' => SponsorTier::Title,
        'main sponsor' => SponsorTier::Main,
        'premium partner' => SponsorTier::Premium,
        'mobility partner' => SponsorTier::Mobility,
        'sponsor tecnico' => SponsorTier::Technical,
        'acqua ufficiale' => SponsorTier::Water,
        'sister companies' => SponsorTier::Sister,
        'health partner' => SponsorTier::Health,
        'official coffee' => SponsorTier::Coffee,
        'sustainability partner' => SponsorTier::Sustainability,
        'official partner' => SponsorTier::Official,
        'institutional education partner' => SponsorTier::Education,
        'official supplier' => SponsorTier::Supplier,
        'official supporter' => SponsorTier::Supporter,
        'official radio' => SponsorTier::Radio,
        'media partner' => SponsorTier::Media,
    ];

    public function handle(): int
    {
        $url = (string) $this->option('url');
        $response = Http::timeout(30)->withHeaders(['User-Agent' => 'SavinoDelBeneVolley/1.0'])->get($url);

        if (! $response->successful()) {
            $this->error("Pagina non raggiungibile ({$response->status()}): {$url}");

            return self::FAILURE;
        }

        $entries = $this->parse($response->body(), $url);

        if ($entries === []) {
            $this->error('Nessuno sponsor riconosciuto: il markup della pagina di origine è cambiato.');

            return self::FAILURE;
        }

        $this->line(count($entries).' sponsor riconosciuti.');

        $created = 0;
        $updated = 0;

        foreach ($entries as $entry) {
            if ($this->option('dry-run')) {
                $this->line(sprintf('  %-42s %-18s %s', $entry['name'], $entry['tier']->value, $entry['website'] ?? '—'));

                continue;
            }

            $sponsor = Sponsor::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($entry['name'])])
                ->first();

            if ($sponsor) {
                $sponsor->fill([
                    'tier' => $entry['tier'],
                    'sort_order' => $entry['sort_order'],
                    'url' => $entry['website'] ?: $sponsor->url,
                ])->save();
                $updated++;
            } else {
                $sponsor = Sponsor::create([
                    'name' => $entry['name'],
                    'tier' => $entry['tier'],
                    'sort_order' => $entry['sort_order'],
                    'url' => $entry['website'],
                ]);
                $created++;
            }

            if (! $this->option('skip-logos') && $entry['logo'] && ! $sponsor->getFirstMedia('sponsors')) {
                $this->attachLogo($sponsor, $entry['logo']);
            }
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $this->info("Sponsor creati: {$created} — aggiornati: {$updated}.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{name: string, tier: SponsorTier, website: ?string, logo: ?string, sort_order: int}>
     */
    private function parse(string $html, string $baseUrl): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//h1|//h2|//h3|//h4|//img');

        $entries = [];
        $tier = null;
        $position = 0;
        $seen = [];

        foreach ($nodes ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if ($node->tagName !== 'img') {
                $heading = Str::lower(trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? ''));
                $tier = self::TIER_BY_HEADING[$heading] ?? $tier;
                $position = 0;

                continue;
            }

            if (! $tier instanceof SponsorTier) {
                continue;
            }

            $source = $this->absoluteUrl($node->getAttribute('src'), $baseUrl);
            // Solo le immagini con un alt sono loghi di sponsor: senza alt sono
            // il marchio della società in testata, i pixel di tracciamento e le
            // decorazioni del tema, che finirebbero in elenco come sponsor.
            $name = $this->cleanName($node->getAttribute('alt'));

            if ($name === '' || $source === null || isset($seen[Str::lower($name)])) {
                continue;
            }

            $seen[Str::lower($name)] = true;
            $entries[] = [
                'name' => $name,
                'tier' => $tier,
                'website' => $this->websiteOf($node),
                'logo' => $source,
                'sort_order' => $position++,
            ];
        }

        return $entries;
    }

    /**
     * Il link al sito dello sponsor è l'ancora che avvolge il logo.
     */
    private function websiteOf(DOMElement $img): ?string
    {
        for ($node = $img->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->tagName !== 'a') {
                continue;
            }

            $href = trim($node->getAttribute('href'));

            return Str::startsWith($href, ['http://', 'https://']) ? $href : null;
        }

        return null;
    }

    private function cleanName(string $alt): string
    {
        $name = trim(html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $name) ?? ''), 255, '');
    }

    private function absoluteUrl(string $src, string $baseUrl): ?string
    {
        $src = trim($src);

        if ($src === '' || Str::startsWith($src, 'data:')) {
            return null;
        }

        if (Str::startsWith($src, ['http://', 'https://'])) {
            return $src;
        }

        $base = parse_url($baseUrl);

        return ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '').'/'.ltrim($src, '/');
    }

    private function attachLogo(Sponsor $sponsor, string $logoUrl): void
    {
        try {
            $risposta = Http::timeout(30)->retry(2, 500, throw: false)->get($logoUrl);

            if (! $risposta->successful()) {
                $this->warn("Logo non scaricato per {$sponsor->name}: HTTP {$risposta->status()}");

                return;
            }

            $nome = Str::slug($sponsor->name);
            $byte = $risposta->body();

            // Sul vecchio sito i marchi stavano dentro riquadri 600x400 con
            // molto bianco intorno: mostrandoli interi nella scheda il logo
            // sembra minuscolo dentro una card grande. Il margine si toglie
            // qui, una volta, invece di combatterlo con il CSS.
            $ritagliato = RitaglioDelMargine::ritaglia($byte);

            if ($ritagliato !== null) {
                $byte = $ritagliato;
                $estensione = 'png';
            } else {
                $estensione = pathinfo(parse_url($logoUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'png';
            }

            $sponsor->addMediaFromString($byte)
                ->usingFileName($nome.'.'.$estensione)
                ->toMediaCollection('sponsors');
        } catch (\Throwable $e) {
            $this->warn("Logo non scaricato per {$sponsor->name}: {$e->getMessage()}");
        }
    }
}
