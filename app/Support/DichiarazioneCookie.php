<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * La dichiarazione dei cookie che si legge nella Cookie Policy.
 *
 * Non è un elenco scritto a mano: sono i cookie e gli host che la scansione
 * settimanale ha davvero trovato sul sito (`scripts/scansione-cookie.mjs`),
 * descritti con il catalogo di `database/data/catalogo_cookie.json`. Un testo
 * redazionale invecchia il giorno in cui si aggiunge un tracker e nessuno se
 * ne accorge; questo cambia da solo, e quando incontra qualcosa che non sa
 * nominare lo pubblica come "non classificato" invece di tacerlo.
 */
class DichiarazioneCookie
{
    private const CATEGORIE = ['necessari', 'statistiche', 'marketing', 'non classificati'];

    /**
     * @return array{aggiornata_il: ?string, categorie: list<array<string, mixed>>, in_regola: bool}
     */
    public static function perIlFrontend(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $file = self::file();

        if (! is_file($file)) {
            return ['aggiornata_il' => null, 'categorie' => [], 'in_regola' => true];
        }

        // La chiave porta con sé la data del file: dopo un rilascio che cambia
        // la scansione, la dichiarazione si aggiorna senza aspettare la
        // scadenza della cache.
        return Cache::remember(
            'public:dichiarazione_cookie:'.$locale.':'.filemtime($file),
            86400,
            fn () => self::costruisci($file, $locale),
        );
    }

    private static function file(): string
    {
        return database_path('data/cookie_rilevati.json');
    }

    /**
     * @return array{aggiornata_il: ?string, categorie: list<array<string, mixed>>, in_regola: bool}
     */
    private static function costruisci(string $file, string $locale): array
    {
        $rilevato = json_decode((string) file_get_contents($file), true);

        if (! is_array($rilevato)) {
            return ['aggiornata_il' => null, 'categorie' => [], 'in_regola' => true];
        }

        $catalogo = self::catalogo();
        $perCategoria = [];

        foreach ($rilevato['cookie'] ?? [] as $cookie) {
            // Si riclassifica in lettura invece di fidarsi di com'era il
            // catalogo il giorno della scansione: correggere una descrizione
            // deve bastare a correggere la pagina.
            $noto = self::cookieNoto($catalogo, $cookie['nome'] ?? '');
            $categoria = $noto['categoria'] ?? ($cookie['categoria'] ?? 'non classificati');

            $perCategoria[$categoria]['cookie'][] = [
                'nome' => $cookie['nome'] ?? '',
                'fornitore' => $noto['fornitore'] ?? $cookie['fornitore'] ?? null,
                'scopo' => self::testo($noto['scopo'] ?? $cookie['scopo'] ?? null, $locale),
                'durata' => self::testo($noto['durata'] ?? $cookie['durata'] ?? null, $locale),
                'solo_con_consenso' => (bool) ($cookie['conConsenso'] ?? false),
            ];
        }

        foreach ($rilevato['host'] ?? [] as $host) {
            $noto = self::hostNoto($catalogo, $host['host'] ?? '');
            $categoria = $noto['categoria'] ?? ($host['categoria'] ?? 'non classificati');

            $perCategoria[$categoria]['host'][] = [
                'host' => $host['host'] ?? '',
                'fornitore' => $noto['fornitore'] ?? $host['fornitore'] ?? null,
                'solo_con_consenso' => (bool) ($host['conConsenso'] ?? false),
            ];
        }

        $categorie = [];

        foreach (self::CATEGORIE as $categoria) {
            if (! isset($perCategoria[$categoria])) {
                continue;
            }

            $categorie[] = [
                'chiave' => $categoria,
                'cookie' => $perCategoria[$categoria]['cookie'] ?? [],
                'host' => $perCategoria[$categoria]['host'] ?? [],
            ];
        }

        return [
            'aggiornata_il' => isset($rilevato['generato_il'])
                ? substr((string) $rilevato['generato_il'], 0, 10)
                : null,
            'categorie' => $categorie,
            'in_regola' => (bool) ($rilevato['senza_consenso_in_regola'] ?? true),
        ];
    }

    /**
     * @return array{cookie: list<array<string, mixed>>, domini: list<array<string, mixed>>}
     */
    private static function catalogo(): array
    {
        $file = database_path('data/catalogo_cookie.json');
        $catalogo = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return [
            'cookie' => is_array($catalogo['cookie'] ?? null) ? $catalogo['cookie'] : [],
            'domini' => is_array($catalogo['domini'] ?? null) ? $catalogo['domini'] : [],
        ];
    }

    /**
     * @param  array{cookie: list<array<string, mixed>>, domini: list<array<string, mixed>>}  $catalogo
     * @return array<string, mixed>|null
     */
    private static function cookieNoto(array $catalogo, string $nome): ?array
    {
        foreach ($catalogo['cookie'] as $voce) {
            if (($voce['nome'] ?? null) === $nome || in_array($nome, $voce['alias'] ?? [], true)) {
                return $voce;
            }

            if (isset($voce['schema']) && preg_match('/'.$voce['schema'].'/', $nome) === 1) {
                return $voce;
            }
        }

        return null;
    }

    /**
     * @param  array{cookie: list<array<string, mixed>>, domini: list<array<string, mixed>>}  $catalogo
     * @return array<string, mixed>|null
     */
    private static function hostNoto(array $catalogo, string $host): ?array
    {
        foreach ($catalogo['domini'] as $voce) {
            $atteso = (string) ($voce['host'] ?? '');

            if ($host === $atteso || ($atteso !== '' && str_ends_with($host, '.'.$atteso))) {
                return $voce;
            }
        }

        return null;
    }

    /**
     * Le descrizioni del catalogo sono per lingua; quelle copiate dentro la
     * scansione possono essere già testo semplice.
     */
    private static function testo(mixed $valore, string $locale): ?string
    {
        if (is_array($valore)) {
            return $valore[$locale] ?? $valore[config('app.fallback_locale', 'it')] ?? reset($valore) ?: null;
        }

        return is_string($valore) && $valore !== '' ? $valore : null;
    }
}
