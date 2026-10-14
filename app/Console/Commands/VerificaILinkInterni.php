<?php

namespace App\Console\Commands;

use App\Support\SeguiILinkInterni;
use Illuminate\Console\Command;

/**
 * Segue i link interni di menu, footer e home e segnala quelli che non
 * arrivano dritti a una pagina: rimandi (301/302), 404 ed errori.
 *
 * `menu:verifica` guarda le voci nel database (pagina in bozza, documento
 * mancante); questo comando chiede davvero gli indirizzi, come un browser, e
 * dice dove portano. Le richieste passano dal kernel dell'applicazione, non
 * dalla rete: si lancia uguale in locale e dalla console di un componente.
 */
class VerificaILinkInterni extends Command
{
    protected $signature = 'link:verifica
        {--pagina=* : pagine da cui raccogliere i link (predefinite: / e /en)}
        {--senza-menu : non controllare le voci di menu e footer}';

    protected $description = 'Segnala i link interni di menu, footer e home che rimandano altrove o non esistono';

    public function handle(): int
    {
        // Ogni richiesta interna aprirebbe una sessione nel database.
        config(['session.driver' => 'array']);

        $pagine = $this->option('pagina') ?: $this->pagineDiPartenza();
        $link = $this->option('senza-menu') ? [] : SeguiILinkInterni::delMenu();

        foreach ($pagine as $pagina) {
            $link = [...$link, ...SeguiILinkInterni::dellaPagina('/'.ltrim((string) $pagina, '/'))];
        }

        $problemi = SeguiILinkInterni::problemi($link);
        $controllati = collect($link)->map(fn (array $voce) => SeguiILinkInterni::percorsoInterno($voce['href']))->filter()->unique()->count();

        if ($problemi === []) {
            $this->info("Tutti i {$controllati} link interni portano dritti a una pagina.");

            return self::SUCCESS;
        }

        $this->table(
            ['Dove', 'Voce', 'Link', 'Stato', 'Porta a', 'Problema'],
            array_map(fn (array $p) => [$p['origine'], $p['voce'], $p['href'], $p['stato'], $p['destinazione'], $p['problema']], $problemi),
        );
        $this->error(count($problemi)." link interni su {$controllati} non portano dritti a una pagina.");

        return self::FAILURE;
    }

    /**
     * La home in ogni lingua.
     *
     * @return list<string>
     */
    private function pagineDiPartenza(): array
    {
        $predefinita = config('app.fallback_locale');

        return collect(config('app.supported_locales', ['it']))
            ->map(fn (string $lingua) => $lingua === $predefinita ? '/' : '/'.$lingua)
            ->values()
            ->all();
    }
}
