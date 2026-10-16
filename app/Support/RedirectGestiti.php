<?php

namespace App\Support;

use App\Http\Middleware\RedirectPrimaDelleRotteVecchie;
use App\Enums\CodiceDelRedirect;
use App\Models\IndirizzoDaMappare;
use App\Models\Reindirizzamento;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * I redirect del vecchio sito decisi a mano (`redirects`) e la lista dei
 * vecchi indirizzi ancora richiesti che nessuno ha mappato (`redirect_mancanti`).
 *
 * La tabella si consulta solo dove serve: sulle rotte di `legacy.php`, sulla
 * rotta generica `/{slug}` (prima del CMS e del permalink automatico delle
 * notizie) e sui 404. Le rotte vere del sito non la toccano mai, quindi la
 * navigazione normale non paga nessuna query; dove la si consulta è una
 * lettura sull'indice unico di `from_path`, che costa quanto una lettura di
 * cache con il driver `database` di produzione.
 *
 * `from_path` è sempre nella forma di `normalizza()`: percorso senza query,
 * slash iniziale, senza slash finale, minuscolo e decodificato. Così
 * `/Tag/Foo/`, `/tag/foo` e `/tag/foo?utm=x` sono lo stesso indirizzo. Unica
 * eccezione la query `?p=<id>` sulla home, che per WordPress è un indirizzo.
 */
class RedirectGestiti
{
    /** Attributo della richiesta: la tabella è già stata consultata. */
    public const GIA_CONSULTATI = 'redirect_gestiti.consultati';

    /** @var list<string> */
    private const MAI = ['api', 'api/*', 'admin*', 'livewire*', 'filament*', 'up'];

    /**
     * La forma con cui un indirizzo si salva e si cerca, o null se non è un
     * indirizzo utilizzabile.
     */
    public static function normalizza(string $valore): ?string
    {
        $valore = trim($valore);

        if ($valore === '') {
            return null;
        }

        $parti = parse_url($valore);

        if ($parti === false) {
            return null;
        }

        $percorso = mb_strtolower(rawurldecode($parti['path'] ?? '/'));
        $percorso = (string) preg_replace('#/+#', '/', '/'.trim($percorso, '/'));

        if ($percorso === '/') {
            parse_str($parti['query'] ?? '', $query);
            $wpId = $query['p'] ?? null;

            if (is_string($wpId) && ctype_digit($wpId) && (int) $wpId > 0) {
                return '/?p='.(int) $wpId;
            }
        }

        return mb_strlen($percorso) > 255 ? null : $percorso;
    }

    /**
     * La risposta del redirect deciso a mano per questa richiesta, o null.
     */
    public static function rispostaPer(Request $request): ?Response
    {
        if (self::escluso($request)) {
            return null;
        }

        $request->attributes->set(self::GIA_CONSULTATI, true);
        $percorso = self::normalizza($request->getRequestUri());

        if ($percorso === null) {
            return null;
        }

        $redirect = Reindirizzamento::query()
            ->where('from_path', $percorso)
            ->first(['id', 'to_path', 'status_code']);

        if ($redirect === null) {
            return null;
        }

        // Atomico e senza eventi del modello: niente registro né updated_at.
        Reindirizzamento::query()->whereKey($redirect->id)->toBase()->update([
            'hits' => DB::raw('hits + 1'),
            'last_hit_at' => now(),
        ]);

        if ($redirect->status_code === CodiceDelRedirect::Rimosso || blank($redirect->to_path)) {
            return app(ExceptionHandler::class)->render($request, new HttpException(410));
        }

        return self::versoLaDestinazione($request, (string) $redirect->to_path);
    }

    /**
     * Chiamato dal gestore delle eccezioni su ogni 404: prima la tabella (se
     * nessuna rotta l'ha già consultata), poi la lista da mappare.
     */
    public static function alNonTrovato(Request $request): ?Response
    {
        if (self::escluso($request)) {
            return null;
        }

        if (! $request->attributes->get(self::GIA_CONSULTATI)) {
            $risposta = self::rispostaPer($request);

            if ($risposta !== null) {
                return $risposta;
            }
        }

        $percorso = self::normalizza($request->getRequestUri());

        if ($percorso !== null && self::sembraWordPress($request, $percorso)) {
            self::registraMancante($percorso, IndirizzoDaMappare::ORIGINE_404);
        }

        return null;
    }

    /**
     * Conta una visita a un vecchio indirizzo senza destinazione precisa.
     *
     * Una riga per percorso: il primo passaggio la crea (finché la lista non
     * è piena), gli altri aumentano il contatore. Un errore qui non deve mai
     * diventare un errore della pagina.
     */
    public static function registraMancante(string $percorso, string $origine): void
    {
        try {
            $adesso = now();

            $contate = IndirizzoDaMappare::query()
                ->where('percorso', $percorso)
                ->toBase()
                ->update([
                    'hits' => DB::raw('hits + 1'),
                    'last_hit_at' => $adesso,
                    'updated_at' => $adesso,
                ]);

            if ($contate > 0 || IndirizzoDaMappare::query()->count() >= IndirizzoDaMappare::MASSIMO_DI_RIGHE) {
                return;
            }

            IndirizzoDaMappare::query()->insertOrIgnore([
                'percorso' => $percorso,
                'origine' => $origine,
                'hits' => 1,
                'last_hit_at' => $adesso,
                'created_at' => $adesso,
                'updated_at' => $adesso,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Un indirizzo che ha la forma di quelli del vecchio WordPress.
     *
     * I 404 qualsiasi (scanner, refusi) non interessano: la lista deve
     * contenere solo ciò che vale la pena mappare. Le sonde agli script di
     * WordPress (`wp-login.php`, `xmlrpc.php`, i plugin) restano fuori.
     */
    public static function sembraWordPress(Request $request, string $percorso): bool
    {
        if (str_starts_with($percorso, '/?p=')) {
            return true;
        }

        if (preg_match('#\.php|wp-admin|wp-login|wp-includes|xmlrpc|/\.#', $percorso) === 1) {
            return false;
        }

        if (preg_match('#^/(tag|category|news-c|giocatrice|gallery|archivi-partite|author|wp-content/uploads)(/|$)#', $percorso) === 1) {
            return true;
        }

        // Archivi per data: `/2019/05/`.
        if (preg_match('#^/\d{4}/\d{2}(/|$)#', $percorso) === 1) {
            return true;
        }

        // WordPress chiudeva ogni permalink con lo slash.
        $grezzo = $request->getPathInfo();

        if ($grezzo !== '/' && str_ends_with($grezzo, '/')) {
            return true;
        }

        // Il permalink di una notizia, alla radice: `/una-frase-col-trattino`.
        return preg_match('#^/[\p{Ll}\p{N}%]+(-[\p{Ll}\p{N}%]+)+$#u', $percorso) === 1;
    }

    /**
     * La rotta che ha risposto è una di quelle generiche di `legacy.php`
     * (`/tag/{any}` → `/news`): porta su un archivio, non sul contenuto.
     */
    public static function rottaGenerica(Request $request): bool
    {
        $uri = (string) $request->route()?->uri();

        return str_contains($uri, '{any}') && ! str_contains($uri, 'feed');
    }

    /**
     * Perché un redirect non si può salvare, o null se va bene. La usano il
     * pannello e `redirect:importa`.
     */
    /**
     * @param  array<string, string>  $inSospeso  redirect non ancora salvati (partenza normalizzata => destinazione), per la prova a secco di `redirect:importa`
     */
    public static function errore(?string $da, ?string $verso, CodiceDelRedirect $codice, array $inSospeso = []): ?string
    {
        return self::erroreNellaPartenza($da) ?? self::erroreNellaDestinazione($da, $verso, $codice, $inSospeso);
    }

    public static function erroreNellaPartenza(?string $da): ?string
    {
        $partenza = self::normalizza((string) $da);

        if ($partenza === null) {
            return 'L\'indirizzo di partenza non è valido.';
        }

        if ($partenza === '/' || preg_match('#^/(api|admin|livewire|filament|up)(/|$)#', $partenza) === 1) {
            return 'Questo indirizzo non si può reindirizzare.';
        }

        return null;
    }

    /**
     * @param  array<string, string>  $inSospeso
     */
    public static function erroreNellaDestinazione(?string $da, ?string $verso, CodiceDelRedirect $codice, array $inSospeso = []): ?string
    {
        $destinazione = trim((string) $verso);

        if ($codice === CodiceDelRedirect::Rimosso) {
            return $destinazione === '' ? null : 'Un 410 non ha destinazione: lascia vuoto il campo.';
        }

        if ($destinazione === '') {
            return 'Un 301 vuole una destinazione.';
        }

        if (mb_strlen($destinazione) > 2048) {
            return 'La destinazione è troppo lunga.';
        }

        $interna = self::percorsoInterno($destinazione);

        if ($interna === false) {
            return 'La destinazione è un percorso del sito (/news/…) o un indirizzo https://.';
        }

        if ($interna !== null && self::normalizza($interna) === self::normalizza((string) $da)) {
            return 'La destinazione coincide con la partenza: il redirect girerebbe su sé stesso.';
        }

        if ($interna !== null && self::tornaAllaPartenza((string) $da, $interna, $inSospeso)) {
            return 'La destinazione, seguendo i redirect già salvati, riporta alla partenza: il browser girerebbe all\'infinito.';
        }

        return null;
    }

    /**
     * Segue i redirect salvati a partire dalla destinazione: `/a` → `/b`
     * con `/b` → `/a` già in tabella è un giro senza fine anche se nessuno
     * dei due punta a sé stesso.
     *
     * Ci si ferma su una pagina vera: lì il redirect non si consulta (solo
     * rotte del vecchio sito, `/{slug}` e 404), quindi il giro non avviene.
     *
     * @param  array<string, string>  $inSospeso
     */
    private static function tornaAllaPartenza(string $da, string $destinazione, array $inSospeso = []): bool
    {
        $partenza = self::normalizza($da);
        $passo = self::normalizza($destinazione);
        $visti = [];

        while ($passo !== null && ! isset($visti[$passo]) && count($visti) < 20) {
            if ($passo === $partenza) {
                return true;
            }

            $visti[$passo] = true;

            if (! self::siConsultaSu($passo)) {
                return false;
            }

            $verso = $inSospeso[$passo] ?? Reindirizzamento::query()
                ->where('from_path', $passo)
                ->where('status_code', '!=', CodiceDelRedirect::Rimosso->value)
                ->value('to_path');

            $interna = is_string($verso) ? self::percorsoInterno($verso) : null;
            $passo = is_string($interna) ? self::normalizza($interna) : null;
        }

        return false;
    }

    /**
     * Se un redirect salvato per questo percorso scatterebbe: sì sulle rotte
     * del vecchio sito e su `/{slug}` (passano da RedirectPrimaDelleRotteVecchie),
     * dove non risponde nessuna rotta e sulle rotte con parametri, che possono
     * rispondere 404 (`/news/notizia-tolta`) e passare dal gestore dei 404.
     * No solo su una pagina fissa (`/news`, `/stagione`).
     */
    private static function siConsultaSu(string $percorso): bool
    {
        try {
            $rotta = RottaDelPercorso::trova($percorso);
        } catch (HttpException) {
            return true;
        }

        return $rotta->parameterNames() !== []
            || in_array(RedirectPrimaDelleRotteVecchie::class, $rotta->gatherMiddleware(), true);
    }

    /**
     * Il percorso della destinazione se è sul sito, null se è su un altro
     * sito, false se non è una destinazione ammessa.
     */
    private static function percorsoInterno(string $destinazione): string|false|null
    {
        if (str_starts_with($destinazione, '/') && ! str_starts_with($destinazione, '//')) {
            return $destinazione;
        }

        if (preg_match('#^https://#i', $destinazione) !== 1 || filter_var($destinazione, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $host = strtolower((string) parse_url($destinazione, PHP_URL_HOST));
        $nostro = strtolower((string) HostFidati::hostDi((string) config('app.url')));

        if ($host !== $nostro && $host !== 'www.'.$nostro) {
            return null;
        }

        $percorso = (string) parse_url($destinazione, PHP_URL_PATH);
        $query = parse_url($destinazione, PHP_URL_QUERY);

        return ($percorso === '' ? '/' : $percorso).(is_string($query) ? '?'.$query : '');
    }

    private static function versoLaDestinazione(Request $request, string $destinazione): Response
    {
        if (str_starts_with($destinazione, '/') && ! str_starts_with($destinazione, '//')) {
            return redirect()->to($destinazione, 301);
        }

        // Un altro sito: una visita Inertia (XHR) morirebbe sul CORS.
        if ($request->header('X-Inertia')) {
            return Inertia::location($destinazione);
        }

        return redirect()->away($destinazione, 301);
    }

    private static function escluso(Request $request): bool
    {
        return (! $request->isMethod('GET') && ! $request->isMethod('HEAD'))
            || $request->is(...self::MAI);
    }
}
