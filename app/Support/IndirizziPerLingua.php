<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RottaRegistrata;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * L'indirizzo della stessa pagina in un'altra lingua.
 *
 * Si calcola dal NOME della rotta, non dal percorso: gli slug sono tradotti
 * (`/contatti` / `/en/contacts`, `/recesso` / `/en/withdrawal`,
 * `/shop/prodotto/x` / `/en/shop/product/x`), mentre il nome è lo stesso in
 * tutte le lingue a meno del prefisso (`contatti` / `en.contatti`, vedi
 * routes/web.php). Aggiungere o togliere `/en` al percorso mandava il selettore
 * della lingua, gli hreflang e la sitemap su indirizzi 404.
 *
 * Quando nell'altra lingua la pagina non ha un indirizzo si restituisce null e
 * chi chiama decide: il selettore ripiega sulla home, hreflang e sitemap
 * tacciono — dichiarare un alternato che non esiste è peggio che non dirlo.
 */
class IndirizziPerLingua
{
    /**
     * La lingua senza prefisso negli indirizzi. È `fallback_locale` e non
     * `app.locale`: a richiesta in corso `App::setLocale()` ha già riscritto
     * quest'ultima con la lingua corrente (CLAUDE.md §23).
     */
    public static function linguaPredefinita(): string
    {
        return (string) config('app.fallback_locale', 'it');
    }

    /**
     * @return list<string>
     */
    public static function lingue(): array
    {
        return array_values(config('app.supported_locales', [self::linguaPredefinita()]));
    }

    /**
     * Il nome della rotta senza il prefisso di lingua (`en.contatti` → `contatti`).
     */
    public static function nomeBase(string $nome): string
    {
        foreach (self::lingue() as $lingua) {
            if ($lingua !== self::linguaPredefinita() && str_starts_with($nome, $lingua.'.')) {
                return substr($nome, strlen($lingua) + 1);
            }
        }

        return $nome;
    }

    /**
     * L'indirizzo della rotta `$nomeBase` nella lingua data, o null se lì
     * non esiste o non si può costruire.
     *
     * @param  array<string, mixed>  $parametri
     */
    public static function perRotta(string $nomeBase, array $parametri, string $lingua, string $query = ''): ?string
    {
        $nome = $lingua === self::linguaPredefinita() ? $nomeBase : $lingua.'.'.$nomeBase;
        $rotta = Route::getRoutes()->getByName($nome);

        if (! $rotta instanceof RottaRegistrata || ! in_array('GET', $rotta->methods(), true)) {
            return null;
        }

        // Una rotta firmata (disiscrizione, conferma della newsletter, ricevuta
        // del recesso) senza la sua firma risponde 403: la firma copre il
        // percorso, quindi nell'altra lingua non c'è un indirizzo valido.
        foreach ($rotta->middleware() as $middleware) {
            if (str_starts_with($middleware, 'signed') || str_contains($middleware, 'ValidateSignature')) {
                return null;
            }
        }

        // Solo i parametri che la rotta di arrivo ha nel percorso: gli altri
        // (i `defaults()` come lo slug di `/summer-camp`) route() li
        // appenderebbe come query.
        $parametri = array_intersect_key($parametri, array_flip($rotta->parameterNames()));

        try {
            $indirizzo = route($nome, $parametri);
        } catch (Throwable) {
            return null;
        }

        return $query !== '' ? $indirizzo.'?'.$query : $indirizzo;
    }

    /**
     * La pagina della richiesta nella lingua data.
     */
    public static function perRichiesta(Request $request, string $lingua, bool $conQuery = true): ?string
    {
        $rotta = $request->route();
        $query = $conQuery ? (string) $request->getQueryString() : '';

        if (! $rotta instanceof RottaRegistrata || $rotta->getName() === null) {
            return null;
        }

        // I parametri così come stanno nell'indirizzo: `parameters()` dopo il
        // binding contiene i modelli, e la chiave di un modello tradotto
        // potrebbe non essere quella scritta nell'URL.
        return self::perRotta(self::nomeBase($rotta->getName()), $rotta->originalParameters(), $lingua, $query);
    }

    /**
     * Un percorso della lingua predefinita (`/contatti`) nella lingua data.
     * Serve alla sitemap, che elenca le pagine per percorso: lo si fa
     * riconoscere al router e si passa dal nome, come per la richiesta.
     */
    public static function perPercorso(string $percorso, string $lingua): ?string
    {
        try {
            $rotta = Route::getRoutes()->match(Request::create($percorso, 'GET'));
        } catch (Throwable) {
            return null;
        }

        if ($rotta->getName() === null) {
            return null;
        }

        return self::perRotta(self::nomeBase($rotta->getName()), $rotta->originalParameters(), $lingua);
    }

    /**
     * La home della lingua data: il ripiego del selettore quando la pagina
     * nell'altra lingua non esiste.
     */
    public static function home(string $lingua): string
    {
        return self::perRotta('home', [], $lingua)
            ?? url($lingua === self::linguaPredefinita() ? '/' : '/'.$lingua);
    }
}
