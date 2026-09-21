<?php

namespace App\Support;

use App\Models\Product;
use App\Models\SiteSetting;

/**
 * La guida alle taglie: quella generale e quella del singolo prodotto.
 *
 * I PDF si caricano in "Guida Taglie & Contatti" e finiscono nell'unica
 * impostazione `shop.size_guides`. Sulla scheda di un prodotto la redazione
 * può sceglierne uno, lasciare la pagina generale, oppure togliere del tutto
 * la voce: per le maglie vecchie, per cui una guida aggiornata non esiste,
 * il link portava a una tabella buona per nessuno.
 */
class GuidaTaglie
{
    /**
     * Il valore con cui un prodotto dichiara di non volere il link.
     *
     * Una stringa e non un booleano a parte: la colonna deve distinguere tre
     * casi (una guida sua, quella generale, nessuna) e due colonne per dirlo
     * si disallineerebbero.
     */
    public const NASCOSTA = 'nessuna';

    /**
     * I documenti caricati, con nome e indirizzo pubblico.
     *
     * L'indirizzo lo dà CmsFile: in produzione i file stanno su Spaces e un
     * "/storage/…" composto a mano non porta da nessuna parte.
     *
     * @return list<array{path: string, url: string|null, name: string}>
     */
    public static function documenti(): array
    {
        $guide = SiteSetting::get('shop.size_guides');

        if (is_string($guide)) {
            $guide = json_decode($guide, true);
        }

        $documenti = [];

        foreach (is_array($guide) ? $guide : [] as $percorso) {
            if (! is_string($percorso) || $percorso === '') {
                continue;
            }

            $documenti[] = [
                'path' => $percorso,
                'url' => CmsFile::url($percorso),
                'name' => pathinfo($percorso, PATHINFO_FILENAME),
            ];
        }

        return $documenti;
    }

    /**
     * Le scelte da mostrare nel modulo del prodotto.
     *
     * @return array<string, string>
     */
    public static function opzioni(): array
    {
        $opzioni = [self::NASCOSTA => 'Nessuna guida (nascondi la voce)'];

        foreach (self::documenti() as $documento) {
            $opzioni[$documento['path']] = $documento['name'];
        }

        return $opzioni;
    }

    /**
     * L'indirizzo a cui deve portare la voce "Guida alle taglie", o null se
     * non ci deve essere.
     *
     * Senza documenti caricati la pagina generale non ha niente da mostrare,
     * quindi la voce sparisce invece di portare a una pagina vuota.
     */
    public static function perIlProdotto(Product $prodotto): ?string
    {
        $scelta = $prodotto->size_guide;

        if ($scelta === self::NASCOSTA) {
            return null;
        }

        if (is_string($scelta) && $scelta !== '') {
            return CmsFile::url($scelta);
        }

        return self::documenti() === [] ? null : route('shop.size-guide');
    }
}
