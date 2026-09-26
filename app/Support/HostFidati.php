<?php

namespace App\Support;

/**
 * Gli Host accettati dal sito, nella forma che vuole TrustHosts.
 *
 * Ogni voce è un'espressione regolare che Symfony racchiude fra `{` e `}`
 * senza ancorarla: passare il nome a dominio così com'è voleva dire accettare
 * qualunque Host lo CONTENESSE (`sito.it.evil.com`), e il punto non escapato
 * valeva per qualunque carattere. Un Host accettato finisce negli URL assoluti
 * generati durante la richiesta, a partire dai link di reset password: è
 * esattamente il dominio che l'attaccante vorrebbe metterci.
 *
 * Per questo ogni nome passa da preg_quote() ed è ancorato, con i sottodomini
 * ammessi in modo esplicito; come IP sono ammessi solo quelli della rete
 * interna, dove sta la sonda di App Platform.
 */
final class HostFidati
{
    /**
     * Indirizzi privati (RFC 1918), di loopback e della rete condivisa
     * 100.64.0.0/10 (RFC 6598), che App Platform usa per i pod: la sonda
     * dell'health check interroga /up con l'IP del pod come Host, e senza
     * questa voce Symfony risponde 400 e il deploy torna indietro.
     */
    public const IP_PRIVATI = '^(?:'
        .'10(?:\.\d{1,3}){3}'
        .'|127(?:\.\d{1,3}){3}'
        .'|192\.168(?:\.\d{1,3}){2}'
        .'|172\.(?:1[6-9]|2\d|3[01])(?:\.\d{1,3}){2}'
        .'|100\.(?:6[4-9]|[7-9]\d|1[01]\d|12[0-7])(?:\.\d{1,3}){2}'
        .')$';

    /**
     * @param  list<string>  $configurati  i valori di TRUSTED_HOSTS
     * @return list<string>
     */
    public static function patterns(array $configurati, string $appUrl): array
    {
        $host = self::hostDi($appUrl);
        $domini = $configurati !== [] ? $configurati : ($host !== null ? [$host] : []);

        if ($domini === []) {
            // Una lista vuota per Symfony significa "nessuna restrizione":
            // meglio saperlo che credere di essere protetti.
            return [];
        }

        $patterns = array_map(
            static fn (string $dominio): string => '^(?:.+\.)?'.preg_quote(strtolower($dominio), '/').'$',
            $domini,
        );

        $patterns[] = self::IP_PRIVATI;
        $patterns[] = '^localhost$';

        return $patterns;
    }

    /**
     * Il nome a dominio di APP_URL. Su App Platform il valore è stato a lungo
     * `${APP_DOMAIN}` senza schema, su cui parse_url() restituisce null: senza
     * normalizzarlo la difesa si spegneva in silenzio.
     */
    public static function hostDi(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST)
            ?: parse_url('https://'.ltrim($url, '/'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    /**
     * La radice da forzare sugli URL generati in produzione: sempre https e
     * sempre l'host di APP_URL, qualunque Host abbia la richiesta.
     */
    public static function radicePubblica(string $appUrl): ?string
    {
        $host = self::hostDi($appUrl);

        if ($host === null) {
            return null;
        }

        $porta = parse_url(str_contains($appUrl, '://') ? $appUrl : 'https://'.ltrim(trim($appUrl), '/'), PHP_URL_PORT);

        return 'https://'.$host.(is_int($porta) ? ':'.$porta : '');
    }
}
