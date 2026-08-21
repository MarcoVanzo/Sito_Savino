<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Controlla che ogni voce di menu porti davvero da qualche parte.
 *
 * Serve perche' i difetti di questo tipo non si vedono in sviluppo: il
 * database locale e' seminato da zero, quello online viene dall'import del
 * vecchio sito e contiene indirizzi che qui non esistono. Tre voci portavano a
 * "pagina non trovata" o a un percorso relativo e nessun test le intercettava.
 *
 * Si esegue anche contro i dati veri passando le credenziali di sola lettura.
 */
class VerificaIlMenu extends Command
{
    protected $signature = 'menu:verifica {--location=* : main, footer… (predefinite: tutte)}';

    protected $description = 'Elenca le voci di menu che non portano a nessuna pagina raggiungibile';

    public function handle(): int
    {
        $posizioni = $this->option('location') ?: ['main', 'footer'];
        $problemi = [];

        foreach ($posizioni as $posizione) {
            foreach ($this->voci($posizione) as $voce) {
                $motivo = $this->motivoDelGuasto($voce->url);

                if ($motivo !== null) {
                    $problemi[] = [$posizione, $this->etichetta($voce->label), (string) $voce->url, $motivo];
                }
            }
        }

        if ($problemi === []) {
            $this->info('Tutte le voci di menu portano a una pagina raggiungibile.');

            return self::SUCCESS;
        }

        $this->table(['Posizione', 'Voce', 'Indirizzo', 'Problema'], $problemi);
        $this->error(count($problemi).' voci di menu non portano da nessuna parte.');

        return self::FAILURE;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    private function voci(string $posizione)
    {
        return MenuItem::query()
            ->where('location', $posizione)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Perche' questa voce non funziona, o null se funziona.
     */
    public function motivoDelGuasto(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return 'indirizzo vuoto';
        }

        if ($url === '#') {
            return 'segnaposto "#": non apre nulla';
        }

        if (str_starts_with($url, 'documento:')) {
            return MenuItem::href($url, 'it') === '/in-costruzione'
                ? 'documento non ancora caricato in Impostazioni → Documenti Legali'
                : null;
        }

        // Indirizzi esterni: si accettano, il sito altrui non lo governiamo.
        if (preg_match('#^(https?:)?//#i', $url) || preg_match('#^(mailto|tel):#i', $url)) {
            return null;
        }

        if (! str_starts_with($url, '/')) {
            return 'percorso relativo: manca la barra iniziale';
        }

        return $this->motivoDelGuastoInterno($url);
    }

    private function motivoDelGuastoInterno(string $url): ?string
    {
        $percorso = rtrim((string) parse_url($url, PHP_URL_PATH), '/') ?: '/';

        try {
            $rotta = app('router')->getRoutes()->match(Request::create($percorso, 'GET'));
        } catch (\Throwable) {
            return 'nessuna rotta corrisponde a questo indirizzo';
        }

        $slug = $rotta->parameter('slug');

        if (! is_string($slug) || $slug === '' || ! $this->portaAUnaPagina($rotta->getName())) {
            return null;
        }

        $pagina = Page::query()->where('slug', $slug)->first();

        if (! $pagina) {
            return 'nessuna pagina con lo slug "'.$slug.'"';
        }

        return $pagina->status === PostStatus::Published
            ? null
            : 'la pagina è in bozza';
    }

    /**
     * La rotta serve una pagina del CMS?
     *
     * Le altre rotte con uno slug (una news, la scheda di un'atleta) prendono
     * il contenuto da un'altra tabella: cercarne lo slug fra le pagine darebbe
     * un falso allarme.
     */
    private function portaAUnaPagina(?string $nomeRotta): bool
    {
        $nome = preg_replace('/^en\./', '', (string) $nomeRotta) ?? '';

        return $nome === 'pages.show' || str_ends_with($nome, '.page');
    }

    private function etichetta(mixed $label): string
    {
        if (is_array($label)) {
            return (string) ($label['it'] ?? reset($label));
        }

        $decodificata = json_decode((string) $label, true);

        return is_array($decodificata)
            ? (string) ($decodificata['it'] ?? '')
            : (string) $label;
    }
}
